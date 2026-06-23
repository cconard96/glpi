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

use Glpi\Features\Clonable;

/**
 * Cartridge class.
 * This class is used to manage printer cartridges.
 * @see CartridgeItem
 * @author Julien Dombre
 **/
class Cartridge extends CommonDBRelation
{
    /** @use Clonable<static> */
    use Clonable;

    // From CommonDBTM
    protected static $forward_entity_to = ['Infocom'];
    public $dohistory                   = true;
    public $no_form_page                = true;

    public static $rightname = 'cartridge';

    public static $itemtype_1 = CartridgeItem::class;
    public static $items_id_1 = 'cartridgeitems_id';

    public static $itemtype_2 = Printer::class;
    public static $items_id_2 = 'printers_id';
    public static $mustBeAttached_2 = false;

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
        return $forbidden;
    }

    public static function getNameField()
    {
        return 'id';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Cartridge', 'Cartridges', $nb);
    }

    public function prepareInputForAdd($input)
    {
        $item = static::getItemFromArray(CartridgeItem::class, CartridgeItem::getForeignKeyField(), $input);
        if ($item === false) {
            return false;
        }

        return [
            "cartridgeitems_id" => $item->fields["id"],
            "entities_id"       => $item->getEntityID(),
            "date_in"           => date("Y-m-d"),
        ];
    }

    public function post_addItem()
    {
        // inherit infocom
        $infocoms = Infocom::getItemsAssociatedTo(CartridgeItem::class, $this->fields[CartridgeItem::getForeignKeyField()]);
        if (count($infocoms)) {
            $infocom = reset($infocoms);
            $infocom->clone([
                'itemtype'  => self::class,
                'items_id'  => $this->getID(),
            ]);
        }

        parent::post_addItem();
    }

    public function post_updateItem($history = true)
    {
        if (in_array('pages', $this->updates, true)) {
            $printer = new Printer();
            if (
                $printer->getFromDB($this->fields['printers_id'])
                && (($this->fields['pages'] > $printer->getField('last_pages_counter'))
                    || ($this->oldvalues['pages'] == $printer->getField('last_pages_counter')))
            ) {
                $printer->update([
                    'id' => $printer->getID(),
                    'last_pages_counter' => $this->fields['pages'],
                ]);
            }
        }
        parent::post_updateItem($history);
    }

    public function getPreAdditionalInfosForName()
    {
        $ci = new CartridgeItem();
        if ($ci->getFromDB($this->fields['cartridgeitems_id'])) {
            return $ci->getName();
        }
        return '';
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        /** @var Cartridge $item */
        switch ($ma->getAction()) {
            case 'uninstall':
                foreach ($ids as $key) {
                    if ($item->can($key, UPDATE)) {
                        if ($item->uninstall($key)) {
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
                return;

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

            case 'updatepages':
                $input = $ma->getInput();
                if (isset($input['pages'])) {
                    foreach ($ids as $key) {
                        if ($item->can($key, UPDATE)) {
                            if (
                                $item->update(['id' => $key,
                                    'pages' => $input['pages'],
                                ])
                            ) {
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
                } else {
                    $ma->itemDone($item::class, $ids, MassiveAction::ACTION_KO);
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * Send the cartridge back to stock.
     *
     * @since 0.85 (before name was restore)
     * @param array   $input
     * @param bool $history
     * @return bool
     */
    public function backToStock(array $input, $history = true)
    {
        global $DB;

        $result = $DB->update(
            static::getTable(),
            [
                'date_out'     => 'NULL',
                'date_use'     => 'NULL',
                'printers_id'  => 0,
            ],
            [
                'id' => $input['id'],
            ]
        );
        return $result && ($DB->affectedRows() > 0);
    }

    // SPECIFIC FUNCTIONS

    /**
     * Link a cartridge to a printer.
     *
     * Link the first unused cartridge of type $Tid to the printer $pID.
     *
     * @param int $tID ID of the cartridge
     * @param int $pID : ID of the printer
     *
     * @return bool True if successful
     **/
    public function install($pID, $tID)
    {
        global $DB;

        // Get first unused cartridge
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => static::getTable(),
            'WHERE'  => [
                'cartridgeitems_id'  => $tID,
                'date_use'           => null,
            ],
            'LIMIT'  => 1,
        ]);

        if (count($iterator)) {
            $result = $iterator->current();
            $cID = $result['id'];
            // Update cartridge taking care of multiple insertion
            $result = $DB->update(
                static::getTable(),
                [
                    'date_use'     => date('Y-m-d'),
                    'printers_id'  => $pID,
                ],
                [
                    'id'        => $cID,
                    'date_use'  => null,
                ]
            );
            if ($result && ($DB->affectedRows() > 0)) {
                $changes = [
                    '0',
                    '',
                    __('Installing a cartridge'),
                ];
                Log::history($pID, 'Printer', $changes, 0, Log::HISTORY_LOG_SIMPLE_MESSAGE);
                return true;
            }
        } else {
            Session::addMessageAfterRedirect(__s('No free cartridge'), false, ERROR);
        }
        return false;
    }

    /**
     * Unlink a cartridge from a printer by cartridge ID.
     *
     * @param int $ID ID of the cartridge
     *
     * @return bool
     **/
    public function uninstall($ID)
    {
        global $DB;

        if ($this->getFromDB($ID)) {
            $printer = new Printer();
            $toadd   = [];
            if ($printer->getFromDB($this->getField("printers_id"))) {
                $toadd['pages'] = $printer->fields['last_pages_counter'];
            }

            $result = $DB->update(
                static::getTable(),
                [
                    'date_out'  => date('Y-m-d'),
                ] + $toadd,
                [
                    'id'  => $ID,
                ]
            );

            if (
                $result
                && ($DB->affectedRows() > 0)
            ) {
                $changes = [
                    '0',
                    '',
                    __('Uninstalling a cartridge'),
                ];
                Log::history(
                    $this->getField("printers_id"),
                    'Printer',
                    $changes,
                    0,
                    Log::HISTORY_LOG_SIMPLE_MESSAGE
                );

                return true;
            }
        }
        return false;
    }

    /**
     * Print the cartridge count HTML array for the cartridge item $tID
     *
     * @param int         $tID      ID of the cartridge item
     * @param int         $alarm_threshold Alarm threshold value
     * @param int|bool $nohtml          True if the return value should be without HTML tags (default 0/false).
     *                                         The return value will anyway be a safe HTML string.
     *
     * @return string String to display
     **/
    public static function getCount($tID, $alarm_threshold, $nohtml = 0)
    {
        // Get total
        $total = self::getTotalNumber($tID);
        $out   = "";
        if ($total !== 0) {
            $unused     = self::getUnusedNumber($tID);
            $used       = self::getUsedNumber($tID);
            $old        = self::getOldNumber($tID);
            $highlight  = $unused <= $alarm_threshold;

            $counts = [
                'new' => [
                    'label' => _nx('cartridge', 'New', 'New', $unused),
                    'value' => $unused,
                ],
                'used' => [
                    'label' => _nx('cartridge', 'Used', 'Used', $used),
                    'value' => $used,
                ],
                'worn' => [
                    'label' => _nx('cartridge', 'Worn', 'Worn', $old),
                    'value' => $old,
                ],
                'total' => [
                    'label' => __('Total'),
                    'value' => $total,
                ],
            ];

            if (!$nohtml) {
            } else {
                //TRANS : for display cartridges count : %1$d is the total number,
                //        %2$d the new one, %3$d the used one, %4$d worn one
                $out .= htmlescape(
                    sprintf(
                        __('Total: %1$d (%2$d new, %3$d used, %4$d worn)'),
                        $total,
                        $unused,
                        $used,
                        $old
                    )
                );
            }
        } else {
            if (!$nohtml) {
                $out .= "<div class='bg-danger-lt fst-italic'>" . __s('No cartridge') . "</div>";
            } else {
                $out .= __s('No cartridge');
            }
        }
        return $out;
    }

    /**
     * Print the cartridge count HTML array for the printer $pID
     *
     * @since 0.85
     *
     * @param int         $pID    ID of the printer
     * @param int|bool $nohtml True if the return value should be without HTML tags (default 0/false).
     *                                The return value will anyway be a safe HTML string.
     *
     * @return string String to display
     **/
    public static function getCountForPrinter($pID, $nohtml = 0)
    {
        // Get total
        $total = self::getTotalNumberForPrinter($pID);
        $out   = "";
        if ($total !== 0) {
            $used       = self::getUsedNumberForPrinter($pID);
            $old        = self::getOldNumberForPrinter($pID);
            $highlight  = $used === 0;

            $counts = [
                'used' => [
                    'label' => _nx('cartridge', 'Used', 'Used', $used),
                    'value' => $used,
                ],
                'worn' => [
                    'label' => _nx('cartridge', 'Worn', 'Worn', $old),
                    'value' => $old,
                ],
                'total' => [
                    'label' => __('Total'),
                    'value' => $total,
                ],
            ];

            if (!$nohtml) {
            } else {
                //TRANS : for display cartridges count : %1$d is the total number,
                //        %2$d the used one, %3$d the worn one
                $out .= htmlescape(sprintf(__('Total: %1$d (%2$d used, %3$d worn)'), $total, $used, $old));
            }
        } else {
            if (!$nohtml) {
                $out .= "<div class='bg-danger-lt fst-italic'>" . __s('No cartridge') . "</div>";
            } else {
                $out .= __s('No cartridge');
            }
        }
        return $out;
    }

    /**
     * Count the total number of cartridges for the cartridge item $tID.
     *
     * @param int $tID ID of cartridge item.
     *
     * @return int Number of cartridges counted.
     **/
    public static function getTotalNumber($tID)
    {
        global $DB;

        $row = $DB->request([
            'FROM'   => self::getTable(),
            'COUNT'  => 'cpt',
            'WHERE'  => ['cartridgeitems_id' => $tID],
        ])->current();
        return $row['cpt'];
    }

    /**
     * Count the number of cartridges used for the printer $pID
     *
     * @since 0.85
     *
     * @param int $pID ID of the printer.
     *
     * @return int Number of cartridges counted.
     **/
    public static function getTotalNumberForPrinter($pID)
    {
        global $DB;

        $row = $DB->request([
            'FROM'   => self::getTable(),
            'COUNT'  => 'cpt',
            'WHERE'  => ['printers_id' => $pID],
        ])->current();
        return (int) $row['cpt'];
    }

    /**
     * Count the number of used cartridges for the cartridge item $tID.
     *
     * @param int $tID ID of the cartridge item.
     *
     * @return int Number of used cartridges counted.
     **/
    public static function getUsedNumber($tID)
    {
        global $DB;

        $row = $DB->request([
            'SELECT' => ['id'],
            'COUNT'  => 'cpt',
            'FROM'   => 'glpi_cartridges',
            'WHERE'  => [
                'cartridgeitems_id'  => $tID,
                'date_out'           => null,
                'NOT'                => [
                    'date_use'  => null,
                ],
            ],
        ])->current();
        return (int) $row['cpt'];
    }

    /**
     * Count the number of used cartridges used for the printer $pID.
     *
     * @since 0.85
     *
     * @param int $pID ID of the printer.
     *
     * @return int Number of used cartridge counted.
     **/
    public static function getUsedNumberForPrinter($pID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'printers_id'  => $pID,
                'date_out'     => null,
                'NOT'          => ['date_use' => null],
            ],
        ])->current();
        return $result['cpt'];
    }

    /**
     * Count the number of old cartridges for the cartridge item $tID.
     *
     * @param int $tID ID of the cartridge item.
     *
     * @return int Number of old cartridges counted.
     **/
    public static function getOldNumber($tID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'cartridgeitems_id'  => $tID,
                'NOT'                => ['date_out' => null],
            ],
        ])->current();
        return $result['cpt'];
    }

    /**
     * count how many old cartbridge for theprinter $pID
     *
     * @since 0.85
     *
     * @param int $pID printer identifier.
     *
     * @return int : number of old cartridge counted.
     **/
    public static function getOldNumberForPrinter($pID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'printers_id'  => $pID,
                'NOT'          => ['date_out' => null],
            ],
        ])->current();
        return $result['cpt'];
    }

    /**
     * count how many cartridge unused for the cartridge item $tID
     *
     * @param int $tID cartridge item identifier.
     *
     * @return int : number of cartridge unused counted.
     **/
    public static function getUnusedNumber($tID)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'cartridgeitems_id'  => $tID,
                'date_use'           => null,
            ],
        ])->current();
        return $result['cpt'];
    }

    /**
     * The desired stock level
     *
     * This is used when the alarm threshold is reached to know how many to order.
     * @param int $tID Cartridge item ID
     * @return int
     */
    public static function getStockTarget(int $tID): int
    {
        global $DB;

        $it = $DB->request([
            'SELECT'  => ['stock_target'],
            'FROM'   => CartridgeItem::getTable(),
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
     * @param int $tID Cartridge item ID
     * @return int
     */
    public static function getAlarmThreshold(int $tID): int
    {
        global $DB;

        $it = $DB->request([
            'SELECT'  => ['alarm_threshold'],
            'FROM'   => CartridgeItem::getTable(),
            'WHERE'  => [
                'id'  => $tID,
            ],
        ]);
        return $it->count() ? $it->current()['alarm_threshold'] : 0;
    }

    /**
     * Get the translated value for the status of a cartridge based on the use and out date (if any).
     *
     * @param string $date_use  Date of use (May be null or empty)
     * @param string $date_out  Date of delete (May be null or empty)
     *
     * @return string : Translated value for the cartridge status.
     **/
    public static function getStatus($date_use, $date_out)
    {
        if (empty($date_use)) {
            return _nx('cartridge', 'New', 'New', 1);
        }
        if (empty($date_out)) {
            return _nx('cartridge', 'Used', 'Used', 1);
        }
        return _nx('cartridge', 'Worn', 'Worn', 1);
    }

    /**
     * Count the number of cartridges associated with the given cartridge item.
     * @param CartridgeItem $item CartridgeItem object
     * @return int
     */
    public static function countForCartridgeItem(CartridgeItem $item)
    {
        return countElementsInTable(['glpi_cartridges'], ['glpi_cartridges.cartridgeitems_id' => $item->getField('id')]);
    }

    /**
     * Count the number of cartridges associated with the given printer.
     * @param Printer $item Printer object
     * @return int
     */
    public static function countForPrinter(Printer $item)
    {
        return countElementsInTable(['glpi_cartridges'], ['glpi_cartridges.printers_id' => $item->getField('id')]);
    }

    public function getRights($interface = 'central')
    {
        return (new CartridgeItem())->getRights($interface);
    }
}
