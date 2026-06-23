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

use Glpi\Socket;

/**
 * NetworkPortInstantiation class
 *
 * Represents the type of given network port. As such, its ID field is the same one than the ID
 * of the network port it instantiates. This class don't have any table associated. It just
 * provides usefull and default methods for the instantiations.
 * Several kind of instanciations are available for a given port :
 *    - NetworkPortLocal
 *    - NetworkPortEthernet
 *    - NetworkPortWifi
 *    - NetworkPortAggregate
 *    - NetworkPortAlias
 *
 * @since 0.84
 *
 **/
class NetworkPortInstantiation extends CommonDBChild
{
    // From CommonDBTM
    public $auto_message_on_action   = false;

    // From CommonDBChild
    public static $itemtype       = NetworkPort::class;
    public static $items_id       = 'networkports_id';
    public $dohistory             = false;

    // Instantiation properties
    /** @var bool */
    public $canHaveVLAN           = true;
    /** @var bool */
    public $canHaveVirtualPort    = true;
    /** @var bool */
    public $haveMAC               = true;

    public static function getIndexName()
    {
        return 'networkports_id';
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInput($input)
    {
        // Try to get mac address from the instantiation ...
        if (!empty($input['mac'])) {
            $input['mac'] = strtolower($input['mac']);
        }
        return $input;
    }

    public function prepareInputForAdd($input)
    {
        return parent::prepareInputForAdd($this->prepareInput($input));
    }

    public function prepareInputForUpdate($input)
    {
        return parent::prepareInputForUpdate($this->prepareInput($input));
    }

    public function post_addItem()
    {
        $this->manageSocket();
    }

    public function post_updateItem($history = true)
    {
        $this->manageSocket();
    }

    /**
     * @return void
     */
    public function manageSocket()
    {
        // add link to define
        if (isset($this->input['sockets_id']) && $this->input['sockets_id'] > 0) {
            $networkport = new NetworkPort();
            if ($networkport->getFromDB($this->fields['networkports_id'])) {
                $socket = new Socket();
                $socket->getFromDB($this->input['sockets_id']);
                $socket->update([
                    "id"              => $socket->getID(),
                    "itemtype"        => $networkport->fields['itemtype'],
                    "name"            => $socket->fields['name'],
                    "position"        => $networkport->fields['logical_number'],
                    "items_id"        => $networkport->fields['items_id'],
                    "networkports_id" => $this->fields['networkports_id'],
                ]);
            }
        } else {
            // Retrieve the associated socket to disconnect it from the NetworkPortEthernet
            $socket = new Socket();
            if ($socket->getFromDBByCrit(["networkports_id" => $this->fields['networkports_id']])) {
                $socket->update([
                    "id" => $socket->getID(),
                    "networkports_id" => 0,
                ]);
            }
        }
    }

    /**
     * Get all NetworkPort and NetworkEquipments that have a specific MAC address
     *
     * @param string  $mac              address to search
     * @param bool $wildcard_search  true if we search with wildcard (false by default)
     *
     * @return array  each value of the array (corresponding to one NetworkPort) is an array of the
     *                items from the master item to the NetworkPort
     **/
    public static function getItemsByMac($mac, $wildcard_search = false)
    {
        global $DB;

        $mac = strtolower($mac);
        if ($wildcard_search) {
            $count = 0;
            $mac = str_replace('*', '%', $mac, $count);
            if ($count === 0) {
                $mac = '%' . $mac . '%';
            }
            $relation = ['LIKE', $mac];
        } else {
            $relation = $mac;
        }

        $macItemWithItems = [];

        $netport = new NetworkPort();

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => NetworkPort::getTable(),
            'WHERE'  => ['mac' => $relation],
        ]);

        foreach ($iterator as $element) {
            if ($netport->getFromDB($element['id'])) {
                $macItemWithItems[] = array_merge(
                    array_reverse($netport->recursivelyGetItems()),
                    [clone $netport]
                );
            }
        }

        return $macItemWithItems;
    }

    /**
     * Get an Object ID by its MAC address (only if one result is found in the entity)
     *
     * @param string  $value   the mac address
     * @param int $entity  the entity to look for
     *
     * @return array containing the object ID
     *         or an empty array is no value of serverals ID where found
     **/
    public static function getUniqueItemByMac($value, $entity)
    {

        $macs_with_items = self::getItemsByMac($value);
        if (count($macs_with_items)) {
            foreach ($macs_with_items as $key => $tab) {
                if (
                    isset($tab[0])
                    && ($tab[0]->getEntityID() != $entity
                    || $tab[0]->isDeleted()
                    || $tab[0]->isTemplate())
                ) {
                    unset($macs_with_items[$key]);
                }
            }
        }

        if (count($macs_with_items)) {
            // Get the first item that is matching entity
            foreach ($macs_with_items as $items) {
                foreach ($items as $item) {
                    if ($item->getEntityID() == $entity) {
                        $result = ["id"       => $item->getID(),
                            "itemtype" => $item->getType(),
                        ];
                        unset($macs_with_items);
                        return $result;
                    }
                }
            }
        }
        return [];
    }


    /**
     * In case of NetworkPort attached to a network card, list the fields that must be duplicate
     * from the network card to the network port (mac address, port type, ...)
     *
     * @return array Array with SQL field (for instance : device.type) => form field (type)
     **/
    public function getNetworkCardInterestingFields()
    {
        return [];
    }

    /**
     * @param array $tab
     * @param array $joinparams
     *
     * @return void
     **/
    public static function getSearchOptionsToAddForInstantiation(array &$tab, array $joinparams) {}

    /**
     * Make a select box for  connected port
     *
     * @param int $ID        ID of the current port to connect
     * @param array   $options   array of possible options:
     *    - name : string / name of the select (default is networkports_id)
     *    - comments : boolean / is the comments displayed near the dropdown (default true)
     *    - entity : integer or array / restrict to a defined entity or array of entities
     *                   (default -1 : no restriction)
     *    - entity_sons : boolean / if entity restrict specified auto select its sons
     *                   only available if entity is a single value not an array (default false)
     *
     * @return int random part of elements id
     **/
    public static function dropdownConnect($ID, $options = [])
    {
        global $CFG_GLPI;

        $p['name']        = 'networkports_id';
        $p['comments']    = 1;
        $p['entity']      = -1;
        $p['entity_sons'] = false;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        // Manage entity_sons
        if ($p['entity'] >= 0 && $p['entity_sons']) {
            if (is_array($p['entity'])) {
                echo "entity_sons options is not available with entity option as array";
            } else {
                $p['entity'] = getSonsOf('glpi_entities', $p['entity']);
            }
        }

        echo "<input type='hidden' name='NetworkPortConnect_networkports_id_1'value='" . htmlescape($ID) . "'>";
        $rand = Dropdown::showItemTypes('NetworkPortConnect_itemtype', $CFG_GLPI["networkport_types"]);

        $params = ['itemtype'           => '__VALUE__',
            'entity_restrict'    => Session::getMatchingActiveEntities($p['entity']),
            'networkports_id'    => $ID,
            'comments'           => $p['comments'],
            'myname'             => $p['name'],
            'instantiation_type' => static::class,
        ];

        Ajax::updateItemOnSelectEvent(
            "dropdown_NetworkPortConnect_itemtype$rand",
            "show_" . $p['name'] . "$rand",
            $CFG_GLPI["root_doc"]
                                       . "/ajax/dropdownConnectNetworkPortDeviceType.php",
            $params
        );

        echo "<span id='show_" . htmlescape($p['name']) . "$rand'>&nbsp;</span>";

        return $rand;
    }
}
