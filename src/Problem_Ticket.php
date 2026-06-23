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

class Problem_Ticket extends CommonITILObject_CommonITILObject
{
    // From CommonDBRelation
    public static $itemtype_1 = Problem::class;
    public static $items_id_1   = 'problems_id';

    public static $itemtype_2 = Ticket::class;
    public static $items_id_2   = 'tickets_id';

    public static function getTypeName($nb = 0)
    {
        return _n('Link Ticket/Problem', 'Links Ticket/Problem', $nb);
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'add_task':
                $task = new TicketTask();
                $field = Ticket::getForeignKeyField();

                $input = $ma->getInput();

                foreach ($ids as $id) {
                    if ($item->can($id, READ)) {
                        $input2 = [
                            $field              => $item->getID(),
                            'taskcategories_id' => $input['taskcategories_id'],
                            'actiontime'        => $input['actiontime'],
                            'content'           => $input['content'],
                        ];
                        if ($task->can(-1, CREATE, $input2)) {
                            if ($task->add($input2)) {
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
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                return;

            case 'solveticket':
                if (!$item instanceof Ticket) {
                    throw new InvalidArgumentException();
                }

                $input  = $ma->getInput();
                foreach ($ids as $id) {
                    if ($item->can($id, READ)) {
                        if ($item->canSolve()) {
                            $solution = new ITILSolution();
                            $added = $solution->add([
                                'itemtype'         => $item::class,
                                'items_id'         => $item->getID(),
                                'solutiontypes_id' => $input['solutiontypes_id'],
                                'content'          => $input['content'],
                            ]);

                            if ($added) {
                                $ma->itemDone($item::class, $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item::class, $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item::class, $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    } else {
                        $ma->itemDone($item::class, $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * Returns problems data for given ticket.
     * Returned data is usable by `Problem::showShort()` method.
     *
     * @param int $tickets_id
     *
     * @return array
     */
    private static function getTicketProblemsData($tickets_id): array
    {
        $ticket = new Ticket();
        $ticket->fields['id'] = $tickets_id;
        $iterator = self::getListForItem($ticket);

        $problems = [];
        foreach ($iterator as $data) {
            $problem = new Problem();
            $problem->getFromDB($data['id']);
            if ($problem->canViewItem()) {
                $problems[$data['id']] = $data;
            }
        }

        return $problems;
    }
}
