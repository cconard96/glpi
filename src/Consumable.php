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

use Glpi\Event;
use Glpi\Features\Clonable;

//!  Consumable Class
/**
 * This class is used to manage the consumables.
 * @see ConsumableItem
 * @author Julien Dombre
 **/
class Consumable extends CommonDBChild
{
    /** @use Clonable<static> */
    use Clonable;

    // From CommonDBTM
    protected static $forward_entity_to = ['Infocom'];
    public $no_form_page                = true;

    public static $rightname                   = 'consumable';

    // From CommonDBChild
    public static $itemtype = ConsumableItem::class;
    public static $items_id             = 'consumableitems_id';

    public function getCloneRelations(): array
    {
        return [
            Infocom::class,
        ];
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'ObjectLock:unlock';
        $forbidden[] = 'add_note';
        $forbidden[] = 'add_transfer_list';

        // Despite using the Clonable trait, the 'clone' option was not available
        // in the massive actions defined by the old Consumable::showForConsumableItem()
        // method.
        // To keep things consistent, clone is blacklisted here.
        $forbidden[] = 'clone';

        return $forbidden;
    }

    public static function getNameField()
    {
        return 'id';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Consumable', 'Consumables', $nb);
    }

    public function prepareInputForAdd($input)
    {
        $item = new ConsumableItem();
        if ($item->getFromDB($input["consumableitems_id"])) {
            return ["consumableitems_id" => $item->fields["id"],
                "entities_id"        => $item->getEntityID(),
                "date_in"            => date("Y-m-d"),
            ];
        }
        return [];
    }

    public function post_addItem()
    {
        // inherit infocom
        $infocoms = Infocom::getItemsAssociatedTo(ConsumableItem::getType(), $this->fields[ConsumableItem::getForeignKeyField()]);
        if (count($infocoms)) {
            $infocom = reset($infocoms);
            $infocom->clone([
                'itemtype'  => self::getType(),
                'items_id'  => $this->getID(),
            ]);
        }

        parent::post_addItem();
    }

    /**
     * send back to stock
     *
     * @param array $input Array of item fields. Only the ID field is used here.
     * @param bool $history Not used
     *
     * @return bool
     */
    public function backToStock(array $input, $history = true)
    {
        global $DB;

        $result = $DB->update(
            static::getTable(),
            [
                'date_out' => 'NULL',
            ],
            [
                'id' => $input['id'],
            ]
        );
        if ($result) {
            return true;
        }
        return false;
    }

    public function getPreAdditionalInfosForName()
    {
        $ci = new ConsumableItem();
        if ($ci->getFromDB($this->fields['consumableitems_id'])) {
            return $ci->getName();
        }
        return '';
    }

    /**
     * UnLink a consumable linked to a printer
     *
     * UnLink the consumable identified by $ID
     *
     * @param int $ID       consumable identifier
     * @param string  $itemtype itemtype of who we give the consumable
     * @param int $items_id ID of the item giving the consumable
     *
     * @return bool
     **/
    public function out($ID, $itemtype = '', $items_id = 0)
    {
        global $DB;

        if (
            !empty($itemtype)
            && ($items_id > 0)
        ) {
            $result = $DB->update(
                static::getTable(),
                [
                    'date_out'  => date('Y-m-d'),
                    'itemtype'  => $itemtype,
                    'items_id'  => $items_id,
                ],
                [
                    'id' => $ID,
                ]
            );
            if ($result) {
                return true;
            }
        }
        return false;
    }

