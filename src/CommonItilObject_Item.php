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
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Glpi\DBAL\QueryUnion;

/**
 * CommonItilObject_Item Class
 *
 * Relation between CommonItilObject_Item and Items
 */
abstract class CommonItilObject_Item extends CommonDBRelation
{
    public static function getIcon()
    {
        return 'ti ti-package';
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function canCreateItem(): bool
    {
        /** @var CommonITILObject $obj */
        $obj = getItemForItemtype(static::$itemtype_1);

        if ($obj->canUpdateItem()) {
            return true;
        }

        return parent::canCreateItem();
    }

    private function updateItemTCO(): void
    {
        //TODO Costs for changes and problems should probably affect TCO too but there should also be a way to handle costs affecting multiple assets
        //Example, A ticket with a cost of $400 with two computers shouldn't add $400 cost of ownership to both.
        $cost_class = match (static::$itemtype_1) {
            Ticket::class => TicketCost::class,
            //Change::class => ChangeCost::class,
            //Problem::class => ProblemCost::class,
            default => null
        };
        if ($cost_class) {
            $cost_obj = new $cost_class();
            $cost_obj->updateTCOItem($this->fields['itemtype'], $this->fields['items_id']);
        }
    }

    public function post_addItem()
    {
        $this->updateItemTCO();
        /** @var CommonITILObject $obj */
        $obj = getItemForItemtype(static::$itemtype_1);
        $input  = [
            'id'            => $this->fields[static::$items_id_1],
            'date_mod'      => $_SESSION["glpi_currenttime"],
        ];

        if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
            $input['_forcenotif'] = true;
        }
        if (isset($this->input['_disablenotif']) && $this->input['_disablenotif']) {
            $input['_disablenotif'] = true;
        }

        $obj->update($input);
        parent::post_addItem();
    }

    public function post_purgeItem()
    {
        $this->updateItemTCO();
        $obj = getItemForItemtype(static::$itemtype_1);
        $input = [
            'id'            => $this->fields[static::$items_id_1],
            'date_mod'      => $_SESSION["glpi_currenttime"],
        ];

        if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
            $input['_forcenotif'] = true;
        }
        $obj->update($input);

