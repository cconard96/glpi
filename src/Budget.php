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
use Glpi\DBAL\QuerySubQuery;
use Glpi\DBAL\QueryUnion;
use Glpi\Features\Clonable;

/**
 * Budget class
 */
class Budget extends CommonDropdown
{
    /** @use Clonable<static> */
    use Clonable;

    // From CommonDBTM
    public $dohistory           = true;

    public static $rightname           = 'budget';
    protected $usenotepad       = true;

    public $can_be_translated = false;

    public function getCloneRelations(): array
    {
        return [
            Document_Item::class,
            KnowbaseItem_Item::class,
            ManualLink::class,
        ];
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Budget', 'Budgets', $nb);
    }

    public static function getLogServiceName(): string
    {
        return 'management';
    }

    public function prepareInputForAdd($input)
    {

        if (isset($input["id"]) && ($input["id"] > 0)) {
            $input["_oldID"] = $input["id"];
        }
        unset($input['id']);
        unset($input['withtemplate']);

        return $input;
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
            'table'              => $this->getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'number',
        ];

        $tab[] = [
            'id'                 => '19',
            'table'              => $this->getTable(),
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '121',
            'table'              => $this->getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => 'glpi_budgettypes',
            'field'              => 'name',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => $this->getTable(),
            'field'              => 'begin_date',
            'name'               => __('Start date'),
            'datatype'           => 'date',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'end_date',
            'name'               => __('End date'),
            'datatype'           => 'date',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => $this->getTable(),
            'field'              => 'value',
            'name'               => _x('price', 'Value'),
            'datatype'           => 'decimal',
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => $this->getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '50',
            'table'              => $this->getTable(),
            'field'              => 'template_name',
            'name'               => __('Template name'),
            'datatype'           => 'text',
            'massiveaction'      => false,
            'nosearch'           => true,
            'nodisplay'          => true,
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '86',
            'table'              => $this->getTable(),
            'field'              => 'is_recursive',
            'name'               => __('Child entities'),
            'datatype'           => 'bool',
        ];

        // add objectlock search options
        $tab = array_merge($tab, ObjectLock::rawSearchOptionsToAdd(get_class($this)));
        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab = array_merge($tab, Notepad::rawSearchOptionsToAdd());

