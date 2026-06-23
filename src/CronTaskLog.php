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

/**
 * CronTaskLog class
 **/
class CronTaskLog extends CommonDBChild
{
    public static $itemtype = CronTask::class;
    public static $items_id  = 'crontasks_id';

    // Prevent CronTaskLog entries from flooding the CronTask historical tab
    public static $logs_for_parent = false;

    // Class constant
    public const STATE_START = 0;
    public const STATE_RUN   = 1;
    public const STATE_STOP  = 2;
    public const STATE_ERROR = 3;

    /**
     * Clean old event for a task
     *
     * @param int $id   ID of the CronTask
     * @param int $days number of day to keep
     *
     * @return int number of events deleted
     **/
    public static function cleanOld($id, $days)
    {
        global $DB;

        $secs      = $days * DAY_TIMESTAMP;

        $result = $DB->delete(
            'glpi_crontasklogs',
            [
                'crontasks_id' => $id,
                new QueryExpression("UNIX_TIMESTAMP(" . $DB->quoteName("date") . ") < UNIX_TIMESTAMP()-$secs"),
            ]
        );

        return $result ? $DB->affectedRows() : 0;
    }
}
