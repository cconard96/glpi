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

use Glpi\DBAL\QuerySubQuery;

use function Safe\strtotime;

/**
 * LevelAgreement base Class for OLA & SLA
 * @since 9.2
 **/
abstract class LevelAgreement extends CommonDBChild
{
    // From CommonDBTM
    public $dohistory          = true;
    public static $rightname       = 'slm';

    // From CommonDBChild
    public static $itemtype = SLM::class;
    public static $items_id = 'slms_id';

    /** @var string  */
    protected static $prefix            = '';
    /** @var string  */
    protected static $prefixticket      = '';
    /** @var ''|class-string<LevelAgreementLevel> */
    protected static $levelclass        = '';
    /** @var string|class-string<CommonDBTM> */
    protected static $levelticketclass  = '';

    /**
     * Return the text needed for a confirmation of adding level agreement to a ticket
     *
     * @return string[]
     */
    abstract public function getAddConfirmation(): array;

    /**
     * Get table fields
     *
     * @param int $subtype of OLA/SLA, can be SLM::TTO or SLM::TTR
     *
     * @return array of 'date' and 'sla' field names
     */
    public static function getFieldNames($subtype)
    {
        $dateField = null;
        $laField  = null;

        switch ($subtype) {
            case SLM::TTO:
                $dateField = static::$prefixticket . 'time_to_own';
                $laField   = static::$prefix . 's_id_tto';
                break;

            case SLM::TTR:
                $dateField = static::$prefixticket . 'time_to_resolve';
                $laField   = static::$prefix . 's_id_ttr';
                break;
        }
        return [$dateField, $laField];
    }

    public static function getWaitingFieldName(): string
    {
        return static::$prefix . '_waiting_duration';
    }

    /**
     * Define calendar of the ticket using the SLA/OLA when using this calendar as sla/ola-s calendar
     *
     * @param int $calendars_id calendars_id of the ticket
     *
     * @return void
     */
    public function setTicketCalendar($calendars_id)
    {
        if ($this->fields['use_ticket_calendar']) {
            $this->fields['calendars_id'] = $calendars_id;
        }
    }

    public function post_getEmpty()
    {
        $this->fields['number_time'] = 4;
        $this->fields['definition_time'] = 'hour';
    }

    /**
     * Get possibles keys and labels for the definition_time field
     *
     * @return array<string, string>
     *
     * @since 10.0.0
     */
    public static function getDefinitionTimeValues(): array
    {
        return [
            'minute' => _n('Minute', 'Minutes', Session::getPluralNumber()),
            'hour'   => _n('Hour', 'Hours', Session::getPluralNumber()),
            'day'    => _n('Day', 'Days', Session::getPluralNumber()),
            'month'  => _n('Month', 'Months', Session::getPluralNumber()),
        ];
    }

    /**
     * Get the matching label for a given key (definition_time field)
     *
     * @param string $value
     *
     * @return string
     *
     * @since 10.0.0
     */
    public static function getDefinitionTimeLabel(string $value): string
    {
        return self::getDefinitionTimeValues()[$value] ?? "";
    }

    /**
     * Get a level for a given action
     *
     * since 10.0
     *
     * @param mixed $nextaction
     *
     * @return false|LevelAgreementLevel
     * @used-by templates/components/itilobject/service_levels.html.twig
     */
    public function getLevelFromAction($nextaction)
    {
        if ($nextaction === false) {
            return false;
        }

        $pre  = static::$prefix;
        $nextlevel  = getItemForItemtype(static::$levelclass);
        if (!$nextlevel->getFromDB($nextaction->fields[$pre . 'levels_id'])) {
            return false;
        }

        return $nextlevel;
    }

    /**
     * Get then next levelagreement action for a given ticket and "LA" type
     *
     * since 10.0
     *
     * @param Ticket $ticket
     * @param SLM::TTO|SLM::TTR $type
     *
     * @return false|OlaLevel_Ticket|SlaLevel_Ticket
     * @used-by templates/components/itilobject/service_levels.html.twig
     */
    public function getNextActionForTicket(Ticket $ticket, int $type)
    {
        /** @var OlaLevel_Ticket|SlaLevel_Ticket $nextaction */
        $nextaction = getItemForItemtype(static::$levelticketclass);
        if (!$nextaction->getFromDBForTicket($ticket->fields["id"], $type)) {
            return false;
        }

        return $nextaction;
    }

