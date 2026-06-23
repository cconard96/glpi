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
 *  Class KnowbaseItem_Item
 *
 *  @author Johan Cwiklinski <jcwiklinski@teclib.com>
 *
 *  @since 9.2
 */
class KnowbaseItem_Item extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = KnowbaseItem::class;
    public static $items_id_1          = 'knowbaseitems_id';
    public static $itemtype_2          = 'itemtype';
    public static $items_id_2          = 'items_id';
    public static $checkItem_2_Rights  = self::HAVE_VIEW_RIGHT_ON_ITEM;

    // From CommonDBTM
    public $dohistory          = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Knowledge base item', 'Knowledge base items', $nb);
    }

    /**
     * Displays linked dropdowns to add linked items
     *
     * @param CommonDBTM $item Item instance
     * @param string     $name Field name
     * @param array<class-string<CommonDBTM>, array<int, int>> $used Already used items
     *
     * @return string
     * @used-by 'templates/tools/kb/knowbaseitem_item.html.twig'
     */
    public static function dropdownAllTypes(CommonDBTM $item, $name, $used = [])
    {
        global $CFG_GLPI;

        $onlyglobal = 0;
        $entity_restrict = -1;
        $checkright = true;

        return Dropdown::showSelectItemFromItemtypes([
            'items_id_name'   => $name,
            'entity_restrict' => $entity_restrict,
            'itemtypes'       => $CFG_GLPI['kb_types'],
            'onlyglobal'      => $onlyglobal,
            'checkright'      => $checkright,
            'used'            => $used,
        ]);
    }

    /**
     * Retrieve items for a knowbase item
     *
     * @param CommonDBTM $item      CommonDBTM object
     * @param int    $start     first line to retrieve (default 0)
     * @param int    $limit     max number of line to retrive (0 for all) (default 0)
     * @param bool    $used      whether to retrieve data for "used" records
     *
     * @return array of linked items
     **/
    public static function getItems(CommonDBTM $item, $start = 0, $limit = 0, $used = false)
    {
        global $DB;

        $criteria = [
            'FROM'      => ['glpi_knowbaseitems_items'],
            'FIELDS'    => ['glpi_knowbaseitems_items' => '*'],
            'ORDER'     => ['itemtype', 'items_id DESC'],
            'GROUPBY'   => [
                'glpi_knowbaseitems_items.id',
                'glpi_knowbaseitems_items.knowbaseitems_id',
                'glpi_knowbaseitems_items.itemtype',
                'glpi_knowbaseitems_items.items_id',
                'glpi_knowbaseitems_items.date_creation',
                'glpi_knowbaseitems_items.date_mod',
            ],
        ];

        if ($item::class === KnowbaseItem::class) {
            $criteria['WHERE'][] = [
                'glpi_knowbaseitems_items.knowbaseitems_id' => $item->getID(),
            ];
        } else {
            $criteria = array_merge_recursive($criteria, self::getVisibilityCriteriaForItem($item));
            $criteria['WHERE'][] = [
                'glpi_knowbaseitems_items.items_id' => $item->getID(),
                'glpi_knowbaseitems_items.itemtype' => $item::class,
            ];
        }

        if ($limit) {
            $criteria['START'] = (int) $start;
            $criteria['LIMIT'] = (int) $limit;
        }

        $linked_items = [];
        $results = $DB->request($criteria);
        foreach ($results as $data) {
            if ($used === false) {
                $linked_items[] = $data;
            } else {
                if ($item::class === KnowbaseItem::class) {
                    $linked_items[$data['itemtype']][$data['items_id']] = $data['items_id'];
                } else {
                    $linked_items[$data['knowbaseitems_id']] = $data['knowbaseitems_id'];
                }
            }
        }
        return $linked_items;
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public static function getMassiveActionsForItemtype(
        array &$actions,
        $itemtype,
        $is_deleted = false,
        ?CommonDBTM $checkitem = null
    ) {

        $kb_item = new KnowbaseItem();
        $kb_item->getEmpty();
        if ($kb_item->canViewItem()) {
            $action_prefix = self::class . MassiveAction::CLASS_ACTION_SEPARATOR;

            $actions[$action_prefix . 'add']
            = "<i class='" . htmlescape(self::getIcon()) . "'></i>"
              . _sx('button', 'Link knowledgebase article');
        }

        parent::getMassiveActionsForItemtype($actions, $itemtype, $is_deleted, $checkitem);
    }

    private static function getCountForItem(CommonDBTM $item): int
    {
        if ($item::class === KnowbaseItem::class) {
            $criteria['WHERE'] = [
                'glpi_knowbaseitems_items.knowbaseitems_id' => $item->getID(),
            ];
        } else {
            $criteria = self::getVisibilityCriteriaForItem($item);
            $criteria['WHERE'][] = [
                'glpi_knowbaseitems_items.itemtype' => $item::class,
                'glpi_knowbaseitems_items.items_id' => $item->getId(),
            ];
        }

        return countElementsInTable('glpi_knowbaseitems_items', $criteria);
    }

    /**
     * Return visibility criteria that must be used to find KB items related to given item.
     */
    private static function getVisibilityCriteriaForItem(CommonDBTM $item): array
    {
        $criteria = array_merge_recursive(
            [
                'INNER JOIN' => [
                    'glpi_knowbaseitems' => [
                        'ON' => [
                            'glpi_knowbaseitems_items' => 'knowbaseitems_id',
                            'glpi_knowbaseitems'       => 'id',
                        ],
                    ],
                ],
            ],
            KnowbaseItem::getVisibilityCriteria()
        );

        $item_table = $item::getTable();
        $entity_criteria = getEntitiesRestrictCriteria($item_table, '', '', $item->maybeRecursive());
        if (!empty($entity_criteria)) {
            $criteria['INNER JOIN'][$item_table] = [
                'ON' => [
                    'glpi_knowbaseitems_items' => 'items_id',
                    $item_table                => 'id',
                ],
            ];
            $criteria['WHERE'][] = $entity_criteria;
        }

        return $criteria;
    }
}
