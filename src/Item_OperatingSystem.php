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

class Item_OperatingSystem extends CommonDBRelation
{
    public static $itemtype_1 = OperatingSystem::class;
    public static $items_id_1 = 'operatingsystems_id';
    public static $itemtype_2 = 'itemtype';
    public static $items_id_2 = 'items_id';
    public static $checkItem_1_Rights = self::DONT_CHECK_ITEM_RIGHTS;

    public static $mustBeAttached_1 = false;


    public static function getTypeName($nb = 0)
    {
        return _n('Item operating system', 'Item operating systems', $nb);
    }

    /**
     * Get operating systems related to a given item
     *
     * @param CommonDBTM $item  Item instance
     * @param string     $sort  Field to sort on
     * @param string     $order Sort order
     *
     * @return DBmysqlIterator
     */
    public static function getFromItem(CommonDBTM $item, $sort = null, $order = null): DBmysqlIterator
    {
        global $DB;

        if ($sort === null) {
            $sort = "glpi_items_operatingsystems.id";
        }
        if ($order === null) {
            $order = 'ASC';
        }

        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_items_operatingsystems.id AS assocID',
                'glpi_operatingsystems.name',
                'glpi_operatingsystemversions.name AS version',
                'glpi_operatingsystemarchitectures.name AS architecture',
                'glpi_operatingsystemservicepacks.name AS servicepack',
            ],
            'FROM'      => 'glpi_items_operatingsystems',
            'LEFT JOIN' => [
                'glpi_operatingsystems'             => [
                    'ON' => [
                        'glpi_items_operatingsystems' => 'operatingsystems_id',
                        'glpi_operatingsystems'       => 'id',
                    ],
                ],
                'glpi_operatingsystemservicepacks'  => [
                    'ON' => [
                        'glpi_items_operatingsystems'       => 'operatingsystemservicepacks_id',
                        'glpi_operatingsystemservicepacks'  => 'id',
                    ],
                ],
                'glpi_operatingsystemarchitectures' => [
                    'ON' => [
                        'glpi_items_operatingsystems'       => 'operatingsystemarchitectures_id',
                        'glpi_operatingsystemarchitectures' => 'id',
                    ],
                ],
                'glpi_operatingsystemversions'      => [
                    'ON' => [
                        'glpi_items_operatingsystems'    => 'operatingsystemversions_id',
                        'glpi_operatingsystemversions'   => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_items_operatingsystems.itemtype' => $item->getType(),
                'glpi_items_operatingsystems.items_id' => $item->getID(),
            ],
            'ORDERBY'   => "$sort $order",
        ]);
        return $iterator;
    }

    public function getConnexityItem(
        $itemtype,
        $items_id,
        $getFromDB = true,
        $getEmpty = true,
        $getFromDBOrEmpty = true
    ) {
        //overrided to set $getFromDBOrEmpty to true
        return parent::getConnexityItem($itemtype, $items_id, $getFromDB, $getEmpty, $getFromDBOrEmpty);
    }

    protected function computeFriendlyName()
    {
        $item = getItemForItemtype($this->fields['itemtype']);
        $item->getFromDB($this->fields['items_id']);
        $name = $item->getTypeName(1) . ' ' . $item->getName();

        return $name;
    }

    public function rawSearchOptions()
    {

        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics'),
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'license_number',
            'name'               => __('Serial number'),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'licenseid',
            'name'               => __('Product ID'),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        return $tab;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return array
     */
    public static function rawSearchOptionsToAdd($itemtype)
    {
        $tab = [];
        $tab[] = [
            'id'                => 'operatingsystem',
            'name'              => __('Operating System'),
        ];

        $tab[] = [
            'id'                 => '45',
            'table'              => 'glpi_operatingsystems',
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_items_operatingsystems',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'specific_itemtype'  => $itemtype,
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '46',
            'table'              => 'glpi_operatingsystemversions',
            'field'              => 'name',
            'name'               => _n('Version', 'Versions', 1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_items_operatingsystems',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'specific_itemtype'  => $itemtype,
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '41',
            'table'              => 'glpi_operatingsystemservicepacks',
            'field'              => 'name',
            'name'               => OperatingSystemServicePack::getTypeName(1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_items_operatingsystems',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'specific_itemtype'  => $itemtype,
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '43',
            'table'              => 'glpi_items_operatingsystems',
            'field'              => 'license_number',
            'name'               => __('Serial number'),
            'datatype'           => 'string',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
                'specific_itemtype'  => $itemtype,
            ],
        ];

        $tab[] = [
            'id'                 => '44',
            'table'              => 'glpi_items_operatingsystems',
            'field'              => 'licenseid',
            'name'               => __('Product ID'),
            'datatype'           => 'string',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
                'specific_itemtype'  => $itemtype,
            ],
        ];

        $tab[] = [
            'id'                 => '66',
            'table'              => 'glpi_items_operatingsystems',
            'field'              => 'install_date',
            'name'               => __('Installation date'),
            'datatype'           => 'datetime',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
                'specific_itemtype'  => $itemtype,
            ],
        ];

        $tab[] = [
            'id'                 => '61',
            'table'              => 'glpi_operatingsystemarchitectures',
            'field'              => 'name',
            'name'               => _n('Architecture', 'Architectures', 1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_items_operatingsystems',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'specific_itemtype'  => $itemtype,
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '64',
            'table'              => 'glpi_operatingsystemkernels',
            'field'              => 'name',
            'name'               => _n('Kernel', 'Kernels', 1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_operatingsystemkernelversions',
                    'joinparams'         => [
                        'beforejoin'   => [
                            'table'        => 'glpi_items_operatingsystems',
                            'joinparams'   => [
                                'jointype'           => 'itemtype_item',
                                'specific_itemtype'  => $itemtype,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '48',
            'table'              => 'glpi_operatingsystemkernelversions',
            'field'              => 'name',
            'name'               => _n('Kernel version', 'Kernel versions', 1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_items_operatingsystems',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'specific_itemtype'  => $itemtype,
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '63',
            'table'              => 'glpi_operatingsystemeditions',
            'field'              => 'name',
            'name'               => _n('Edition', 'Editions', 1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_items_operatingsystems',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'specific_itemtype'  => $itemtype,
                    ],
                ],
            ],
        ];

        return $tab;
    }


    public static function getRelationMassiveActionsSpecificities()
    {
        global $CFG_GLPI;

        $specificities              = parent::getRelationMassiveActionsSpecificities();

        $specificities['itemtypes'] = $CFG_GLPI['operatingsystem_types'];
        return $specificities;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {

        switch ($ma->getAction()) {
            case 'update':
                $input = $ma->getInput();
                unset($input['update']);
                unset($input['os_field']);
                $ios = new Item_OperatingSystem();
                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        if ($item->can($id, UPDATE, $input)) {
                            $exists = $ios->getFromDBByCrit([
                                'itemtype'  => $item->getType(),
                                'items_id'  => $item->getID(),
                            ]);
                            $ok = false;
                            if ($exists) {
                                $ok = $ios->update(['id'  => $ios->getID()] + $input);
                            } else {
                                $ok = $ios->add(['itemtype' => $item->getType(), 'items_id' => $item->getID()] + $input);
                            }

                            if ($ok != false) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                    }
                }
                break;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function prepareInputForAdd($input)
    {
        $item = getItemForItemtype($input['itemtype']);
        $item->getFromDB($input['items_id']);
        $input['entities_id'] = $item->fields['entities_id'];
        $input['is_recursive'] = $item->fields['is_recursive'];
        return $input;
    }
}
