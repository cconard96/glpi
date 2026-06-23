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
 * ContractCost Class
 * @since 0.84
 */
class ContractCost extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype = Contract::class;
    public static $items_id = 'contracts_id';
    public $dohistory       = true;


    public static function getTypeName($nb = 0)
    {
        return _n('Cost', 'Costs', $nb);
    }

    public static function getIcon()
    {
        return Infocom::getIcon();
    }

    public function prepareInputForAdd($input)
    {

        if (
            !empty($input['begin_date'])
            && (empty($input['end_date'])
              || ($input['end_date'] === 'NULL')
              || ($input['end_date'] < $input['begin_date']))
        ) {
            $input['end_date'] = $input['begin_date'];
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {

        if (
            !empty($input['begin_date'])
            && (empty($input['end_date'])
              || ($input['end_date'] === 'NULL')
              || ($input['end_date'] < $input['begin_date']))
        ) {
            $input['end_date'] = $input['begin_date'];
        }

        return parent::prepareInputForUpdate($input);
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
            'name'               => __('Title'),
            'searchtype'         => 'contains',
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
            'id'                 => '16',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => static::getTable(),
            'field'              => 'begin_date',
            'name'               => __('Begin date'),
            'datatype'           => 'datetime',
        ];

        $tab[] = [
            'id'                 => '10',
            'table'              => static::getTable(),
            'field'              => 'end_date',
            'name'               => __('End date'),
            'datatype'           => 'datetime',
        ];

        $tab[] = [
            'id'                 => '14',
            'table'              => static::getTable(),
            'field'              => 'cost',
            'name'               => _n('Cost', 'Costs', 1),
            'datatype'           => 'decimal',
        ];

        $tab[] = [
            'id'                 => '18',
            'table'              => 'glpi_budgets',
            'field'              => 'name',
            'name'               => Budget::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    /**
     * Get last datas for a contract
     *
     * @param int $contracts_id ID of the contract
     * @return array
     **/
    public function getLastCostForContract($contracts_id)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'   => static::getTable(),
            'WHERE'  => ['contracts_id' => $contracts_id],
            'ORDER'  => ['end_date DESC', 'id DESC'],
        ]);
        if ($result = $iterator->current()) {
            return $result;
        }

        return [];
    }
}
