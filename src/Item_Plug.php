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

class Item_Plug extends CommonDBRelation
{
    public static $itemtype_1 = 'itemtype';
    public static $items_id_1 = 'items_id';
    public static $itemtype_2 = Plug::class;
    public static $items_id_2 = 'plugs_id';
    public static $checkItem_1_Rights = self::DONT_CHECK_ITEM_RIGHTS;
    public static $mustBeAttached_1      = false;
    public static $mustBeAttached_2      = false;

    public static function getTypeName($nb = 0)
    {
        return _n('Plug', 'Plugs', $nb);
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
     * @param array $input Input data
     *
     * @return false|array
     */
    private function prepareInput(array $input): false|array
    {
        // Check plugs_id requirement
        if (
            $this->isNewItem()
            && (!isset($input['plugs_id']) || $input['plugs_id'] <= 0)
        ) {
            Session::addMessageAfterRedirect(
                __s('A plug must be selected'),
                true,
                ERROR
            );
            return false;
        }

        // Check number_plugs requirement
        if (
            ($this->isNewItem() || isset($input['number_plugs']))
            && (!isset($input['number_plugs']) || $input['number_plugs'] === '' || $input['number_plugs'] < 1)
        ) {
            Session::addMessageAfterRedirect(
                __s('A number of plugs is required'),
                true,
                ERROR
            );
            return false;
        }

        return $input;
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'CommonDBConnexity:affect';
        $forbidden[] = 'CommonDBConnexity:unaffect';
        return $forbidden;
    }
}
