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

class PDU_Rack extends CommonDBRelation
{
    public static $itemtype_1 = Rack::class;
    public static $items_id_1 = 'racks_id';
    public static $itemtype_2 = PDU::class;
    public static $items_id_2 = 'pdus_id';
    public static $checkItem_1_Rights = self::DONT_CHECK_ITEM_RIGHTS;
    public static $mustBeAttached_1      = false;
    public static $mustBeAttached_2      = false;

    public const SIDE_LEFT   = 1;
    public const SIDE_RIGHT  = 2;
    public const SIDE_TOP    = 3;
    public const SIDE_BOTTOM = 4;

    public static function getTypeName($nb = 0)
    {
        return _n('Item', 'Item', $nb);
    }

    public function post_getEmpty()
    {
        $this->fields['bgcolor'] = '#FF9D1F';
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'MassiveAction:update';
        $forbidden[] = 'CommonDBConnexity:affect';
        $forbidden[] = 'CommonDBConnexity:unaffect';

        return $forbidden;
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
     * Prepares and check validity of input (for update and add) and
     *
     * @param array $input Input data
     *
     * @return false|array
     */
    private function prepareInput($input)
    {
        $error_detected = [];

        //check for requirements
        if ($this->isNewItem()) {
            if (empty($input['pdus_id'])) {
                $error_detected[] = __('A pdu is required');
            }

            if (empty($input['racks_id'])) {
                $error_detected[] = __('A rack is required');
            }

            if (!isset($input['position'])) {
                $error_detected[] = __('A position is required');
            }

            if (!isset($input['side'])) {
                $error_detected[] = __('A side is required');
            }
        }

        $pdus_id  = $input['pdus_id'] ?? $this->fields['pdus_id'] ?? null;
        $racks_id = $input['racks_id'] ?? $this->fields['racks_id'] ?? null;
        $position = $input['position'] ?? $this->fields['position'] ?? 0;
        $side     = $input['side'] ?? $this->fields['side'] ?? null;

        if (!count($error_detected)) {
            //check if required U are available at position
            $required_units = 1;

            $rack = new Rack();
            $rack->getFromDB($racks_id);

            $pdu = new PDU();
            $pdu->getFromDB($pdus_id);

            $filled = self::getFilled($rack, $side);

            $model = new PDUModel();
            if ($model->getFromDB($pdu->fields['pdumodels_id'])) {
                if ($model->fields['required_units'] > 1) {
                    $required_units = (int) $model->fields['required_units'];
                }
            }

            if (
                in_array($side, [self::SIDE_LEFT, self::SIDE_RIGHT])
                && ($position > $rack->fields['number_units']
                 || $position + $required_units  > (int) $rack->fields['number_units'] + 1)
            ) {
                $error_detected[] = __('Item is out of rack bounds');
            } else {
                for ($i = 0; $i < $required_units; $i++) {
                    if (
                        $filled[$position + $i] > 0
                        && $filled[$position + $i] != $pdus_id
                    ) {
                        $error_detected[] = __('Not enough space available to place item');
                        break;
                    }
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

    /**
     * Get already filled places
     * @param  Rack    $rack The current rack
     * @param  int $side The side of rack to check
     * @return array   [position -> racks_id | 0]
     */
    public static function getFilled(Rack $rack, $side = 0)
    {
        $pdu    = new PDU();
        $model  = new PDUModel();
        $filled = array_fill(0, $rack->fields['number_units'], 0);

        $used = self::getForRackSide($rack, $side);
        foreach ($used as $current_pdu) {
            $required_units = 1;
            $pdu->getFromDB($current_pdu['pdus_id']);

            if (
                in_array($side, [self::SIDE_LEFT, self::SIDE_RIGHT])
                && $model->getFromDB($pdu->fields['pdumodels_id'])
            ) {
                if ($model->fields['required_units'] > 1) {
                    $required_units = $model->fields['required_units'];
                }
            }

            for ($i = 0; $i <= $required_units; $i++) {
                $position = $current_pdu['position'] + $i;
                $filled[$position] = $current_pdu['pdus_id'];
            }
        }

        return $filled;
    }

    /**
     * Return all possible side in a rack where a pdu can be placed
     * @return array (int => label)
     */
    public static function getSides()
    {
        return [
            self::SIDE_LEFT   => __('Left'),
            self::SIDE_RIGHT  => __('Right'),
            self::SIDE_TOP    => __('Top'),
            self::SIDE_BOTTOM => __('Bottom'),
        ];
    }

    /**
     * Get a side name from its index
     * @param  int $side See class constants and above `getSides`` method
     * @return string        the side name
     */
    public static function getSideName($side)
    {
        return self::getSides()[$side];
    }

    /**
     * Return an iterator for all pdu used in a side of a rack
     * @param  Rack      $rack
     * @param  int|array $side Side to target, use an array for multiple sides
     * @return DBmysqlIterator
     */
    public static function getForRackSide(Rack $rack, $side)
    {
        global $DB;

        return $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'racks_id' => $rack->getID(),
                'side'     => $side,
            ],
            'ORDER' => 'position ASC',
        ]);
    }

    /**
     * Return an iterator for all used pdu in all racks
     *
     * @param array $fields_requested Fields to request
     * @return DBmysqlIterator
     */
    public static function getUsed($fields_requested = ['*'])
    {
        global $DB;

        return $DB->request([
            'SELECT' => $fields_requested,
            'FROM'  => self::getTable(),
        ]);
    }

    /**
     * Return the opposite side from a passed side
     * @param  int $side
     * @return false|int       the opposite side
     */
    public static function getOtherSide($side)
    {
        switch ($side) {
            case self::SIDE_TOP:
                return self::SIDE_BOTTOM;
            case self::SIDE_BOTTOM:
                return self::SIDE_TOP;
            case self::SIDE_LEFT:
                return self::SIDE_RIGHT;
            case self::SIDE_RIGHT:
                return self::SIDE_LEFT;
        }
        return false;
    }
}
