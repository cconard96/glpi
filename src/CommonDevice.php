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

use Glpi\DBAL\QueryFunction;

/**
 * CommonDevice Class
 * for Device*class
 */
abstract class CommonDevice extends CommonDropdown
{
    public static $rightname          = 'device';

    public $can_be_translated  = false;

    // From CommonDBTM
    public $dohistory           = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Component', 'Components', $nb);
    }

    /**
     * Get all the kind of devices available inside the system.
     *
     * @since 0.85
     *
     * @param bool $grouped If true, returns an array grouped by category, otherwise returns a flat array
     * @return ($grouped is true ? array<string, class-string<CommonDevice>[]> : class-string<CommonDevice>[])
     **/
    public static function getDeviceTypes(bool $grouped = false)
    {
        //TODO After GLPI 11.0, make this always return grouped values
        global $CFG_GLPI;

        $valid_types = [
            __('Input/Output') => [
                // Components that transfer data to/from the computer
                DeviceNetworkCard::class,
                DeviceDrive::class,
                DeviceGraphicCard::class,
                DeviceSoundCard::class,
                DevicePci::class,
                DeviceCamera::class,
            ],
            __('Power management') => [
                DeviceBattery::class,
                DevicePowerSupply::class,
            ],
            __('Others') => [
                DeviceMotherboard::class,
                DeviceFirmware::class,
                DeviceProcessor::class,
                DeviceControl::class,
                DeviceCase::class,
                DeviceGeneric::class,
                DeviceSimcard::class,
                DeviceSensor::class,
            ],
        ];
        $added = array_merge(
            $valid_types[__('Input/Output')],
            $valid_types[__('Power management')],
            $valid_types[__('Others')]
        );
        $all_device_types = $CFG_GLPI['device_types'] ?? [];
        // Remove the default device types which are not in $all_device_types (may have been removed by plugins)
        // Add any devices not already in the list to 'Others'
        foreach ($valid_types as &$device_classes) {
            foreach ($device_classes as $k => $device_class) {
                if (!in_array($device_class, $all_device_types, true)) {
                    unset($device_classes[$k]);
                }
            }
        }
        unset($device_classes);
        foreach ($all_device_types as $device_class) {
            if (!is_a($device_class, self::class, true)) {
                // Invalid type registered by a plugin.
                trigger_error(
                    sprintf('Invalid device type `%s`.', $device_class),
                    E_USER_WARNING
                );
                continue;
            }
            if (!in_array($device_class, $added, true)) {
                $valid_types[__('Others')][] = $device_class;
            }
        }

        if (!$grouped) {
            $valid_types = array_merge(...array_values($valid_types));
        }

        return $valid_types;
    }

    /**
     * Get the associated item_device associated with this device
     * This method can be overridden, for instance by the plugin
     *
     * @since 0.85
     * @since 9.3 added the $devicetype parameter
     *
     * @param class-string|null $devicetype class name of device type, defaults to called class name
     *
     * @return class-string<Item_Devices>
     **/
    public static function getItem_DeviceType($devicetype = null)
    {
        if (null === $devicetype) {
            $devicetype = static::class;
        }
        if ($plug = isPluginItemType($devicetype)) {
            return 'Plugin' . $plug['plugin'] . 'Item_' . $plug['class'];
        }
        return "Item_$devicetype";
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'manufacturers_id',
                'label' => Manufacturer::getTypeName(1),
                'type'  => 'dropdownValue',
            ],
        ];
    }

    public function canUnrecurs()
    {
        global $DB;

        $ID = $this->fields['id'];
        if (
            ($ID < 0)
            || !$this->fields['is_recursive']
        ) {
            return true;
        }
        if (!parent::canUnrecurs()) {
            return false;
        }
        $entities = getAncestorsOf("glpi_entities", $this->fields['entities_id']);
        $entities[] = $this->fields['entities_id'];

        // RELATION : device -> item_device -> item
        $linktype  = static::getItem_DeviceType();
        $linktable = getTableForItemType($linktype);

        $result = $DB->request(
            [
                'SELECT'    => [
                    'itemtype',
                    QueryFunction::groupConcat(
                        expression: 'items_id',
                        distinct: true,
                        alias: 'ids'
                    ),
                ],
                'FROM'      => $linktable,
                'WHERE'     => [
                    $this->getForeignKeyField() => $ID,
                ],
                'GROUPBY'   => [
                    'itemtype',
                ],
            ]
        );

        foreach ($result as $data) {
            if (!empty($data["itemtype"])) {
                $itemtable = getTableForItemType($data["itemtype"]);
                if ($item = getItemForItemtype($data["itemtype"])) {
                    // For each itemtype which are entity dependant
                    if ($item->isEntityAssign()) {
                        if (
                            countElementsInTable($itemtable, ['id'  => $data["ids"],
                                'NOT' => ['entities_id' => $entities ],
                            ]) > 0
                        ) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics'),
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => static::getTable(),
            'field'              => 'designation',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'datatype'           => 'number',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getItem_DeviceType()::getTable(),
            'field'              => 'id',
            'name'               => _x('quantity', 'Number of items'),
            'datatype'           => 'count',
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'joinparams' => [
                'jointype' => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '23',
            'table'              => 'glpi_manufacturers',
            'field'              => 'name',
            'name'               => Manufacturer::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '19',
            'table'              => static::getTable(),
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '121',
            'table'              => static::getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    public static function getNameField()
    {
        return 'designation';
    }

    /**
     * Import a device is not exists
     *
     * @param array $input Array of datas
     *
     * @return int ID of existing or new Device
     **/
    public function import(array $input)
    {
        global $DB;

        $with_history = $input['with_history'] ?? true;
        unset($input['with_history']);

        if (empty($input['designation'])) {
            return 0;
        }
        $where      = [];
        $a_criteria = $this->getImportCriteria();
        foreach ($a_criteria as $field => $compare) {
            if (isset($input[$field])) {
                $compare = explode(':', $compare);
                switch ($compare[0]) {
                    case 'equal':
                        $where[$field] = $input[$field];
                        break;

                    case 'delta':
                        $where[] = [
                            [$field => ['>', ((int) $input[$field] - (int) $compare[1])]],
                            [$field => ['<', ((int) $input[$field] + (int) $compare[1])]],
                        ];
                        break;
                }
            }
        }

        $model_fk = getForeignKeyFieldForItemType(static::class . 'Model');
        if ($DB->fieldExists(static::getTable(), $model_fk)) {
            if (isset($input[$model_fk])) {
                $where[$model_fk] = $input[$model_fk];
            } else {
                $where[] = [
                    'OR' => [
                        [$model_fk => null],
                        [$model_fk => 0],
                    ],
                ];
            }
        }

        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => static::getTable(),
            'WHERE'  => $where,
        ]);

        if (count($iterator) > 0) {
            $line = $iterator->current();
            return $line['id'];
        }

        return $this->add($input, [], $with_history);
    }

    /**
     * Criteria used for import function
     *
     * @return array<string, string> Fieldname => Comparison type where comparison type is one of:
     * - 'equal': field must be equal
     * - 'delta:n': field must be within n of the value
     * @since 0.84
     **/
    public function getImportCriteria()
    {
        return [
            'designation'      => 'equal',
            'manufacturers_id' => 'equal',
        ];
    }

    /**
     * @return void
     * @since 0.85
     **/
    public function post_workOnItem()
    {
        if (
            (isset($this->input['_registeredID']))
            && (is_array($this->input['_registeredID']))
        ) {
            $input = ['itemtype' => $this->getType(),
                'items_id' => $this->getID(),
            ];

            foreach ($this->input['_registeredID'] as $id => $registered_id) {
                $id_object     = new RegisteredID();
                $input['name'] = $registered_id;

                if (isset($this->input['_registeredID_type'][$id])) {
                    $input['device_type'] = $this->input['_registeredID_type'][$id];
                } else {
                    $input['device_type'] = '';
                }
                //$input['device_type'] = '';
                if ($id < 0) {
                    if (!empty($registered_id)) {
                        $id_object->add($input);
                    }
                } else {
                    if (!empty($registered_id)) {
                        $input['id'] = $id;
                        $id_object->update($input);
                        unset($input['id']);
                    } else {
                        $id_object->delete(['id' => $id]);
                    }
                }
            }
            unset($this->input['_registeredID']);
        }
    }

    public function post_addItem()
    {
        $this->post_workOnItem();
        parent::post_addItem();
    }

    public function post_updateItem($history = true)
    {
        $this->post_workOnItem();
        parent::post_updateItem($history);
    }
}
