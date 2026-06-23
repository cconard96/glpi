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

/**
 * Process Class
 **/
class Item_Process extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';
    public $dohistory       = true;


    public static function getTypeName($nb = 0)
    {
        return _n('Process', 'Processes', $nb);
    }

    public static function convertFiltersValuesToSqlCriteria(array $filters = []): array
    {
        $sql_filters = [];

        $like_filters = [
            'id',
            'cmd',
            'tty',
            'virtualmemory',
        ];
        foreach ($like_filters as $filter_key) {
            if (strlen(($filters[$filter_key] ?? ""))) {
                $sql_filters[$filter_key] = ['LIKE', '%' . $filters[$filter_key] . '%'];
            }
        }

        $min_filters = ['cpuusage', 'memusage'];
        foreach ($min_filters as $filter_key) {
            if (($filters[$filter_key] ?? 0) > 0) {
                $sql_filters[$filter_key] = ['>=', $filters[$filter_key]];
            }
        }

        if (isset($filters['user']) && !empty($filters['user'])) {
            $sql_filters['user'] = $filters['user'];
        }

        if (isset($filters['started']) && !empty($filters['started'])) {
            $sql_filters[] = [
                ['started' => ['>=', "{$filters['started']} 00:00:00"]],
                ['started' => ['<=', "{$filters['started']} 23:59:59"]],
            ];
        }

        return $sql_filters;
    }
}
