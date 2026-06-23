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

class Calendar_Holiday extends CommonDBRelation
{
    public $auto_message_on_action = false;

    // From CommonDBRelation
    public static $itemtype_1 = Calendar::class;
    public static $items_id_1 = 'calendars_id';
    public static $itemtype_2 = Holiday::class;
    public static $items_id_2 = 'holidays_id';

    public static $checkItem_2_Rights = self::DONT_CHECK_ITEM_RIGHTS;

    /**
     * @since 0.84
     **/
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function post_addItem()
    {
        $this->invalidateCalendarCache($this->fields['calendars_id']);

        parent::post_addItem();
    }

    public function post_updateItem($history = true)
    {
        if (in_array('calendars_id', $this->updates)) {
            $this->invalidateCalendarCache($this->oldvalues['calendars_id']);
        }

        $this->invalidateCalendarCache($this->fields['calendars_id']);

        parent::post_updateItem($history);
    }

    public function post_deleteFromDB()
    {
        $this->invalidateCalendarCache($this->fields['calendars_id']);

        parent::post_deleteFromDB();
    }

    /**
     * Return holidays related to given calendar.
     *
     * @param int $calendars_id
     *
     * @return array
     */
    public function getHolidaysForCalendar(int $calendars_id): array
    {
        global $DB, $GLPI_CACHE;

        $cache_key = $this->getCalendarHolidaysCacheKey($calendars_id);
        if (($holidays = $GLPI_CACHE->get($cache_key)) === null) {
            $table = self::getTable();
            $holidays_iterator = $DB->request(
                [
                    'SELECT'     => ['begin_date', 'end_date', 'is_perpetual'],
                    'FROM'       => Holiday::getTable(),
                    'INNER JOIN' => [
                        $table => [
                            'FKEY'   => [
                                $table => Holiday::getForeignKeyField(),
                                Holiday::getTable()          => 'id',
                            ],
                        ],
                    ],
                    'WHERE'      => [
                        self::getTableField(Calendar::getForeignKeyField()) => $calendars_id,
                    ],
                ]
            );
            $holidays = iterator_to_array($holidays_iterator);
            $GLPI_CACHE->set($cache_key, $holidays);
        }

        return $holidays;
    }

    /**
     * Invalidate cache for given holiday.
     *
     * @param int $holidays_id
     *
     * @return bool
     */
    public function invalidateHolidayCache(int $holidays_id): bool
    {
        global $DB;

        $success = true;

        $iterator = $DB->request(
            [
                'SELECT'     => [Calendar::getForeignKeyField()],
                'FROM'       => self::getTable(),
                'WHERE'      => [
                    Holiday::getForeignKeyField() => $holidays_id,
                ],
            ]
        );
        foreach ($iterator as $link) {
            $success = $success && $this->invalidateCalendarCache($link[Calendar::getForeignKeyField()]);
        }

        return $success;
    }

    /**
     * Get cache key of cache entry containing holidays of given calendar.
     *
     * @param int $calendars_id
     *
     * @return string
     */
    private function getCalendarHolidaysCacheKey(int $calendars_id): string
    {
        return sprintf('calendar-%s-holidays', $calendars_id);
    }

    /**
     * Invalidate holidays cache of given calendar.
     *
     * @param int $calendars_id
     *
     * @return bool
     */
    private function invalidateCalendarCache(int $calendars_id): bool
    {
        global $GLPI_CACHE;
        return $GLPI_CACHE->delete($this->getCalendarHolidaysCacheKey($calendars_id));
    }
}