    public static function getMassiveActionsForItemtype(
        array &$actions,
        $itemtype,
        $is_deleted = false,
        ?CommonDBTM $checkitem = null
    ) {
        // Special actions only for self
        if ($itemtype !== static::class) {
            return;
        }

        $action_prefix = self::getType() . MassiveAction::CLASS_ACTION_SEPARATOR;
        $actions[$action_prefix . 'backtostock'] = __s('Back to stock');
        $actions[$action_prefix . 'give'] = _sx('button', 'Give');
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        /** @var Consumable $item */
        switch ($ma->getAction()) {
            case 'backtostock':
                foreach ($ids as $id) {
                    if ($item->can($id, UPDATE)) {
                        if ($item->backToStock(["id" => $id])) {
                            $ma->itemDone($item::class, $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item::class, $id, MassiveAction::ACTION_KO);
                            $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                        }
                    } else {
                        $ma->itemDone($item::class, $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                return;
            case 'give':
                $input = $ma->getInput();
                if (
                    ($input["give_items_id"] > 0)
                    && !empty($input['give_itemtype'])
                ) {
                    foreach ($ids as $key) {
                        if ($item->can($key, UPDATE)) {
                            if ($item->out($key, $input['give_itemtype'], $input["give_items_id"])) {
                                $ma->itemDone($item::class, $key, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item::class, $key, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item::class, $key, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    }
                    Event::log(
                        $item->fields['consumableitems_id'],
                        "consumableitems",
                        5,
                        "inventory",
                        //TRANS: %s is the user login
                        sprintf(__('%s gives a consumable'), $_SESSION["glpiname"])
                    );
                } else {
                    $ma->itemDone($item::class, $ids, MassiveAction::ACTION_KO);
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * count how many consumable for the consumable item $tID
     *
     * @param int $tID consumable item identifier.
     *
     * @return int number of consumable counted.
     **/
    public static function getTotalNumber($tID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => 'glpi_consumables',
            'WHERE'  => ['consumableitems_id' => $tID],
        ])->current();
        return (int) $result['cpt'];
    }

    /**
     * count how many old consumable for the consumable item $tID
     *
     * @param int $tID consumable item identifier.
     *
     * @return int number of old consumable counted.
     **/
    public static function getOldNumber($tID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => 'glpi_consumables',
            'WHERE'  => [
                'consumableitems_id' => $tID,
                'NOT'                => ['date_out' => null],
            ],
        ])->current();
        return (int) $result['cpt'];
    }

    /**
     * count how many consumable unused for the consumable item $tID
     *
     * @param int $tID consumable item identifier.
     *
     * @return int number of consumable unused counted.
     **/
    public static function getUnusedNumber($tID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => 'glpi_consumables',
            'WHERE'  => [
                'consumableitems_id' => $tID,
                'date_out'           => null,
            ],
        ])->current();
        return(int) $result['cpt'];
    }

    /**
     * The desired stock level
     *
     * This is used when the alarm threshold is reached to know how many to order.
     * @param int $tID Consumable item ID
     * @return int
     */
    public static function getStockTarget(int $tID): int
    {
        global $DB;

        $it = $DB->request([
            'SELECT'  => ['stock_target'],
            'FROM'   => ConsumableItem::getTable(),
            'WHERE'  => [
                'id'  => $tID,
            ],
        ]);
        if ($it->count()) {
            return $it->current()['stock_target'];
        }
        return 0;
    }

    /**
     * The lower threshold for the stock amount before an alarm is triggered
     *
     * @param int $tID Consumable item ID
     * @return int
     */
    public static function getAlarmThreshold(int $tID): int
    {
        global $DB;

        $it = $DB->request([
            'SELECT'  => ['alarm_threshold'],
            'FROM'   => ConsumableItem::getTable(),
            'WHERE'  => [
                'id'  => $tID,
            ],
        ]);
        if ($it->count()) {
            return $it->current()['alarm_threshold'];
        }
        return 0;
    }

    /**
     * Get the consumable count HTML array for a defined consumable type
     *
     * @param int $tID             consumable item identifier.
     * @param int $alarm_threshold threshold alarm value.
     * @param bool $nohtml          Return value without HTML tags.
     *                                 The return value will anyway be a safe HTML string.
     *
     * @return string to display
     **/
    public static function getCount($tID, $alarm_threshold, $nohtml = false)
    {
        // Get total
        $total = self::getTotalNumber($tID);

        if ($total !== 0) {
            $unused = self::getUnusedNumber($tID);
            $old    = self::getOldNumber($tID);

            $highlight = "";
            if ($unused <= $alarm_threshold) {
                $highlight = "class='tab_bg_1_2'";
            }
            //TRANS: For consumable. %1$d is total number, %2$d is unused number, %3$d is old number
            $tmptxt = sprintf(__('Total: %1$d, New: %2$d, Used: %3$d'), $total, $unused, $old);
            if ($nohtml) {
                $out = htmlescape($tmptxt);
            } else {
                $out = "<div $highlight>" . htmlescape($tmptxt) . "</div>";
            }
        } else {
            if ($nohtml) {
                $out = __s('No consumable');
            } else {
                $out = "<div class='tab_bg_1_2'><i>" . __s('No consumable') . "</i></div>";
            }
        }
        return $out;
    }

    /**
     * Check if a Consumable is New (not used, in stock)
     *
     * @param int $cID consumable ID.
     *
     * @return bool
     **/
    public static function isNew($cID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => 'glpi_consumables',
            'WHERE'  => [
                'id'        => $cID,
                'date_out'  => null,
            ],
        ])->current();
        return $result['cpt'] === 1;
    }

    /**
     * Check if a consumable is Old (used, not in stock)
     *
     * @param int $cID consumable ID.
     *
     * @return bool
     **/
    public static function isOld($cID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => 'glpi_consumables',
            'WHERE'  => [
                'id'     => $cID,
                'NOT'   => ['date_out' => null],
            ],
        ])->current();
        return $result['cpt'] === 1;
    }

    /**
     * Get the localized string for the status of a consumable
     *
     * @param int $cID consumable ID.
     *
     * @return string
     **/
    public static function getStatus($cID)
    {
        if (self::isNew($cID)) {
            return _nx('consumable', 'New', 'New', 1);
        } elseif (self::isOld($cID)) {
            return _nx('consumable', 'Used', 'Used', 1);
        }
        return '';
    }

    /**
     * @param ConsumableItem $item
     *
     * @return int
     **/
    public static function countForConsumableItem(ConsumableItem $item)
    {
        return countElementsInTable(['glpi_consumables'], ['glpi_consumables.consumableitems_id' => $item->getField('id')]);
    }

    /**
     * @param User $item
     *
     * @return int
     **/
    public static function countForUser(User $item)
    {
        return countElementsInTable(['glpi_consumables'], [
            'glpi_consumables.itemtype' => 'User',
            'glpi_consumables.items_id' => $item->getField('id'),
            'NOT' => ['glpi_consumables.date_out' => 'NULL'],
        ]);
    }

    public function getRights($interface = 'central')
    {
        return (new ConsumableItem())->getRights($interface);
    }

    public static function convertFiltersValuesToSqlCriteria(array $filters = []): array
    {
        $sql_filters = [];

        $like_filters = [
            'id'        => 'glpi_consumables.id',
            'itemname'  => 'glpi_consumableitems.name',
            'ref'       => 'glpi_consumableitems.ref',
            'date_in'   => 'glpi_consumables.date_in',
            'date_out'  => 'glpi_consumables.date_out',
        ];
        foreach ($like_filters as $filter_key => $filter_field) {
            if (($filters[$filter_key] ?? "") !== '') {
                $sql_filters[$filter_field] = ['LIKE', '%' . $filters[$filter_key] . '%'];
            }
        }

        return $sql_filters;
    }

    public function rawSearchOptions()
    {
        $options = parent::rawSearchOptions();

        $options[] = [
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'number',
        ];

        $options[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'date_out',
            'name'               => _n('State', 'States', 1),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific',
        ];

        $options[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'date_in',
            'name'               => __('Add date'),
            'massiveaction'      => false,
            'datatype'           => 'date',
        ];

        $options[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'date_out',
            'name'               => __('Use date'),
            'massiveaction'      => false,
            'datatype'           => 'date',
        ];

        $options[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'items_id',
            'name'               => __('Given to'),
            'massiveaction'      => false,
            'additionalfields'   => ['itemtype'],
            'datatype'           => 'specific',
            'searchtype'         => ['equals', 'notequals'],
        ];

        $infocom_label = Infocom::getTypeName();
        $options[] = [
            'id'                 => '7',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => $infocom_label,
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific',
            'nosort'             => 'true',
        ];

        $options[] = [
            'id'                 => '8',
            'table'              => ConsumableItem::getTable(),
            'field'              => 'name',
            'name'               => ConsumableItem::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'searchtype'         => ['equals', 'notequals'],
        ];

        return $options;
    }
}
