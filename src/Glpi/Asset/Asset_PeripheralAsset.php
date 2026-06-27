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

namespace Glpi\Asset;

use CommonDBRelation;
use CommonDBTM;
use CommonGLPI;
use DBmysqlIterator;
use Dropdown;
use Entity;
use Glpi\DBAL\QueryFunction;
use Html;
use LogicException;
use MassiveAction;
use Override;
use Session;

final class Asset_PeripheralAsset extends CommonDBRelation
{
    public static $itemtype_1          = 'itemtype_asset';
    public static $items_id_1          = 'items_id_asset';

    public static $itemtype_2          = 'itemtype_peripheral';
    public static $items_id_2          = 'items_id_peripheral';
    public static $checkItem_2_Rights  = self::HAVE_VIEW_RIGHT_ON_ITEM;


    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public static function getIcon()
    {
        return 'ti ti-sitemap';
    }

    /**
     * Count connections between an item and a peripheral.
     *
     * @param CommonDBTM $main_item
     * @param CommonDBTM $peripheral_item
     *
     * @return bool
     */
    private static function isAlreadyConnected(CommonDBTM $main_item, CommonDBTM $peripheral_item): bool
    {
        $connections = countElementsInTable(
            self::getTable(),
            [
                'itemtype_asset'      => $main_item::class,
                'items_id_asset'      => $main_item->getID(),
                'itemtype_peripheral' => $peripheral_item::class,
                'items_id_peripheral' => $peripheral_item->getID(),
            ]
        );
        return $connections > 0;
    }

    public function prepareInputForAdd($input)
    {
        $peripheral = self::getItemFromArray(self::$itemtype_2, self::$items_id_2, $input);

        if (
            !($peripheral instanceof CommonDBTM)
            || (!$peripheral->isGlobal()
              && (self::countLinkedAssets($peripheral) > 0))
        ) {
            return false;
        }

        $asset = self::getItemFromArray(self::$itemtype_1, self::$items_id_1, $input);
        if (
            !($asset instanceof CommonDBTM)
            || self::isAlreadyConnected($asset, $peripheral)
            || !(in_array($asset::class, self::getPeripheralHostItemtypes(), true))
        ) {
            // no duplicates
            return false;
        }

        if (!$peripheral->isGlobal()) {
            // Autoupdate some fields - should be in post_addItem (here to avoid more DB access)
            $updates = [];

            if (
                $asset->fields['locations_id'] !== $peripheral->getField('locations_id')
                && Entity::getUsedConfig('is_location_autoupdate', $asset->getEntityID())
            ) {
                $updates['locations_id'] = $asset->fields['locations_id'];
                Session::addMessageAfterRedirect(
                    __s('Location updated. The connected items have been moved in the same location.'),
                    true
                );
            }
            if (
                (Entity::getUsedConfig('is_user_autoupdate', $asset->getEntityID())
                && ($asset->fields['users_id'] !== $peripheral->getField('users_id')))
                || (Entity::getUsedConfig('is_group_autoupdate', $asset->getEntityID())
                 && ($asset->fields['groups_id'] !== $peripheral->getField('groups_id')))
            ) {
                if (Entity::getUsedConfig('is_user_autoupdate', $asset->getEntityID())) {
                    $updates['users_id'] = $asset->fields['users_id'];
                }
                if (Entity::getUsedConfig('is_group_autoupdate', $asset->getEntityID())) {
                    $updates['groups_id'] = $asset->fields['groups_id'];
                }
                Session::addMessageAfterRedirect(
                    __s('User or group updated. The connected items have been moved in the same values.'),
                    true
                );
            }

            if (
                (($asset->fields['contact'] !== $peripheral->fields['contact'])
                 || ($asset->fields['contact_num'] !== $peripheral->fields['contact_num']))
                && Entity::getUsedConfig('is_contact_autoupdate', $peripheral->getEntityID())
            ) {
                $updates['contact']     = $asset->fields['contact'] ?? '';
                $updates['contact_num'] = $asset->fields['contact_num'] ?? '';
                $updates['is_dynamic']  = $asset->fields['is_dynamic'] ?? 0;
                Session::addMessageAfterRedirect(
                    __s('Alternate username updated. The connected items have been updated using this alternate username.'),
                    true
                );
            }

            $state_autoupdate_mode = Entity::getUsedConfig('state_autoupdate_mode', $peripheral->getEntityID());
            if (
                ($state_autoupdate_mode < 0)
                && ($asset->fields['states_id'] !== $peripheral->fields['states_id'])
            ) {
                $updates['states_id'] = $asset->fields['states_id'];
                Session::addMessageAfterRedirect(
                    __s('Status updated. The connected items have been updated using this status.'),
                    true
                );
            }

            if (
                ($state_autoupdate_mode > 0)
                && ($peripheral->fields['states_id'] !== $state_autoupdate_mode)
            ) {
                $updates['states_id'] = $state_autoupdate_mode;
            }

            if (count($updates)) {
                $updates['id'] = $input['items_id_peripheral'];
                $history = true;
                if (isset($input['_no_history']) && $input['_no_history']) {
                    $history = false;
                }
                $peripheral->update($updates, $history);
            }
        }
        return parent::prepareInputForAdd($input);
    }

