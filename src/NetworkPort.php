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
use function Safe\preg_replace;
use function Safe\strtotime;

/**
 * NetworkPort Class
 *
 * There is two parts for a given NetworkPort.
 * The first one, generic, only contains the link to the item, the name and the type of network port.
 * All specific characteristics are owned by the instanciation of the network port : NetworkPortInstantiation.
 * Whenever a port is display (through its form or though item port listing), the NetworkPort class
 * load its instantiation from the instantiation database to display the elements.
 * Moreover, in NetworkPort form, if there is no more than one NetworkName attached to the current
 * port, then, the fields of NetworkName are display. Thus, NetworkPort UI remain similar to 0.83
 **/
class NetworkPort extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype             = 'itemtype';
    public static $items_id             = 'items_id';
    public $dohistory                   = true;

    public static $checkParentRights    = CommonDBConnexity::HAVE_SAME_RIGHT_ON_ITEM;

    protected static $forward_entity_to = ['NetworkName'];

    public static $rightname                   = 'networking';

    /**
     * Subset of input that will be used for NetworkPortInstantiation.
     * @var array|null
     */
    private ?array $input_for_instantiation = null;
    /**
     * Subset of input that will be used for NetworkName.
     * @var array|null
     */
    private ?array $input_for_NetworkName = null;
    /**
     * Subset of input that will be used for NetworkPort_NetworkPort.
     * @var array|null
     */
    private ?array $input_for_NetworkPortConnect = null;

    /**
     * @param string $property
     *
     * @return mixed
     */
    public function __get(string $property)
    {
        $value = null;
        switch ($property) {
            case 'input_for_instantiation':
            case 'input_for_NetworkName':
            case 'input_for_NetworkPortConnect':
                Toolbox::deprecated(sprintf('Reading private property %s::%s is deprecated', self::class, $property));
                $value = $this->$property;
                break;
            default:
                $trace = debug_backtrace();
                trigger_error(
                    sprintf('Undefined property: %s::%s in %s on line %d', self::class, $property, $trace[0]['file'], $trace[0]['line']),
                    E_USER_WARNING
                );
                break;
        }
        return $value;
    }

    public function useDeletedToLockIfDynamic()
    {
        return false;
    }

    public static function canView(): bool
    {
        if (static::$rightname && Session::haveRight(static::$rightname, READ)) {
            return true;
        }
        return static::canChild('canView');
    }

    public static function canCreate(): bool
    {
        if (static::$rightname && Session::haveRight(static::$rightname, CREATE)) {
            return true;
        }
        return static::canChild('canUpdate');
    }

    public static function canUpdate(): bool
    {
        if (static::$rightname && Session::haveRight(static::$rightname, UPDATE)) {
            return true;
        }
        return static::canChild('canUpdate');
    }

    /**
     * @param string $property
     * @param mixed $value
     *
     * @return void
     */
    public function __set(string $property, $value)
    {
        switch ($property) {
            case 'input_for_instantiation':
            case 'input_for_NetworkName':
            case 'input_for_NetworkPortConnect':
                Toolbox::deprecated(sprintf('Writing private property %s::%s is deprecated', self::class, $property));
                $this->$property = $value;
                break;
            default:
                $trace = debug_backtrace();
                trigger_error(
                    sprintf('Undefined property: %s::%s in %s on line %d', self::class, $property, $trace[0]['file'], $trace[0]['line']),
                    E_USER_WARNING
                );
                break;
        }
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function getPreAdditionalInfosForName()
    {
        if ($item = $this->getItem()) {
            return $item->getName();
        }
        return '';
    }

    /**
     * Get the list of available network port type.
     *
     * @since 0.84
     *
     * @return class-string<NetworkPortInstantiation>[] Array of available type of network ports
     **/
    public static function getNetworkPortInstantiations()
    {
        global $CFG_GLPI;

        return $CFG_GLPI['networkport_instantiations'];
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Network port', 'Network ports', $nb);
    }

    /**
     * Get the instantiation of the current NetworkPort
     * The instantiation rely on the instantiation_type field and the id of the NetworkPort. If the
     * network port exists, but not its instantiation, then, the instantiation will be empty.
     *
     * @since 0.84
     *
     * @return NetworkPortInstantiation|false  the instantiation object or false if the type of instantiation is not known
     **/
    public function getInstantiation()
    {
        if (
            isset($this->fields['instantiation_type'])
            && in_array($this->fields['instantiation_type'], self::getNetworkPortInstantiations(), true)
        ) {
            if ($instantiation = getItemForItemtype($this->fields['instantiation_type'])) {
                if (!$instantiation->getFromDB($this->getID())) {
                    if (!$instantiation->getEmpty()) {
                        unset($instantiation);
                        return false;
                    }
                }
                return $instantiation;
            }
        }
        return false;
    }

    /**
     * Change the instantion type of a NetworkPort : check validity of the new type of
     * instantiation and that it is not equal to current ones. Update the NetworkPort and delete
     * the previous instantiation. It is up to the caller to create the new instantiation !
     *
     * @since 0.84
     *
     * @param class-string<NetworkPortInstantiation> $new_instantiation_type  the name of the new instaniation type
     *
     * @return NetworkPortInstantiation|bool false on error, true if the previous instantiation is not available
     *                 (ie.: invalid instantiation type) or the object of the previous instantiation.
     **/
    public function switchInstantiationType($new_instantiation_type)
    {
        // First, check if the new instantiation is a valid one ...
        if (!in_array($new_instantiation_type, self::getNetworkPortInstantiations())) {
            return false;
        }

        // Load the previous instantiation
        $previousInstantiation = $this->getInstantiation();

        // If the previous instantiation is the same than the new one: nothing to do !
        if (
            ($previousInstantiation !== false)
            && ($previousInstantiation::class === $new_instantiation_type)
        ) {
            return $previousInstantiation;
        }

        // We update the current NetworkPort
        $input                       = $this->fields;
        $input['instantiation_type'] = $new_instantiation_type;
        $this->update($input);

        // Then, we delete the previous instantiation
        if ($previousInstantiation !== false) {
            $previousInstantiation->delete($previousInstantiation->fields);
            return $previousInstantiation;
        }

        return true;
    }

    public function prepareInputForUpdate($input)
    {
        if (!isset($input["_no_history"])) {
            $input['_no_history'] = false;
        }
        if (
            isset($input['_create_children'])
            && $input['_create_children']
        ) {
            return $this->splitInputForElements($input);
        }

        return $input;
    }

    public function post_updateItem($history = true)
    {
        global $DB;

        if (count($this->updates)) {
            // Update Ticket Tco
            if (
                in_array("itemtype", $this->updates, true)
                || in_array("items_id", $this->updates, true)
            ) {
                $ip = new IPAddress();
                // Update IPAddress
                foreach (
                    $DB->request([
                        'FROM' => 'glpi_networknames',
                        'WHERE' => [
                            'itemtype' => NetworkPort::class,
                            'items_id' => $this->getID(),
                        ],
                    ]) as $dataname
                ) {
                    foreach (
                        $DB->request([
                            'FROM' => 'glpi_ipaddresses',
                            'WHERE' => [
                                'itemtype' => NetworkName::class,
                                'items_id' => $dataname['id'],
                            ],
                        ]) as $data
                    ) {
                        $ip->update(['id'           => $data['id'],
                            'mainitemtype' => $this->fields['itemtype'],
                            'mainitems_id' => $this->fields['items_id'],
                        ]);
                    }
                }
            }
        }
        parent::post_updateItem($history);

        $this->updateDependencies(!$this->input['_no_history']);
        $this->updateMetrics();
    }

    public function post_clone($source, $history)
    {
        $instantiation = $source->getInstantiation();
        if ($instantiation !== false) {
            $instantiation->fields[$instantiation->getIndexName()] = $this->getID();
            $instantiation->clone([], $history);
        }
    }

    /**
     * Split input fields when validating a port
     *
     * The form of the NetworkPort can contain the details of the NetworkPortInstantiation as well as
     * NetworkName elements (if no more than one name is attached to this port). Feilds from both
     * NetworkPortInstantiation and NetworkName must not be process by the NetworkPort::add or
     * NetworkPort::update. But they must be kept for adding or updating these elements. This is
     * done after creating or updating the current port. Otherwise, its ID may not be known (in case
     * of new port).
     * To keep the unused fields, we check each field key. If it is owned by NetworkPort (ie :
     * exists inside the $this->fields array), then they remain inside $input. If they are prefix by
     * "Networkname_", then they are added to $this->input_for_NetworkName. Else, they are for the
     * instantiation and added to $this->input_for_instantiation.
     *
     * This method must be call before NetworkPort::add or NetworkPort::update in case of NetworkPort
     * form. Otherwise, the entry of the database may contain wrong values.
     *
     * @since 0.84
     *
     * @param ?array $input
     * @return array|void
     *
     * @see self::updateDependencies() for the update
     **/
    public function splitInputForElements($input)
    {

        if (
            $this->input_for_instantiation !== null
            || $this->input_for_NetworkName !== null
            || $this->input_for_NetworkPortConnect !== null
            || !isset($input)
        ) {
            return;
        }

        $this->input_for_instantiation      = [];
        $this->input_for_NetworkName        = [];
        $this->input_for_NetworkPortConnect = [];

        $clone = clone $this;
        $clone->getEmpty();

        foreach ($input as $field => $value) {
            if (array_key_exists($field, $clone->fields) || $field[0] === '_') {
                continue;
            }
            if (str_starts_with($field, "NetworkName_")) {
                $networkName_field = preg_replace('/^NetworkName_/', '', $field);
                $this->input_for_NetworkName[$networkName_field] = $value;
            } elseif (str_starts_with($field, "NetworkPortConnect_")) {
                $networkName_field = preg_replace('/^NetworkPortConnect_/', '', $field);
                $this->input_for_NetworkPortConnect[$networkName_field] = $value;
            } else {
                $this->input_for_instantiation[$field] = $value;
            }
            unset($input[$field]);
        }

        return $input;
    }

    /**
     * Update all related elements after adding or updating an element
     *
     * splitInputForElements() prepare the data for adding or updating NetworkPortInstantiation and
     * NetworkName. This method will update NetworkPortInstantiation and NetworkName. I must be call
     * after NetworkPort::add or NetworkPort::update otherwise, the networkport ID will not be known
     * and the dependencies won't have a valid items_id field.
     *
     * @since 0.84
     *
     * @param bool $history
     *
     * @see splitInputForElements() for preparing the input
     *
     * @return void
     **/
    public function updateDependencies($history = true)
    {
        $instantiation = $this->getInstantiation();
        if (
            $instantiation !== false
            && is_array($this->input_for_instantiation)
            && count($this->input_for_instantiation) > 0
        ) {
            $this->input_for_instantiation['networkports_id'] = $this->getID();
            if ($instantiation::isNewID($instantiation->getID())) {
                $instantiation->add($this->input_for_instantiation, [], $history);
            } else {
                $instantiation->update($this->input_for_instantiation, $history);
            }
        }
        $this->input_for_instantiation = null;

        if (
            is_array($this->input_for_NetworkName)
            && count($this->input_for_NetworkName) > 0
            && !isset($_POST['several'])
        ) {
            // Check to see if the NetworkName is empty
            $empty_networkName = empty($this->input_for_NetworkName['name'])
                              && empty($this->input_for_NetworkName['fqdns_id']);
            if (($empty_networkName) && is_array($this->input_for_NetworkName['_ipaddresses'])) {
                foreach ($this->input_for_NetworkName['_ipaddresses'] as $ip_address) {
                    if (!empty($ip_address)) {
                        $empty_networkName = false;
                        break;
                    }
                }
            }

            $network_name = new NetworkName();
            if (isset($this->input_for_NetworkName['id'])) {
                if ($empty_networkName) {
                    // If the NetworkName is empty, then delete it !
                    $network_name->delete($this->input_for_NetworkName, true, $history);
                } else {
                    // Else, update it
                    $this->input_for_NetworkName['entities_id'] = $this->fields['entities_id'];
                    $network_name->update($this->input_for_NetworkName, $history);
                }
            } elseif (!$empty_networkName) { // Only create a NetworkName if it is not empty
                $this->input_for_NetworkName['itemtype']    = 'NetworkPort';
                $this->input_for_NetworkName['items_id']    = $this->getID();
                $this->input_for_NetworkName['entities_id'] = $this->fields['entities_id'];
                $network_name->add($this->input_for_NetworkName, [], $history);
            }
        }
        $this->input_for_NetworkName = null;

        if (
            is_array($this->input_for_NetworkPortConnect)
            && count($this->input_for_NetworkPortConnect) > 0
        ) {
            if (
                isset($this->input_for_NetworkPortConnect['networkports_id_1'], $this->input_for_NetworkPortConnect['networkports_id_2'])
                && !empty($this->input_for_NetworkPortConnect['networkports_id_2'])
            ) {
                $nn  = new NetworkPort_NetworkPort();
                $nn->add($this->input_for_NetworkPortConnect, [], $history);
            }
        }
        $this->input_for_NetworkPortConnect = null;
    }

    /**
     * @return void
     */
    public function updateMetrics()
    {
        $unicity_input = [
            'networkports_id' => $this->fields['id'],
            'date'            => date('Y-m-d', strtotime($_SESSION['glpi_currenttime'])),
        ];
        $input = array_merge(
            [
                'ifinbytes'       => $this->fields['ifinbytes'] ?? 0,
                'ifoutbytes'      => $this->fields['ifoutbytes'] ?? 0,
                'ifinerrors'      => $this->fields['ifinerrors'] ?? 0,
                'ifouterrors'     => $this->fields['ifouterrors'] ?? 0,
                'is_dynamic'     =>  $this->fields['is_dynamic'] ?? 0,
            ],
            $unicity_input
        );

        $metrics = new NetworkPortMetrics();
        if ($metrics->getFromDBByCrit($unicity_input)) {
            $input['id'] = $metrics->fields['id'];
            $metrics->update($input, false);
        } else {
            $metrics->add($input, [], false);
        }
    }

    public function prepareInputForAdd($input)
    {
        if (isset($input["logical_number"]) && ($input["logical_number"] === '')) {
            unset($input["logical_number"]);
        }

        if (!isset($input["_no_history"])) {
            $input['_no_history'] = false;
        }

        if (
            isset($input['_create_children'])
            && $input['_create_children']
        ) {
            $input = $this->splitInputForElements($input);
        }

        return parent::prepareInputForAdd($input);
    }

    public function post_addItem()
    {
        parent::post_addItem(); //for history
        $this->updateDependencies(!$this->input['_no_history']);
        $this->updateMetrics();
    }

    public function cleanDBonPurge()
    {
        $instantiation = $this->getInstantiation();
        if ($instantiation !== false) {
            $instantiation->cleanDBonItemDelete(static::class, $this->getID());
            unset($instantiation);
        }

        $this->deleteChildrenAndRelationsFromDb(
            [
                NetworkName::class,
                NetworkPort_NetworkPort::class,
                NetworkPort_Vlan::class,
                NetworkPortAggregate::class,
                NetworkPortAlias::class,
                NetworkPortDialup::class,
                NetworkPortEthernet::class,
                NetworkPortFiberchannel::class,
                NetworkPortLocal::class,
                NetworkPortMetrics::class,
                NetworkPortWifi::class,
            ]
        );
    }

    /**
     * Get port opposite port ID if linked item
     *
     * @param int $ID  networking port ID
     *
     * @return int|false  ID of the NetworkPort found, false if not found
     **/
    public function getContact($ID): bool|int
    {
        $wire = new NetworkPort_NetworkPort();
        if ($contact_id = $wire->getOppositeContact($ID)) {
            return $contact_id;
        }
        return false;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param int $items_id
     *
     * @return DBmysqlIterator
     */
    protected function getIpsForPort($itemtype, $items_id)
    {
        /** @var DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'ipa.*',
            'FROM'   => IPAddress::getTable() . ' AS ipa',
            'INNER JOIN'   => [
                NetworkName::getTable() . ' AS netname' => [
                    'ON' => [
                        'ipa' => 'items_id',
                        'netname' => 'id', [
                            'AND' => [
                                'ipa.itemtype' => 'NetworkName',
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE'  => [
                'netname.items_id'   => $items_id,
                'netname.itemtype'   => $itemtype,
            ],
        ]);
        return $iterator;
    }

    private function getAssetLink(CommonDBTM $asset): string
    {

        if ($asset instanceof NetworkPort) {
            $link = $asset->getLink();
        } else {
            $link = sprintf(
                '<i class="%1$s"></i> %2$s </i>',
                htmlescape($asset->getIcon()),
                $asset->getLink(),
            );
        }


        if (!empty($asset->fields['mac'])) {
            $link .= '<br/>' . htmlescape($asset->fields['mac']);
        }

        $ips_iterator = $this->getIpsForPort($asset->getType(), $asset->getID());
        $ips = '';
        foreach ($ips_iterator as $ipa) {
            $ips .= ' ' . htmlescape($ipa['name']);
        }
        if (!empty($ips)) {
            $link .= '<br/>' . $ips;
        }

        return $link;
    }

    /**
     * @param ?string $itemtype
     * @return array
     */
    public static function rawSearchOptionsToAdd($itemtype = null)
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'network',
            'name'               => __('Networking'),
        ];

        $joinparams = ['jointype' => 'itemtype_item'];

        $tab[] = [
            'id'                 => '21',
            'table'              => 'glpi_networkports',
            'field'              => 'mac',
            'name'               => __('MAC address'),
            'datatype'           => 'mac',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => $joinparams,
        ];

        $tab[] = [
            'id'                 => '87',
            'table'              => 'glpi_networkports',
            'field'              => 'instantiation_type',
            'name'               => NetworkPortType::getTypeName(1),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'networkport_instantiations',
            'massiveaction'      => false,
            'joinparams'         => $joinparams,
        ];

        $networkNameJoin = ['jointype'          => 'itemtype_item',
            'specific_itemtype' => 'NetworkPort',
            'condition'         => ['NEWTABLE.is_deleted' => 0],
            'beforejoin'        => ['table'      => 'glpi_networkports',
                'joinparams' => $joinparams,
            ],
        ];
        NetworkName::rawSearchOptionsToAdd($tab, $networkNameJoin);

        $instantjoin = ['jointype'   => 'child',
            'beforejoin' => ['table'      => 'glpi_networkports',
                'joinparams' => $joinparams,
            ],
        ];
        foreach (self::getNetworkPortInstantiations() as $instantiationType) {
            $instantiationType::getSearchOptionsToAddForInstantiation($tab, $instantjoin);
        }

        $netportjoin = [
            [
                'table'      => 'glpi_networkports',
                'joinparams' => ['jointype' => 'itemtype_item'],
            ],
            [
                'table'      => 'glpi_networkports_vlans',
                'joinparams' => ['jointype' => 'child'],
            ],
        ];

        $tab[] = [
            'id'                 => '88',
            'table'              => 'glpi_vlans',
            'field'              => 'name',
            'name'               => Vlan::getTypeName(1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => ['beforejoin' => $netportjoin],
        ];

        return $tab;
    }

    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = $checkitem !== null && $checkitem->canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        // add purge action if main item is not dynamic
        // NetworkPort delete / purge are handled a different way on dynamic asset (lock)
        if ($checkitem instanceof CommonDBTM && !$checkitem->isDynamic()) {
            $actions['NetworkPort' . MassiveAction::CLASS_ACTION_SEPARATOR . 'purge']    = __s('Delete permanently');
        }

        if ($isadmin) {
            $vlan_prefix                    = 'NetworkPort_Vlan' . MassiveAction::CLASS_ACTION_SEPARATOR;
            $actions[$vlan_prefix . 'add']    = __s('Associate a VLAN');
            $actions[$vlan_prefix . 'remove'] = __s('Dissociate a VLAN');
        }
        return $actions;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'purge':
                foreach ($ids as $id) {
                    if ($item->can($id, PURGE)) {
                        // Only mark deletion for
                        if (!$item->isDeleted()) {
                            $ma->itemDone($item::class, $id, MassiveAction::ACTION_KO);
                            $ma->addMessage(sprintf(__s('%1$s: %2$s'), $item->getLink(), __s('Item need to be deleted first')));
                        } else {
                            $delete_array = ['id' => $id];

                            if ($item->delete($delete_array, true)) {
                                $ma->itemDone($item::class, $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item::class, $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        }
                    } else {
                        $ma->itemDone($item::class, $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    }
                }
                return;
        }
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
            'name'               => __('Name'),
            'type'               => 'text',
            'massiveaction'      => false,
            'datatype'           => 'itemlink',
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
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'logical_number',
            'name'               => _n('Port number', 'Port numbers', 1),
            'datatype'           => 'integer',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'mac',
            'name'               => __('MAC address'),
            'datatype'           => 'mac',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'instantiation_type',
            'name'               => NetworkPortType::getTypeName(1),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'networkport_instantiations',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'is_deleted',
            'name'               => __('Deleted'),
            'datatype'           => 'bool',
            'massiveaction'      => false,
        ];

        if ($this->isField('sockets_id')) {
            $tab[] = [
                'id'                 => '9',
                'table'              => 'glpi_sockets',
                'field'              => 'name',
                'name'               => Socket::getTypeName(1),
                'datatype'           => 'dropdown',
            ];
        }

        $tab[] = [
            'id'                 => '16',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '20',
            'table'              => static::getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'networkport_types',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '21',
            'table'              => static::getTable(),
            'field'              => 'items_id',
            'name'               => __('ID'),
            'datatype'           => 'integer',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'    => '30',
            'table' => static::getTable(),
            'field' => 'ifmtu',
            'name'  => __('MTU'),
        ];

        $tab[] = [
            'id'    => '31',
            'table' => static::getTable(),
            'field' => 'ifspeed',
            'name'  => __('Speed'),
        ];

        $tab[] = [
            'id'    => '32',
            'table' => static::getTable(),
            'field' => 'ifinternalstatus',
            'name'  => __('Internal status'),
        ];

        $tab[] = [
            'id'    => '33',
            'table' => static::getTable(),
            'field' => 'iflastchange',
            'name'  => __('Last change'),
        ];

        $tab[] = [
            'id'    => '34',
            'table' => static::getTable(),
            'field' => 'ifinbytes',
            'name'  => __('Number of I/O bytes'),
        ];

        $tab[] = [
            'id'    => '35',
            'table' => static::getTable(),
            'field' => 'ifinerrors',
            'name'  => __('Number of I/O errors'),
        ];

        $tab[] = [
            'id'    => '36',
            'table' => static::getTable(),
            'field' => 'portduplex',
            'name'  => __('Duplex'),
        ];

        $netportjoin = [
            [
                'table'      => 'glpi_networkports',
                'joinparams' => ['jointype' => 'itemtype_item'],
            ], [
                'table'      => 'glpi_networkports_vlans',
                'joinparams' => ['jointype' => 'child'],
            ],
        ];

        $tab[] = [
            'id' => '38',
            'table' => Vlan::getTable(),
            'field' => 'name',
            'name' => Vlan::getTypeName(1),
            'datatype' => 'dropdown',
            'forcegroupby' => true,
            'massiveaction' => false,
            'joinparams' => ['beforejoin' => $netportjoin],
        ];

        $tab[] = [
            'id'    => '39',
            'table' => static::getTable(),
            'field' => '_virtual_connected_to',
            'name' => __('Connected to'),
            'nosearch' => true,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'    => '40',
            'table' => static::getTable(),
            'field' => 'ifconnectionstatus',
            'name'  => _n('Connection', 'Connections', 1),
        ];

        $tab[] = [
            'id'       => '41',
            'table'    => static::getTable(),
            'field'    => 'lastup',
            'name'     => __('Last connection'),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id'    => '42',
            'table' => static::getTable(),
            'field' => 'ifalias',
            'name'  => __('Alias'),
        ];

        $joinparams = ['jointype' => 'itemtype_item'];
        $networkNameJoin = [
            'jointype'          => 'itemtype_item',
            'specific_itemtype' => 'NetworkPort',
            'condition'         => ['NEWTABLE.is_deleted' => 0],
            'beforejoin'        => ['table'      => 'glpi_networkports',
                'joinparams' => $joinparams,
            ],
        ];
        NetworkName::rawSearchOptionsToAdd($tab, $networkNameJoin);

        return $tab;
    }

    /**
     * @param CommonDBTM $item
     * @return int
     */
    public static function countForItem(CommonDBTM $item): int
    {
        return countElementsInTable(
            'glpi_networkports',
            [
                'itemtype'   => $item::class,
                'items_id'   => $item->getField('id'),
                'is_deleted' => 0,
            ]
        );
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $CFG_GLPI;

        if (!$item instanceof CommonDBTM) {
            return false;
        }

        if (
            $item::class === self::class
            || in_array($item::class, $CFG_GLPI["networkport_types"], true)
        ) {
            self::showForItem($item, $withtemplate);
        }
        return true;
    }

    public static function getConnexityMassiveActionsSpecificities()
    {
        $specificities                           = parent::getConnexityMassiveActionsSpecificities();

        $specificities['reaffect']               = true;
        $specificities['itemtypes']              = ['Computer', 'NetworkEquipment'];

        $specificities['normalized']['unaffect'] = [];
        $specificities['action_name']['affect']  = _sx('button', 'Move');

        return $specificities;
    }

    public function getLink($options = [])
    {
        $port_link = parent::getLink($options);

        if (!isset($this->fields['itemtype']) || !class_exists($this->fields['itemtype'])) {
            return $port_link;
        }

        $itemtype = $this->fields['itemtype'];
        $equipment = getItemForItemtype($itemtype);

        if ($equipment && $equipment->getFromDB($this->fields['items_id'])) {
            return sprintf(
                '<i class="%1$s"></i> %2$s > <i class="%3$s"></i> %4$s',
                htmlescape($equipment::getIcon()),
                $equipment->getLink(),
                htmlescape(self::getIcon()),
                $port_link,
            );
        }

        return $port_link;
    }

    /**
     * Is port connected to a hub?
     *
     * @param int $networkports_id Port ID
     *
     * @return bool
     */
    public function isHubConnected($networkports_id): bool
    {
        global $DB;

        $wired = new NetworkPort_NetworkPort();
        $opposite = $wired->getOppositeContact($networkports_id);

        if (empty($opposite)) {
            return false;
        }

        $table = static::getTable();
        $result = $DB->request([
            'FROM'         => Unmanaged::getTable(),
            'COUNT'        => 'cpt',
            'INNER JOIN'   => [
                $table => [
                    'ON' => [
                        $table       => 'items_id',
                        Unmanaged::getTable()   => 'id', [
                            'AND' => [
                                $table . '.itemtype' => Unmanaged::getType(),
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE'        => [
                'hub' => 1,
                $table . '.id' => $opposite,
            ],
        ])->current();

        return ($result['cpt'] > 0);
    }

    public function getNonLoggedFields(): array
    {
        return [
            'ifinbytes',
            'ifoutbytes',
            'ifinerrors',
            'ifouterrors',
        ];
    }

    public static function getIcon()
    {
        return "ti ti-network";
    }
}
