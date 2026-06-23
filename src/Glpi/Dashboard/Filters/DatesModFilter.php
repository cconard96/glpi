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

use Html;

class DatesModFilter extends AbstractFilter
{
    public static function getName(): string
    {
        return __("Last update");
    }

    public static function getId(): string
    {
        return "dates_mod";
    }

    public static function canBeApplied(string $table): bool
    {
        global $DB;

        return $DB->fieldExists($table, 'date_mod');
    }

    public static function getCriteria(string $table, $value): array
    {
        if (!is_array($value) || count($value) !== 2) {
            // Empty filter value
            return [];
        }

        return [
            'WHERE' => self::getDatesCriteria("$table.date_mod", $value),
        ];
    }

    public static function getSearchCriteria(string $table, $value): array
    {
        if (!is_array($value) || count($value) !== 2) {
            // Empty filter value
            return [];
        }

        $date_mod_option_id = self::getSearchOptionID($table, "date_mod", $table);

        return [
            self::getDatesSearchCriteria($date_mod_option_id, $value, 'begin'),
            self::getDatesSearchCriteria($date_mod_option_id, $value, 'end'),
        ];
    }
}