        return $tab;
    }

    /**
     * Get the SQL union query to get the list of items on a budget and the associated costs
     * @param bool $entity_restrict Whether to restrict the items to the current entity
     * @return QueryUnion
     */
    private function getItemListCriteria(bool $entity_restrict = true): QueryUnion
    {
        global $DB;

        $budgets_id = $this->fields['id'];

        // Get a list of possible itemtypes first, so we can filter them by read permissions
        $iterator = $DB->request([
            'SELECT'          => 'itemtype',
            'DISTINCT'        => true,
            'FROM'            => 'glpi_infocoms',
            'WHERE'           => [
                'budgets_id'   => $budgets_id,
                'NOT'          => ['itemtype' => [ConsumableItem::class, CartridgeItem::class, Software::class]],
            ],
            'ORDER'           => 'itemtype',
        ]);
        $itemtypes = [
            // These types shouldn't be in the glpi_infocoms table, but have their costs elsewhere
            Contract::class, Ticket::class, Problem::class, Change::class, Project::class,
        ];
        foreach ($iterator as $row) {
            $itemtypes[] = $row['itemtype'];
        }
        $infocom_itemtypes = [];
        $other_cost_tables = [
            Contract::class => ContractCost::getTable(),
            Ticket::class => TicketCost::getTable(),
            Problem::class => ProblemCost::getTable(),
            Change::class => ChangeCost::getTable(),
            Project::class => ProjectCost::getTable(),
        ];
        foreach ($itemtypes as $itemtype) {
            if (in_array($itemtype, $infocom_itemtypes)) {
                continue; // prevent duplicates
            }

            if (!is_a($itemtype, CommonDBTM::class, true) || !$itemtype::canView()) {
                continue;
            }

            if (!in_array($itemtype, [Contract::class, Ticket::class, Problem::class, Change::class, Project::class], true)) {
                $infocom_itemtypes[] = $itemtype;
            }
        }

        $queries = [];

        foreach ($infocom_itemtypes as $itemtype) {
            $item_table = $itemtype::getTable();
            $criteria = [
                'SELECT'       => [
                    new QueryExpression($DB::quoteValue($itemtype), '_itemtype'),
                    "$item_table.id",
                    "$item_table.entities_id",

                ],
                'FROM'         => 'glpi_infocoms',
                'INNER JOIN'   => [
                    $item_table => [
                        'ON' => [
                            $item_table => 'id',
                            'glpi_infocoms'   => 'items_id',
                        ],
                    ],
                ],
                'WHERE'        => [
                    'glpi_infocoms.itemtype'            => $itemtype,
                    'glpi_infocoms.budgets_id'          => $budgets_id,
                ],
                'ORDERBY'      => [
                    $item_table . '.entities_id',
                ],
            ];
            if ($entity_restrict) {
                $criteria['WHERE'] += getEntitiesRestrictCriteria($item_table);
            }

            /** @var CommonDBTM $item */
            $item = new $itemtype();

            $criteria['SELECT'][] = $item->maybeDeleted() ? "$item_table.is_deleted" : new QueryExpression('0', 'is_deleted');
            $criteria['SELECT'][] = $item->isField('serial') ? "$item_table.serial" : new QueryExpression('NULL', 'serial');
            $criteria['SELECT'][] = $item->isField('otherserial') ? "$item_table.otherserial" : new QueryExpression('NULL', 'otherserial');
            if ($item instanceof Item_Devices) {
                $criteria['SELECT'][] = $item_table . '.' . $item::$items_id_2 . ' AS devices_id';
            } else {
                $criteria['SELECT'][] = new QueryExpression('NULL', 'devices_id');
            }
            $criteria['SELECT'][] = 'glpi_infocoms.value';
            if ($item->maybeTemplate()) {
                $criteria['WHERE'][$item_table . '.is_template'] = 0;
            }

            $queries[] = new QuerySubQuery($criteria);
        }

        foreach ($other_cost_tables as $itemtype => $cost_table) {
            $item_table = $itemtype::getTable();
            $item = new $itemtype();
            $criteria = [
                'SELECT' => [
                    new QueryExpression($DB::quoteValue($itemtype), '_itemtype'),
                    $item_table => ['id', 'entities_id'],
                    new QueryExpression('NULL', 'serial'),
                    new QueryExpression('NULL', 'otherserial'),
                    new QueryExpression('NULL', 'devices_id'),
                ],
                'FROM' => $cost_table,
                'INNER JOIN' => [
                    $item_table => [
                        'ON' => [
                            $item_table => 'id',
                            $cost_table => $itemtype::getForeignKeyField(),
                        ],
                    ],
                ],
                'WHERE' => [
                    $cost_table . '.budgets_id' => $budgets_id,
                ],
                'GROUPBY'      => [
                    $item_table . '.id',
                    $item_table . '.entities_id',
                ],
                'ORDERBY'      => [
                    $item_table . '.entities_id',
                    $item_table . '.name',
                ],
            ];
            if ($entity_restrict) {
                $criteria['WHERE'] += getEntitiesRestrictCriteria($item_table);
            }
            $criteria['ORDERBY'][] = $item_table . '.name';

            $criteria['SELECT'][] = match ($itemtype) {
                Ticket::class, Problem::class, Change::class => QueryFunction::sum(
                    expression: new QueryExpression($DB::quoteName("$cost_table.actiontime") . " * " . $DB::quoteName("$cost_table.cost_time") . "/" . HOUR_TIMESTAMP . "
                                      + " . $DB::quoteName("$cost_table.cost_fixed") . "
                                      + " . $DB::quoteName("$cost_table.cost_material")),
                    alias: 'value'
                ),
                default => QueryFunction::sum(expression: "{$cost_table}.cost", alias: 'value'),
            };
            $criteria['SELECT'][] = $item->maybeDeleted() ? "$item_table.is_deleted" : '0 AS is_deleted';

            if ($item->maybeTemplate()) {
                $criteria['WHERE'][$item_table . '.is_template'] = 0;
            }

            $queries[] = new QuerySubQuery($criteria);
        }

        return new QueryUnion($queries);
    }
}
