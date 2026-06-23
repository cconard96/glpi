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

class DeviceBattery extends CommonDevice
{
    protected static $forward_entity_to = ['Item_DeviceBattery', 'Infocom'];

    public static function getTypeName($nb = 0)
    {
        return _n('Battery', 'Batteries', $nb);
    }

    public function getAdditionalFields()
    {
        return array_merge(
            parent::getAdditionalFields(),
            [
                [
                    'name'  => 'devicebatterytypes_id',
                    'label' => _n('Type', 'Types', 1),
                    'type'  => 'dropdownValue',
                ],
                [
                    'name'   => 'capacity',
                    'label'  => __('Capacity'),
                    'type'   => 'integer',
                    'min'    => 0,
                    'unit'   => __('mWh'),
                ],
                [
                    'name'   => 'voltage',
                    'label'  => __('Voltage'),
                    'type'   => 'integer',
                    'min'    => 0,
                    'unit'   => __('mV'),
                ],
            ]
        );
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '11',
            'table'              => static::getTable(),
            'field'              => 'capacity',
            'name'               => __('Capacity'),
            'datatype'           => 'integer',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => static::getTable(),
            'field'              => 'voltage',
            'name'               => __('Voltage'),
            'datatype'           => 'integer',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => 'glpi_devicebatterytypes',
            'field'              => 'name',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param mixed[] $main_joinparams
     * @return mixed[]
     */
    public static function rawSearchOptionsToAdd($itemtype, $main_joinparams)
    {
        $tab = [];

        $tab[] = [
            'id'            => '1340',
            'table'         => 'glpi_devicebatteries',
            'field'         => 'capacity',
            'name'          => sprintf(__('%1$s: %2$s'), self::getTypeName(1), __('Design capacity')),
            'forcegroupby'  => true,
            'usehaving'     => true,
            'massiveaction' => false,
            'datatype'      => 'integer',
            'unit'          => __('mWh'),
            'joinparams'    => [
                'beforejoin' => [
                    'table'      => 'glpi_items_devicebatteries',
                    'joinparams' => $main_joinparams,
                ],
            ],
        ];

        $tab[] = [
            'id'            => '1341',
            'table'         => 'glpi_items_devicebatteries',
            'field'         => 'real_capacity',
            'name'          => sprintf(__('%1$s: %2$s'), self::getTypeName(1), __('Real capacity')),
            'forcegroupby'  => true,
            'usehaving'     => true,
            'massiveaction' => false,
            'datatype'      => 'integer',
            'unit'          => __('mWh'),
            'joinparams'    => $main_joinparams,
        ];

        return $tab;
    }

    public function getImportCriteria()
    {
        return [
            'designation'           => 'equal',
            'devicebatterytypes_id' => 'equal',
            'manufacturers_id'      => 'equal',
            'capacity'              => 'delta:10',
            'voltage'               => 'delta:10',
        ];
    }
}