        parent::post_purgeItem();
    }

    public function prepareInputForAdd($input)
    {
        // Avoid duplicate entry
        if (
            countElementsInTable(
                static::getTable(),
                [
                    'WHERE' => [
                        static::$items_id_1 => $input[static::$items_id_1],
                        static::$itemtype_2   => $input[static::$itemtype_2],
                        static::$items_id_2   => $input[static::$items_id_2],
                    ],
                    'LIMIT' => 1,
                ]
            ) > 0
        ) {
            //TODO add Session::addMessageAfterRedirect() w/ relevant msg
            return false;
        }

        if (!is_subclass_of(static::$itemtype_1, CommonITILObject::class)) {
            return parent::prepareInputForAdd($input);
        }

        /** @var CommonITILObject $itil */
        $itil = new static::$itemtype_1();
        $item = getItemForItemtype($input["itemtype"]);

        // Process rules based on linked item location if needed
        if (
            $itil->getFromDB($input[static::$items_id_1])
            && empty($itil->fields['locations_id'])
            && !$itil->isClosed() // Do not allow rules to modify a closed ITIL item
            && $item->getFromDB($input["items_id"])
            && $item->maybeLocated()
        ) {
            $itil->fields['_locations_id_of_item'] = $item->fields['locations_id'];

            $rules = $itil::getRuleCollectionClassInstance((int) $itil->getEntityID());
            $itil->fields = $rules->processAllRules(
                $itil->fields,
                $itil->fields,
                ['recursive' => true]
            );
            unset($itil->fields['_locations_id_of_item']);
            // Update only the location field
            $itil->updateInDB(['locations_id']);
        }

        return parent::prepareInputForAdd($input);
    }

    /**
     * Count number of ITIL objects for the provided item and other items linked to the requested item
     * @param CommonDBTM $item
     * @return int
     * @see Asset_PeripheralAsset
     * @see static::getLinkedItems()
     */
    public static function countForItemAndLinked(CommonDBTM $item)
    {
        // Direct links
        $nb = parent::countForItem($item);

        // Linked items
        $itil_table = static::$itemtype_1::getTable();
        $linkeditems = $item->getLinkedItems();
        foreach ($linkeditems as $type => $ids) {
            $type_item = getItemForItemtype($type);
            if (!$type_item) {
                continue;
            }
            // Only count valid links and non-deleted items
            $criteria = [
                'INNER JOIN' => [
                    $itil_table => [
                        'FKEY' => [
                            static::getTable() => static::$items_id_1,
                            $itil_table       => 'id',
                        ],
                    ],
                ],
                'WHERE' => [
                    static::getTable() . '.' . static::$itemtype_2 => $type,
                    static::getTable() . '.' . static::$items_id_2 => $ids,
                ],
            ];
            if ($type_item->maybeDeleted()) {
                $criteria['WHERE']['is_deleted'] = 0;
            }
            $nb += countElementsInTable(static::getTable(), $criteria);
        }

        return $nb;
    }

    /**
     * Count number of ITIL objects for the provided actor item (user, group, etc)
     * @param CommonDBTM $item
     * @return int
     */
    protected static function countForActor(CommonDBTM $item): int
    {
        global $DB;

        /** @var CommonITILObject $itil */
        $itil = getItemForItemtype(static::$itemtype_1);
        $link_class_prop = strtolower($item::class) . 'linkclass';
        if (!isset($itil->{$link_class_prop})) {
            return 0;
        }
        $link_table = ($itil->{$link_class_prop})::getTable();
        $result = $DB->request([
            'SELECT' => [
                QueryFunction::count($itil::getForeignKeyField(), true, 'cpt'),
            ],
            'FROM'   => $link_table,
            'WHERE'  => [
                $item->getForeignKeyField()   => $item->fields['id'],
            ],
        ])->current();
        return $result['cpt'] ?? 0;
    }

    /**
     * Retrieves a list of devices associated with a user, including their own devices,
     * devices owned by their groups, installed software, and linked items to computers.
     *
     * @param int $userID The ID of the user whose devices are to be retrieved.
     * @param int|array $entity_restrict Optional. The entity restriction to apply. Default is -1.
     *
     * @return array<string, array<string, string>> An associative array of devices associated with the user.
     *               The array keys are the categories, and the values are arrays of device descriptions.
     *
     * The categories include:
     * - 'My devices': Devices directly assigned to the user.
     * - 'Devices own by my groups': Devices owned by the user's groups.
     * - 'Installed software': Software linked to all owned items.
     * - 'Connected devices': Items linked to computers.
     */
    public static function getMyDevices(int $userID, mixed $entity_restrict = -1): array
    {
        $my_devices = [];
        $already_add = [];

        // My items
        $devices = self::getMyAssigneeDevices($userID, $entity_restrict, $already_add);
        foreach ($devices as $itemtype => $items) {
            foreach ($items as $data) {
                $output = $data[$itemtype::getNameField()];
                if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                    $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                }
                $output = sprintf(__('%1$s - %2$s'), $itemtype::getTypeName(), $output);
                if ($itemtype != 'Software') {
                    if (!empty($data['serial'])) {
                        $output = sprintf(__('%1$s - %2$s'), $output, $data['serial']);
                    }
                    if (!empty($data['otherserial'])) {
                        $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                    }
                }
                $my_devices[__('My devices')][$itemtype . "_" . $data["id"]] = $output;
            }
        }

        // My group items
        if (Session::haveRight("show_group_hardware", READ)) {
            $devices = self::getMyGroupsDevices($userID, $entity_restrict, $already_add);
            foreach ($devices as $itemtype => $items) {
                foreach ($items as $data) {
                    $output = $data[$itemtype::getNameField()];
                    if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                        $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                    }
                    $output = sprintf(__('%1$s - %2$s'), $itemtype::getTypeName(), $output);
                    if (!empty($data['serial'])) {
                        $output = sprintf(__('%1$s - %2$s'), $output, $data['serial']);
                    }
                    if (!empty($data['otherserial'])) {
                        $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                    }
                    $my_devices[__('Devices own by my groups')][$itemtype . "_" . $data["id"]] = $output;
                }
            }
        }

        // Get software linked to all owned items
        $software = self::getLinkedSoftware($already_add, $entity_restrict, $already_add);
        foreach ($software as $data) {
            $output = sprintf(__('%1$s - %2$s'), Software::getTypeName(), $data["name"]);
            $output = sprintf(
                __('%1$s (%2$s)'),
                $output,
                sprintf(__('%1$s: %2$s'), __('version'), $data["version"])
            );
            if ($_SESSION["glpiis_ids_visible"]) {
                $output = sprintf(__('%1$s (%2$s)'), $output, $data["id"]);
            }
            $my_devices[__('Installed software')]["Software_" . $data["id"]] = $output;
        }

        // Get linked items to computers
        $linked_items = self::getLinkedItemsToComputers($already_add, $entity_restrict, $already_add);
        foreach ($linked_items as $itemtype => $items) {
            foreach ($items as $data) {
                $output = $data[$itemtype::getNameField()];
                if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                    $output = sprintf(__('%1$s (%2$s)'), $output, $data['id']);
                }
                $output = sprintf(__('%1$s - %2$s'), $itemtype::getTypeName(), $output);
                if ($itemtype !== Software::class) {
                    $output = sprintf(__('%1$s - %2$s'), $output, $data['otherserial']);
                }
                $my_devices[__('Connected devices')][$itemtype . "_" . $data["id"]] = $output;
            }
        }

        return $my_devices;
    }

    /**
     * Retrieves the devices assigned to a specific user within a restricted entity.
     *
     * @param int $userID The ID of the user for whom to retrieve the devices.
     * @param int|array $entity_restrict Optional. The entity restriction criteria. Default is -1 (no restriction).
     * @param array &$already_add Optional. An array to keep track of already added devices to avoid duplicates.
     *
     * @return array<string, array>  An associative array of devices assigned to the user, categorized by item type.
     */
    private static function getMyAssigneeDevices(int $userID, mixed $entity_restrict = -1, array &$already_add = []): array
    {
        global $CFG_GLPI, $DB;

        $devices = [];

        foreach ($CFG_GLPI["assignable_types"] as $itemtype) {
            if (
                ($item = getItemForItemtype($itemtype))
                && CommonITILObject::isPossibleToAssignType($itemtype)
            ) {
                $itemtable = getTableForItemType($itemtype);

                $criteria = [
                    'FROM'   => $itemtable,
                    'WHERE'  => [
                        'users_id' => $userID,
                    ] + getEntitiesRestrictCriteria($itemtable, '', $entity_restrict, $item->maybeRecursive())
                    + $itemtype::getSystemSQLCriteria(),
                    'ORDER'  => $item::getNameField(),
                ];

                if ($item->maybeDeleted()) {
                    $criteria['WHERE']['is_deleted'] = 0;
                }
                if ($item->maybeTemplate()) {
                    $criteria['WHERE']['is_template'] = 0;
                }
                if (in_array($itemtype, $CFG_GLPI["helpdesk_visible_types"])) {
                    $criteria['WHERE']['is_helpdesk_visible'] = 1;
                }

                $iterator = $DB->request($criteria);
                foreach ($iterator as $data) {
                    if (!isset($already_add[$itemtype]) || !in_array($data["id"], $already_add[$itemtype])) {
                        $devices[$itemtype][] = $data;
                        $already_add[$itemtype][] = $data["id"];
                    }
                }
            }
        }

        return $devices;
    }

    /**
     * Retrieves the devices associated with the groups of a given user.
     *
     * @param int $userID The ID of the user whose groups' devices are to be retrieved.
     * @param int|array $entity_restrict Optional. The entity restriction criteria. Default is -1 (no restriction).
     * @param array &$already_add Optional. An array to keep track of already added devices to avoid duplicates.
     *
     * @return array<string, array> An associative array of devices grouped by item type.
     */
    private static function getMyGroupsDevices(int $userID, mixed $entity_restrict = -1, array &$already_add = []): array
    {
        global $CFG_GLPI, $DB;

        $devices = [];
        $groups  = [];

        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_groups_users.groups_id',
                'glpi_groups.name',
            ],
            'FROM'      => 'glpi_groups_users',
            'LEFT JOIN' => [
                'glpi_groups'  => [
                    'ON' => [
                        'glpi_groups_users'  => 'groups_id',
                        'glpi_groups'        => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_groups_users.users_id'  => $userID,
            ] + getEntitiesRestrictCriteria('glpi_groups', '', $entity_restrict, true),
        ]);

        if (count($iterator)) {
            foreach ($iterator as $data) {
                $a_groups                     = getAncestorsOf("glpi_groups", $data["groups_id"]);
                $a_groups[$data["groups_id"]] = $data["groups_id"];
                $groups = array_merge($groups, $a_groups);
            }

            foreach ($CFG_GLPI["assignable_types"] as $itemtype) {
                if (
                    ($item = getItemForItemtype($itemtype))
                    && CommonITILObject::isPossibleToAssignType($itemtype)
                ) {
                    $itemtable  = getTableForItemType($itemtype);
                    $criteria = [
                        'SELECT'  => [$itemtable . '.*'],
                        'FROM'    => $itemtable,
                        'LEFT JOIN' => [
                            Group_Item::getTable() => [
                                'ON' => [
                                    $itemtable => 'id',
                                    Group_Item::getTable() => 'items_id', [
                                        'AND' => [
                                            Group_Item::getTable() . '.itemtype' => $itemtype,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'WHERE'   => [
                            Group_Item::getTable() . '.type' => Group_Item::GROUP_TYPE_NORMAL,
                            Group_Item::getTable() . '.groups_id' => $groups,
                        ] + getEntitiesRestrictCriteria($itemtable, '', $entity_restrict, $item->maybeRecursive())
                        + $itemtype::getSystemSQLCriteria(),
                        'GROUPBY' => $itemtable . '.id',
                        'ORDER'   => $item::getNameField(),
                    ];

                    if ($item->maybeDeleted()) {
                        $criteria['WHERE']['is_deleted'] = 0;
                    }
                    if ($item->maybeTemplate()) {
                        $criteria['WHERE']['is_template'] = 0;
                    }

                    $iterator = $DB->request($criteria);
                    if (count($iterator)) {
                        if (!isset($already_add[$itemtype])) {
                            $already_add[$itemtype] = [];
                        }
                        foreach ($iterator as $data) {
                            if (!in_array($data["id"], $already_add[$itemtype])) {
                                $devices[$itemtype][] = $data;
                                $already_add[$itemtype][] = $data["id"];
                            }
                        }
                    }
                }
            }
        }

        return $devices;
    }

    /**
     * Retrieves a list of linked software for a given user.
     *
     * @param array $devices The devices to retrieve linked software for.
     * @param int|array $entity_restrict Optional. The entity restriction criteria. Default is -1 (no restriction).
     * @param array &$already_add Reference to an array that keeps track of already added items to avoid duplicates.
     *
     * @return array An array of linked software information, including software name, version, and ID.
     */
    private static function getLinkedSoftware(array $devices, mixed $entity_restrict = -1, array &$already_add = []): array
    {
        global $CFG_GLPI, $DB;

        $software = [];

        if (in_array('Software', $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
            $software_helpdesk_types = array_intersect($CFG_GLPI['software_types'], $_SESSION["glpiactiveprofile"]["helpdesk_item_type"]);
            foreach ($software_helpdesk_types as $itemtype) {
                if (isset($devices[$itemtype]) && count($devices[$itemtype])) {
                    $iterator = $DB->request([
                        'SELECT'          => [
                            'glpi_softwareversions.name AS version',
                            'glpi_softwares.name AS name',
                            'glpi_softwares.id',
                        ],
                        'DISTINCT'        => true,
                        'FROM'            => 'glpi_items_softwareversions',
                        'LEFT JOIN'       => [
                            'glpi_softwareversions'  => [
                                'ON' => [
                                    'glpi_items_softwareversions' => 'softwareversions_id',
                                    'glpi_softwareversions'       => 'id',
                                ],
                            ],
                            'glpi_softwares'        => [
                                'ON' => [
                                    'glpi_softwareversions' => 'softwares_id',
                                    'glpi_softwares'        => 'id',
                                ],
                            ],
                        ],
                        'WHERE'        => [
                            'glpi_items_softwareversions.items_id' => $devices[$itemtype],
                            'glpi_items_softwareversions.itemtype' => $itemtype,
                            'glpi_softwares.is_helpdesk_visible'   => 1,
                        ] + getEntitiesRestrictCriteria('glpi_softwares', '', $entity_restrict),
                        'ORDERBY'      => 'glpi_softwares.name',
                    ]);

                    if (count($iterator)) {
                        if (!isset($already_add['Software'])) {
                            $already_add['Software'] = [];
                        }
                        foreach ($iterator as $data) {
                            if (!in_array($data["id"], $already_add['Software'])) {
                                $software[] = $data;
                                $already_add['Software'][] = $data["id"];
                            }
                        }
                    }
                }
            }
        }

        return $software;
    }

    /**
     * Retrieves linked items to computers based on the given user ID and entity restriction.
     *
     * @param array $devices The computers to retrieve linked items for.
     * @param int $entity_restrict The entity restriction to apply. Default is -1 (no restriction).
     * @param array &$already_add Reference to an array that keeps track of already added items.
     *
     * @return array<class-string<CommonDBTM>, array> An array of linked items categorized by their item types.
     */
    private static function getLinkedItemsToComputers(array $devices, mixed $entity_restrict = -1, array &$already_add = []): array
    {
        global $CFG_GLPI, $DB;

        $linked_items = [];

        foreach (Asset_PeripheralAsset::getPeripheralHostItemtypes() as $peripheralhost_itemtype) {
            if (isset($devices[$peripheralhost_itemtype]) && count($devices[$peripheralhost_itemtype])) {
                foreach ($CFG_GLPI['directconnect_types'] as $peripheral_itemtype) {
                    if (
                        in_array($peripheral_itemtype, $_SESSION["glpiactiveprofile"]["helpdesk_item_type"])
                        && ($item = getItemForItemtype($peripheral_itemtype))
                    ) {
                        $itemtable = getTableForItemType($peripheral_itemtype);
                        if (!isset($already_add[$peripheral_itemtype])) {
                            $already_add[$peripheral_itemtype] = [];
                        }
                        $relation_table = Asset_PeripheralAsset::getTable();
                        $criteria = [
                            'SELECT'          => "$itemtable.*",
                            'DISTINCT'        => true,
                            'FROM'            => $relation_table,
                            'LEFT JOIN'       => [
                                $itemtable  => [
                                    'ON' => [
                                        $relation_table => 'items_id_peripheral',
                                        $itemtable      => 'id',
                                    ],
                                ],
                            ],
                            'WHERE'           => [
                                $relation_table . '.itemtype_peripheral' => $peripheral_itemtype,
                                $relation_table . '.itemtype_asset'      => $peripheralhost_itemtype,
                                $relation_table . '.items_id_asset'      => $devices[$peripheralhost_itemtype],
                            ] + getEntitiesRestrictCriteria($itemtable, '', $entity_restrict),
                            'ORDERBY'         => "$itemtable.name",
                        ];

                        if ($item->maybeDeleted()) {
                            $criteria['WHERE']["$itemtable.is_deleted"] = 0;
                        }
                        if ($item->maybeTemplate()) {
                            $criteria['WHERE']["$itemtable.is_template"] = 0;
                        }

                        $iterator = $DB->request($criteria);
                        if (count($iterator)) {
                            foreach ($iterator as $data) {
                                if (!in_array($data["id"], $already_add[$peripheral_itemtype])) {
                                    $linked_items[$peripheral_itemtype][] = $data;
                                    $already_add[$peripheral_itemtype][] = $data["id"];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $linked_items;
    }

    /**
     * Make a select box with all glpi items
     *
     * @param array<string,mixed> $options array of possible options:
     *    - name         : string / name of the select (default is users_id)
     *    - value
     *    - comments     : boolean / is the comments displayed near the dropdown (default true)
     *    - entity       : integer or array / restrict to a defined entity or array of entities
     *                      (default -1 : no restriction)
     *    - entity_sons  : boolean / if entity restrict specified auto select its sons
     *                      only available if entity is a single value not an array(default false)
     *    - rand         : integer / already computed rand value
     *    - toupdate     : array / Update a specific item on select change on dropdown
     *                      (need value_fieldname, to_update, url
     *                      (see Ajax::updateItemOnSelectEvent for information)
     *                      and may have moreparams)
     *    - used         : array / Already used items ID: not to display in dropdown (default empty)
     *    - on_change    : string / value to transmit to "onChange"
     *    - display      : boolean / display or get string (default true)
     *    - width        : specific width needed
     *    - hide_if_no_elements  : boolean / hide dropdown if there is no elements (default false)
     *
     **/
    public static function dropdown($options = [])
    {
        global $DB;

        $p = array_replace([
            'name' => 'items',
            'value' => '',
            'all' => 0,
            'on_change' => '',
            'comments' => 1,
            'width' => '',
            'entity' => -1,
            'entity_sons' => false,
            'used' => [],
            'toupdate' => '',
            'rand' => mt_rand(),
            'display' => true,
            'hide_if_no_elements' => false,
        ], $options);

        $itemtypes = ['Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer'];

        $union = new QueryUnion();
        foreach ($itemtypes as $type) {
            $table = getTableForItemType($type);
            $union->addQuery([
                'SELECT' => [
                    'id',
                    new QueryExpression($type, 'itemtype'),
                    "name",
                ],
                'FROM'   => $table,
                'WHERE'  => [
                    'NOT'          => ['id' => null],
                    'is_deleted'   => 0,
                    'is_template'  => 0,
                ],
            ]);
        }

        $iterator = $DB->request(['FROM' => $union]);

        if ($p['hide_if_no_elements'] && $iterator->count() === 0) {
            return false;
        }

        $output = [];

        foreach ($iterator as $data) {
            $item = getItemForItemtype($data['itemtype']);
            $output[$data['itemtype'] . "_" . $data['id']] = $item::getTypeName() . " - " . $data['name'];
        }

        return Dropdown::showFromArray($p['name'], $output, $p);
    }

    /**
     * Return used items for a ITIL object
     *
     * @param int $items_id ITIL object on which the used item are attached
     *
     * @return array
     */
    public static function getUsedItems($items_id)
    {

        $data = getAllDataFromTable(static::getTable(), [static::$items_id_1 => $items_id]);
        $used = [];
        if (!empty($data)) {
            foreach ($data as $val) {
                $used[$val['itemtype']][] = $val['items_id'];
            }
        }

        return $used;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'add_item':
                $input = $ma->getInput();

                $item_obj = new static();
                foreach ($ids as $id) {
                    if (!empty($input['items_id']) && $item->getFromDB($id)) {
                        $input[static::$items_id_1] = $id;
                        $input['itemtype'] = $input['item_itemtype'];

                        if ($item_obj->can(-1, CREATE, $input)) {
                            $ok = true;
                            if (!$item_obj->add($input)) {
                                $ok = false;
                            }

                            if ($ok) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                    }
                }
                return;

            case 'delete_item':
                $input = $ma->getInput();
                $item_obj = new static();
                foreach ($ids as $id) {
                    if (!empty($input['items_id']) && $item->getFromDB($id)) {
                        $item_found = $item_obj->find([
                            static::$items_id_1   => $id,
                            'itemtype'     => $input['item_itemtype'],
                            'items_id'     => $input['items_id'],
                        ]);
                        if (!empty($item_found)) {
                            $item_founds_id = array_keys($item_found);
                            $input['id'] = $item_founds_id[0];

                            if ($item_obj->can($input['id'], DELETE, $input)) {
                                $ok = true;
                                if (!$item_obj->delete($input)) {
                                    $ok = false;
                                }

                                if ($ok) {
                                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                                } else {
                                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                    $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                                }
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                                $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
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
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => static::$items_id_1,
            'name'               => static::$itemtype_1::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => static::getTable(),
            'field'              => 'items_id',
            'name'               => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'datatype'           => 'specific',
            'comments'           => true,
            'nosort'             => true,
            'additionalfields'   => ['itemtype'],
        ];

        $tab[] = [
            'id'                 => '131',
            'table'              => static::getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Associated item type', 'Associated item types', Session::getPluralNumber()),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'ticket_types',
            'nosort'             => true,
        ];

        return $tab;
    }

    /**
     * Add a message on add action
     **/
    public function addMessageOnAddAction()
    {
        $addMessAfterRedirect = false;
        if (isset($this->input['_add'])) {
            $addMessAfterRedirect = true;
        }

        if (
            isset($this->input['_no_message'])
            || !$this->auto_message_on_action
        ) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = getItemForItemtype($this->fields['itemtype']);
            $item->getFromDB($this->fields['items_id']);

            if ($item->getName() === NOT_AVAILABLE) {
                //TRANS: %1$s is the itemtype, %2$d is the id of the item
                $item->fields['name'] = sprintf(
                    __('%1$s - ID %2$d'),
                    $item::getTypeName(1),
                    $item->fields['id']
                );
            }

            $display = (isset($this->input['_no_message_link']) ? htmlescape($item->getNameID())
                                                            : $item->getLink());

            //TRANS : %s is the description of the added item
            Session::addMessageAfterRedirect(sprintf(
                __s('%1$s: %2$s'),
                __s('Item successfully added'),
                $display
            ));
        }
    }

    /**
     * Add a message on delete action
     **/
    public function addMessageOnPurgeAction()
    {
        if (!$this->maybeDeleted()) {
            return;
        }

        $addMessAfterRedirect = false;
        if (isset($this->input['_delete'])) {
            $addMessAfterRedirect = true;
        }

        if (
            isset($this->input['_no_message'])
            || !$this->auto_message_on_action
        ) {
            $addMessAfterRedirect = false;
        }

        if ($addMessAfterRedirect) {
            $item = getItemForItemtype($this->fields['itemtype']);
            $item->getFromDB($this->fields['items_id']);

            if (isset($this->input['_no_message_link'])) {
                $display = htmlescape($item->getNameID());
            } else {
                $display = $item->getLink();
            }
            //TRANS : %s is the description of the updated item
            Session::addMessageAfterRedirect(sprintf(__s('%1$s: %2$s'), __s('Item successfully deleted'), $display));
        }
    }
}
