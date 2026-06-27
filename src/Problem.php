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

use Glpi\ContentTemplates\Parameters\CommonITILObjectParameters;
use Glpi\ContentTemplates\Parameters\ProblemParameters;
use Glpi\Search\DefaultSearchRequestInterface;

/**
 * Problem class
 **/
class Problem extends CommonITILObject implements DefaultSearchRequestInterface
{
    // From CommonDBTM
    public $dohistory = true;
    protected static $forward_entity_to = ['ProblemCost'];

    // From CommonITIL
    public $userlinkclass        = 'Problem_User';
    public $grouplinkclass       = 'Group_Problem';
    public $supplierlinkclass    = 'Problem_Supplier';

    public static $rightname            = 'problem';
    protected $usenotepad        = true;


    public const MATRIX_FIELD         = 'priority_matrix';
    public const URGENCY_MASK_FIELD   = 'urgency_mask';
    public const IMPACT_MASK_FIELD    = 'impact_mask';
    public const STATUS_MATRIX_FIELD  = 'problem_status';

    
    public static function getTypeName($nb = 0)
    {
        return _n('Problem', 'Problems', $nb);
    }

    
    public function canSolve()
    {

        return (self::isAllowedStatus($this->fields['status'], self::SOLVED)
              // No edition on closed status
              && !in_array($this->fields['status'], static::getClosedStatusArray())
              && (Session::haveRight(self::$rightname, UPDATE)
                  || (Session::haveRight(self::$rightname, self::READMY)
                      && ($this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
                          || (isset($_SESSION["glpigroups"])
                              && $this->haveAGroup(
                                  CommonITILActor::ASSIGN,
                                  $_SESSION["glpigroups"]
                              ))))));
    }

    
    public static function canView(): bool
    {
        return Session::haveRightsOr(self::$rightname, [self::READALL, self::READMY]);
    }

    
    public function canViewItem(): bool
    {

        if (!Session::haveAccessToEntity($this->getEntityID(), $this->isRecursive())) {
            return false;
        }
        return (Session::haveRight(self::$rightname, self::READALL)
              || (Session::haveRight(self::$rightname, self::READMY)
                  && ($this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
                      || $this->isUser(CommonITILActor::OBSERVER, Session::getLoginUserID())
                      || (isset($_SESSION["glpigroups"])
                          && ($this->haveAGroup(CommonITILActor::REQUESTER, $_SESSION["glpigroups"])
                              || $this->haveAGroup(
                                  CommonITILActor::OBSERVER,
                                  $_SESSION["glpigroups"]
                              )))
                      || ($this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
                          || (isset($_SESSION["glpigroups"])
                              && $this->haveAGroup(
                                  CommonITILActor::ASSIGN,
                                  $_SESSION["glpigroups"]
                              ))))));
    }

    
    public function canCreateItem(): bool
    {

        if (!Session::haveAccessToEntity($this->getEntityID())) {
            return false;
        }
        return Session::haveRight(self::$rightname, CREATE);
    }

