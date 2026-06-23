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

use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Features\Clonable;

/**
 * Group class
 **/
class Group extends CommonTreeDropdown
{
    /** @use Clonable<static> */
    use Clonable;

    public $dohistory       = true;

    public static $rightname       = 'group';

    protected $usenotepad  = true;


    public function getCloneRelations(): array
    {
        return [];
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Group', 'Groups', $nb);
    }

    public static function getSectorizedDetails(): array
    {
        return ['admin', self::class];
    }

    public static function getAdditionalMenuOptions()
    {
        if (Session::haveRight('user', User::UPDATEAUTHENT)) {
            return [
                'ldap' => [
                    'title' => AuthLDAP::getTypeName(Session::getPluralNumber()),
                    'page'  => '/front/ldap.group.php',
                ],
            ];
        }
        return false;
    }

    public function post_getEmpty()
    {
        $this->fields['is_requester'] = 1;
        $this->fields['is_watcher']   = 1;
        $this->fields['is_assign']    = 1;
        $this->fields['is_task']      = 1;
        $this->fields['is_notify']    = 1;
        $this->fields['is_itemgroup'] = 1;
        $this->fields['is_usergroup'] = 1;
        $this->fields['is_manager']   = 1;
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                Change_Group::class,
                Group_Item::class,
                Group_KnowbaseItem::class,
                Group_Problem::class,
                Group_Reminder::class,
                Group_RSSFeed::class,
                Group_Ticket::class,
                Group_User::class,
                ProjectTaskTeam::class,
                ProjectTeam::class,
            ]
        );

        // Ticket rules use various _groups_id_*
        Rule::cleanForItemAction($this, '_groups_id%');
        Rule::cleanForItemCriteria($this, '_groups_id%');
    }

    public static function getAdditionalMenuLinks()
    {
        $links = [];
        if (
            AuthLDAP::useAuthLdap()
            && Session::haveRight("user", User::IMPORTEXTAUTHUSERS)
            && static::canUpdate()
        ) {
            $links['<i class="ti ti-settings"></i><span>' . __s('LDAP directory link') . '</span>'] = "/front/ldap.group.php";
        }
        return $links;
    }

    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);
        if ($isadmin) {
            $prefix                            = 'Group_User' . MassiveAction::CLASS_ACTION_SEPARATOR;
            $actions[$prefix . 'add']            = "<i class='ti ti-user-plus'></i>"
                                              . _sx('button', 'Add a user');
            $actions[$prefix . 'add_supervisor'] = "<i class='ti ti-user-star'></i>"
                                              . _sx('button', 'Add a manager');
            $actions[$prefix . 'add_delegatee']  = "<i class='fas fa-user-check'></i>"
                                              . _sx('button', 'Add a delegatee');
            $actions[$prefix . 'remove']         = "<i class='ti ti-user-minus'></i>"
                                              . _sx('button', 'Remove a user');
        }

        return $actions;
    }

    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        $input = $ma->getInput();

        switch ($ma->getAction()) {
            case 'changegroup':
                if (
                    isset($input['is_tech'])
                    && isset($input['check_items_id'])
                    && isset($input['check_itemtype'])
                ) {
                    if ($group = getItemForItemtype($input['check_itemtype'])) {
                        if ($group->getFromDB($input['check_items_id'])) {
                            $condition = [];
                            if ($input['is_tech']) {
                                $condition['is_assign'] = 1;
                            } else {
                                $condition['is_itemgroup'] = 1;
                            }
                            self::dropdown([
                                'entity'    => $group->fields["entities_id"],
                                'used'      => [$group->fields["id"]],
                                'condition' => $condition,
                            ]);
                            echo "<br><br><input type='submit' name='massiveaction' class='btn btn-primary' value='"
                                  . _sx('button', 'Move') . "'>";
                            return true;
                        }
                    }
                }
                return true;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {

        switch ($ma->getAction()) {
            case 'changegroup':
                $input = $ma->getInput();
                if (isset($input["field"], $input['groups_id'])) {
                    foreach ($ids as $id) {
                        if ($item->can($id, UPDATE)) {
                            if (
                                $item->update(['id'            => $id,
                                    $input["field"] => $input["groups_id"],
                                ])
                            ) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                    $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        if (AuthLDAP::useAuthLdap()) {
            $tab[] = [
                'id'                 => '3',
                'table'              => static::getTable(),
                'field'              => 'ldap_field',
                'name'               => __('Attribute of the user containing its groups'),
                'datatype'           => 'string',
            ];

            $tab[] = [
                'id'                 => '4',
                'table'              => static::getTable(),
                'field'              => 'ldap_value',
                'name'               => __('Attribute value'),
                'datatype'           => 'text',
            ];

            $tab[] = [
                'id'                 => '5',
                'table'              => static::getTable(),
                'field'              => 'ldap_group_dn',
                'name'               => __('Group DN'),
                'datatype'           => 'text',
            ];
        }

        $tab[] = [
            'id'                 => '11',
            'table'              => static::getTable(),
            'field'              => 'is_requester',
            'name'               => _n('Requester', 'Requesters', 1),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => static::getTable(),
            'field'              => 'is_assign',
            'name'               => __('Assigned to'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '21',
            'table'              => static::getTable(),
            'field'              => 'is_watcher',
            'name'               => _n('Observer', 'Observers', 1),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '18',
            'table'              => static::getTable(),
            'field'              => 'is_manager',
            'name'               => __('Can be manager'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '20',
            'table'              => static::getTable(),
            'field'              => 'is_notify',
            'name'               => __('Can be notified'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '17',
            'table'              => static::getTable(),
            'field'              => 'is_itemgroup',
            'name'               => sprintf(__('%1$s %2$s'), __('Can contain'), _n('Item', 'Items', Session::getPluralNumber())),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '15',
            'table'              => static::getTable(),
            'field'              => 'is_usergroup',
            'name'               => sprintf(__('%1$s %2$s'), __('Can contain'), User::getTypeName(Session::getPluralNumber())),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '70',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'name'               => _n('Manager', 'Managers', 1),
            'datatype'           => 'dropdown',
            'right'              => 'all',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_groups_users',
                    'joinparams'         => [
                        'jointype'           => 'child',
                        'condition'          => ['NEWTABLE.is_manager' => 1],
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '71',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'name'               => __('Delegatee'),
            'datatype'           => 'dropdown',
            'right'              => 'all',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_groups_users',
                    'joinparams'         => [
                        'jointype'           => 'child',
                        'condition'          => ['NEWTABLE.is_userdelegate' => 1],
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '72',
            'table'              => static::getTable(),
            'field'              => 'is_task',
            'name'               => __('Can be in charge of a task'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '73',
            'table'              => static::getTable(),
            'field'              => 'recursive_membership',
            'name'               => __('Recursive membership'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '74',
            'table'              => static::getTable(),
            'field'              => 'code',
            'name'               => __('Group code'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab = array_merge($tab, Group_User::rawSearchOptionsToAdd());

        return $tab;
    }

    /**
     * get list of assets in a group
     *
     * @since 0.83
     *
     * @param bool $tech     Whether to fetch items related to technician assignment or not.
     * @param bool $tree  Include child groups
     * @param bool $user  Include members (users)
     * @param int $start First row to retrieve
     * @param array $res     Result filled on ouput
     * @param array $extra_criteria
     *
     * @return int total of items
     **/
    public function getDataItems(bool $tech, bool $tree, bool $user, int $start, array &$res, array $extra_criteria = []): int
    {
        global $DB, $CFG_GLPI;

        $types  = $CFG_GLPI['assignable_types'];
        $ufield = $tech ? 'users_id_tech' : 'users_id';

        // include item of child groups ?
        if ($tree) {
            $groups_ids = getSonsOf('glpi_groups', $this->getID());
        } else {
            $groups_ids = [$this->getID()];
        }

        $groups_criteria = [
            Group_Item::getTable() . '.groups_id' => $groups_ids,
            Group_Item::getTable() . '.type' => $tech ? Group_Item::GROUP_TYPE_TECH : Group_Item::GROUP_TYPE_NORMAL,
        ];

        if ($user) {
            // Get also items that are assigned to any user of the corresponding groups
            $groups_criteria = [
                'OR' => [
                    $groups_criteria,
                    [
                        $ufield => new QuerySubQuery(
                            [
                                'SELECT' => 'users_id',
                                'FROM'   => 'glpi_groups_users',
                                'WHERE'  => [
                                    'groups_id'  => $groups_ids,
                                ],
                            ]
                        ),
                    ],
                ],
            ];
        }

        // Count the total of item
        $nb  = [];
        $tot = 0;
        $joins = [];
        $restrict = [];
        foreach ($types as $itemtype) {
            $nb[$itemtype] = 0;
            if (!($item = getItemForItemtype($itemtype))) {
                continue;
            }
            if (!$item::canView()) {
                continue;
            }

            // Multiple groups
            $joins[$itemtype][Group_Item::getTable()] = [
                'ON' => [
                    Group_Item::getTable() => 'items_id',
                    $item::getTable()      => 'id', [
                        'AND' => [
                            Group_Item::getTable() . '.itemtype' => $itemtype,
                        ],
                    ],
                ],
            ];
            $restrict[$itemtype] = $groups_criteria + $item::getSystemSQLCriteria();

            if ($item->isEntityAssign()) {
                $restrict[$itemtype] += getEntitiesRestrictCriteria(
                    $item::getTable(),
                    '',
                    '',
                    $item->maybeRecursive()
                );
            }
            if ($item->maybeTemplate()) {
                $restrict[$itemtype]['is_template'] = 0;
            }
            if ($item->maybeDeleted()) {
                $restrict[$itemtype]['is_deleted'] = 0;
            }
            $tot += $nb[$itemtype] = countElementsInTable($item::getTable(), [
                'LEFT JOIN' => $joins[$itemtype],
                'WHERE'     => $restrict[$itemtype],
            ]);
        }
        $max = $_SESSION['glpilist_limit'];
        if ($start >= $tot) {
            $start = 0;
        }
        $res = [];
        foreach ($types as $itemtype) {
            if (!($item = getItemForItemtype($itemtype))) {
                continue;
            }
            if ($start >= $nb[$itemtype]) {
                // No need to read
                $start -= $nb[$itemtype];
            } else {
                $request = [
                    'SELECT'    => [
                        $item::getTable() . '.id',
                        new QueryExpression($DB::quoteValue($itemtype), 'itemtype'),
                    ],
                    'FROM'      => $item::getTable(),
                    'LEFT JOIN' => $joins[$itemtype] ?? [],
                    'WHERE'     => $restrict[$itemtype],
                    'GROUPBY'   => $item::getTable() . '.id',
                    'LIMIT'     => $max,
                    'START'     => $start,
                ] + $extra_criteria;

                if ($item->isField('name')) {
                    $request['ORDER'] = 'name';
                }

                if ($itemtype === 'Consumable') {
                    $request['LEFT JOIN'] = [
                        'glpi_consumableitems' => [
                            'FKEY'   => [
                                'glpi_consumables'     => 'consumableitems_id',
                                'glpi_consumableitems' => 'id',
                            ],
                        ],
                    ];
                }

                $iterator = $DB->request($request);
                foreach ($iterator as $data) {
                    $res[] = ['itemtype' => $itemtype,
                        'items_id' => $data['id'],
                    ];
                    $max--;
                }
                // For next type
                $start = 0;
            }
            if (!$max) {
                break;
            }
        }
        return $tot;
    }

    public function cleanRelationData()
    {
        global $DB;

        parent::cleanRelationData();

        if ($this->isUsedInConsumables()) {
            // Replace relation with Consumable
            $newval = ($this->input['_replace_by'] ?? 0);

            $fields_updates = [
                'items_id' => $newval,
            ];
            if (empty($newval)) {
                $fields_updates['itemtype'] = 'NULL';
                $fields_updates['date_out'] = 'NULL';
            }

            $DB->update(
                'glpi_consumables',
                $fields_updates,
                [
                    'items_id' => $this->fields['id'],
                    'itemtype' => self::class,
                ]
            );
        }
    }

    public function isUsed()
    {
        if (parent::isUsed()) {
            return true;
        }

        return $this->isUsedInConsumables();
    }

    /**
     * Check if group is used in consumables.
     *
     * @return bool
     */
    private function isUsedInConsumables()
    {
        return countElementsInTable(
            Consumable::getTable(),
            [
                'items_id' => $this->fields['id'],
                'itemtype' => self::class,
            ]
        ) > 0;
    }

    public function getName($options = [])
    {
        if (
            Session::getCurrentInterface() === 'helpdesk'
            && ($anon = self::getAnonymizedName()) !== null
        ) {
            return $anon;
        }

        return parent::getName($options);
    }

    public function getRawCompleteName()
    {
        if (
            Session::getCurrentInterface() === 'helpdesk'
            && ($anon = static::getAnonymizedName()) !== null
        ) {
            return $anon;
        }

        return parent::getRawCompleteName();
    }

    public static function getAnonymizedName(?int $entities_id = null): ?string
    {
        switch (Entity::getAnonymizeConfig($entities_id)) {
            default:
            case Entity::ANONYMIZE_DISABLED:
                return null;

            case Entity::ANONYMIZE_USE_GENERIC:
            case Entity::ANONYMIZE_USE_NICKNAME:
            case Entity::ANONYMIZE_USE_GENERIC_GROUP:
                return __("Helpdesk group");
        }
    }

    public static function getIcon()
    {
        return "ti ti-users";
    }

    /**
     * Get group link.
     *
     * @param bool  $enable_anonymization
     * @param array<string, mixed> $options
     *
     * @return string
     */
    public function getGroupLink(bool $enable_anonymization = false, $options = []): string
    {
        if ($enable_anonymization && Session::getCurrentInterface() === 'helpdesk' && ($anon = static::getAnonymizedName()) !== null) {
            // if anonymized name active, return only the anonymized name
            return $anon;
        }

        return $this->getLink($options);
    }

    public function post_addItem()
    {
        parent::post_addItem();
        // Adding a new group might invalidate the group cache if it's a new child
        // group and recursive membership is enabled
        if ($this->fields['groups_id']) {
            self::updateLastGroupChange();
        }
    }

    public function post_updateItem($history = true)
    {
        parent::post_updateItem($history);
        // Changing a group's parent might invalidate the group cache if recursive
        // membership is enabled
        $parent_changed
            = isset($this->oldvalues['groups_id'])
            && $this->fields['groups_id'] !== $this->oldvalues['groups_id']
        ;

        // Enabling or disabling recursion on a group will invalidate the group
        // cache
        $recursive_membership_changed
            = isset($this->oldvalues['recursive_membership'])
            && $this->fields['recursive_membership'] !== $this->oldvalues['recursive_membership']
        ;

        if ($parent_changed || $recursive_membership_changed) {
            self::updateLastGroupChange();
        }
    }

    public function post_purgeItem()
    {
        // Purging a group will invalidate the group cache
        self::updateLastGroupChange();
    }

    /**
     * Mark groups data as "changed"
     * This will trigger a rebuilding of the 'glpigroups' session data for all
     * users
     *
     * @return void
     */
    public static function updateLastGroupChange()
    {
        global $GLPI_CACHE;
        $GLPI_CACHE->set('last_group_change', $_SESSION['glpi_currenttime']);

        // Reload groups immediatly
        if (Session::getLoginUserID()) {
            Session::loadGroups();
        }
    }
}
