<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Search\Provider;

use Glpi\Api\HL\Controller\AbstractController;
use Glpi\Api\HL\Doc\Schema;
use Glpi\Api\HL\Router;
use Glpi\Api\HL\Search;
use Glpi\Debug\Profiler;
use Glpi\Search\SearchOption;
use Glpi\Toolbox\ArrayPathAccessor;

/**
 * Search provider wrapper for the HLAPI Search Engine to allow it to be used by the search in the web interface.
 * While this provider/HLAPI search is also backed by the database, the process of converting criteria to SQL is quite different.
 * Unlike the SQLProvider, this provider does NOT rely on numerical search option IDs except for backwards compatibility (handled here).
 *
 * @phpstan-type ParsedSchemaData array{schema: Schema, itemtype: string, table: string, joins: array, properties: array}
 */
final class HLAPIProvider implements SearchProviderInterface
{
    public static function prepareData(array &$data, array $options = []): array
    {
        Profiler::getInstance()->start('get search options');
        $all_opts = SearchOption::getOptionsForItemtype($data['itemtype']);
        $to_view_opt = [];
        foreach ($data['toview'] as $opt_id) {
            foreach ($all_opts as $opt_key => $opt) {
                if (is_numeric($opt_key) && $opt_key === $opt_id) {
                    $opt_arr = $opt + ['id' => $opt_key, 'itemtype' => $data['itemtype']];
                    $data['data']['cols'][] = $opt_arr;
                    $to_view_opt[$opt_key] = new SearchOption($opt_arr);
                    break;
                }
            }
        }
        Profiler::getInstance()->stop('get search options');
        Profiler::getInstance()->start('get schema for ' . $data['itemtype']);
        $main_schema = self::getSchemaForItemtype($data['itemtype']);
        Profiler::getInstance()->stop('get schema for ' . $data['itemtype']);
        Profiler::getInstance()->start('create search option lookups');
        //$flattened_props = Schema::flattenProperties($main_schema['properties']);
        $lookup = self::getSchemaPropertyLookupTable($main_schema, $to_view_opt);
        $lookup = array_filter($lookup, static fn ($path) => $path !== null);
        $reverse_lookup = array_flip($lookup);
        Profiler::getInstance()->stop('create search option lookups');

        // We will build a new schema with the exact data we need for the search and nothing more
//        $schema_for_search = $main_schema;
//        $schema_for_search['properties'] = [];
//        foreach ($to_view_opt as $opt_key => $opt) {
//            $path = $lookup[$opt_key] ?? null;
//            if ($path === null) {
//                continue;
//            }
//            $prop_key = explode('.', $path);
//            $prop_key = end($prop_key);
//            $new_path = str_replace('.', '.properties.', $path);
//            ArrayPathAccessor::setElementByArrayPath($schema_for_search['properties'], $new_path, ArrayPathAccessor::getElementByArrayPath($flattened_props, $path));
//        }
        // Manually remove unwanted props. This will be replaced with actual logic later.
        unset(
            $main_schema['properties']['comment'],
            $main_schema['properties']['date_creation'],
            $main_schema['properties']['status'],
            $main_schema['properties']['is_recursive'],
            $main_schema['properties']['user_tech'],
            $main_schema['properties']['group_tech'],
            $main_schema['properties']['user'],
            $main_schema['properties']['group'],
            $main_schema['properties']['contact'],
            $main_schema['properties']['contact_num'],
            $main_schema['properties']['otherserial'],
            $main_schema['properties']['uuid'],
            $main_schema['properties']['autoupdatesystem'],
        );


        $timer_start = microtime(true);
        Profiler::getInstance()->start('search by schema');
        $response = Search::searchBySchema($main_schema, [
            'start' => $data['search']['start'],
            'limit' => $data['search']['list_limit'],
        ]);
        Profiler::getInstance()->stop('search by schema');
        $data['data']['execution_time'] = microtime(true) - $timer_start;

        Profiler::getInstance()->start('format results');
        $results = json_decode((string) $response->getBody(), true);
        $content_range = $response->getHeaderLine('Content-Range');
        $data['data']['totalcount'] = (int) explode('/', $content_range)[1];
        $data['data']['count'] = count($results);
        // get start and end from the content range header
        $range = explode('/', $content_range)[0];
        $range = explode('-', $range);
        $data['data']['begin'] = (int) $range[0];
        $data['data']['end'] = (int) $range[1];

        // Results need formatted to match the expected format for 'rows'
        // Each row has a 'raw' key with an array of key-value pairs. The keys are ITEM_itemtype_optid and the values are the raw values.
        // Each row also has keys itemtype_optid with array values containing a 'count', numeric key(s) with the raw data
        // (in some cases, this is an array with multiple props. itemlinks for example will name an id and a name), and 'displayname' with the formatted data.
        $flattened_results = [];
        // Flatten each result so that nested properties are at the top level (ex: {id: 1, entity: {id: 3, name: 'Test'}} becomes {id: 1, entity.id: 3, entity.name: 'Test'})
        $fn_flatten_array = static function (array $arr, string $prefix = '') use (&$fn_flatten_array) {
            $result = [];
            foreach ($arr as $key => $value) {
                if (is_array($value)) {
                    $result += $fn_flatten_array($value, $prefix . $key . '.');
                } else {
                    $result[$prefix . $key] = $value;
                }
            }
            return $result;
        };
        foreach ($results as $result) {
            $flattened_results[] = $fn_flatten_array($result);
        }

        foreach ($flattened_results as $flat_result) {
            $row = [];
            foreach ($flat_result as $prop => $value) {
                if ($prop === 'id') {
                    $row['id'] = $value;
                }
                // Find the search option that corresponds to this property
                $opt_id = $reverse_lookup[$prop] ?? null;
                if ($opt_id === null) {
                    continue;
                }
                $path_excluding_last_leaf = explode('.', $lookup[$opt_id]);
                array_pop($path_excluding_last_leaf);
                $path_excluding_last_leaf = implode('.', $path_excluding_last_leaf);

                $row['raw']['ITEM_' . $data['itemtype'] . '_' . $opt_id] = $value;
                $row[$data['itemtype'] . '_' . $opt_id] = [
                    'count' => 1,
                    0 => [
                        'name' => $value
                    ],
                    'displayname' => $value
                ];
                // if there is an id property too, we need to add it to the row
                $id_path = trim($path_excluding_last_leaf . '.id', '.');
                if (isset($flat_result[$id_path])) {
                    $row[$data['itemtype'] . '_' . $opt_id][0]['id'] = $flat_result[$id_path];
                }
            }
            $data['data']['rows'][] = $row;
        }
        Profiler::getInstance()->stop('format results');

        return $data;
    }

