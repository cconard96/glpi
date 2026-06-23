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

class Item_Rack extends CommonDBRelation
{
    public static $itemtype_1 = Rack::class;
    public static $items_id_1 = 'racks_id';
    public static $itemtype_2 = 'itemtype';
    public static $items_id_2 = 'items_id';
    public static $checkItem_2_Rights = self::DONT_CHECK_ITEM_RIGHTS;
    public static $mustBeAttached_1 = false; // FIXME It make no sense for a rack item to not be attached to a Rack.
    public static $mustBeAttached_2 = false; // FIXME It make no sense for a rack item to not be attached to an Item.

    public static function getTypeName($nb = 0)
    {
        return _n('Item', 'Item', $nb);
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'MassiveAction:update';
        $forbidden[] = 'CommonDBConnexity:affect';
        $forbidden[] = 'CommonDBConnexity:unaffect';

        return $forbidden;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'delete':
                $input = $ma->getInput();
                $item_rack = new Item_Rack();
                foreach ($ids as $id) {
                    if ($item->can($id, UPDATE, $input)) {
                        $relation_criteria = [
                            'itemtype' => $item->getType(),
                            'items_id' => $item->getID(),
                        ];
                        if (countElementsInTable(Item_Rack::getTable(), $relation_criteria) > 0) {
                            if ($item_rack->deleteByCriteria($relation_criteria)) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            // Item is not linked to a rack, not an error
                            $ma->itemDone($item->getType(), $id, MassiveAction::NO_ACTION);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function post_getEmpty()
    {
        $this->fields['bgcolor'] = '#69CEBA';
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

        $itemtype    = !$this->isNewItem() ? $this->fields['itemtype'] : null;
        $items_id    = !$this->isNewItem() ? $this->fields['items_id'] : null;
        $racks_id    = !$this->isNewItem() ? $this->fields['racks_id'] : null;
        $position    = !$this->isNewItem() ? $this->fields['position'] : null;
        $hpos        = !$this->isNewItem() ? $this->fields['hpos'] : null;
        $orientation = !$this->isNewItem() ? $this->fields['orientation'] : null;

        //check for requirements
        if (
            ($this->isNewItem() && (!isset($input['itemtype']) || empty($input['itemtype'])))
            || (isset($input['itemtype']) && empty($input['itemtype']))
        ) {
            $error_detected[] = __('An item type is required');
        }
        if (
            ($this->isNewItem() && (!isset($input['items_id']) || empty($input['items_id'])))
            || (isset($input['items_id']) && empty($input['items_id']))
        ) {
            $error_detected[] = __('An item is required');
        }
        if (
            ($this->isNewItem() && (!isset($input['racks_id']) || empty($input['racks_id'])))
            || (isset($input['racks_id']) && empty($input['racks_id']))
        ) {
            $error_detected[] = __('A rack is required');
        }
        if (
            ($this->isNewItem() && (!isset($input['position']) || empty($input['position'])))
            || (isset($input['position']) && empty($input['position']))
        ) {
            $error_detected[] = __('A position is required');
        }

        if (isset($input['itemtype'])) {
            $itemtype = $input['itemtype'];
        }
        if (isset($input['items_id'])) {
            $items_id = $input['items_id'];
        }
        if (isset($input['racks_id'])) {
            $racks_id = $input['racks_id'];
        }
        if (isset($input['position'])) {
            $position = $input['position'];
        }
        if (isset($input['hpos'])) {
            $hpos = $input['hpos'];
        }
        if (isset($input['orientation'])) {
            $orientation = $input['orientation'];
        }

        if (!count($error_detected)) {
            //check if required U are available at position
            $rack = new Rack();
            $rack->getFromDB($racks_id);

            if ($this->isNewItem()) {
                $filled = $rack->getFilled();
            } else {
                // If object is existing, exclude current state from used positions
                $filled = $rack->getFilled($this->fields['itemtype'], $this->fields['items_id']);
            }

            $item = getItemForItemtype($itemtype);
            $item->getFromDB($items_id);
            $model = $item->getModelClassInstance();
            $modelsfield = $model::getForeignKeyField();

            $required_units = 1;
            $width          = 1;
            $depth          = 1;
            if ($model->getFromDB($item->fields[$modelsfield])) {
                if ($model->fields['required_units'] > 1) {
                    $required_units = $model->fields['required_units'];
                }
                if ($model->fields['is_half_rack'] == 1) {
                    if ($this->isNewItem() && !isset($input['hpos']) || $input['hpos'] == 0) {
                        $error_detected[] = __('You must define an horizontal position for this item');
                    }
                    $width = 0.5;
                }
                if ($model->fields['depth'] != 1) {
                    if ($this->isNewItem() && !isset($input['orientation'])) {
                        $error_detected[] = __('You must define an orientation for this item');
                    }
                    $depth = $model->fields['depth'];
                }
            }

            /**
             * @var int $position
             * @var int $required_units
             */
            if (
                $position > $rack->fields['number_units']
                || $position + $required_units  > $rack->fields['number_units'] + 1
            ) {
                $error_detected[] = __('Item is out of rack bounds');
            } elseif (!count($error_detected)) {
                $i = 0;
                while ($i < $required_units) {
                    $current_position = $position + $i;
                    if (isset($filled[$current_position])) {
                        $content_filled = $filled[$current_position];

                        if ($hpos == Rack::POS_NONE || $hpos == Rack::POS_LEFT) {
                            $d = 0;
                            while ($d / 4 < $depth) {
                                $pos = ($orientation == Rack::REAR) ? 3 - $d : $d;
                                $val = 1;
                                if (isset($content_filled[Rack::POS_LEFT][$pos]) && $content_filled[Rack::POS_LEFT][$pos] != 0) {
                                    $error_detected[] = __('Not enough space available to place item');
                                    break 2;
                                }
                                ++$d;
                            }
                        }

                        if ($hpos == Rack::POS_NONE || $hpos == Rack::POS_RIGHT) {
                            $d = 0;
                            while ($d / 4 < $depth) {
                                $pos = ($orientation == Rack::REAR) ? 3 - $d : $d;
                                $val = 1;
                                if (isset($content_filled[Rack::POS_RIGHT][$pos]) && $content_filled[Rack::POS_RIGHT][$pos] != 0) {
                                    $error_detected[] = __('Not enough space available to place item');
                                    break 2;
                                }
                                ++$d;
                            }
                        }
                    }
                    ++$i;
                }
            }
        }

        if (count($error_detected)) {
            foreach ($error_detected as $error) {
                Session::addMessageAfterRedirect(
                    htmlescape($error),
                    true,
                    ERROR
                );
            }
            return false;
        }

        return $input;
    }

    protected function computeFriendlyName()
    {
        $rack = new Rack();
        $rack->getFromDB($this->fields['racks_id']);
        $name = sprintf(
            __('Item for rack "%1$s"'),
            $rack->getName()
        );

        return $name;
    }
}