    /**
     * @since 9.4.0
     * @return bool
     */
    public function canReopen()
    {
        return Session::haveRight('followup', CREATE)
             && in_array($this->fields["status"], static::getClosedStatusArray())
             && ($this->isAllowedStatus($this->fields['status'], self::INCOMING)
                 || $this->isAllowedStatus($this->fields['status'], self::ASSIGNED));
    }

    
    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI['use_notifications']) {
            NotificationEvent::raiseEvent('delete', $this);
        }
        return true;
    }

    
    public function cleanDBonPurge()
    {
        // CommonITILTask does not extends CommonDBConnexity
        $pt = new ProblemTask();
        $pt->deleteByCriteria(['problems_id' => $this->fields['id']]);

        $this->deleteChildrenAndRelationsFromDb(
            [
                Change_Problem::class,
                // Done by parent: Group_Problem::class,
                Item_Problem::class,
                // Done by parent: ITILSolution::class,
                // Done by parent: Problem_Supplier::class,
                Problem_Ticket::class,
                // Done by parent: Problem_User::class,
                ProblemCost::class,
                Problem_Problem::class,
            ]
        );

        parent::cleanDBonPurge();
    }

    
    public function post_updateItem($history = true)
    {
        global $CFG_GLPI;

        parent::post_updateItem($history);

        $donotif = count($this->updates);

        if (isset($this->input['_forcenotif'])) {
            $donotif = true;
        }

        if (isset($this->input['_disablenotif'])) {
            $donotif = false;
        }

        if ($donotif && $CFG_GLPI["use_notifications"]) {
            $mailtype = "update";
            if (
                isset($this->input["status"]) && $this->input["status"]
                && in_array("status", $this->updates)
                && in_array($this->input["status"], static::getSolvedStatusArray())
            ) {
                $mailtype = "solved";
            }

            if (
                isset($this->input["status"])
                && $this->input["status"]
                && in_array("status", $this->updates)
                && in_array($this->input["status"], static::getClosedStatusArray())
            ) {
                $mailtype = "closed";
            }

            // Read again problem to be sure that all data are up to date
            $this->getFromDB($this->fields['id']);
            $trigger = $this->input['_trigger'] ?? null;
            NotificationEvent::raiseEvent($mailtype, $this, [], $trigger);
        }
    }

    
    public function prepareInputForAdd($input)
    {
        $input =  parent::prepareInputForAdd($input);
        if ($input === false) {
            return false;
        }

        $this->processRules(RuleCommonITILObject::ONADD, $input);

        if (!isset($input['_skip_auto_assign']) || $input['_skip_auto_assign'] === false) {
            // Manage auto assign
            $auto_assign_mode = Entity::getUsedConfig('auto_assign_mode', $input['entities_id']);

            switch ($auto_assign_mode) {
                case Entity::CONFIG_NEVER:
                    break;

                case Entity::AUTO_ASSIGN_HARDWARE_CATEGORY:
                case Entity::AUTO_ASSIGN_CATEGORY_HARDWARE:
                    // Auto assign tech/group from Category
                    // Problems are not associated to a hardware then both settings behave the same way
                    $input = $this->setTechAndGroupFromItilCategory($input);
                    break;
            }
        }

        return $input;
    }

    
    public function prepareInputForUpdate($input)
    {
        $input = $this->transformActorsInput($input);

        $entid = $input['entities_id'] ?? $this->fields['entities_id'];
        $this->processRules(RuleCommonITILObject::ONUPDATE, $input, $entid);

        $input = parent::prepareInputForUpdate($input);
        return $input;
    }

    
    public function post_addItem()
    {
        global $DB;

        parent::post_addItem();

        if (isset($this->input['_tickets_id'])) {
            $ticket = new Ticket();
            if ($ticket->getFromDB($this->input['_tickets_id'])) {
                $pt = new Problem_Ticket();
                $pt->add(['tickets_id'  => $this->input['_tickets_id'],
                    'problems_id' => $this->fields['id'],
                ]);

                if (
                    !empty($ticket->fields['itemtype'])
                    && ($ticket->fields['items_id'] > 0)
                ) {
                    $it = new Item_Problem();
                    $it->add(['problems_id' => $this->fields['id'],
                        'itemtype'    => $ticket->fields['itemtype'],
                        'items_id'    => $ticket->fields['items_id'],
                    ]);
                }

                //Copy associated elements
                $iterator = $DB->request([
                    'FROM'   => Item_Ticket::getTable(),
                    'WHERE'  => [
                        'tickets_id'   => $this->input['_tickets_id'],
                    ],
                ]);
                $assoc = new Item_Problem();
                foreach ($iterator as $row) {
                    unset($row['tickets_id']);
                    unset($row['id']);
                    $row['problems_id'] = $this->fields['id'];
                    $assoc->add($row);
                }
            }
        }

        $this->handleNewItemNotifications();
    }

    
    public static function getDefaultSearchRequest(): array
    {
        $search = ['criteria' => [0 => ['field'      => 12,
            'searchtype' => 'equals',
            'value'      => 'notold',
        ],
        ],
            'sort'     => 19,
            'order'    => 'DESC',
        ];

        return $search;
    }

    
    public function getSpecificMassiveActions($checkitem = null)
    {
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (Session::getCurrentInterface() === 'central') {
            if (Item_Problem::canCreate()) {
                $actions['Item_Problem' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_item']
                = "<i class='ti ti-plus'></i>"
                 . _sx('button', 'Add an item');
            }

            if (Item_Problem::canDelete()) {
                $actions['Item_Problem' . MassiveAction::CLASS_ACTION_SEPARATOR . 'delete_item']
                = _sx('button', 'Remove an item');
            }
        }

        if (ProblemTask::canCreate()) {
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_task'] = __s('Add a new task');
        }
        if ($this->canAdminActors()) {
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_actor'] = __s('Add an actor');
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'update_notif']
               = __s('Set notifications for all actors');
        }

        return $actions;
    }

    
    public function rawSearchOptions()
    {
        $tab = [];

        $tab = array_merge($tab, $this->getSearchOptionsMain());

        $tab[] = [
            'id'                 => '63',
            'table'              => 'glpi_items_problems',
            'field'              => 'id',
            'name'               => _x('quantity', 'Number of items'),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'datatype'           => 'count',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => 'glpi_items_problems',
            'field'              => 'items_id',
            'name'               => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'datatype'           => 'specific',
            'comments'           => true,
            'nosort'             => true,
            'nosearch'           => true,
            'additionalfields'   => ['itemtype'],
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'forcegroupby'       => true,
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '131',
            'table'              => 'glpi_items_problems',
            'field'              => 'itemtype',
            'name'               => _n('Associated item type', 'Associated item types', Session::getPluralNumber()),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'ticket_types',
            'nosort'             => true,
            'additionalfields'   => ['itemtype'],
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'forcegroupby'       => true,
            'massiveaction'      => false,
        ];

        $tab = array_merge($tab, $this->getSearchOptionsActors());

        $tab[] = [
            'id'                 => 'analysis',
            'name'               => __('Analysis'),
        ];

        $tab[] = [
            'id'                 => '60',
            'table'              => $this->getTable(),
            'field'              => 'impactcontent',
            'name'               => __('Impacts'),
            'massiveaction'      => false,
            'datatype'           => 'text',
            'htmltext'           => true,
        ];

        $tab[] = [
            'id'                 => '61',
            'table'              => $this->getTable(),
            'field'              => 'causecontent',
            'name'               => __('Causes'),
            'massiveaction'      => false,
            'datatype'           => 'text',
            'htmltext'           => true,
        ];

        $tab[] = [
            'id'                 => '62',
            'table'              => $this->getTable(),
            'field'              => 'symptomcontent',
            'name'               => __('Symptoms'),
            'massiveaction'      => false,
            'datatype'           => 'text',
            'htmltext'           => true,
        ];

        $tab = array_merge($tab, Notepad::rawSearchOptionsToAdd());

        $tab = array_merge($tab, ITILFollowup::rawSearchOptionsToAdd());

        $tab = array_merge($tab, ProblemTask::rawSearchOptionsToAdd());

        $tab = array_merge($tab, $this->getSearchOptionsSolution());

        $tab = array_merge($tab, $this->getSearchOptionsStats());

        $tab = array_merge($tab, ProblemCost::rawSearchOptionsToAdd());

        $tab[] = [
            'id'                 => 'ticket',
            'name'               => Ticket::getTypeName(Session::getPluralNumber()),
        ];

        $tab[] = [
            'id'                 => '141',
            'table'              => 'glpi_problems_tickets',
            'field'              => 'id',
            'name'               => _x('quantity', 'Number of tickets'),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'datatype'           => 'count',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        if (Session::haveRight('change', READ)) {
            $tab = array_merge($tab, Change::rawSearchOptionsToAdd(self::class));
        }

        return $tab;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @return array
     */
    public static function rawSearchOptionsToAdd(string $itemtype)
    {
        global $CFG_GLPI;

        $tab = [];

        if ($itemtype == Ticket::class) {
            $tab[] = [
                'id'                 => 'problem',
                'name'               => __('Problems'),
            ];

            //FIXME: Fix the search options for linked ITIL objects
            $tab[] = [
                'id'                 => '200',
                'table'              => 'glpi_problems_tickets',
                'field'              => 'id',
                'name'               => _x('quantity', 'Number of problems'),
                'forcegroupby'       => true,
                'usehaving'          => true,
                'datatype'           => 'count',
                'massiveaction'      => false,
                'joinparams'         => [
                    'jointype'           => 'child',
                ],
            ];

            $tab[] = [
                'id'                 => '201',
                'table'              => Problem::getTable(),
                'field'              => 'name',
                'name'               => Problem::getTypeName(1),
                'datatype'           => 'dropdown',
                'massiveaction'      => false,
                'forcegroupby'       => true,
                'joinparams'         => [
                    'beforejoin'         => [
                        'table'              => Problem_Ticket::getTable(),
                        'joinparams'         => [
                            'jointype'           => 'child',
                        ],
                    ],
                ],
            ];

            $tab[] = [
                'id'                  => '202',
                'table'               => Problem::getTable(),
                'field'               => 'status',
                'name'                => __('Status'),
                'datatype'            => 'specific',
                'searchtype'          => 'equals',
                'searchequalsonfield' => true,
                'massiveaction'       => false,
                'forcegroupby'        => true,
                'joinparams'          => [
                    'beforejoin'          => [
                        'table'               => Problem_Ticket::getTable(),
                        'joinparams'          => [
                            'jointype'            => 'child',
                        ],
                    ],
                ],
            ];

            $tab[] = [
                'id'                 => '203',
                'table'              => Problem::getTable(),
                'field'              => 'solvedate',
                'name'               => __('Resolution date'),
                'datatype'           => 'datetime',
                'massiveaction'      => false,
                'forcegroupby'       => true,
                'joinparams'         => [
                    'beforejoin'         => [
                        'table'              => Problem_Ticket::getTable(),
                        'joinparams'         => [
                            'jointype'           => 'child',
                        ],
                    ],
                ],
            ];

            $tab[] = [
                'id'                 => '204',
                'table'              => Problem::getTable(),
                'field'              => 'date',
                'name'               => __('Opening date'),
                'datatype'           => 'datetime',
                'massiveaction'      => false,
                'forcegroupby'       => true,
                'joinparams'         => [
                    'beforejoin'         => [
                        'table'              => Problem_Ticket::getTable(),
                        'joinparams'         => [
                            'jointype'           => 'child',
                        ],
                    ],
                ],
            ];
        } elseif (in_array($itemtype, $CFG_GLPI["ticket_types"])) {
            $tab[] = [
                'id'            => 140,
                'table'         => self::getTable(),
                'field'         => "id",
                'datatype'      => "count",
                'name'          => _x('quantity', 'Number of problems'),
                'forcegroupby'  => true,
                'usehaving'     => true,
                'massiveaction' => false,
                'joinparams'    => [
                    'beforejoin' => [
                        'table' => self::getItemLinkClass()::getTable(),
                        'joinparams' => [
                            'jointype' => 'itemtype_item',
                        ],
                    ],
                    'condition' => getEntitiesRestrictCriteria('NEWTABLE'),
                ],
            ];
        }

        return $tab;
    }

    
    public static function getAllStatusArray($withmetaforsearch = false)
    {
        $tab = [
            self::INCOMING => _x('status', 'New'),
            self::ACCEPTED => _x('status', 'Accepted'),
            self::ASSIGNED => _x('status', 'Processing (assigned)'),
            self::PLANNED  => _x('status', 'Processing (planned)'),
            self::WAITING  => __('Pending'),
            self::SOLVED   => _x('status', 'Solved'),
            self::OBSERVED => __('Under observation'),
            self::CLOSED   => _x('status', 'Closed'),
        ];

        if ($withmetaforsearch) {
            $tab['notold']    = _x('status', 'Not solved');
            $tab['notclosed'] = _x('status', 'Not closed');
            $tab['process']   = __('Processing');
            $tab['old']       = _x('status', 'Solved + Closed');
            $tab['all']       = __('All');
        }
        return $tab;
    }

    /**
     * Get the ITIL object closed status list
     *
     * @since 0.83
     *
     * @return array
     **/
    public static function getClosedStatusArray()
    {

        // To be overridden by class
        $tab = [self::CLOSED];
        return $tab;
    }

    /**
     * Get the ITIL object solved or observe status list
     *
     * @since 0.83
     *
     * @return array
     **/
    public static function getSolvedStatusArray()
    {
        // To be overridden by class
        $tab = [self::OBSERVED, self::SOLVED];
        return $tab;
    }

    /**
     * Get the ITIL object new status list
     *
     * @since 0.83.8
     *
     * @return array
     **/
    public static function getNewStatusArray()
    {
        return [self::INCOMING, self::ACCEPTED];
    }

    /**
     * Get the ITIL object assign, plan or accepted status list
     *
     * @since 0.83
     *
     * @return array
     **/
    public static function getProcessStatusArray()
    {

        // To be overridden by class
        $tab = [self::ACCEPTED, self::ASSIGNED, self::PLANNED];

        return $tab;
    }

    /**
     * @param CommonDBTM $item
     * @return array
     */
    public static function getListForItemRestrict(CommonDBTM $item)
    {
        $restrict = [];

        switch (true) {
            case $item instanceof User:
                $restrict['glpi_problems_users.users_id'] = $item->getID();
                $restrict['glpi_problems_users.type'] = CommonITILActor::REQUESTER;
                break;

            case $item instanceof Supplier:
                $restrict['glpi_problems_suppliers.suppliers_id'] = $item->getID();
                $restrict['glpi_problems_suppliers.type'] = CommonITILActor::ASSIGN;
                break;

            case $item instanceof Group:
                if ($item->haveChildren()) {
                    $tree = Session::getSavedOption(self::class, 'tree', 0);
                } else {
                    $tree = 0;
                }
                $restrict['glpi_groups_problems.groups_id'] = ($tree ? getSonsOf('glpi_groups', $item->getID()) : $item->getID());
                $restrict['glpi_groups_problems.type'] = CommonITILActor::REQUESTER;
                break;

            default:
                $restrict['glpi_items_problems.items_id'] = $item->getID();
                $restrict['glpi_items_problems.itemtype'] = $item->getType();
                // you can only see your tickets
                if (!Session::haveRight(self::$rightname, self::READALL)) {
                    $or = [
                        'glpi_problems.users_id_recipient'   => Session::getLoginUserID(),
                        [
                            'AND' => [
                                'glpi_problems_users.problems_id'  => 'glpi_problems.id',
                                'glpi_problems_users.users_id'    => Session::getLoginUserID(),
                            ],
                        ],
                    ];
                    if (count($_SESSION['glpigroups'])) {
                        $or['glpi_groups_problems.groups_id'] = $_SESSION['glpigroups'];
                    }
                    $restrict[] = ['OR' => $or];
                }
        }

        return $restrict;
    }

    /**
     * @since 0.85
     *
     * @see commonDBTM::getRights()
     **/
    public function getRights($interface = 'central')
    {

        $values = parent::getRights();
        unset($values[READ]);

        $values[self::READALL] = __('See all');
        $values[self::READMY]  = __('See (author)');

        return $values;
    }

    public static function getDefaultValues($entity = 0)
    {
        $default_use_notif = Entity::getUsedConfig('is_notif_enable_default', $_SESSION['glpiactive_entity'], '', 1);
        return [
            '_users_id_requester'        => Session::getLoginUserID(),
            '_users_id_requester_notif'  => [
                'use_notification'  => $default_use_notif,
                'alternative_email' => '',
            ],
            '_groups_id_requester'       => 0,
            '_users_id_assign'           => 0,
            '_users_id_assign_notif'     => [
                'use_notification'  => $default_use_notif,
                'alternative_email' => '',
            ],
            '_groups_id_assign'          => 0,
            '_users_id_observer'         => 0,
            '_users_id_observer_notif'   => [
                'use_notification'  => $default_use_notif,
                'alternative_email' => '',
            ],
            '_suppliers_id_assign_notif' => [
                'use_notification'  => $default_use_notif,
                'alternative_email' => '',
            ],
            '_groups_id_observer'        => 0,
            '_suppliers_id_assign'       => 0,
            'priority'                   => 3,
            'urgency'                    => 3,
            'impact'                     => 3,
            'content'                    => '',
            'name'                       => '',
            'entities_id'                => $_SESSION['glpiactive_entity'],
            'itilcategories_id'          => 0,
            'actiontime'                 => 0,
            'date'                       => 'NULL',
            '_add_validation'            => 0,
            '_validation_targets'        => [],
            '_tasktemplates_id'          => [],
            'items_id'                   => 0,
            '_actors'                    => [],
            'status'                     => self::INCOMING,
            'time_to_resolve'            => 'NULL',
            'itemtype'                   => '',
            'locations_id'               => 0,
            'impactcontent'              => '',
            'causecontent'               => '',
            'symptomcontent'             => '',
        ];
    }

    /**
     * get active problems for an item
     *
     * @since 9.5
     *
     * @param string $itemtype     Item type
     * @param int $items_id    ID of the Item
     *
     * @return DBmysqlIterator
     */
    public function getActiveProblemsForItem($itemtype, $items_id)
    {
        global $DB;

        return $DB->request([
            'SELECT'    => [
                $this->getTable() . '.id',
                $this->getTable() . '.name',
                $this->getTable() . '.priority',
            ],
            'FROM'      => $this->getTable(),
            'LEFT JOIN' => [
                'glpi_items_problems' => [
                    'ON' => [
                        'glpi_items_problems' => 'problems_id',
                        $this->getTable()    => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_items_problems.itemtype'   => $itemtype,
                'glpi_items_problems.items_id'   => $items_id,
                $this->getTable() . '.is_deleted' => 0,
                'NOT'                         => [
                    $this->getTable() . '.status' => array_merge(
                        static::getSolvedStatusArray(),
                        static::getClosedStatusArray()
                    ),
                ],
            ],
        ]);
    }

    
    public static function getItemLinkClass(): string
    {
        return Item_Problem::class;
    }

    
    public static function getContentTemplatesParametersClassInstance(): CommonITILObjectParameters
    {
        return new ProblemParameters();
    }
}
