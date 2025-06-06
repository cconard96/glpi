<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

/**
 * Item_TicketRecurrent Class
 *
 *  Relation between TicketRecurrents and Items
 **/
class Item_TicketRecurrent extends CommonItilObject_Item
{
    // From CommonDBRelation
    public static $itemtype_1          = 'TicketRecurrent';
    public static $items_id_1          = 'ticketrecurrents_id';

    public static $itemtype_2          = 'itemtype';
    public static $items_id_2          = 'items_id';
    public static $checkItem_2_Rights  = self::HAVE_VIEW_RIGHT_ON_ITEM;

    public static function getTypeName($nb = 0)
    {
        return _n('Ticket recurrent item', 'Ticket recurrent items', $nb);
    }

    /**
     * Print the HTML ajax associated item add
     *
     * @param TicketRecurrent $ticketrecurrent   object holding the item
     * @param array $options   array of possible options:
     *    - id                  : ID of the object holding the items
     *    - items_id            : array of elements (itemtype => array(id1, id2, id3, ...))
     *
     * @return void
     */
    public static function itemAddForm(CommonDBTM $ticketrecurrent, $options = [])
    {
        parent::displayItemAddForm($ticketrecurrent, $options);
    }
}
