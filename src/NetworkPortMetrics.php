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

use Safe\DateTime;

/**
 * Store network port metrics
 */
class NetworkPortMetrics extends CommonDBChild
{
    public static $itemtype = NetworkPort::class;
    public static $items_id        = 'networkports_id';
    public $dohistory              = false;

    public static function getTypeName($nb = 0)
    {
        return __('Network port metrics');
    }

    /**
     * Get metrics
     *
     * @param NetworkPort $netport      Printer instance
     * @param array       $user_filters User filters
     *
     * @return array
     */
    public function getMetrics(NetworkPort $netport, $user_filters = []): array
    {
        global $DB;

        $bdate = new DateTime();
        $bdate->sub(new DateInterval('P1Y'));
        $filters = [
            'date' => ['>', $bdate->format('Y-m-d')],
        ];
        $filters = array_merge($filters, $user_filters);

        $iterator = $DB->request([
            'FROM'   => static::getTable(),
            'WHERE'  => [
                static::$items_id  => $netport->fields['id'],
            ] + $filters,
        ]);

        return iterator_to_array($iterator);
    }

    private function getLabelFor(string $key): string
    {
        return match ($key) {
            'ifinbytes' => __('Input megabytes'),
            'ifoutbytes' => __('Output megabytes'),
            'ifinerrors' => __('Input errors'),
            'ifouterrors' => __('Output errors'),
            default => $key,
        };
    }
}
