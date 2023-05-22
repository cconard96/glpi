<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
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

namespace Glpi\Debug;

use CommonDBTM;
use CommonGLPI;
use Exception;
use mysqli_result;
use Plugin;
use Search;
use Session;

/**
 * Provides reflection on GLPI internals.
 */
final class Reflection
{
    private static $instance;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Try to guess the itemtype context for the given page.
     * @return class-string<CommonGLPI>|null
     */
    public function getItemtypeForPage(string $page): ?string
    {
        // get the called script's name
        if (!str_contains($page, 'front/')) {
            return null;
        }
        $base_name = basename($page);
        $itemtype = substr($base_name, 0, strpos($base_name, '.'));
        if (!class_exists($itemtype) || !is_subclass_of($itemtype, \CommonGLPI::class)) {
            return null;
        }
        return $itemtype;
    }

    /**
     * @param class-string<CommonGLPI> $itemtype
     * @return array
     */
    public function getClassInfo(string $itemtype): array
    {
        return [
            'class' => $itemtype::getType(),
            'type_name' => $itemtype::getTypeName(),
            'type_name_plural' => $itemtype::getTypeName(Session::getPluralNumber()),
            'icon' => is_subclass_of($itemtype, CommonDBTM::class) ? $itemtype::getIcon() : '',
            'rightname' => $itemtype::$rightname,
        ];
    }

    /**
     * Get all search options for a given itemtype.
     * @param class-string<CommonGLPI> $itemtype
     * @return array
     */
    public function getSearchOptions(string $itemtype): array
    {
        try {
            $options = Search::getOptions($itemtype::getType());
        } catch (Exception $e) {
            $options = [];
        }
        $options = array_filter($options, static function ($k) {
            return is_numeric($k);
        }, ARRAY_FILTER_USE_KEY);

        if (Plugin::isPluginActive('datainjection')) {
            // Add injectable property to all $options set to 0
            foreach ($options as &$option) {
                $option['injectable'] = 0;
            }
            unset($option);

            // Get injection class
            $injection_class = 'PluginDatainjection' . $itemtype . 'Injection';
            if (class_exists($injection_class)) {
                /** @var \PluginDatainjectionInjectionInterface $injection */
                $injection = new $injection_class();
                $injection_options = $injection->getOptions($itemtype);
                foreach ($injection_options as $id => $injection_option) {
                    if (isset($options[$id])) {
                        $options[$id]['injectable'] = $injection_option['injectable'] ?? 0;
                    }
                }
            }
        }

        return $options;
    }

    public function getTableSchema(string $table): array
    {
        global $DB;

        $schema = $DB->listFields($table);
        // List Indexes
        /** @var mysqli_result $result */
        $result = $DB->query("SHOW INDEX FROM $table FROM " . $DB->dbdefault);
        $indexes = $result->fetch_all(MYSQLI_ASSOC);

        // Cleanup Indexes Data
        foreach ($indexes as &$index) {
            $index['Unique'] = !$index['Non_unique'];
            $index['Null'] = $index['Null'] === 'YES';
        }
        unset($index);

        // Keep only relevant fields
        $schema = array_map(static function ($field) {
            return array_intersect_key($field, array_flip(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra']));
        }, $schema);
        $indexes = array_map(static function ($index) {
            return array_intersect_key($index, array_flip(['Key_name', 'Seq_in_index', 'Column_name', 'Null', 'Unique']));
        }, $indexes);

        return [
            'fields' => $schema,
            'indexes' => $indexes
        ];
    }
}