    public function cleanDBonPurge()
    {
        if (!isset($this->input['_no_auto_action'])) {
            // Get the item
            $asset = getItemForItemtype($this->fields['itemtype_asset']);
            if (!($asset instanceof CommonDBTM) || !$asset->getFromDB($this->fields['items_id_asset'])) {
                return;
            }

            $is_mainitem_dynamic = (bool) ($asset->fields['is_dynamic'] ?? false);

            // Get peripheral fields
            if ($peripheral = getItemForItemtype($this->fields['itemtype_peripheral'])) {
                if ($peripheral->getFromDB($this->fields['items_id_peripheral'])) {
                    if (!$peripheral->fields['is_global']) {
                        $updates = [];
                        if (Entity::getUsedConfig('is_location_autoclean', $peripheral->getEntityID()) && $peripheral->isField('locations_id')) {
                            $updates['locations_id'] = 0;
                        }
                        if (Entity::getUsedConfig('is_user_autoclean', $peripheral->getEntityID()) && $peripheral->isField('users_id')) {
                            $updates['users_id'] = 0;
                        }
                        if (Entity::getUsedConfig('is_group_autoclean', $peripheral->getEntityID()) && $peripheral->isField('groups_id')) {
                            $updates['groups_id'] = 0;
                        }
                        if (Entity::getUsedConfig('is_contact_autoclean', $peripheral->getEntityID()) && $peripheral->isField('contact')) {
                            $updates['contact'] = "";
                        }
                        if (Entity::getUsedConfig('is_contact_autoclean', $peripheral->getEntityID()) && $peripheral->isField('contact_num')) {
                            $updates['contact_num'] = "";
                        }

                        $state_autoclean_mode = Entity::getUsedConfig('state_autoclean_mode', $peripheral->getEntityID());
                        if (
                            ($state_autoclean_mode < 0)
                            && $peripheral->isField('states_id')
                        ) {
                            $updates['states_id'] = 0;
                        }

                        if (
                            ($state_autoclean_mode > 0)
                            && $peripheral->isField('states_id')
                            && ($peripheral->fields['states_id'] !== $state_autoclean_mode)
                        ) {
                            $updates['states_id'] = $state_autoclean_mode;
                        }

                        if (count($updates)) {
                            //propage is_dynamic value if needed to prevent locked fields
                            if ((bool) ($peripheral->fields['is_dynamic'] ?? false) && $is_mainitem_dynamic) {
                                $updates['is_dynamic'] = 1;
                            }
                            $updates['id'] = $this->fields['items_id_peripheral'];
                            $peripheral->update($updates);
                        }
                    }
                }
            }
        }
    }

    public static function getMassiveActionsForItemtype(
        array &$actions,
        $itemtype,
        $is_deleted = false,
        ?CommonDBTM $checkitem = null
    ) {
        global $CFG_GLPI;

        $action_prefix = self::class . MassiveAction::CLASS_ACTION_SEPARATOR;

        if (in_array($itemtype, $CFG_GLPI['directconnect_types'], true)) {
            $actions[$action_prefix . 'add']    = "<i class='ti ti-plug'></i>" . _sx('button', 'Connect');
            $actions[$action_prefix . 'remove'] = "<i class='ti ti-plug-off'></i>" . _sx('button', 'Disconnect');
        }
        parent::getMassiveActionsForItemtype($actions, $itemtype, $is_deleted, $checkitem);
    }

