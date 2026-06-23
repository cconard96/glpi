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

use Glpi\Asset\Asset_PeripheralAsset;

/**
 * This class manages locks
 * Lock management is available for objects and link between objects. It relies on the use of
 * a is_dynamic field, to incidate if item supports lock, and is_deleted field to incidate if the
 * item or link is locked
 * By setting is_deleted to 0 again, the item is unlocked.
 *
 * Note : GLPI's core supports locks for objects. It's up to the external inventory tool to manage
 * locks for fields
 *
 * @since 0.84
 * @see ObjectLock - Object-level locks
 * @see Lockedfield - Field-level locks
 **/
class Lock extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return _n('Lock', 'Locks', $nb);
    }

    /**
     * Get infos to build an SQL query to get locks fields in a table.
     * The criteria returned will only retrieve the 'id' column of the main table by default.
     *
     * @param class-string<CommonDBTM> $itemtype      itemtype of the item to look for locked fields
     * @param class-string<CommonDBTM> $baseitemtype  itemtype of the based item
     *
     * @return array{criteria: array, field: string, type: class-string<CommonDBTM>} Necessary information to build the SQL query.
     * <ul>
     *     <li>'criteria' array contains the joins and where criteria to apply to the SQL query (DBmysqlIterator format).</li>
     *     <li>'field' refers to the criteria condition key where the item ID should be inserted. This key is not already present in the criteria array.</li>
     *     <li>'type' refers to the class of the item to look for locked fields.</li>
     * </ul>
     **/
    private static function getLocksQueryInfosByItemType($itemtype, $baseitemtype)
    {
        $criteria = [];
        $field     = '';
        $type      = $itemtype;

        switch ($itemtype) {
            case 'Peripheral':
            case 'Monitor':
            case 'Printer':
            case 'Phone':
                $relation_table = Asset_PeripheralAsset::getTable();
                $criteria = [
                    'SELECT' => [$relation_table . '.id'],
                    'FROM' => $relation_table,
                    'WHERE' => [
                        'itemtype_asset'      => $baseitemtype,
                        'itemtype_peripheral' => $itemtype,
                        'is_dynamic'          => 1,
                        'is_deleted'          => 1,
                    ],
                ];
                $field = 'items_id_asset';
                $type  = Asset_PeripheralAsset::class;
                break;

            case 'NetworkPort':
                $criteria = [
                    'SELECT' => ['glpi_networkports.id'],
                    'FROM' => 'glpi_networkports',
                    'WHERE' => [
                        'itemtype'   => $baseitemtype,
                        'is_dynamic' => 1,
                        'is_deleted' => 1,
                    ],
                ];
                $field     = 'items_id';
                break;

            case 'NetworkName':
                $criteria = [
                    'SELECT' => ['glpi_networknames.id'],
                    'FROM' => 'glpi_networknames',
                    'INNER JOIN' => [
                        'glpi_networkports' => [
                            'ON' => [
                                'glpi_networknames' => 'items_id',
                                'glpi_networkports' => 'id', [
                                    'AND' => [
                                        'glpi_networkports.itemtype'  => $baseitemtype,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'WHERE' => [
                        'glpi_networknames.is_dynamic' => 1,
                        'glpi_networknames.is_deleted' => 1,
                        'glpi_networknames.itemtype'   => 'NetworkPort',
                    ],
                ];
                $field     = 'glpi_networkports.items_id';
                break;

            case 'IPAddress':
                $criteria = [
                    'SELECT' => ['glpi_ipaddresses.id'],
                    'FROM' => 'glpi_ipaddresses',
                    'INNER JOIN' => [
                        'glpi_networknames' => [
                            'ON' => [
                                'glpi_ipaddresses' => 'items_id',
                                'glpi_networknames' => 'id', [
                                    'AND' => [
                                        'glpi_networknames.itemtype'  => 'NetworkPort',
                                    ],
                                ],
                            ],
                        ],
                        'glpi_networkports' => [
                            'ON' => [
                                'glpi_networknames' => 'items_id',
                                'glpi_networkports' => 'id', [
                                    'AND' => [
                                        'glpi_networkports.itemtype'  => $baseitemtype,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'WHERE' => [
                        'glpi_ipaddresses.is_dynamic' => 1,
                        'glpi_ipaddresses.is_deleted' => 1,
                        'glpi_ipaddresses.itemtype'   => 'NetworkName',
                    ],
                ];
                $field     = 'glpi_networkports.items_id';
                break;

            case 'Item_Disk':
                $criteria = [
                    'SELECT' => ['glpi_items_disks.id'],
                    'FROM' => 'glpi_items_disks',
                    'WHERE' => [
                        'is_dynamic' => 1,
                        'is_deleted' => 1,
                        'itemtype'   => $itemtype,
                    ],
                ];
                $field     = 'items_id';
                break;

            case 'ItemVirtualMachine':
                $table = $itemtype::getTable();
                $criteria = [
                    'SELECT' => ["$table.id"],
                    'FROM' => $table,
                    'WHERE' => [
                        'is_dynamic' => 1,
                        'is_deleted' => 1,
                        'itemtype'   => $itemtype,
                    ],
                ];
                $field     = 'items_id';
                break;

            case 'SoftwareVersion':
                $criteria = [
                    'SELECT' => ['glpi_items_softwareversions.id'],
                    'FROM' => 'glpi_items_softwareversions',
                    'WHERE' => [
                        'is_dynamic' => 1,
                        'is_deleted' => 1,
                        'itemtype'   => $itemtype,
                    ],
                ];
                $field     = 'items_id';
                $type      = 'Item_SoftwareVersion';
                break;

            default:
                // Devices
                if (str_starts_with($itemtype, "Item_Device")) {
                    $table = getTableForItemType($itemtype);
                    $criteria = [
                        'SELECT' => ["$table.id"],
                        'FROM' => $table,
                        'WHERE' => [
                            'itemtype'   => $itemtype,
                            'is_dynamic' => 1,
                            'is_deleted' => 1,
                        ],
                    ];
                    $field     = 'items_id';
                }
        }

        return [
            'criteria' => $criteria,
            'field' => $field,
            'type' => $type,
        ];
    }

    /**
     * @param array $actions
     * @param class-string<CommonDBTM> $itemtype
     * @param bool $is_deleted
     * @param ?CommonDBTM $checkitem
     * @return void
     */
    public static function getMassiveActionsForItemtype(
        array &$actions,
        $itemtype,
        $is_deleted = false,
        ?CommonDBTM $checkitem = null
    ) {
        global $CFG_GLPI;

        if (!is_subclass_of($itemtype, CommonDBTM::class)) {
            return;
        }

        $action_unlock_component = self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'unlock_component';
        $action_unlock_fields = self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'unlock_fields';

        if (
            Session::haveRight($itemtype::$rightname, UPDATE)
            && in_array($itemtype, $CFG_GLPI['inventory_types'] + $CFG_GLPI['inventory_lockable_objects'], true)
        ) {
            $actions[$action_unlock_component] = __s('Unlock components');
            $actions[$action_unlock_fields] = __s('Unlock fields');
        }
    }

    /**
     * @param MassiveAction $ma
     * @param CommonDBTM $baseitem
     * @param array $ids
     *
     * @return void
     */
    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $baseitem,
        array $ids
    ) {
        global $DB;

        switch ($ma->getAction()) {
            case 'unlock_fields':
                $input = $ma->getInput();
                if (isset($input['attached_fields'])) {
                    $base_itemtype = $baseitem->getType();
                    foreach ($ids as $id) {
                        $lock_fields_name = [];
                        foreach ($input['attached_fields'] as $fields) {
                            [, $field] = explode(' - ', $fields);
                            $lock_fields_name[] = $field;
                        }
                        $lockfield = new Lockedfield();
                        $res = $lockfield->deleteByCriteria([
                            "itemtype" => $base_itemtype,
                            "items_id" => $id,
                            "field" => $lock_fields_name,
                            "is_global" => 0,
                        ]);
                        if ($res) {
                            $ma->itemDone($base_itemtype, $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($base_itemtype, $id, MassiveAction::ACTION_KO);
                        }
                    }
                }
                return;
            case 'unlock_component':
                $input = $ma->getInput();
                if (isset($input['attached_item'])) {
                    $attached_items = $input['attached_item'];
                    if (($device_key = array_search('Device', $attached_items, true)) !== false) {
                        unset($attached_items[$device_key]);
                        $attached_items = array_merge($attached_items, Item_Devices::getDeviceTypes());
                    }
                    $links = [];
                    foreach ($attached_items as $attached_item) {
                        $infos = self::getLocksQueryInfosByItemType($attached_item, $baseitem->getType());
                        if ($item = getItemForItemtype($infos['type'])) {
                            $infos['item'] = $item;
                            $links[$attached_item] = $infos;
                        }
                    }
                    foreach ($ids as $id) {
                        $action_valid = false;
                        foreach ($links as $infos) {
                            $infos['criteria']['WHERE'][$infos['field']] = $id;
                            $locked_items = $DB->request($infos['criteria']);

                            if ($locked_items->count() === 0) {
                                $action_valid = true;
                                continue;
                            }
                            foreach ($locked_items as $data) {
                                // Restore without history
                                $action_valid = $infos['item']->restore(['id' => $data['id']]);
                            }
                        }

                        $baseItemType = $baseitem->getType();
                        if ($action_valid) {
                            $ma->itemDone($baseItemType, $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($baseItemType, $id, MassiveAction::ACTION_KO);

                            $erroredItem = getItemForItemtype($baseItemType);
                            $erroredItem->getFromDB($id);
                            $ma->addMessage($erroredItem->getErrorMessage(ERROR_ON_ACTION));
                        }
                    }
                }
                return;
        }
    }
}
