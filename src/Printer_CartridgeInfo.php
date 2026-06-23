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

class Printer_CartridgeInfo extends CommonDBChild
{
    public static $itemtype = Printer::class;
    public static $items_id        = 'printers_id';
    public $dohistory              = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Cartridge inventoried information', 'Cartridge inventoried information', $nb);
    }

    /**
     * @param Printer $printer
     *
     * @return array
     */
    public function getInfoForPrinter(Printer $printer)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'   => static::getTable(),
            'WHERE'  => [
                self::$items_id => $printer->fields['id'],
            ],
        ]);

        $info = [];
        foreach ($iterator as $row) {
            $info[$row['id']] = $row;
        }

        return $info;
    }

    /**
     * @return array
     */
    public static function rawSearchOptionsToAdd()
    {
        $tab = [];

        $tab[] = [
            'id' => strtolower(self::getType()),
            'name' => self::getTypeName(1),
        ];

        $tab[] = [
            'id'                => 1400,
            'table'             => self::getTable(),
            'field'             => "_virtual_toner_percent",
            'name'              => __('Toner percentage'),
            'datatype'          => 'specific',
            'massiveaction'     => false,
            'nosearch'          => true,
            'joinparams'        => [
                'jointype' => 'child',
            ],
            'additionalfields'  => ['property', 'value'],
            'forcegroupby'      => true,
            'aggregate'         => true,
            'searchtype'        => ['contains'],
            'nosort'            => true,
        ];

        $tab[] = [
            'id'                => 1401,
            'table'             => self::getTable(),
            'field'             => "_virtual_drum_percent",
            'name'              => __('Drum percentage'),
            'datatype'          => 'specific',
            'massiveaction'     => false,
            'nosearch'          => true,
            'joinparams'        => [
                'jointype' => 'child',
            ],
            'additionalfields'  => ['property', 'value'],
            'forcegroupby'      => true,
            'aggregate'         => true,
            'searchtype'        => ['contains'],
            'nosort'            => true,
        ];

        return $tab;
    }
}
