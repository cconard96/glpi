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

class Appliance_Item_Relation extends CommonDBRelation
{
    public static $itemtype_1 = Appliance_Item::class;
    public static $items_id_1 = 'appliances_items_id';
    //static public $take_entity_1 = false;

    public static $itemtype_2 = 'itemtype';
    public static $items_id_2 = 'items_id';
    //static public $take_entity_2 = true;

    public static function getTypeName($nb = 0)
    {
        return _nx('appliance', 'Relation', 'Relations', $nb);
    }

    /**
     * Get item types that can be linked to an appliance item
     *
     * @param bool $all Get all possible types or only allowed ones
     *
     * @return array
     */
    public static function getTypes($all = false): array
    {
        global $CFG_GLPI;

        $types = $CFG_GLPI['appliance_relation_types'];

        foreach ($types as $key => $type) {
            if (!class_exists($type)) {
                continue;
            }

            if ($all === false && !$type::canView()) {
                unset($types[$key]);
            }
        }
        return $types;
    }

    public static function canCreate(): bool
    {
        return Appliance_Item::canUpdate();
    }


    public function canCreateItem(): bool
    {
        $app_item = new Appliance_Item();
        $app_item->getFromDB($this->fields[Appliance_Item::getForeignKeyField()]);
        return $app_item->canUpdateItem();
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
            ($this->isNewItem() && (!isset($input[self::$items_id_1]) || empty($input[self::$items_id_1])))
            || (isset($input[self::$items_id_1]) && empty($input[self::$items_id_1]))
        ) {
            $error_detected[] = __s('An appliance item is required');
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

    /**
     * count number of appliance's items relations for a give item
     *
     * @param CommonDBTM $item the give item
     * @param array $extra_types_where additional criteria to pass to the count function
     *
     * @return int number of relations
     */
    public static function countForMainItem(CommonDBTM $item, $extra_types_where = [])
    {
        $types = self::getTypes();
        $clause = [];
        if (count($types)) {
            $clause = ['itemtype' => $types];
        } else {
            $clause = [new QueryExpression('true = false')];
        }
        $extra_types_where = array_merge(
            $extra_types_where,
            $clause
        );
        return parent::countForMainItem($item, $extra_types_where);
    }

    /**
     * return an array of relations for a given Appliance_Item's id
     *
     * @param int $appliances_items_id
     *
     * @return array array of string with icons and names
     */
    public static function getForApplianceItem(int $appliances_items_id = 0)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id', 'itemtype', 'items_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                Appliance_Item::getForeignKeyField() => $appliances_items_id,
            ],
        ]);

        $relations = [];
        foreach ($iterator as $row) {
            $itemtype = $row['itemtype'];
            $item = getItemForItemtype($itemtype);
            $item->getFromDB($row['items_id']);
            $relations[$row['id']] = "<i class='" . htmlescape($item->getIcon()) . "' title='" . htmlescape($item::getTypeName(1)) . "'></i>"
                        . "&nbsp;" . htmlescape($item::getTypeName(1))
                        . "&nbsp;-&nbsp;" . $item->getLink();
        }

        return $relations;
    }
}
