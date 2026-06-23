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
use Glpi\DBAL\QueryFunction;

/**
 * ReservationItem Class
 **/
class ReservationItem extends CommonDBChild
{
    /// From CommonDBChild
    public static $itemtype          = 'itemtype';
    public static $items_id          = 'items_id';

    public static $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;

    public static $rightname                = 'reservation';

    public const RESERVEANITEM              = 1024;

    public static function canView(): bool
    {
        return Session::haveRightsOr(self::$rightname, [READ, self::RESERVEANITEM]);
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Reservable item', 'Reservable items', $nb);
    }

    public static function getForbiddenActionsForMenu()
    {
        return ['add'];
    }

    /**
     * Retrieve an item from the database for a specific item
     *
     * @param class-string<CommonDBTM> $itemtype Type of the item
     * @param int $ID ID of the item
     *
     * @return bool true if succeed else false
     **/
    public function getFromDBbyItem($itemtype, $ID)
    {
        return $this->getFromDBByCrit([
            static::getTable() . '.itemtype'  => $itemtype,
            static::getTable() . '.items_id'  => $ID,
        ]);
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                Reservation::class,
            ]
        );

        // Alert does not extend CommonDBConnexity
        $alert = new Alert();
        $alert->cleanDBonItemDelete(static::class, $this->fields['id']);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
            'htmltext'           => true,
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'is_active',
            'name'               => __('Active'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics'),
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => 'reservation_types',
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
            'addobjectparams'    => [
                'forcetab'           => 'Reservation$1',
            ],
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => 'reservation_types',
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'number',
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => static::getTable(),
            'field'              => '_virtual',
            'name'               => __('Planning'),
            'datatype'           => 'specific',
            'massiveaction'      => false,
            'nosearch'           => true,
            'nosort'             => true,
            'additionalfields'   => ['is_active'],
        ];

        $loc = Location::rawSearchOptionsToAdd();
        // Force massive actions to false
        foreach ($loc as &$val) {
            $val['massiveaction'] = false;
        }
        $tab = array_merge($tab, $loc);

        $tab[] = [
            'id'                 => '6',
            'table'              => 'reservation_types',
            'field'              => 'otherserial',
            'name'               => __('Inventory number'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => 'reservation_types',
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '70',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'name'               => User::getTypeName(1),
            'datatype'           => 'dropdown',
            'right'              => 'all',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '71',
            'table'              => 'glpi_groups',
            'field'              => 'completename',
            'name'               => Group::getTypeName(1),
            'datatype'           => 'dropdown',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '19',
            'table'              => 'reservation_types',
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '23',
            'table'              => 'glpi_manufacturers',
            'field'              => 'name',
            'name'               => Manufacturer::getTypeName(1),
            'datatype'           => 'dropdown',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '24',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'linkfield'          => 'users_id_tech',
            'name'               => __('Technician in charge'),
            'datatype'           => 'dropdown',
            'right'              => 'interface',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return array
     */
    public static function rawSearchOptionsToAdd($itemtype = null)
    {
        return [
            [
                'id'                 => '81',
                'table'              => static::getTable(),
                'name'               => __('Reservable'),
                'field'              => 'is_active',
                'joinparams'         => [
                    'jointype' => 'itemtype_item',
                ],
                'datatype'           => 'bool',
                'massiveaction'      => false,
            ],
        ];
    }

    /**
     * @param string $name
     *
     * @return array
     * @used-by CronTask
     **/
    public static function cronInfo($name)
    {
        return ['description' => __('Alerts on reservations')];
    }

    /**
     * Cron action on reservation : alert on end of reservations
     *
     * @param CronTask $task Task to log, if NULL use display (default NULL)
     *
     * @return int 0 : nothing to do 1 : done with success
     * @used-by CronTask
     **/
    public static function cronReservation($task = null)
    {
        global $CFG_GLPI, $DB;

        if (!$CFG_GLPI["use_notifications"]) {
            return 0;
        }

        $cron_status    = 0;
        $items_infos    = [];
        $items_messages = [];

        foreach (Entity::getEntitiesToNotify('use_reservations_alert') as $entity => $value) {
            $secs = (int) $value * HOUR_TIMESTAMP;

            // Reservation already begin and reservation ended in $value hours
            $criteria = [
                'SELECT' => [
                    'glpi_reservationitems.*',
                    'glpi_reservations.end AS end',
                    'glpi_reservations.id AS resaid',
                ],
                'FROM'   => 'glpi_reservations',
                'LEFT JOIN' => [
                    'glpi_alerts'  => [
                        'ON'  => [
                            'glpi_reservations'  => 'id',
                            'glpi_alerts'        => 'items_id', [
                                'AND' => [
                                    'glpi_alerts.itemtype'  => 'Reservation',
                                    'glpi_alerts.type'      => Alert::END,
                                ],
                            ],
                        ],
                    ],
                    'glpi_reservationitems' => [
                        'ON'  => [
                            'glpi_reservations'     => 'reservationitems_id',
                            'glpi_reservationitems' => 'id',
                        ],
                    ],
                ],
                'WHERE'     => [
                    'glpi_reservationitems.entities_id' => $entity,
                    new QueryExpression(
                        QueryFunction::unixTimestamp('glpi_reservations.end') . ' - ' . $secs
                            . ' < ' . QueryFunction::unixTimestamp()
                    ),
                    'glpi_reservations.begin'  => ['<', QueryFunction::now()],
                    'glpi_alerts.date'         => null,
                ],
            ];
            $iterator = $DB->request($criteria);

            foreach ($iterator as $data) {
                if ($item_resa = getItemForItemtype($data['itemtype'])) {
                    if ($item_resa->getFromDB($data["items_id"])) {
                        $data['item_name']                     = $item_resa->getName();
                        $data['entity']                        = $entity;
                        $items_infos[$entity][$data['resaid']] = $data;

                        if (!isset($items_messages[$entity])) {
                            $items_messages[$entity] = [__('Device reservations expiring today')];
                        }
                        $items_messages[$entity][] = sprintf(
                            __('%1$s - %2$s'),
                            $item_resa::getTypeName(),
                            $item_resa->getName()
                        );
                    }
                }
            }
        }

        foreach ($items_infos as $entity => $items) {
            $resitem = new self();
            if (
                NotificationEvent::raiseEvent(
                    "alert",
                    new Reservation(),
                    ['entities_id' => $entity,
                        'items'       => $items,
                    ]
                )
            ) {
                $messages    = $items_messages[$entity];
                $cron_status = 1;
                if ($task) {
                    $task->addVolume(1);
                    $task->log(sprintf(
                        __('%1$s: %2$s') . "\n",
                        Dropdown::getDropdownName("glpi_entities", $entity),
                        implode("\n", $messages)
                    ));
                } else {
                    //TRANS: %1$s is a name, %2$s is text of message
                    Session::addMessageAfterRedirect(sprintf(
                        __s('%1$s: %2$s'),
                        htmlescape(Dropdown::getDropdownName("glpi_entities", $entity)),
                        implode('<br>', array_map('htmlescape', $messages))
                    ));
                }

                $alert             = new Alert();
                $input["itemtype"] = 'Reservation';
                $input["type"]     = Alert::END;
                foreach (array_keys($items) as $resaid) {
                    $input["items_id"] = $resaid;
                    $alert->add($input);
                    unset($alert->fields['id']);
                }
            } else {
                $entityname = Dropdown::getDropdownName('glpi_entities', $entity);
                //TRANS: %s is entity name
                $msg = sprintf(__('%1$s: %2$s'), $entityname, __('Send reservation alert failed'));
                if ($task) {
                    $task->log($msg);
                } else {
                    Session::addMessageAfterRedirect(htmlescape($msg), false, ERROR);
                }
            }
        }
        return $cron_status;
    }

    public function getRights($interface = 'central')
    {
        if ($interface === 'central') {
            $values = parent::getRights();
        } else {
            $values = [READ => __('Read')];
        }
        $values[self::RESERVEANITEM] = __('Make a reservation');

        return $values;
    }

    public function isNewItem()
    {
        return false;
    }

    /**
     * Get available items for a given itemtype
     *
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return DBmysqlIterator
     */
    public static function getAvailableItems(string $itemtype): DBmysqlIterator
    {
        global $DB;

        $reservation_table = self::getTable();
        $item_table = $itemtype::getTable();

        $criteria = self::getAvailableItemsCriteria($itemtype);
        $criteria['SELECT'] = [
            "$reservation_table.id",
            "$item_table.name",
        ];

        return $DB->request($criteria);
    }

    /**
     * Get available items for a given itemtype
     *
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return int
     */
    public static function countAvailableItems(string $itemtype): int
    {
        global $DB;

        $criteria = self::getAvailableItemsCriteria($itemtype);
        $criteria['COUNT'] = 'total';
        $results = $DB->request($criteria);
        return $results->current()['total'];
    }

    /**
     * Get common criteria for getAvailableItems and countAvailableItems functions
     *
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return array
     */
    private static function getAvailableItemsCriteria(string $itemtype): array
    {
        $reservation_table = self::getTable();
        /** @var CommonDBTM $item */
        $item = getItemForItemtype($itemtype);
        $item_table = $itemtype::getTable();

        $criteria = [
            'FROM' => $item_table,
            'INNER JOIN' => [
                $reservation_table => [
                    'ON' => [
                        $reservation_table => 'items_id',
                        $item_table => 'id',
                        ['AND' => ["$reservation_table.itemtype" => $itemtype]],
                    ],
                ],
            ],
            'WHERE' => [
                "$reservation_table.is_active"   => 1,
                "$item_table.is_deleted"  => 0,
            ],
        ];

        if ($item->isEntityAssign()) {
            $criteria['WHERE'] += getEntitiesRestrictCriteria($item_table, '', '', $item->maybeRecursive());
        }

        if ($item->maybeTemplate()) {
            $criteria['WHERE']["$item_table.is_template"] = 0;
        }

        return $criteria;
    }
}