    /**
     * Get all LevelAgreements related to the ticket, filtered by LevelAgreement type (SLM::TTR | SLM::TTO)
     *
     * @param int $tickets_id
     * @param int $type
     * @return false|iterable
     * @used-by templates/components/itilobject/service_levels.html.twig
     */
    public function getDataForTicket($tickets_id, $type)
    {
        global $DB;

        [, $field] = static::getFieldNames($type);

        $iterator = $DB->request([
            'SELECT'       => [static::getTable() . '.id'],
            'FROM'         => static::getTable(),
            'INNER JOIN'   => [
                'glpi_tickets' => [
                    'FKEY'   => [
                        static::getTable()   => 'id',
                        'glpi_tickets'       => $field,
                    ],
                ],
            ],
            'WHERE'        => ['glpi_tickets.id' => $tickets_id],
            'LIMIT'        => 1,
        ]);

        if (count($iterator)) {
            return self::getFromIter($iterator);
        }
        return false;
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
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'number',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'number_time',
            'name'               => _x('hour', 'Time'),
            'datatype'           => 'specific',
            'massiveaction'      => false,
            'nosearch'           => true,
            'additionalfields'   => ['definition_time'],
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'end_of_working_day',
            'name'               => __('End of working day'),
            'datatype'           => 'bool',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => static::getTable(),
            'field'              => 'type',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => 'glpi_slms',
            'field'              => 'name',
            'name'               => __('SLM'),
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
            'id'              => '80',
            'table'           => Entity::getTable(),
            'field'           => 'completename',
            'name'            => Entity::getTypeName(1),
            'massiveaction'   => false,
            'datatype'        => 'dropdown',
        ];

        $tab[] = [
            'id'            => '86',
            'table'         => static::getTable(),
            'field'         => 'is_recursive',
            'name'          => __('Child entities'),
            'datatype'      => 'bool',
            'massiveaction' => false,
        ];

