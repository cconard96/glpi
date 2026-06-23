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

class Item_Line extends CommonDBRelation
{
    public static $itemtype_1 = Line::class;
    public static $items_id_1 = 'lines_id';
    public static $itemtype_2 = 'itemtype';
    public static $items_id_2 = 'items_id';

    public static function getTypeName($nb = 0)
    {
        return _n('Line item', 'Line items', $nb);
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'MassiveAction:update';
        $forbidden[] = 'CommonDBConnexity:affect';
        $forbidden[] = 'CommonDBConnexity:unaffect';

        return $forbidden;
    }

    public static function getRelationMassiveActionsPeerForSubForm(MassiveAction $ma)
    {
        return match ($ma->getAction()) {
            'add', 'remove' => 1,
            'add_item', 'remove_item' => 2,
            default => 0,
        };
    }

    public static function getRelationMassiveActionsSpecificities()
    {
        global $CFG_GLPI;

        $specificities              = parent::getRelationMassiveActionsSpecificities();
        $specificities['itemtypes'] = $CFG_GLPI['line_types'];

        // Define normalized action for add_item and remove_item
        $specificities['normalized']['add'][]          = 'add_item';
        $specificities['normalized']['remove'][]       = 'remove_item';

        // Set the labels for add_item and remove_item
        $specificities['button_labels']['add_item']    = $specificities['button_labels']['add'];
        $specificities['button_labels']['remove_item'] = $specificities['button_labels']['remove'];

        return $specificities;
    }

    /**
     * Count the number of lines associated to an item through a simcard.
     *
     * @param CommonDBTM $item
     * @return int
     */
    protected static function countSimcardLinesForItem(CommonDBTM $item)
    {
        return countElementsInTable(Item_DeviceSimcard::getTable(), [
            'items_id' => $item->getID(),
            'itemtype' => $item->getType(),
            'NOT'   => [
                'lines_id' => 0,
            ],
        ]);
    }

    /**
     * Count the number of items associated to a line through a simcard.
     *
     * @param Line $line
     * @return int
     */
    protected static function countSimcardItemsForLine(Line $line)
    {
        return countElementsInTable(Item_DeviceSimcard::getTable(), [
            'lines_id' => $line->getID(),
        ]);
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }

    /**
     * Prepares input (for update and add)
     *
     * @param array<string, mixed> $input data used to update the item
     *
     * @return false|array<string, mixed> the modified $input array
     */
    private function prepareInput($input)
    {
        $error_detected = [];

        //check for requirements
        if (
            ($this->isNewItem() && (!isset($input['itemtype']) || empty($input['itemtype'])))
            || (isset($input['itemtype']) && empty($input['itemtype']))
        ) {
            $error_detected[] = __s('An item type is required');
        }
        if (
            ($this->isNewItem() && (!isset($input['items_id']) || empty($input['items_id'])))
            || (isset($input['items_id']) && empty($input['items_id']))
        ) {
            $error_detected[] = __s('An item is required');
        }
        if (
            ($this->isNewItem() && (!isset($input['lines_id']) || empty($input['lines_id'])))
            || (isset($input['lines_id']) && empty($input['lines_id']))
        ) {
            $error_detected[] = __s('A line is required');
        }

        if (count($error_detected)) {
            foreach ($error_detected as $error) {
                Session::addMessageAfterRedirect(
                    $error,
                    true,
                    ERROR
                );
            }
            return false;
        }

        return $input;
    }
}
