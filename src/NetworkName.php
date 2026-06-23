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
 * NetworkName Class
 *
 * represent the internet name of an element.
 * It is compose of the name itself, its domain and one or several IP addresses (IPv4 and/or IPv6).
 * An address can be affected to an item, or can be "free" to be reuse by another item
 * (for instance, in case of maintenance, when you change the network card of a computer,
 *  but not its network information)
 *
 * @since 0.84
 **/
class NetworkName extends FQDNLabel
{
    // From CommonDBChild
    public static $itemtype              = 'itemtype';
    public static $items_id              = 'items_id';
    public $dohistory                    = true;

    protected static $forward_entity_to  = ['IPAddress', 'NetworkAlias'];

    public static $canDeleteOnItemClean  = false;

    public static $checkParentRights     = CommonDBConnexity::HAVE_SAME_RIGHT_ON_ITEM;

    public static $mustBeAttached        = false;

    public static $rightname                   = 'internet';


    public static function getTypeName($nb = 0)
    {
        return _n('Network name', 'Network names', $nb);
    }

    public static function getSectorizedDetails(): array
    {
        return ['config', CommonDropdown::class, self::class];
    }

    public function useDeletedToLockIfDynamic()
    {
        return false;
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '12',
            'table'              => 'glpi_fqdns',
            'field'              => 'fqdn',
            'name'               => FQDN::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => 'glpi_ipaddresses',
            'field'              => 'name',
            'name'               => IPAddress::getTypeName(1),
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '20',
            'table'              => static::getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'itemtypename',
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

        return $tab;
    }

    /**
     * @param array $tab the array to fill
     * @param array $joinparams
     *
     * @return void
     **/
    public static function rawSearchOptionsToAdd(array &$tab, array $joinparams)
    {
        $tab[] = [
            'id'                  => '126',
            'table'               => 'glpi_ipaddresses',
            'field'               => 'name',
            'name'                => __('IP'),
            'forcegroupby'        => true,
            'searchequalsonfield' => true,
            'massiveaction'       => false,
            'joinparams'          => [
                'jointype'  => 'mainitemtype_mainitem',
                'condition' => ['NEWTABLE.is_deleted' => 0,
                    'NOT' => ['NEWTABLE.name' => ''],
                ],
            ],
        ];

        $tab[] = [
            'id'                  => '127',
            'table'               => 'glpi_networknames',
            'field'               => 'name',
            'name'                => self::getTypeName(Session::getPluralNumber()),
            'forcegroupby'        => true,
            'massiveaction'       => false,
            'joinparams'          => $joinparams,
        ];

        $tab[] = [
            'id'                  => '128',
            'table'               => 'glpi_networkaliases',
            'field'               => 'name',
            'name'                => NetworkAlias::getTypeName(Session::getPluralNumber()),
            'forcegroupby'        => true,
            'massiveaction'       => false,
            'joinparams'          => [
                'jointype'   => 'child',
                'beforejoin' => [
                    'table'      => 'glpi_networknames',
                    'joinparams' => $joinparams,
                ],
            ],
        ];
    }

    /**
     * Update IPAddress database
     *
     * Update IPAddress database to remove old IPs and add new ones.
     *
     * @return void
     **/
    public function post_workOnItem()
    {
        if (
            (isset($this->input['_ipaddresses']))
            && (is_array($this->input['_ipaddresses']))
        ) {
            $input = [
                'itemtype' => NetworkName::class,
                'items_id' => $this->getID(),
            ];
            foreach ($this->input['_ipaddresses'] as $id => $ip) {
                $ipaddress     = new IPAddress();
                $input['name'] = $ip;
                if ($id < 0) {
                    if (!empty($ip)) {
                        $ipaddress->add($input);
                    }
                } else {
                    if (!empty($ip)) {
                        $input['id'] = $id;
                        $ipaddress->update($input);
                        unset($input['id']);
                    } else {
                        $ipaddress->delete(['id' => $id]);
                    }
                }
            }
        }
    }

    public function post_addItem()
    {
        $this->post_workOnItem();
        parent::post_addItem();
    }

    public function post_updateItem($history = true)
    {
        global $DB;

        $this->post_workOnItem();
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
                        'FROM' => 'glpi_ipaddresses',
                        'WHERE' => [
                            'itemtype' => NetworkName::class,
                            'items_id' => $this->getID(),
                        ],
                    ]) as $data
                ) {
                    $ip->update([
                        'id'       => $data['id'],
                        'itemtype' => NetworkName::class,
                        'items_id' => $this->getID(),
                    ]);
                }
            }
        }
        parent::post_updateItem($history);
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                IPAddress::class,
                NetworkAlias::class,
            ]
        );
    }

    /**
     * Detach an address from an item
     *
     * The address can be unaffected, and remain "free"
     *
     * @param int $items_id  the id of the item
     * @param string  $itemtype  the type of the item
     *
     * @return void
     **/
    public static function unaffectAddressesOfItem($items_id, $itemtype)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'itemtype'  => $itemtype,
                'items_id'  => $items_id,
            ],
        ]);

        foreach ($iterator as $networkNameID) {
            self::unaffectAddressByID($networkNameID['id']);
        }
    }

    /**
     * Detach an address from an item
     *
     * The address can be unaffected, and remain "free"
     *
     * @param int $networkNameID the id of the NetworkName
     *
     * @return bool
     **/
    public static function unaffectAddressByID($networkNameID)
    {
        return self::affectAddress($networkNameID, 0, '');
    }

    /**
     * @param int $networkNameID
     * @param int $items_id
     * @param string $itemtype
     * @return bool
     */
    public static function affectAddress($networkNameID, $items_id, $itemtype)
    {
        $networkName = new self();
        return $networkName->update([
            'id'       => $networkNameID,
            'items_id' => $items_id,
            'itemtype' => $itemtype,
        ]);
    }

    /**
     * @param CommonDBTM $item
     * @return int
     */
    public static function countForItem(CommonDBTM $item): int
    {
        global $DB;

        switch ($item::class) {
            case FQDN::class:
                return countElementsInTable(
                    'glpi_networknames',
                    ['fqdns_id'   => $item->fields["id"],
                        'is_deleted' => 0,
                    ]
                );

            case NetworkPort::class:
                return countElementsInTable(
                    'glpi_networknames',
                    ['itemtype'   => $item->getType(),
                        'items_id'   => $item->getID(),
                        'is_deleted' => 0,
                    ]
                );

            case NetworkEquipment::class:
                $result = $DB->request([
                    'SELECT'          => ['COUNT DISTINCT' => 'glpi_networknames.id AS cpt'],
                    'FROM'            => 'glpi_networknames',
                    'INNER JOIN'       => [
                        'glpi_networkports'  => [
                            'ON' => [
                                'glpi_networknames'  => 'items_id',
                                'glpi_networkports'  => 'id', [
                                    'AND' => [
                                        'glpi_networknames.itemtype' => 'NetworkPort',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'WHERE'           => [
                        'glpi_networkports.itemtype'     => $item->getType(),
                        'glpi_networkports.items_id'     => $item->getID(),
                        'glpi_networkports.is_deleted'   => 0,
                        'glpi_networknames.is_deleted'   => 0,
                    ],
                ])->current();

                return (int) $result['cpt'];
        }
        return 0;
    }

    public function getRights($interface = 'central')
    {
        $rights = parent::getRights($interface);
        // Rename READ and UPDATE right labels to match other assets
        $rights[READ] = __('View all');
        $rights[UPDATE] = __('Update all');
        return $rights;
    }
}