    /**
     * @param class-string<\CommonDBTM> $itemtype
     * @param SearchOption[] $opts
     * @return array
     */
    private static function getSchemaPropertyLookupTable(array $main_schema, array $opts): array
    {
        static $lookup_tables = [];

        $itemtype = $main_schema['x-itemtype'];
        if (!isset($lookup_tables[$itemtype])) {
            $props = Schema::flattenProperties($main_schema['properties']);
            $joins = Schema::getJoins($main_schema['properties']);

            foreach ($opts as $opt_key => $opt) {
                $lookup_tables[$itemtype][$opt_key] = self::getPropertyPathForSearchOption($opt, [
                    'itemtype' => $itemtype,
                    'table' => getTableForItemType($itemtype),
                    'schema' => $main_schema,
                    'joins' => $joins,
                    'properties' => $props
                ]);
            }

        }

        return $lookup_tables[$itemtype];
    }

    private static function getSchemaForItemtype(string $itemtype): ?array
    {
        foreach (Router::getInstance()->getControllers() as $controller) {
            foreach ($controller::getKnownSchemas() as $schema) {
                if (($schema['x-itemtype'] ?? null) === $itemtype) {
                    return $schema;
                }
            }
        }
        return null;
    }

    /**
     * @param SearchOption $opt
     * @param ParsedSchemaData $schema_data
     * @return string|null
     */
    private static function getPropertyPathForSearchOption(SearchOption $opt, array $schema_data): ?string
    {
        $path = null;

        $is_direct_field = $opt['table'] === $schema_data['table'];
        $target_table = $opt['table'];

        foreach ($schema_data['properties'] as $prop_path => $prop) {
            // if the property has x-field property and it matches the field name of the search option, we found the path
            if (isset($prop['x-field']) && $prop['x-field'] === $opt['field']) {
                $path = $prop_path;
                break;
            }
        }
        if ($path === null) {
            // Match on property key
            foreach ($schema_data['properties'] as $prop_path => $prop) {
                $prop_path_parts = explode('.', $prop_path);
                $prop_key = end($prop_path_parts);
                if ($prop_key === $opt['field']) {
                    if ($is_direct_field) {
                        $path = $prop_path;
                        break;
                    }

                    // Is there a join for this property?
                    $join = array_filter($schema_data['joins'], static fn ($j, $jkey) => str_starts_with($prop_path, $jkey) && $j['table'] === $target_table, ARRAY_FILTER_USE_BOTH);
                    if (!empty($join)) {
                        $path = $prop_path;
                        break;
                    }
                }
                //TODO Support properties that are in a join but are not part of the partial schema (we need to get the full schema)
            }
        }

        return $path;
    }
}
