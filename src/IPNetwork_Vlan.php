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
 * @since 0.84
 **/
class IPNetwork_Vlan extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = IPNetwork::class;
    public static $items_id_1          = 'ipnetworks_id';

    public static $itemtype_2 = Vlan::class;
    public static $items_id_2          = 'vlans_id';
    public static $checkItem_2_Rights  = self::HAVE_VIEW_RIGHT_ON_ITEM;

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    /**
     * @param int $portID
     * @param int $vlanID
     *
     * @return bool
     */
    public function unassignVlan($portID, $vlanID)
    {

        $this->getFromDBByCrit([
            'ipnetworks_id'   => $portID,
            'vlans_id'        => $vlanID,
        ]);

        return $this->delete($this->fields);
    }


    /**
     * @param int $port
     * @param int $vlan
     *
     * @return int
     **/
    public function assignVlan($port, $vlan)
    {

        $input = ['ipnetworks_id' => $port,
            'vlans_id'      => $vlan,
        ];

        return $this->add($input);
    }

    /**
     * @param int $portID
     *
     * @return array
     */
    public static function getVlansForIPNetwork($portID)
    {
        global $DB;

        $vlans = [];
        $iterator = $DB->request([
            'SELECT' => 'vlans_id',
            'FROM'   => self::getTable(),
            'WHERE'  => ['ipnetworks_id' => $portID],
        ]);
        foreach ($iterator as $data) {
            $vlans[$data['vlans_id']] = $data['vlans_id'];
        }

        return $vlans;
    }
}
