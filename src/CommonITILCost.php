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
use Glpi\DBAL\QueryFunction;

/**
 * CommonITILCost Class
 *
 * @since 0.85
 **/
abstract class CommonITILCost extends CommonDBChild
{
    public $dohistory        = true;


    public static function getTypeName($nb = 0)
    {
        return _n('Cost', 'Costs', $nb);
    }


    /**
     * @return class-string<CommonDBTM>
     */
    public function getItilObjectItemType()
    {
        return str_replace('Cost', '', static::class);
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
            'id'                 => '11',
            'table'              => static::getTable(),
            'field'              => 'actiontime',
            'name'               => __('Duration'),
            'datatype'           => 'timestamp',
        ];

        $tab[] = [
            'id'                 => '14',
            'table'              => static::getTable(),
            'field'              => 'cost_time',
            'name'               => __('Time cost'),
            'datatype'           => 'decimal',
        ];

        $tab[] = [
            'id'                 => '15',
            'table'              => static::getTable(),
            'field'              => 'cost_fixed',
            'name'               => __('Fixed cost'),
            'datatype'           => 'decimal',
        ];

        $tab[] = [
            'id'                 => '19',
            'table'              => static::getTable(),
            'field'              => 'cost_material',
            'name'               => __('Material cost'),
            'datatype'           => 'decimal',
        ];

