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

class Domain_Item extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = Domain::class;
    public static $items_id_1 = 'domains_id';

    public static $itemtype_2 = 'itemtype';
    public static $items_id_2 = 'items_id';

    public static function getTypeName($nb = 0)
    {
        return _n('Domain item', 'Domain items', $nb);
    }

    /**
     * @param CommonDBTM $item
     *
     * @return void
     */
    public static function cleanForItem(CommonDBTM $item)
    {
        $temp = new self();
        $temp->deleteByCriteria(
            ['itemtype' => $item->getType(),
                'items_id' => $item->getField('id'),
            ]
        );
    }

    /**
     * @param Domain $item
     *
     * @return int
     */
    public static function countForDomain(Domain $item)
    {
        $types = $item->getTypes();
        if (count($types) === 0) {
            return 0;
        }
        return countElementsInTable(
            'glpi_domains_items',
            [
                "domains_id"   => $item->getID(),
                "itemtype"     => $types,
            ]
        );
    }

    public static function countForItem(CommonDBTM $item)
    {
        if ($item instanceof DomainRelation) {
            $criteria = ['domainrelations_id' => $item->fields['id']];
        } else {
            $criteria = [
                'itemtype'  => $item::class,
                'items_id'  => $item->fields['id'],
            ];
        }

        return countElementsInTable(
            self::getTable(),
            $criteria
        );
    }

    /**
     * @param int $domains_id
     * @param int $items_id
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return bool
     */
    public function getFromDBbyDomainsAndItem($domains_id, $items_id, $itemtype)
    {
        $criteria = ['domains_id' => $domains_id];

        if (is_a($itemtype, DomainRelation::class, true)) {
            $criteria += ['domainrelations_id' => $items_id];
        } else {
            $criteria += [
                'itemtype'  => $itemtype,
                'items_id'  => $items_id,
            ];
        }

        return $this->getFromDBByCrit($criteria);
    }

    /**
     * @param array $values
     *
     * @return false|int
     */
    public function addItem($values)
    {
        return $this->add([
            'domains_id'         => $values['domains_id'],
            'items_id'           => $values['items_id'],
            'itemtype'           => $values['itemtype'],
            'domainrelations_id' => $values['domainrelations_id'],
        ]);
    }

    /**
     * @param int $domains_id
     * @param int $items_id
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return bool
     */
    public function deleteItemByDomainsAndItem($domains_id, $items_id, $itemtype)
    {
        if ($this->getFromDBbyDomainsAndItem($domains_id, $items_id, $itemtype)) {
            return $this->delete(['id' => $this->fields["id"]]);
        }
        return false;
    }

    /**
     * Get links between the given item and domains.
     *
     * @param CommonDBTM $item
     * @return DBmysqlIterator
     */
    public static function getForItem(CommonDBTM $item): DBmysqlIterator
    {
        global $DB;

        $criteria = [
            'SELECT'    => [
                'glpi_domains_items.id AS assocID',
                'glpi_domains_items.domainrelations_id',
                'glpi_domains_items.is_deleted',
                'glpi_domains_items.is_dynamic',
                'glpi_entities.id AS entity',
                'glpi_domains.name AS assocName',
                'glpi_domains.*',
                QueryFunction::groupConcat(
                    expression: Group_Item::getTable() . '.groups_id',
                    separator: ',',
                    alias: 'groups_id_tech',
                ),
            ],
            'FROM'      => self::getTable(),
            'LEFT JOIN' => [
                Domain::getTable()   => [
                    'ON'  => [
                        Domain::getTable()   => 'id',
                        self::getTable()     => 'domains_id',
                    ],
                ],
                Entity::getTable()   => [
                    'ON'  => [
                        Domain::getTable()   => 'entities_id',
                        Entity::getTable()   => 'id',
                    ],
                ],
                Group_Item::getTable() => [
                    'ON'  => [
                        Group_Item::getTable() => 'items_id',
                        Domain::getTable()       => 'id', [
                            'AND' => [
                                Group_Item::getTable() . '.itemtype' => Domain::class,
                                Group_Item::getTable() . '.type' => Group_Item::GROUP_TYPE_TECH,
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE'     => [],//to be filled
            'ORDER'     => 'assocName',
            'GROUPBY' => [
                'glpi_domains_items.id',
            ],
        ];

        if ($item instanceof DomainRelation) {
            $criteria['WHERE'] = ['glpi_domains_items.domainrelations_id' => $item->getID()];
        } else {
            $criteria['WHERE'] = [
                'glpi_domains_items.itemtype' => $item::class,
                'glpi_domains_items.items_id' => $item->getID(),
            ];
        }
        $criteria['WHERE'] += getEntitiesRestrictCriteria(Domain::getTable(), '', '', true);

        $criteria['WHERE']
            //deleted and dynamic domain_item are displayed from lock tab
            //non dynamic domain_item are always displayed
            += [
                'OR'  => [
                    'AND' => [
                        "glpi_domains_items.is_deleted" => 0,
                        "glpi_domains_items.is_dynamic" => 1,
                    ],
                    "glpi_domains_items.is_dynamic" => 0,
                ],
            ];

        return $DB->request($criteria);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => '2',
            'table'              => DomainRelation::getTable(),
            'field'              => 'name',
            'name'               => DomainRelation::getTypeName(),
            'datatype'           => 'itemlink',
            'itemlink_type'      => static::class,
        ];

        return $tab;
    }
}
