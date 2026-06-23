<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
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

namespace Glpi\Dashboard\Filters;

use Search;
use function Safe\strtotime;

abstract class AbstractFilter
{
    /**
     * Get the filter name
     *
     * @return string
     */
    abstract public static function getName(): string;

    /**
    * Get the filter id
    *
    * @return string
    */
    abstract public static function getId(): string;

    /**
     * Can the filter be applied to the given table?
     *
     * @param string $table
     *
     * @return bool
     */
    abstract public static function canBeApplied(string $table): bool;

    /**
     * Get the filter criteria
     *
     * @param string $table
     * @param mixed  $value
     *
     * @return array
     */
    abstract public static function getCriteria(string $table, $value): array;

    /**
     * Get the search filter criteria
     *
     * example :
     * [
     * 'link'       => 'AND',
     * 'field'      => self::getSearchOptionID($table, 'itilcategories_id', 'glpi_itilcategories'), // itilcategory
     * 'searchtype' => 'under',
     * 'value'      => (int) $apply_filters[ItilCategoryFilter::getId()]
     * ]
     *
     * @param string $table
     * @param mixed  $value
     *
     * @return array
     */
    abstract public static function getSearchCriteria(string $table, $value): array;

    protected static function getSearchOptionID(string $table, string $name, string $tableToSearch): int
    {
        $data = Search::getOptions(getItemTypeForTable($table), true);
        $sort = [];
        foreach ($data as $ref => $opt) {
            if (isset($opt['field'])) {
                $sort[$ref] = $opt['linkfield'] . "-" . $opt['table'];
            }
        }
        return array_search($name . "-" . $tableToSearch, $sort);
    }

    protected static function getDatesCriteria(string $field, array $dates): array
    {
        $begin = strtotime($dates[0]);
        $end   = strtotime($dates[1]);

        return [
            [$field => ['>=', date('Y-m-d', $begin)]],
            [$field => ['<=', date('Y-m-d', $end)]],
        ];
    }

    protected static function getDatesSearchCriteria(int $searchoption_id, array $dates, string $when): array
    {
        if ($when == "begin") {
            $begin = strtotime($dates[0]);
            return [
                'link'       => 'AND',
                'field'      => $searchoption_id,
                'searchtype' => 'morethan',
                'value'      => date('Y-m-d 00:00:00', $begin),
            ];
        } else {
            $end   = strtotime($dates[1]);
            return [
                'link'       => 'AND',
                'field'      => $searchoption_id,
                'searchtype' => 'lessthan',
                'value'      => date('Y-m-d 00:00:00', $end),
            ];
        }
    }
}
