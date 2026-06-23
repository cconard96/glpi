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

class DeviceCamera extends CommonDevice
{
    protected static $forward_entity_to = ['Item_DeviceCamera', 'Infocom'];

    public static function getTypeName($nb = 0)
    {
        return _n('Camera', 'Cameras', $nb);
    }

    public function getAdditionalFields()
    {
        return array_merge(
            parent::getAdditionalFields(),
            [
                [
                    'name'  => 'devicecameramodels_id',
                    'label' => _n('Model', 'Models', 1),
                    'type'  => 'dropdownValue',
                ],
                [
                    'name'   => 'flashunit',
                    'label'  => __('Flashunit'),
                    'type'   => 'bool',
                ],
                [
                    'name'   => 'lensfacing',
                    'label'  => __('Lensfacing'),
                    'type'   => 'text',
                ],
                [
                    'name'   => 'orientation',
                    'label'  => __('Orientation'),
                    'type'   => 'text',
                ],
                [
                    'name'   => 'focallength',
                    'label'  => __('Focal length'),
                    'type'   => 'text',
                ],
                [
                    'name'   => 'sensorsize',
                    'label'  => __('Sensor size'),
                    'type'   => 'text',
                ],
                [
                    'name'   => 'support',
                    'label'  => __('Support'),
                    'type'   => 'text',
                ],
            ]
        );
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '10',
            'table'              => 'glpi_devicecameramodels',
            'field'              => 'name',
            'name'               => _n('Model', 'Models', 1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '11',
            'table'              => static::getTable(),
            'field'              => 'flashunit',
            'name'               => __('Flashunit'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => static::getTable(),
            'field'              => 'lensfacing',
            'name'               => __('Lensfacing'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => static::getTable(),
            'field'              => 'orientation',
            'name'               => __('orientation'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '14',
            'table'              => static::getTable(),
            'field'              => 'focallength',
            'name'               => __('Focal length'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '15',
            'table'              => static::getTable(),
            'field'              => 'sensorsize',
            'name'               => __('Sensor size'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '17',
            'table'              => static::getTable(),
            'field'              => 'support',
            'name'               => __('Support'),
            'datatype'           => 'string',
        ];

        return $tab;
    }

    public function getImportCriteria()
    {
        return [
            'designation'           => 'equal',
            'devicecameramodels_id' => 'equal',
            'manufacturers_id'      => 'equal',
        ];
    }
}
