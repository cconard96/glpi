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

use Change;
use Html;
use Problem;
use Ticket;

class DatesFilter extends AbstractFilter
{
    public static function getName(): string
    {
        return __("Creation date");
    }

    public static function getId(): string
    {
        return "dates";
    }

    public static function canBeApplied(string $table): bool
    {
        global $DB;

        return $DB->fieldExists($table, 'date')
            || (
                $DB->fieldExists($table, 'date_creation')
                // exclude itilobject already processed with 'date'
                && !in_array($table, [Ticket::getTable(), Change::getTable(), Problem::getTable()])
            );
    }

    public static function getCriteria(string $table, $value): array
    {
        global $DB;

        if (!is_array($value) || count($value) !== 2) {
            // Empty filter value
            return [];
        }

        $criteria = [
            'WHERE' => [],
        ];

        if ($DB->fieldExists($table, 'date')) {
            $criteria['WHERE'][] = self::getDatesCriteria("$table.date", $value);
        }

        if (
            $DB->fieldExists($table, 'date_creation')
            // exclude itilobject already processed with 'date'
            && !in_array($table, [Ticket::getTable(), Change::getTable(), Problem::getTable()])
        ) {
            $criteria['WHERE'][] = self::getDatesCriteria("$table.date_creation", $value);
        }

        return $criteria;
    }

    public static function getSearchCriteria(string $table, $value): array
    {
        global $DB;

        if (!is_array($value) || count($value) !== 2) {
            // Empty filter value
            return [];
        }

        $criteria = [];

        if ($DB->fieldExists($table, 'date')) {
            $date_option_id = self::getSearchOptionID($table, "date", $table);
            $criteria[] = self::getDatesSearchCriteria($date_option_id, $value, 'begin');
            $criteria[] = self::getDatesSearchCriteria($date_option_id, $value, 'end');
        }

        if (
            $DB->fieldExists($table, 'date_creation')
            // exclude itilobject already processed with 'date'
            && !in_array($table, [Ticket::getTable(), Change::getTable(), Problem::getTable()])
        ) {
            $date_creation_option_id = self::getSearchOptionID($table, "date_creation", $table);
            $criteria[] = self::getDatesSearchCriteria($date_creation_option_id, $value, 'begin');
            $criteria[] = self::getDatesSearchCriteria($date_creation_option_id, $value, 'end');
        }

        return $criteria;
    }
}