    public static function getRelationMassiveActionsSpecificities()
    {
        global $CFG_GLPI;

        $specificities              = parent::getRelationMassiveActionsSpecificities();
        $specificities['itemtypes'] = self::getPeripheralHostItemtypes();
        $specificities['select_items_options_1']['itemtypes']       = self::getPeripheralHostItemtypes();
        $specificities['select_items_options_2']['entity_restrict'] = $_SESSION['glpiactive_entity'];
        $specificities['select_items_options_2']['itemtypes']       = $CFG_GLPI['directconnect_types'];
        $specificities['select_items_options_2']['onlyglobal']      = true;
        $specificities['only_remove_all_at_once']                   = true;

        // Set the labels for add_item and remove_item
        $specificities['button_labels']['add']                      = _sx('button', 'Connect');
        $specificities['button_labels']['remove']                   = _sx('button', 'Disconnect');

        return $specificities;
    }

    /**
     * Unglobalize an item : duplicate item and connections
     *
     * @param CommonDBTM $item object to unglobalize
     * @return bool true on success, false on failure
     **/
    public static function unglobalizeItem(CommonDBTM $item): bool
    {
        global $DB, $CFG_GLPI;

        if (
            !\in_array($item::class, $CFG_GLPI['directconnect_types'], true)
            || !$item->isField('is_global')
        ) {
            throw new LogicException(\sprintf('Item of class "%s" does not support being unglobalized', $item::class));
        }

        if (!$item->getField('is_global')) {
            return true;
        }

        // Update item to unit management :
        $input = [
            'id'        => $item->fields['id'],
            'is_global' => 0,
        ];
        if (!$item->update($input)) {
            return false;
        }

        // Get connect_wire for this connection
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'items_id_peripheral' => $item->getID(),
                'itemtype_peripheral' => $item->getType(),
            ],
        ]);

        $first = true;
        foreach ($iterator as $data) {
            if ($first) {
                $first = false;
                unset($input['id']);
            } else {
                $temp = clone $item;
                unset($temp->fields['id']);
                if ($newID = $temp->add($temp->fields)) {
                    $conn = new self();
                    $conn->update([
                        'id'                  => $data['id'],
                        'items_id_peripheral' => $newID,
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * Make a select box for connections
     *
     * @param string            $itemtype        type to connect
     * @param string            $fromtype        from where the connection is
     * @param string            $myname          select name
     * @param int|int[] $entity_restrict Restrict to a defined entity (default = -1)
     * @param bool           $onlyglobal      display only global devices (used for templates) (default 0)
     * @param int[]         $used            Already used items ID: not to display in dropdown
     *
     * @return int Random generated number used for select box ID (select box HTML is printed)
     */
    public static function dropdownConnect(
        $itemtype,
        $fromtype,
        $myname,
        $entity_restrict = -1,
        $onlyglobal = false,
        $used = []
    ): int {
        global $CFG_GLPI;

        $rand     = mt_rand();

        $field_id = Html::cleanId("dropdown_" . $myname . $rand);
        $param    = [
            'entity_restrict' => $entity_restrict,
            'fromtype'        => $fromtype,
            'itemtype'        => $itemtype,
            'onlyglobal'      => $onlyglobal,
            'used'            => $used,
            '_idor_token'     => Session::getNewIDORToken($itemtype, [
                'entity_restrict' => $entity_restrict,
            ]),
        ];

        echo Html::jsAjaxDropdown(
            $myname,
            $field_id,
            $CFG_GLPI['root_doc'] . "/ajax/getDropdownConnect.php",
            $param
        );

        return $rand;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        global $CFG_GLPI;

        // can exists for Template
        /** @var CommonDBTM $item */
        if ($item->can($item->getID(), READ)) {
            $nb = 0;

            if (in_array($item::class, $CFG_GLPI['directconnect_types'], true)) {
                $canview = true;
                if ($_SESSION['glpishow_count_on_tabs']) {
                    $nb = self::countLinkedAssets($item);
                }
            } else {
                $canview = self::canViewPeripherals($item);
                if ($canview && $_SESSION['glpishow_count_on_tabs']) {
                    $nb = self::countPeripherals($item);
                }
            }

            if ($canview) {
                return self::createTabEntry(
                    _n('Connection', 'Connections', Session::getPluralNumber()),
                    $nb,
                    $item::class
                );
            }
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;

        if (!$item instanceof CommonDBTM || !$item->can($item->getID(), READ)) {
            return false;
        }

        if (in_array($item::class, $CFG_GLPI['directconnect_types'], true)) {
            self::showForPeripheral($item, $withtemplate);
            return true;
        } elseif (self::canViewPeripherals($item)) {
            self::showForAsset($item, $withtemplate);
            return true;
        }

        return false;
    }

    /**
     * @param CommonDBTM $item
     * @param array      $entities
     *
     * @return bool
     */
    public static function canUnrecursSpecif(CommonDBTM $item, $entities)
    {
        global $DB;

        if (in_array($item::class, self::getPeripheralHostItemtypes(), true)) {
            // RELATION : peripherals -> items
            $iterator = $DB->request([
                'SELECT' => [
                    'itemtype_peripheral',
                    QueryFunction::groupConcat(
                        expression: 'items_id_peripheral',
                        distinct: true,
                        alias: 'ids'
                    ),
                ],
                'FROM' => self::getTable(),
                'WHERE' => [
                    'itemtype_asset' => $item->getType(),
                    'items_id_asset' => $item->getID(),
                ],
                'GROUP' => 'itemtype_peripheral',
            ]);

            foreach ($iterator as $data) {
                if (!class_exists($data['itemtype_peripheral'])) {
                    continue;
                }
                if (
                    countElementsInTable(
                        $data['itemtype_peripheral']::getTable(),
                        [
                            'id' => explode(',', $data['ids']),
                            'NOT' => ['entities_id' => $entities],
                        ]
                    ) > 0
                ) {
                    return false;
                }
            }
        } else {
            // RELATION : computers -> items
            $iterator = $DB->request([
                'SELECT' => [
                    'itemtype_peripheral',
                    QueryFunction::groupConcat(
                        expression: 'items_id_peripheral',
                        distinct: true,
                        alias: 'ids'
                    ),
                    'itemtype_asset',
                    'items_id_asset',
                ],
                'FROM'   => self::getTable(),
                'WHERE'  => [
                    'itemtype_peripheral' => $item->getType(),
                    'items_id_peripheral' => $item->fields['id'],
                ],
                'GROUP'  => 'itemtype_peripheral',
            ]);

            foreach ($iterator as $data) {
                if (countElementsInTable($data['itemtype_asset']::getTable(), ['id' => $data['items_id_asset'], 'NOT' => ['entities_id' => $entities]]) > 0) {
                    return false;
                }
            }
        }

        return true;
    }

    protected static function getListForItemParams(CommonDBTM $item, $noent = false)
    {
        $params = parent::getListForItemParams($item, $noent);
        $params['WHERE'][self::getTable() . '.is_deleted'] = 0;
        return $params;
    }

    
    public static function getTypeItemsQueryParams_Select(CommonDBTM $item): array
    {
        $table = self::getTable();
        $select = parent::getTypeItemsQueryParams_Select($item);
        $select[] = "$table.is_dynamic AS {$table}_is_dynamic";

        return $select;
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Connection', 'Connections', $nb);
    }

    /**
     * @param ?class-string<CommonDBTM> $itemtype
     *
     * @return array
     */
    public static function rawSearchOptionsToAdd($itemtype = null)
    {
        global $CFG_GLPI;

        $tab = [];
        $peripherals = $CFG_GLPI['directconnect_types'];

        foreach ($peripherals as $peripheral) {
            if (class_exists($peripheral) && method_exists($peripheral, 'rawSearchOptionsToAdd')) {
                $tab = [...$tab, ...$peripheral::rawSearchOptionsToAdd($itemtype)];
            }
        }

        return $tab;
    }

    
    public static function getRelationMassiveActionsPeerForSubForm(MassiveAction $ma)
    {
        global $CFG_GLPI;

        $items = $ma->getItems();

        if (
            count(array_intersect(
                array_keys($items),
                $CFG_GLPI['directconnect_types']
            )) > 0
        ) {
            return 1;
        }

        if (
            empty(array_diff(
                array_keys($items),
                self::getPeripheralHostItemtypes()
            ))
        ) {
            return 2;
        }

        return parent::getRelationMassiveActionsPeerForSubForm($ma);
    }

    /**
     * Check whether the user can view peripherals from the given item.
     */
    private static function canViewPeripherals(CommonDBTM $item): bool
    {
        if (!$item::canView()) {
            return false;
        }

        foreach (self::getPeripheralHostItemtypes() as $itemtype) {
            if ($item instanceof $itemtype) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns itemtypes of assets that can have peripherals.
     *
     * @return class-string<CommonDBTM>[]
     */
    public static function getPeripheralHostItemtypes(): array
    {
        global $CFG_GLPI;

        return $CFG_GLPI['peripheralhost_types'];
    }

    /**
     * Return peripheral assets count for given main asset.
     */
    private static function countPeripherals(CommonDBTM $asset): int
    {
        global $CFG_GLPI;

        $count = 0;

        foreach ($CFG_GLPI['directconnect_types'] as $itemtype) {
            $count += count(self::getPeripheralAssets($asset, $itemtype));
        }

        return $count;
    }

    /**
     * Return linked assets count for given peripheral asset.
     */
    private static function countLinkedAssets(CommonDBTM $peripheral): int
    {
        $count = 0;

        foreach (self::getPeripheralHostItemtypes() as $itemtype) {
            $count += count(self::getItemConnectionsForItemtype($peripheral, $itemtype));
        }

        return $count;
    }

    
    public static function countForItem(CommonDBTM $item)
    {
        return self::countLinkedAssets($item);
    }

    
    public static function getItemField($itemtype): string
    {
        global $CFG_GLPI;

        if (in_array($itemtype, self::getPeripheralHostItemtypes(), true)) {
            return 'items_id_asset';
        }
        if (in_array($itemtype, $CFG_GLPI['directconnect_types'], true)) {
            return 'items_id_peripheral';
        }

        return parent::getItemField($itemtype);
    }

    /**
     * Returns peripheral assets data for given main asset.
     *
     * @param CommonDBTM $asset Main asset.
     * @param string $itemtype  Itemtype of the peripherals to retrieve.
     * @return iterable
     */
    private static function getPeripheralAssets(CommonDBTM $asset, string $itemtype): iterable
    {
        global $DB;

        $peripheral = getItemForItemtype($itemtype);

        return $DB->request([
            'SELECT' => self::getTypeItemsQueryParams_Select($peripheral),
            'FROM'   => $peripheral::getTable(),
            'LEFT JOIN' => [
                self::getTable() => [
                    'FKEY' => [
                        self::getTable()      => 'items_id_peripheral',
                        $peripheral::getTable() => 'id',
                        [
                            'AND' => [
                                self::getTable() . '.itemtype_peripheral' => $itemtype,
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE' => [
                self::getTable() . '.is_deleted'     => 0,
                self::getTable() . '.itemtype_asset' => $asset::class,
                self::getTable() . '.items_id_asset' => $asset->getID(),
            ] + getEntitiesRestrictCriteria($peripheral::getTable()),
            'ORDER' => $peripheral::getTable() . '.' . $peripheral::getNameField(),
        ]);
    }

    /**
     * Returns used peripherals.
     *
     * @param class-string<CommonDBTM> $itemtype Itemtype of the peripherals to retrieve.
     *
     * @return DBmysqlIterator
    */
    private static function getUsedPeripherals(string $itemtype): DBmysqlIterator
    {
        global $DB;

        $peripheral = getItemForItemtype($itemtype);

        return $DB->request([
            'SELECT' => self::getTypeItemsQueryParams_Select($peripheral),
            'FROM'   => $peripheral::getTable(),
            'LEFT JOIN' => [
                self::getTable() => [
                    'FKEY' => [
                        self::getTable()      => 'items_id_peripheral',
                        $peripheral::getTable() => 'id',
                        [
                            'AND' => [
                                self::getTable() . '.itemtype_peripheral' => $itemtype,
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE' => [
                self::getTable() . '.is_deleted'     => 0,
            ] + getEntitiesRestrictCriteria($peripheral::getTable()),
            'ORDER' => $peripheral::getTable() . '.' . $peripheral::getNameField(),
        ]);
    }

    /**
     * Returns linked main assets data for given peripheral asset.
     *
     * @param CommonDBTM               $peripheral Peripheral asset.
     * @param class-string<CommonDBTM> $itemtype   Itemtype of the main assets to retrieve.
     *
     * @return DBmysqlIterator
     */
    private static function getItemConnectionsForItemtype(CommonDBTM $peripheral, string $itemtype): DBmysqlIterator
    {
        global $DB;

        $item = getItemForItemtype($itemtype);

        return $DB->request([
            'SELECT' => self::getTypeItemsQueryParams_Select($item),
            'FROM'   => $item::getTable(),
            'LEFT JOIN' => [
                self::getTable() => [
                    'FKEY' => [
                        self::getTable() => 'items_id_asset',
                        $item::getTable()  => 'id',
                        [
                            'AND' => [
                                self::getTable() . '.itemtype_asset' => $itemtype,
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE' => [
                self::getTable() . '.is_deleted'          => 0,
                self::getTable() . '.itemtype_peripheral' => $peripheral::class,
                self::getTable() . '.items_id_peripheral' => $peripheral->getID(),
            ] + getEntitiesRestrictCriteria($item::getTable()),
            'ORDER' => $item::getTable() . '.' . $item::getNameField(),
        ]);
    }
}
