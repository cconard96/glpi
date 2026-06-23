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

use Glpi\CalDAV\Contracts\CalDAVCompatibleItemInterface;
use Glpi\CalDAV\Traits\VobjectConverterTrait;
use Glpi\Features\Clonable;
use Glpi\Features\PlanningEvent;
use Ramsey\Uuid\Uuid;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VTodo;

use function Safe\preg_replace;

/**
 * Reminder Class
 **/
class Reminder extends CommonDBVisible implements
    CalDAVCompatibleItemInterface,
    ExtraVisibilityCriteria
{
    use PlanningEvent {
        post_getEmpty as trait_post_getEmpty;
    }
    use VobjectConverterTrait;
    /** @use Clonable<static> */
    use Clonable;

    // From CommonDBTM
    public $dohistory                   = true;
    /** @var bool */
    public $can_be_translated           = true;

    public static $rightname    = 'reminder_public';

    public const PERSONAL = 128;

    public static function getTypeName($nb = 0)
    {
        if (Session::haveRight('reminder_public', READ)) {
            return _n('Reminder', 'Reminders', $nb);
        }
        return _n('Personal reminder', 'Personal reminders', $nb);
    }

    public static function canCreate(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [CREATE, self::PERSONAL]));
    }

    public static function canView(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [READ, self::PERSONAL]));
    }

    public function canViewItem(): bool
    {
        // Is my reminder or is in visibility
        return ($this->fields['users_id'] == Session::getLoginUserID()
              || (Session::haveRight(self::$rightname, READ)
                  && $this->haveVisibilityAccess()));
    }

    public function canCreateItem(): bool
    {
        // Is my reminder
        return ($this->fields['users_id'] == Session::getLoginUserID());
    }

    public function canUpdateItem(): bool
    {
        return ($this->fields['users_id'] == Session::getLoginUserID()
              || (Session::haveRight(self::$rightname, UPDATE)
                  && $this->haveVisibilityAccess()));
    }

    public function canPurgeItem(): bool
    {
        return ($this->fields['users_id'] === Session::getLoginUserID()
              || (Session::haveRight(self::$rightname, PURGE)
                  && $this->haveVisibilityAccess()));
    }

    public static function canUpdate(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [UPDATE, self::PERSONAL]));
    }

    public static function canPurge(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [PURGE, self::PERSONAL]));
    }

    public function post_getFromDB()
    {
        // Users
        $this->users    = Reminder_User::getUsers($this->fields['id']);
        // Entities
        $this->entities = Entity_Reminder::getEntities($this);
        // Group / entities
        $this->groups   = Group_Reminder::getGroups($this->fields['id']);
        // Profile / entities
        $this->profiles = Profile_Reminder::getProfiles($this->fields['id']);
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                Entity_Reminder::class,
                Group_Reminder::class,
                PlanningRecall::class,
                Profile_Reminder::class,
                Reminder_User::class,
                ReminderTranslation::class,
            ]
        );
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInputForClone($input)
    {
        // regenerate uuid
        $input['uuid'] = Uuid::uuid4();
        return $input;
    }

    public function getCloneRelations(): array
    {
        return [
            Entity_Reminder::class,
            Group_Reminder::class,
            Profile_Reminder::class,
            Reminder_User::class,
            ReminderTranslation::class,
        ];
    }

    public function haveVisibilityAccess()
    {
        if (!self::canView()) {
            return false;
        }
        return parent::haveVisibilityAccess();
    }

    /**
     * Return visibility SQL restriction to add
     *
     * @return string restrict to add
     **/
    public static function addVisibilityRestrict()
    {
        //not deprecated because used in Search

        //get and clean criteria
        $criteria = self::getVisibilityCriteria();
        unset($criteria['LEFT JOIN']);
        $criteria['FROM'] = self::getTable();

        $it = new DBmysqlIterator(null);
        $it->buildQuery($criteria);
        $sql = $it->getSql();
        $sql = preg_replace('/.*WHERE /', '', $sql);

        return $sql;
    }

    /**
     * Return visibility joins to add to DBIterator parameters
     *
     * @since 9.4
     *
     * @param bool $forceall force all joins (false by default)
     *
     * @return array
     */
    public static function getVisibilityCriteria(bool $forceall = false): array
    {
        if (!Session::haveRight(self::$rightname, READ)) {
            return [
                'WHERE' => ['glpi_reminders.users_id' => Session::getLoginUserID()],
            ];
        }

        $join = [];
        $where = [];

        // Users
        $join['glpi_reminders_users'] = [
            'FKEY' => [
                'glpi_reminders_users'  => 'reminders_id',
                'glpi_reminders'        => 'id',
            ],
        ];

        if (Session::getLoginUserID()) {
            $where['OR'] = [
                'glpi_reminders.users_id'        => Session::getLoginUserID(),
                'glpi_reminders_users.users_id'  => Session::getLoginUserID(),
            ];
        } else {
            $where = [
                0,
            ];
        }

        // Groups
        if (
            $forceall
            || (isset($_SESSION["glpigroups"]) && count($_SESSION["glpigroups"]))
        ) {
            $join['glpi_groups_reminders'] = [
                'FKEY' => [
                    'glpi_groups_reminders' => 'reminders_id',
                    'glpi_reminders'        => 'id',
                ],
            ];

            $or = ['glpi_groups_reminders.no_entity_restriction' => 1];
            $restrict = getEntitiesRestrictCriteria(
                'glpi_groups_reminders',
                '',
                '',
                true
            );
            if (count($restrict)) {
                $or += $restrict;
            }
            $where['OR'][] = [
                'glpi_groups_reminders.groups_id' => count($_SESSION["glpigroups"])
                                                      ? $_SESSION["glpigroups"]
                                                      : [-1],
                'OR' => $or,
            ];
        }

        // Profiles
        if (
            $forceall
            || (isset($_SESSION["glpiactiveprofile"])
              && isset($_SESSION["glpiactiveprofile"]['id']))
        ) {
            $join['glpi_profiles_reminders'] = [
                'FKEY' => [
                    'glpi_profiles_reminders'  => 'reminders_id',
                    'glpi_reminders'           => 'id',
                ],
            ];

            $or = ['glpi_profiles_reminders.no_entity_restriction' => 1];
            $restrict = getEntitiesRestrictCriteria(
                'glpi_profiles_reminders',
                '',
                '',
                true
            );
            if (count($restrict)) {
                $or += $restrict;
            }
            $where['OR'][] = [
                'glpi_profiles_reminders.profiles_id' => $_SESSION["glpiactiveprofile"]['id'],
                'OR' => $or,
            ];
        }

        // Entities
        if (
            $forceall
            || (isset($_SESSION["glpiactiveentities"]) && count($_SESSION["glpiactiveentities"]))
        ) {
            $join['glpi_entities_reminders'] = [
                'FKEY' => [
                    'glpi_entities_reminders'  => 'reminders_id',
                    'glpi_reminders'           => 'id',
                ],
            ];
        }
        if (isset($_SESSION["glpiactiveentities"]) && count($_SESSION["glpiactiveentities"])) {
            $restrict = getEntitiesRestrictCriteria('glpi_entities_reminders', '', '', true, true);
            if (count($restrict)) {
                $where['OR'] += $restrict;
            }
        }

        $criteria = [
            'LEFT JOIN' => $join,
            'WHERE'     => $where,
        ];

        return $criteria;
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
            'field'              => 'name',
            'name'               => __('Title'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
            'forcegroupby'       => true,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'name'               => __('Writer'),
            'datatype'           => 'dropdown',
            'massiveaction'      => false,
            'right'              => 'all',
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'state',
            'name'               => __('Status'),
            'datatype'           => 'specific',
            'massiveaction'      => false,
            'searchtype'         => ['equals', 'notequals'],
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'text',
            'name'               => __('Description'),
            'massiveaction'      => false,
            'datatype'           => 'text',
            'htmltext'           => true,
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'begin_view_date',
            'name'               => __('Visibility start date'),
            'datatype'           => 'datetime',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'end_view_date',
            'name'               => __('Visibility end date'),
            'datatype'           => 'datetime',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => static::getTable(),
            'field'              => 'is_planned',
            'name'               => __('Planning'),
            'datatype'           => 'bool',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => static::getTable(),
            'field'              => 'begin',
            'name'               => __('Planning start date'),
            'datatype'           => 'datetime',
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => static::getTable(),
            'field'              => 'end',
            'name'               => __('Planning end date'),
            'datatype'           => 'datetime',
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

        // add objectlock search options
        $tab = array_merge($tab, ObjectLock::rawSearchOptionsToAdd(static::class));

        return $tab;
    }

    public function post_getEmpty()
    {
        $this->fields["name"] = __('New note');
        $this->trait_post_getEmpty();
    }

    final public static function getListCriteria(): array
    {
        $users_id = Session::getLoginUserID();
        $today    = date('Y-m-d');
        $now      = date('Y-m-d H:i:s');

        $visibility_criteria = [
            [
                'OR' => [
                    ['glpi_reminders.begin_view_date' => null],
                    ['glpi_reminders.begin_view_date' => ['<', $now]],
                ],
            ], [
                'OR' => [
                    ['glpi_reminders.end_view_date'   => null],
                    ['glpi_reminders.end_view_date'   => ['>', $now]],
                ],
            ],
        ];

        $personal_criteria = [
            'SELECT' => ['glpi_reminders.*'],
            'FROM'   => 'glpi_reminders',
            'WHERE'  => array_merge([
                'glpi_reminders.users_id'  => $users_id,
                [
                    'OR'        => [
                        'end'          => ['>=', $today],
                        'is_planned'   => 0,
                    ],
                ],
            ], $visibility_criteria),
            'ORDER'  => 'glpi_reminders.name',
        ];

        $public_criteria = array_merge_recursive(
            [
                'SELECT'          => ['glpi_reminders.*'],
                'DISTINCT'        => true,
                'FROM'            => 'glpi_reminders',
                'WHERE'           => $visibility_criteria,
                'ORDERBY'         => 'glpi_reminders.name',
            ],
            self::getVisibilityCriteria()
        );
        // Do not force the inclusion of reminders created by the current user
        unset($public_criteria['WHERE']['glpi_reminders.users_id'], $public_criteria['WHERE']['OR']['glpi_reminders.users_id']);

        if (countElementsInTable('glpi_remindertranslations') > 0) {
            $additional_criteria = [
                'SELECT'    => ["glpi_remindertranslations.name AS transname", "glpi_remindertranslations.text AS transtext"],
                'LEFT JOIN' => [
                    'glpi_remindertranslations' => [
                        'ON'  => [
                            'glpi_reminders'             => 'id',
                            'glpi_remindertranslations'  => 'reminders_id', [
                                'AND'                            => [
                                    'glpi_remindertranslations.language' => $_SESSION['glpilanguage'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
            $personal_criteria = array_merge_recursive($personal_criteria, $additional_criteria);
            $public_criteria   = array_merge_recursive($public_criteria, $additional_criteria);
        }

        // TOOD: would be cleaner to have two separate method like getPersonalCriteria
        // and getPublicCriteria
        return [
            'personal' => $personal_criteria,
            'public' => $public_criteria,
        ];
    }

    final public static function countPublicReminders(): int
    {
        global $DB;

        $criteria = self::getListCriteria();
        $public_criteria = $criteria['public'];

        // Replace select * by count
        $public_criteria['COUNT'] = 'total_rows';
        unset($public_criteria['ORDER BY']);
        unset($public_criteria['DISTINCT']);
        unset($public_criteria['SELECT']);

        $data = $DB->request($public_criteria);
        $row = $data->current();
        return $row['total_rows'];
    }

    public function getRights($interface = 'central')
    {
        if ($interface === 'helpdesk') {
            $values = [READ => __('Read')];
        } else {
            $values = parent::getRights();
            $values[self::PERSONAL] = __('Manage personal');
        }
        return $values;
    }

    public static function getGroupItemsAsVCalendars($groups_id)
    {
        return self::getItemsAsVCalendars(
            [
                'DISTINCT'  => true,
                'FROM'      => self::getTable(),
                'LEFT JOIN' => [
                    Group_Reminder::getTable() => [
                        'ON' => [
                            Group_Reminder::getTable() => 'reminders_id',
                            self::getTable()           => 'id',
                        ],
                    ],
                ],
                'WHERE'     => [
                    Group_Reminder::getTableField('groups_id') => $groups_id,
                ],
            ]
        );
    }

    public static function getUserItemsAsVCalendars($users_id)
    {
        return self::getItemsAsVCalendars(
            [
                'FROM'  => self::getTable(),
                'WHERE' => [
                    self::getTableField('users_id') => $users_id,
                ],
            ]
        );
    }

    /**
     * Returns items as VCalendar objects.
     *
     * @param array $query
     *
     * @return VCalendar[]
     */
    private static function getItemsAsVCalendars(array $query)
    {
        global $DB;

        $reminder_iterator = $DB->request($query);

        $vcalendars = [];
        foreach ($reminder_iterator as $reminder) {
            $item = new self();
            $item->getFromResultSet($reminder);
            $vcalendar = $item->getAsVCalendar();
            if (null !== $vcalendar) {
                $vcalendars[] = $vcalendar;
            }
        }

        return $vcalendars;
    }

    public function getAsVCalendar()
    {
        if (!$this->canViewItem()) {
            return null;
        }

        $is_task = in_array($this->fields['state'], [Planning::DONE, Planning::TODO], true);
        $is_planned = !empty($this->fields['begin']) && !empty($this->fields['end']);
        $target_component = $this->getTargetCaldavComponent($is_planned, $is_task);
        if (null === $target_component) {
            return null;
        }

        return $this->getVCalendarForItem($this, $target_component);
    }

    public function getInputFromVCalendar(VCalendar $vcalendar)
    {
        $vcomp = $vcalendar->getBaseComponent();

        $input = $this->getCommonInputFromVcomponent($vcomp, $this->isNewItem());

        $input['text'] = $input['content'];
        unset($input['content']);

        if ($vcomp instanceof VTodo && !array_key_exists('state', $input)) {
            // Force default state to TODO or reminder will be considered as VEVENT
            $input['state'] = Planning::TODO;
        }

        return $input;
    }
}