        $tab[] = [
            'id'                 => '18',
            'table'              => Budget::getTable(),
            'field'              => 'name',
            'name'               => Budget::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => Entity::getTable(),
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    public static function rawSearchOptionsToAdd(): array
    {
        global $DB;

        $tab = [];

        $tab[] = [
            'id'                 => 'cost',
            'name'               => _n('Cost', 'Costs', 1),
        ];

        $tab[] = [
            'id'                 => '48',
            'table'              => static::getTable(),
            'field'              => 'totalcost',
            'name'               => __('Total cost'),
            'datatype'           => 'decimal',
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'computation'        => new QueryExpression(
                '(' . QueryFunction::sum(
                    expression: new QueryExpression($DB::quoteName('TABLE.actiontime') . ' * ' . $DB::quoteName('TABLE.cost_time') . ' / '
                        . HOUR_TIMESTAMP . ' + ' . $DB::quoteName('TABLE.cost_fixed') . ' + ' . $DB::quoteName('TABLE.cost_material'))
                ) . ' / ' . QueryFunction::count('TABLE.id') . ') * '
                    . QueryFunction::count(expression: 'TABLE.id', distinct: true)
            ),
            'nometa'             => true, // cannot GROUP_CONCAT a SUM
        ];

        $tab[] = [
            'id'                 => '42',
            'table'              => static::getTable(),
            'field'              => 'cost_time',
            'name'               => __('Time cost'),
            'datatype'           => 'decimal',
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'computation'        => new QueryExpression(
                '(' . QueryFunction::sum(
                    expression: new QueryExpression($DB::quoteName('TABLE.actiontime') . ' * ' . $DB::quoteName('TABLE.cost_time') . ' / '
                        . HOUR_TIMESTAMP)
                ) . ' / ' . QueryFunction::count('TABLE.id') . ') * '
                    . QueryFunction::count(expression: 'TABLE.id', distinct: true)
            ),
            'nometa'             => true, // cannot GROUP_CONCAT a SUM
        ];

        $tab[] = [
            'id'                 => '49',
            'table'              => static::getTable(),
            'field'              => 'actiontime',
            'name'               => sprintf(__('%1$s - %2$s'), _n('Cost', 'Costs', 1), __('Duration')),
            'datatype'           => 'timestamp',
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '43',
            'table'              => static::getTable(),
            'field'              => 'cost_fixed',
            'name'               => __('Fixed cost'),
            'datatype'           => 'decimal',
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'computation'        => new QueryExpression(
                '(' . QueryFunction::sum(
                    expression: 'TABLE.cost_fixed'
                ) . ' / ' . QueryFunction::count('TABLE.id') . ') * '
                    . QueryFunction::count(expression: 'TABLE.id', distinct: true)
            ),
            'nometa'             => true, // cannot GROUP_CONCAT a SUM
        ];

        $tab[] = [
            'id'                 => '44',
            'table'              => static::getTable(),
            'field'              => 'cost_material',
            'name'               => __('Material cost'),
            'datatype'           => 'decimal',
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'computation'        => new QueryExpression(
                '(' . QueryFunction::sum(
                    expression: 'TABLE.cost_material'
                ) . ' / ' . QueryFunction::count('TABLE.id') . ') * '
                    . QueryFunction::count(expression: 'TABLE.id', distinct: true)
            ),
            'nometa'             => true, // cannot GROUP_CONCAT a SUM
        ];

        return $tab;
    }

    /**
     * Get total action time used on costs for an item
     *
     * @param int $items_id ID of the item
     *
     * @return int
     **/
    public function getTotalActionTimeForItem($items_id)
    {
        global $DB;

        $result = $DB->request([
            'SELECT' => ['SUM' => 'actiontime AS sumtime'],
            'FROM'   => static::getTable(),
            'WHERE'  => [static::$items_id => $items_id],
        ])->current();

        return (int) $result['sumtime'];
    }

    /**
     * Get last datas for an item
     *
     * @param int $items_id ID of the item
     *
     * @return array
     **/
    public function getLastCostForItem($items_id)
    {
        global $DB;

        $result = $DB->request([
            'FROM'   => static::getTable(),
            'WHERE'  => [
                static::$items_id => $items_id,
            ],
            'ORDER'  => [
                'end_date DESC',
                'id DESC',
            ],
        ])->current();
        return $result;
    }


    /**
     * Get costs summary values
     *
     * @param string $type type
     * @param int $ID ID of the ticket
     *
     * @return array of costs and actiontime
     * @used-by src/NotificationTargetCommonITILObject.php
     **/
    public static function getCostsSummary($type, $ID)
    {
        global $DB;

        $result = $DB->request(
            [
                'SELECT'    => [
                    'actiontime',
                    'cost_time',
                    'cost_fixed',
                    'cost_material',
                ],
                'FROM'      => getTableForItemType($type),
                'WHERE'     => [
                    static::$items_id      => $ID,
                ],
                'ORDER'     => [
                    'begin_date',
                ],
            ]
        );

        $tab = ['totalcost'   => 0,
            'actiontime'   => 0,
            'costfixed'    => 0,
            'costtime'     => 0,
            'costmaterial' => 0,
        ];

        foreach ($result as $data) {
            $tab['actiontime']   += $data['actiontime'];
            $tab['costfixed']    += $data['cost_fixed'];
            $tab['costmaterial'] += $data['cost_material'];
            $tab['costtime']     += ($data['actiontime'] * $data['cost_time'] / HOUR_TIMESTAMP);
            $tab['totalcost']    +=  self::computeTotalCost(
                $data['actiontime'],
                $data['cost_time'],
                $data['cost_fixed'],
                $data['cost_material']
            );
        }
        foreach ($tab as $key => $val) {
            $tab[$key] = Html::formatNumber($val);
        }
        return $tab;
    }


    /**
     * Computer total cost of a item
     *
     * @param float $actiontime actiontime
     * @param float $cost_time time cost
     * @param float $cost_fixed fixed cost
     * @param float $cost_material material cost
     * @param bool $edit used for edit of computation ? (true by default)
     *
     * @return string total cost formatted string
     **/
    public static function computeTotalCost($actiontime, $cost_time, $cost_fixed, $cost_material, $edit = true)
    {
        $cost = ($actiontime * $cost_time / HOUR_TIMESTAMP) + $cost_fixed + $cost_material;
        return Html::formatNumber($cost, $edit);
    }
}
