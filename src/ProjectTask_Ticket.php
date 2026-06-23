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

use Glpi\DBAL\QueryFunction;

/**
 * ProjectTask_Ticket Class
 *
 * Relation between ProjectTasks and Tickets
 *
 * @since 0.85
 **/
class ProjectTask_Ticket extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = ProjectTask::class;
    public static $items_id_1   = 'projecttasks_id';

    public static $itemtype_2 = Ticket::class;
    public static $items_id_2   = 'tickets_id';

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function prepareInputForAdd($input)
    {
        if (
            countElementsInTable(
                static::getTable(),
                [
                    static::$items_id_1 => $input[static::$items_id_1] ?? 0,
                    static::$items_id_2 => $input[static::$items_id_2] ?? 0,
                ]
            ) > 0
        ) {
            Session::addMessageAfterRedirect(__s('Relation already exists.'), false, ERROR);
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Link Ticket/Project task', 'Links Ticket/Project task', $nb);
    }

    /**
     * Get total duration of tickets linked to a project task
     *
     * @param int $projecttasks_id ID of the project task
     *
     * @return int total actiontime
     **/
    public static function getTicketsTotalActionTime($projecttasks_id)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT'    => [
                QueryFunction::sum(
                    expression: 'glpi_tickets.actiontime',
                    alias: 'duration'
                ),
            ],
            'FROM'         => self::getTable(),
            'INNER JOIN'   => [
                'glpi_tickets' => [
                    'FKEY'   => [
                        self::getTable()  => 'tickets_id',
                        'glpi_tickets'    => 'id',
                    ],
                ],
            ],
            'WHERE'        => ['projecttasks_id' => $projecttasks_id],
        ]);

        return count($iterator) ? $iterator->current()['duration'] : 0;
    }
}