        return $tab;
    }

    /**
     * Get delay (due time duration) in seconds for the current agreement
     *
     * The time to own or to resolve duration
     *
     * @return int own/resolution time (default 0)
     **/
    public function getTime()
    {
        if (isset($this->fields['id'])) {
            return match ($this->fields['definition_time']) {
                'minute' => $this->fields['number_time'] * MINUTE_TIMESTAMP,
                'hour'   => $this->fields['number_time'] * HOUR_TIMESTAMP,
                'day'    => $this->fields['number_time'] * DAY_TIMESTAMP,
                'month'  => $this->fields['number_time'] * MONTH_TIMESTAMP,
                default   => 0
            };
        }
        return 0;
    }

    /**
     * Elapsed time between two dates in seconds
     *
     * @param string $start start date formated 'Y-m-d H:i:s'
     * @param string $end end date formated 'Y-m-d H:i:s'
     *
     * @return int elapsed time in seconds
     **/
    public function getActiveTimeBetween($start, $end)
    {
        if ($end < $start) {
            return 0;
        }

        if (isset($this->fields['id'])) {
            $cal          = new Calendar();

            // Based on a calendar
            if ($this->fields['calendars_id'] > 0) {
                if ($cal->getFromDB($this->fields['calendars_id'])) {
                    return $cal->getActiveTimeBetween($start, $end);
                }
            } else { // No calendar
                $timestart = strtotime($start);
                $timeend   = strtotime($end);
                return ($timeend - $timestart);
            }
        }
        return 0;
    }

    /**
     * Get due date for current agreement
     *
     * @param string  $start_date        datetime start date ('Y-m-d H:i:s')
     * @param int $additional_delay  integer  additional delay to add or substract (for waiting time)
     *
     * @return string|null  due datetime 'Y-m-d H:i:s' (NULL if sla/ola not exists)
     **/
    public function computeDate($start_date, $additional_delay = 0)
    {
        if (isset($this->fields['id'])) {
            $delay = $this->getTime();
            // Based on a calendar
            if ($this->fields['calendars_id'] > 0) {
                $cal          = new Calendar();
                $work_in_days = ($this->fields['definition_time'] === 'day' || $this->fields['definition_time'] === 'month');

                if ($cal->getFromDB($this->fields['calendars_id']) && $cal->hasAWorkingDay()) {
                    return $cal->computeEndDate(
                        $start_date,
                        $delay,
                        (int) $additional_delay,
                        $work_in_days,
                        $this->fields['end_of_working_day']
                    );
                }
            }

            // No calendar defined or invalid calendar
            if ($this->fields['number_time'] >= 0) {
                $starttime = strtotime($start_date);
                $endtime   = $starttime + $delay + (int) $additional_delay;
                return date('Y-m-d H:i:s', $endtime);
            }
        }

        return null;
    }

    /**
     * Should calculation on this LevelAgreement target date be done using
     * the "work_in_day" parameter set to true ?
     *
     * @return bool
     */
    public function shouldUseWorkInDayMode(): bool
    {
        return
            $this->fields['definition_time'] === 'day'
            || $this->fields['definition_time'] === 'month'
        ;
    }

    /**
     * Get execution date of a level
     *
     * @param string  $start_date        start date
     * @param int $levels_id         sla/ola level id
     * @param int $additional_delay  additional delay to add or substract (for waiting time)
     *
     * @return string|null  execution date time (NULL if ola/sla not exists)
     **/
    public function computeExecutionDate($start_date, $levels_id, $additional_delay = 0)
    {
        if (isset($this->fields['id'])) {
            $level = getItemForItemtype(static::$levelclass);
            $fk = getForeignKeyFieldForItemType(static::class);

            if ($level->getFromDB($levels_id)) { // level exists
                if ((int) $level->fields[$fk] === (int) $this->fields['id']) { // correct level
                    $delay        = $this->getTime();

                    // Based on a calendar
                    if ($this->fields['calendars_id'] > 0) {
                        $cal = new Calendar();
                        if ($cal->getFromDB($this->fields['calendars_id']) && $cal->hasAWorkingDay()) {
                            // Take SLA into account
                            $date_with_sla = $cal->computeEndDate(
                                $start_date,
                                $delay,
                                0,
                                $this->shouldUseWorkInDayMode(),
                                $this->fields['end_of_working_day']
                            );

                            // Take waiting duration time into account
                            $date_with_waiting_time = $cal->computeEndDate(
                                $date_with_sla,
                                $additional_delay,
                            );

                            // Take current SLA escalation level into account
                            $date_with_sla_and_escalation_level = $cal->computeEndDate(
                                $date_with_waiting_time,
                                $level->fields['execution_time'],
                                0,
                                $level->shouldUseWorkInDayMode(),
                            );

                            return $date_with_sla_and_escalation_level;
                        }
                    }
                    // No calendar defined or invalid calendar
                    $delay    += $additional_delay + $level->fields['execution_time'];
                    $starttime = strtotime($start_date);
                    $endtime   = $starttime + $delay;
                    return date('Y-m-d H:i:s', $endtime);
                }
            }
        }
        return null;
    }

    /**
     * Get types
     *
     * @return array array of types
     **/
    public static function getTypes()
    {
        return [
            SLM::TTO => __('Time to own'),
            SLM::TTR => __('Time to resolve'),
        ];
    }

    /**
     * Get types name
     *
     * @param  int $type
     * @return string  name
     **/
    public static function getOneTypeName($type)
    {
        $types = self::getTypes();
        return $types[$type] ?? null;
    }

    /**
     * Get SLA types dropdown
     *
     * @param array $options
     *
     * @return string
     */
    public static function getTypeDropdown($options)
    {
        return Dropdown::showFromArray($options['name'] ?? 'type', self::getTypes(), $options);
    }

    public function prepareInputForAdd($input)
    {
        if (
            $input['definition_time'] !== 'day'
            && $input['definition_time'] !== 'month'
        ) {
            $input['end_of_working_day'] = 0;
        }

        // Copy calendar settings from SLM
        $slm = new SLM();
        if (array_key_exists('slms_id', $input) && $slm->getFromDB($input['slms_id'])) {
            $input['use_ticket_calendar'] = $slm->fields['use_ticket_calendar'];
            $input['calendars_id'] = $slm->fields['calendars_id'];
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (
            isset($input['definition_time']) && ($input['definition_time'] !== 'day'
            && $input['definition_time'] !== 'month')
        ) {
            $input['end_of_working_day'] = 0;
        }

        // Copy calendar settings from SLM
        $slm = new SLM();
        if (
            array_key_exists('slms_id', $input)
            && (int) $input['slms_id'] !== (int) $this->fields['slms_id']
            && $slm->getFromDB($input['slms_id'])
        ) {
            $input['use_ticket_calendar'] = $slm->fields['use_ticket_calendar'];
            $input['calendars_id'] = $slm->fields['calendars_id'];
        }

        return $input;
    }

    /**
     * Add a level to do for a ticket
     *
     * Add an entry in slalevels_tickets | olalevels_tickets table
     * The level is set by $levels_id parameter or the current level set in slalevels_id_ttr | olalevels_id_ttr (if set)
     *
     * @param Ticket  $ticket Ticket object
     * @param int $levels_id SlaLevel or OlaLevel ID
     *
     * @return void
     **/
    public function addLevelToDo(Ticket $ticket, $levels_id = 0)
    {
        $pre = static::$prefix;

        if (!$levels_id && isset($ticket->fields[$pre . 'levels_id_ttr'])) {
            $levels_id = $ticket->fields[$pre . "levels_id_ttr"];
        }

        if ($levels_id) {
            $toadd = [];

            // Compute start date
            if ($pre === "ola") {
                // OLA have their own start date which is set when the OLA is added to the ticket
                if (
                    (int) $this->fields['type'] === SLM::TTO
                    && $ticket->fields['ola_tto_begin_date'] !== null
                ) {
                    $date_field = "ola_tto_begin_date";
                } elseif (
                    (int) $this->fields['type'] === SLM::TTR
                    && $ticket->fields['ola_ttr_begin_date'] !== null
                ) {
                    $date_field = "ola_ttr_begin_date";
                } else {
                    // Fall back to default date in case the specific date fields
                    // are not set (which may be the case for tickets created
                    // before their addition)
                    $date_field = 'date';
                }
            } else {
                // SLA are based on the ticket opening date
                $date_field = 'date';
            }

            $date = $this->computeExecutionDate(
                $ticket->fields[$date_field],
                $levels_id,
                $ticket->fields[$pre . '_waiting_duration']
            );
            if ($date !== null) {
                $toadd['date']           = $date;
                $toadd[$pre . 'levels_id'] = $levels_id;
                $toadd['tickets_id']     = $ticket->fields["id"];
                $levelticketclass = static::$levelticketclass;
                if (
                    !countElementsInTable(
                        $levelticketclass::getTable(),
                        [
                            $pre . 'levels_id' => $levels_id,
                            'tickets_id'       => $ticket->fields["id"],
                        ]
                    )
                ) {
                    $levelticket = getItemForItemtype($levelticketclass);
                    $levelticket->add($toadd);
                }
            }
        }
    }

    /**
     * remove a level to do for a ticket
     *
     * @param Ticket $ticket object
     *
     * @return void
     **/
    public static function deleteLevelsToDo(Ticket $ticket)
    {
        global $DB;

        $ticketfield = static::$prefix . "levels_id_ttr";

        if ($ticket->fields[$ticketfield] > 0) {
            $levelticketclass = static::$levelticketclass;
            $iterator = $DB->request([
                'SELECT' => 'id',
                'FROM'   => $levelticketclass::getTable(),
                'WHERE'  => ['tickets_id' => $ticket->fields['id']],
            ]);

            foreach ($iterator as $data) {
                $levelticket = getItemForItemtype($levelticketclass);
                $levelticket->delete(['id' => $data['id']]);
            }
        }
    }

    public function cleanDBonPurge()
    {
        global $DB;

        // Clean levels
        $fk        = getForeignKeyFieldForItemType(static::class);
        $level     = getItemForItemtype(static::$levelclass);
        $level->deleteByCriteria([$fk => $this->getID()]);

        // Update tickets : clean SLA/OLA
        [, $laField] = static::getFieldNames($this->fields['type']);
        $iterator =  $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_tickets',
            'WHERE'  => [$laField => $this->fields['id']],
        ]);

        if (count($iterator)) {
            $ticket = new Ticket();
            foreach ($iterator as $data) {
                $ticket->deleteLevelAgreement(static::class, $data['id'], $this->fields['type']);
            }
        }

        Rule::cleanForItemAction($this);
    }

    public function post_clone($source, $history)
    {
        // Clone levels
        $classname = static::class;
        $fk        = getForeignKeyFieldForItemType($classname);
        $level     = getItemForItemtype(static::$levelclass);
        foreach ($level->find([$fk => $source->getID()]) as $data) {
            $level->getFromDB($data['id']);
            $level->clone([$fk => $this->getID()]);
        }
    }

    /**
     * Getter for the protected $levelclass static property
     *
     * @return class-string<LevelAgreementLevel>
     */
    public function getLevelClass(): string
    {
        return static::$levelclass;
    }

    /**
     * Getter for the protected $levelticketclass static property
     *
     * @return class-string<CommonDBTM>
     */
    public function getLevelTicketClass(): string
    {
        return static::$levelticketclass;
    }

    /**
     * Remove level of previously assigned level agreements for a given ticket
     *
     * @param int $tickets_id
     *
     * @return void
     */
    public function clearInvalidLevels(int $tickets_id): void
    {
        // CLear levels of others LA of the same type
        // e.g. if a new LA TTR was assigned, clear levels from others (= previous) LA TTR
        $level_ticket_class = $this->getLevelTicketClass();
        $level_ticket = getItemForItemtype($level_ticket_class);
        $level_class = $this->getLevelClass();
        $levels = $level_ticket->find([
            'tickets_id' => $tickets_id,
            [$level_class::getForeignKeyField() => ['!=', $this->getID()]],
            [
                $level_class::getForeignKeyField() => new QuerySubQuery([
                    'SELECT' => 'id',
                    'FROM' => $level_class::getTable(),
                    'WHERE' => [
                        static::getForeignKeyField() => new QuerySubQuery([
                            'SELECT' => 'id',
                            'FROM' => static::getTable(),
                            'WHERE' => ['type' => $this->fields['type']],
                        ]),
                    ],
                ]),
            ],
        ]);

        // Delete invalid levels
        foreach ($levels as $level) {
            $level_ticket->delete(['id' => $level['id']]);
        }
    }
}
