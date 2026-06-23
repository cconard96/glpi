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

/**
 * Contract_Item Class
 *
 * Relation between Contracts and Items
 **/
class Contract_Item extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = Contract::class;
    public static $items_id_1 = 'contracts_id';

    public static $itemtype_2 = 'itemtype';
    public static $items_id_2 = 'items_id';

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function canCreateItem(): bool
    {
        // Try to load the contract
        $contract = $this->getConnexityItem(static::$itemtype_1, static::$items_id_1);
        if ($contract === false) {
            return false;
        }

        // Don't create a Contract_Item on contract that is alreay max used
        // Was previously done (until 0.83.*) by Contract_Item::can()
        if (
            ($contract->fields['max_links_allowed'] > 0)
            && (countElementsInTable(
                static::getTable(),
                ['contracts_id' => $this->input['contracts_id']]
            )
                >= $contract->fields['max_links_allowed'])
        ) {
            return false;
        }

        return parent::canCreateItem();
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Link Contract/Item', 'Links Contract/Item', $nb);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'number',
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'items_id',
            'name'               => __('Associated item ID'),
            'massiveaction'      => false,
            'datatype'           => 'specific',
            'additionalfields'   => ['itemtype'],
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'massiveaction'      => false,
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'contract_types',
        ];

        return $tab;
    }

    /**
     * @since 0.84
     *
     * @param int $contract_id   contract ID
     * @param int $entities_id   entity ID
     *
     * @return array of items linked to contracts
     **/
    public static function getItemsForContract($contract_id, $entities_id)
    {
        $items = [];

        $types_iterator = self::getDistinctTypes($contract_id);

        foreach ($types_iterator as $type_row) {
            $itemtype = $type_row['itemtype'];
            if (!getItemForItemtype($itemtype)) {
                continue;
            }

            $iterator = self::getTypeItems($contract_id, $itemtype);
            foreach ($iterator as $objdata) {
                $items[$itemtype][$objdata['id']] = $objdata;
            }
        }

        return $items;
    }

    public static function getRelationMassiveActionsSpecificities()
    {
        global $CFG_GLPI;

        $specificities              = parent::getRelationMassiveActionsSpecificities();
        $specificities['itemtypes'] = $CFG_GLPI['contract_types'];

        return $specificities;
    }
}
