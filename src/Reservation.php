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

use Glpi\Event;

use function Safe\strtotime;

/**
 * Reservation Class
 **/
class Reservation extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype = ReservationItem::class;
    public static $items_id          = 'reservationitems_id';

    public static $rightname                = 'reservation';
    public static $checkParentRights = self::HAVE_VIEW_RIGHT_ON_ITEM;

    public static function getTypeName($nb = 0)
    {
        return _n('Reservation', 'Reservations', $nb);
    }

    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if (
            isset($this->fields["users_id"])
            && (($this->fields["users_id"] === Session::getLoginUserID())
              || Session::haveRight("reservation", PURGE))
        ) {
            // Processing Email
            if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_notifications"]) {
                // Only notify for non-completed reservations
                if (strtotime($this->fields['end']) > time()) {
                    NotificationEvent::raiseEvent("delete", $this);
                }
            }
        }
        return true;
    }

    public function prepareInputForUpdate($input)
    {
        // Save fields
        $oldfields             = $this->fields;
        // Needed for test already planned
        if (isset($input["begin"])) {
            $this->fields["begin"] = $input["begin"];
        }
        if (isset($input["end"])) {
            $this->fields["end"] = $input["end"];
        }

        if (!$this->isReservationInputValid()) {
            return false;
        }

        // Restore fields
        $this->fields = $oldfields;

        return parent::prepareInputForUpdate($input);
    }

    public function post_updateItem($history = true)
    {
        global $CFG_GLPI;

        if (
            count($this->updates)
            && $CFG_GLPI["use_notifications"]
            && !isset($this->input['_disablenotif'])
        ) {
            NotificationEvent::raiseEvent("update", $this);
        }

        parent::post_updateItem($history);
    }

    public function prepareInputForAdd($input)
    {
        // Error on previous added reservation on several add
        if (isset($input['_ok']) && !$input['_ok']) {
            return false;
        }

        // set new date.
        $this->fields["reservationitems_id"] = $input["reservationitems_id"];
        $this->fields["begin"] = $input["begin"];
        $this->fields["end"] = $input["end"];

        if (!$this->isReservationInputValid()) {
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    public static function handleAddForm(array $input): void
    {
        if (empty($input['users_id'])) {
            $input['users_id'] = Session::getLoginUserID();
        }

        // Check if user has permission to create reservations
        if (!self::canCreate()) {
            Session::addMessageAfterRedirect(
                __s('You do not have permission to create reservations'),
                false,
                ERROR
            );
            return;
        }

        // Additional check: if creating for another user, ensure user has CREATE right (not just RESERVEANITEM)
        if ($input['users_id'] != Session::getLoginUserID() && !Session::haveRight(self::$rightname, CREATE)) {
            Session::addMessageAfterRedirect(
                __s('You do not have permission to create reservations for other users'),
                false,
                ERROR
            );
            return;
        }

        Toolbox::manageBeginAndEndPlanDates($input['resa']);
        if (!isset($input['resa']["begin"], $input['resa']["end"])) {
            return;
        }

        if (!isset($input['items']) || !is_array($input['items']) || count($input['items']) === 0) {
            Session::addMessageAfterRedirect(
                __s('No selected items'),
                false,
                ERROR
            );
        }

        $dates_to_add = [];
        $dates_to_add[$input['resa']["begin"]] = $input['resa']["end"];
        if (!empty($input['periodicity']['type'])) {
            $dates_to_add += self::computePeriodicities(
                $input['resa']["begin"],
                $input['resa']["end"],
                $input['periodicity']
            );
        }
        ksort($dates_to_add);

        foreach ($input['items'] as $reservationitems_id) {
            $rr = new self();
            $group = (count($dates_to_add) > 1) ? $rr->getUniqueGroupFor($reservationitems_id) : null;

            foreach ($dates_to_add as $begin => $end) {
                $reservation_input = [
                    'begin' => $begin,
                    'end' => $end,
                    'reservationitems_id' => $reservationitems_id,
                    'comment' => $input['comment'],
                    'users_id' => (int) $input['users_id'],
                ];
                if (count($dates_to_add) > 1) {
                    $reservation_input['group'] = $group;
                }

                if ($newID = $rr->add($reservation_input)) {
                    Event::log(
                        $newID,
                        "reservation",
                        4,
                        "inventory",
                        sprintf(
                            __s('%1$s adds the reservation %2$s for item %3$s'),
                            $_SESSION["glpiname"],
                            $newID,
                            $reservationitems_id
                        )
                    );

                    $rri = new ReservationItem();
                    $rri->getFromDB($reservationitems_id);
                    $item = getItemForItemtype($rri->fields["itemtype"]);
                    $item->getFromDB($rri->fields["items_id"]);

                    Session::addMessageAfterRedirect(
                        sprintf(
                            __s('Reservation added for item %s at %s'),
                            $item->getLink(),
                            htmlescape(Html::convDateTime($reservation_input['begin']))
                        )
                    );
                }
            }
        }
    }

    /**
     * Check reservation input.
     *
     * @return bool
     */
    private function isReservationInputValid(): bool
    {
        if (!$this->test_valid_date()) {
            Session::addMessageAfterRedirect(
                __s('Error in entering dates. The starting date is later than the ending date'),
                false,
                ERROR
            );
            return false;
        }

        if ($this->is_reserved()) {
            Session::addMessageAfterRedirect(
                __s('The required item is already reserved for this timeframe'),
                false,
                ERROR
            );
            return false;
        }

        return true;
    }

    public function post_addItem()
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_notifications"]) {
            NotificationEvent::raiseEvent("new", $this);
        }

        parent::post_addItem();
    }

    // SPECIFIC FUNCTIONS

    /**
     * Returns an integer that is not already used as a group for the given reservation item.
     *
     * @param int $reservationitems_id
     *
     * @return int
     */
    public function getUniqueGroupFor($reservationitems_id): int
    {
        global $DB;

        do {
            $rand = random_int(1, mt_getrandmax());

            $result = $DB->request([
                'COUNT'  => 'cpt',
                'FROM'   => 'glpi_reservations',
                'WHERE'  => [
                    'reservationitems_id'   => $reservationitems_id,
                    'group'                 => $rand,
                ],
            ])->current();
            $count = (int) $result['cpt'];
        } while ($count > 0);

        return $rand;
    }

    /**
     * Is the item already reserved ?
     *
     *@return bool
     **/
    public function is_reserved()
    {
        global $DB;

        if (
            !isset($this->fields["reservationitems_id"])
            || empty($this->fields["reservationitems_id"])
        ) {
            return true;
        }

        // When modify a reservation do not itself take into account
        $where = [];
        if (isset($this->fields["id"])) {
            $where['id'] = ['<>', $this->fields['id']];
        }

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => static::getTable(),
            'WHERE'  => $where + [
                'reservationitems_id'   => $this->fields['reservationitems_id'],
                'end'                   => ['>', $this->fields['begin']],
                'begin'                 => ['<', $this->fields['end']],
            ],
        ])->current();
        return $result['cpt'] > 0;
    }

    /**
     * Current dates are valid ? begin before end
     *
     * @return bool
     **/
    public function test_valid_date()
    {
        return (!empty($this->fields["begin"])
              && !empty($this->fields["end"])
              && (strtotime($this->fields["begin"]) < strtotime($this->fields["end"])));
    }

    public static function canView(): bool
    {
        // Users with READ right can see all reservations
        if (Session::haveRight(self::$rightname, READ)) {
            return true;
        }

        // Users with RESERVEANITEM right can see their own reservations (checked in canViewItem)
        if (Session::haveRight(self::$rightname, ReservationItem::RESERVEANITEM)) {
            return true;
        }

        // Delegate to parent to check parent item permissions
        return parent::canView();
    }

    public static function canCreate(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [CREATE, ReservationItem::RESERVEANITEM]));
    }

    public function canCreateItem(): bool
    {
        return self::canCreate();
    }

    public static function canUpdate(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [UPDATE, ReservationItem::RESERVEANITEM]));
    }

    public static function canDelete(): bool
    {
        return (Session::haveRight(self::$rightname, ReservationItem::RESERVEANITEM));
    }

    public static function canPurge(): bool
    {
        return (Session::haveRightsOr(self::$rightname, [PURGE, ReservationItem::RESERVEANITEM]));
    }

    public function canChildItem($methodItem, $methodNotItem)
    {
        // All users can manage their own reservations (read, create, update, purge)
        if ($this->fields['users_id'] === Session::getLoginUserID()) {
            return true;
        }

        // If user only has RESERVEANITEM right (no other reservation rights),
        // they can only manage their own reservations (already handled above)
        $reservation_rights = $_SESSION['glpiactiveprofile'][self::$rightname] ?? 0;
        if ($reservation_rights == ReservationItem::RESERVEANITEM) {
            return false; // Only own reservations allowed with RESERVEANITEM only
        }

        // Check if user has rights on the parent item (asset)
        /** @var ReservationItem $ri */
        $ri = $this->getItem();
        $item = $ri !== false ? $ri->getItem() : false;
        if ($item !== false) {
            // Users with permission to update the specific asset can CRUD all reservations for that asset
            if ($item->canUpdateItem() && Session::haveRight($item::$rightname, UPDATE)) {
                return true;
            }
        }

        // Check if user has global rights for this operation
        if (!parent::canChildItem($methodItem, $methodNotItem)) {
            return false;
        }

        // At minimum, check entity access for the asset
        if ($item !== false) {
            return Session::haveAccessToEntity($item->getEntityID(), $item->isRecursive());
        }

        return false;
    }

    public function canViewItem(): bool
    {
        // Users with READ right can see all reservations they have entity access to
        if (Session::haveRight(self::$rightname, READ)) {
            return $this->canChildItem('canViewItem', 'canView');
        }

        // All users can see their own reservations
        if ($this->fields['users_id'] === Session::getLoginUserID()) {
            return true;
        }

        // If user only has RESERVEANITEM right, they can only see their own reservations
        $reservation_rights = $_SESSION['glpiactiveprofile'][self::$rightname] ?? 0;
        if ($reservation_rights == ReservationItem::RESERVEANITEM) {
            return false; // Only own reservations allowed with RESERVEANITEM only
        }

        // Check if user has rights on the parent item (asset)
        /** @var ReservationItem $ri */
        $ri = $this->getItem();
        if ($ri === false) {
            return false;
        }

        $item = $ri->getItem();
        if ($item === false) {
            return false;
        }

        // Users with permission to update the specific asset can see all reservations for that asset
        if ($item->canUpdateItem() && Session::haveRight($item::$rightname, UPDATE)) {
            return true;
        }

        return false;
    }

    public function canPurgeItem(): bool
    {
        // Follow the same pattern as canUpdateItem and canDeleteItem by delegating to canChildItem
        return $this->canChildItem('canUpdateItem', 'canUpdate');
    }

    public function post_purgeItem()
    {
        global $DB;

        if (isset($this->input['_delete_group']) && $this->input['_delete_group']) {
            $iterator = $DB->request([
                'FROM'   => 'glpi_reservations',
                'WHERE'  => [
                    'reservationitems_id'   => $this->fields['reservationitems_id'],
                    'group'                 => $this->fields['group'],
                ],
            ]);
            $rr = clone $this;
            foreach ($iterator as $data) {
                $rr->delete(['id' => $data['id']]);
            }
        }
    }
    /**
     * @return array
     */
    public static function getResources()
    {
        global $DB;

        $res_i_table = ReservationItem::getTable();

        $iterator = $DB->request([
            'SELECT' => [
                "$res_i_table.items_id",
                "$res_i_table.itemtype",
            ],
            'FROM'   => $res_i_table,
            'WHERE'  => [
                'is_active'  => 1,
            ],
        ]);

        $resources = [];
        if (!count($iterator)) {
            return [];
        }
        foreach ($iterator as $data) {
            $item = getItemForItemtype($data['itemtype']);
            if (!$item->getFromDB($data['items_id'])) {
                continue;
            }

            $resources[] = [
                'id' => $data['itemtype'] . "-" . $data['items_id'],
                'title' => sprintf(__("%s - %s"), $data['itemtype']::getTypeName(), $item->getName()),
            ];
        }

        return $resources;
    }

    /**
     * Change dates of a selected reservation.
     * Called from a drag&drop in planning
     *
     * @param array{id: int, start: string, end: string} $event
     * <ul>
     *     <li>id: integer to identify reservation</li>
     *     <li>start: planning start (should be an ISO_8601 date, but could be anything that can be parsed by strtotime)</li>
     *     <li>end: planning end (should be an ISO_8601 date, but could be anything that can be parsed by strtotime)</li>
     * </ul>
     * @return bool
     */
    public static function updateEvent(array $event): bool
    {
        $reservation = new static();
        if (!$reservation->getFromDB((int) $event['id'])) {
            return false;
        }

        $event = Planning::cleanDates($event);

        return $reservation->update([
            'id'    => (int) $event['id'],
            'begin' => date("Y-m-d H:i:s", strtotime($event['start'])),
            'end'   => date("Y-m-d H:i:s", strtotime($event['end'])),
        ]);
    }

    /**
     * compute periodicities for reservation
     *
     * @since 0.84
     *
     * @param string $begin  Planning start (should be an ISO_8601 date, but could be anything that can be parsed by strtotime)
     * @param string $end    Planning end (should be an ISO_8601 date, but could be anything that can be parsed by strtotime)
     * @param array{type: 'day'|'week'|'month', end: string, subtype?: string, days?: int} $options Periodicity parameters
     *
     * @return array
     **/
    public static function computePeriodicities($begin, $end, $options)
    {
        $toadd = [];
        if (!isset($options['type'], $options['end'])) {
            return $toadd;
        }

        $begin_time = strtotime($begin);
        $end_time   = strtotime($end);
        $repeat_end = strtotime($options['end'] . ' 23:59:59');

        switch ($options['type']) {
            case 'day':
                $begin_time = strtotime("+1 day", $begin_time);
                $end_time   = strtotime("+1 day", $end_time);
                while ($begin_time < $repeat_end) {
                    $toadd[date('Y-m-d H:i:s', $begin_time)] = date('Y-m-d H:i:s', $end_time);
                    $begin_time = strtotime("+1 day", $begin_time);
                    $end_time   = strtotime("+1 day", $end_time);
                }
                break;

            case 'week':
                $dates = [];

                // No days set add 1 week
                if (!isset($options['days'])) {
                    $dates = [['begin' => strtotime('+1 week', $begin_time),
                        'end'   => strtotime('+1 week', $end_time),
                    ],
                    ];
                } else {
                    if (is_array($options['days'])) {
                        $begin_hour = $begin_time - strtotime(date('Y-m-d', $begin_time));
                        $end_hour   = $end_time - strtotime(date('Y-m-d', $end_time));
                        foreach ($options['days'] as $day => $val) {
                            $end_day = $day;
                            // Check that the start and end times are different else set the end day at the next day
                            if ($begin_hour == $end_hour) {
                                $end_day = date('l', strtotime($day . ' +1 day'));
                            }
                            $dates[] = ['begin' => strtotime("next $day", $begin_time) + $begin_hour,
                                'end'   => strtotime("next $end_day", $end_time) + $end_hour,
                            ];
                        }
                    }
                }
                foreach ($dates as $key => $val) {
                    $begin_time = $val['begin'];
                    $end_time   = $val['end'];
                    while ($begin_time < $repeat_end) {
                        $toadd[date('Y-m-d H:i:s', $begin_time)] = date('Y-m-d H:i:s', $end_time);
                        $begin_time = strtotime('+1 week', $begin_time);
                        $end_time   = strtotime('+1 week', $end_time);
                    }
                }
                break;

            case 'month':
                if (isset($options['subtype'])) {
                    switch ($options['subtype']) {
                        case 'date':
                            $i = 1;
                            $calc_begin_time = strtotime("+$i month", $begin_time);
                            $calc_end_time   = strtotime("+$i month", $end_time);
                            while ($calc_begin_time < $repeat_end) {
                                $toadd[date('Y-m-d H:i:s', $calc_begin_time)] = date(
                                    'Y-m-d H:i:s',
                                    $calc_end_time
                                );
                                $i++;
                                $calc_begin_time = strtotime("+$i month", $begin_time);
                                $calc_end_time   = strtotime("+$i month", $end_time);
                            }
                            break;

                        case 'day':
                            $dayofweek = date('l', $begin_time);

                            $i               = 1;
                            $calc_begin_time = strtotime("+$i month", $begin_time);
                            $calc_end_time   = strtotime("+$i month", $end_time);
                            $begin_hour      = $begin_time - strtotime(date('Y-m-d', $begin_time));
                            $end_hour        = $end_time - strtotime(date('Y-m-d', $end_time));

                            $calc_begin_time = strtotime("next $dayofweek", $calc_begin_time)
                                    + $begin_hour;
                            $calc_end_time   = strtotime("next $dayofweek", $calc_end_time) + $end_hour;

                            while ($calc_begin_time < $repeat_end) {
                                $toadd[date('Y-m-d H:i:s', $calc_begin_time)] = date(
                                    'Y-m-d H:i:s',
                                    $calc_end_time
                                );
                                $i++;
                                $calc_begin_time = strtotime("+$i month", $begin_time);
                                $calc_end_time   = strtotime("+$i month", $end_time);
                                $calc_begin_time = strtotime("next $dayofweek", $calc_begin_time)
                                       + $begin_hour;
                                $calc_end_time   = strtotime("next $dayofweek", $calc_end_time)
                                       + $end_hour;
                            }
                            break;
                    }
                }

                break;
        }
        return $toadd;
    }

    /**
     * Get reservation data for a user
     * @param int $users_id ID of the user
     * @return array
     */
    public static function getForUser(int $users_id): array
    {
        global $DB;

        $now = $_SESSION["glpi_currenttime"];

        $common_criteria = [
            'SELECT'    => [
                'begin',
                'end',
                'items_id',
                'glpi_reservationitems.entities_id',
                'users_id',
                'glpi_reservations.comment',
                'reservationitems_id',
                'completename',
            ],
            'FROM'      => 'glpi_reservations',
            'LEFT JOIN' => [
                'glpi_reservationitems' => [
                    'ON' => [
                        'glpi_reservationitems' => 'id',
                        'glpi_reservations'     => 'reservationitems_id',
                    ],
                ],
                'glpi_entities' => [
                    'ON' => [
                        'glpi_reservationitems' => 'entities_id',
                        'glpi_entities'         => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'users_id'  => $users_id,
            ],
        ];

        // Print reservation in progress
        $in_progress_criteria = $common_criteria;
        $in_progress_criteria['WHERE']['end'] = ['>', $now];
        $in_progress_criteria['ORDERBY'] = 'begin';
        $iterator = $DB->request($in_progress_criteria);

        $ri = new ReservationItem();

        $fn_get_entry = static function (array $data) use ($ri) {
            $entry = [
                'id' => $data['reservationitems_id'],
                'start_date' => $data['begin'],
                'end_date' => $data['end'],
                'item' => null,
                'entity' => null,
                'by' => $data["users_id"],
                'comments' => $data["comment"],
            ];

            if ($ri->getFromDB($data["reservationitems_id"])) {
                $entry['item']['itemtype'] = $ri->fields['itemtype'];
                $entry['item']['id'] = $ri->fields['items_id'];
                $entry['entity'] = $data['entities_id'];
            }
            return $entry;
        };

        $progress_entries = [];
        foreach ($iterator as $data) {
            $progress_entries[] = $fn_get_entry($data);
        }

        // Print old reservations
        $old_criteria = $common_criteria;
        $old_criteria['WHERE']['end'] = ['<=', $now];
        $old_criteria['ORDERBY'] = 'begin DESC';
        $iterator = $DB->request($old_criteria);

        $old_entries = [];
        foreach ($iterator as $data) {
            $old_entries[] = $fn_get_entry($data);
        }

        return [
            'in_progress' => $progress_entries,
            'old' => $old_entries,
        ];
    }

    /**
     * Get reservable itemtypes from GLPI config, filtering out itemtype with no
     * reservable items
     *
     * @return array
     */
    public static function getReservableItemtypes(): array
    {
        global $CFG_GLPI;

        return array_filter(
            $CFG_GLPI['reservation_types'],
            static fn($type) => ReservationItem::countAvailableItems($type) > 0
        );
    }

    public static function getMassiveActionsForItemtype(array &$actions, $itemtype, $is_deleted = false, ?CommonDBTM $checkitem = null)
    {
        global $CFG_GLPI;

        $action_prefix = 'Reservation' . MassiveAction::CLASS_ACTION_SEPARATOR;
        if (in_array($itemtype, $CFG_GLPI["reservation_types"], true)) {
            $show_all = $checkitem === null || $checkitem->isNewItem();
            $reservable = false;
            $available = false;
            if (!$show_all) {
                if ($checkitem->isTemplate()) {
                    return;
                }
                $ri = new ReservationItem();
                $reservable = $ri->getFromDBbyItem($checkitem::class, $checkitem->getID());
                if ($reservable) {
                    $available = (bool) $ri->fields['is_active'];
                }
            }
            if ($show_all || !$reservable) {
                $actions[$action_prefix . 'enable'] = "<i class='" . htmlescape(self::getIcon()) . "'></i>" . __s('Authorize reservations');
            }
            if ($show_all || $reservable) {
                $actions[$action_prefix . 'disable'] = "<i class='ti ti-calendar-off'></i>" . __s('Prohibit reservations');
            }
            if ($show_all || ($reservable && !$available)) {
                $actions[$action_prefix . 'available'] = "<i class='" . htmlescape(self::getIcon()) . "'></i>" . __s('Make available for reservations');
            }
            if ($show_all || $available) {
                $actions[$action_prefix . 'unavailable'] = "<i class='ti ti-calendar-off'></i>" . __s('Make unavailable for reservations');
            }
        }
    }

    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        if (!ReservationItem::canUpdate()) {
            return;
        }
        $reservation_item = new ReservationItem();

        switch ($ma->getAction()) {
            case 'enable':
                foreach ($ids as $id) {
                    if ($reservation_item->getFromDBbyItem($item::getType(), $id)) {
                        // Treat as OK
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $result = $reservation_item->add([
                            'itemtype' => $item->getType(),
                            'items_id' => $id,
                            'is_active' => 1,
                        ]);
                        $ma->itemDone($item->getType(), $id, $result ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
                    }
                }
                break;
            case 'disable':
                foreach ($ids as $id) {
                    if ($reservation_item->getFromDBbyItem($item::getType(), $id)) {
                        $result = $reservation_item->delete(['id' => $reservation_item->getID()]);
                        $ma->itemDone($item->getType(), $id, $result ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    }
                }
                break;
            case 'available':
                foreach ($ids as $id) {
                    if ($reservation_item->getFromDBbyItem($item::getType(), $id)) {
                        $result = $reservation_item->update([
                            'id' => $reservation_item->getID(),
                            'is_active' => 1,
                        ]);
                        $ma->itemDone($item->getType(), $id, $result ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    }
                }
                break;
            case 'unavailable':
                foreach ($ids as $id) {
                    if ($reservation_item->getFromDBbyItem($item::getType(), $id)) {
                        $result = $reservation_item->update([
                            'id' => $reservation_item->getID(),
                            'is_active' => 0,
                        ]);
                        $ma->itemDone($item->getType(), $id, $result ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    }
                }
                break;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }
}
