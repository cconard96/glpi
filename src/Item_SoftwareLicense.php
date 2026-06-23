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
 * Manage link between items and software licenses.
 */
class Item_SoftwareLicense extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = 'itemtype';
    public static $items_id_1 = 'items_id';

    public static $itemtype_2 = SoftwareLicense::class;
    public static $items_id_2 = 'softwarelicenses_id';


    public function post_addItem()
    {
        SoftwareLicense::updateValidityIndicator($this->fields['softwarelicenses_id']);

        parent::post_addItem();
    }

    public function post_deleteFromDB()
    {
        SoftwareLicense::updateValidityIndicator($this->fields['softwarelicenses_id']);

        parent::post_deleteFromDB();
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics'),
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
            'id'                 => '4',
            'table'              => 'glpi_softwarelicenses',
            'field'              => 'name',
            'name'               => _n('License', 'Licenses', 1),
            'datatype'           => 'dropdown',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'items_id',
            'name'               => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'datatype'           => 'specific',
            'comments'           => true,
            'nosort'             => true,
            'massiveaction'      => false,
            'additionalfields'   => ['itemtype'],
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'itemtype',
            'name'               => _x('software', 'Request source'),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'move_license':
                $input = $ma->getInput();
                if (isset($input['softwarelicenses_id'])) {
                    foreach ($ids as $id) {
                        if ($item->can($id, UPDATE)) {
                            //Process rules
                            if (
                                $item->update(['id'  => $id,
                                    'softwarelicenses_id'
                                           => $input['softwarelicenses_id'],
                                ])
                            ) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }
                return;

            case 'install':
                $csl = new self();
                $csv = new Item_SoftwareVersion();
                foreach ($ids as $id) {
                    if ($csl->getFromDB($id)) {
                        $sl = new SoftwareLicense();

                        if ($sl->getFromDB($csl->fields["softwarelicenses_id"])) {
                            $version = 0;
                            if ($sl->fields["softwareversions_id_use"] > 0) {
                                $version = $sl->fields["softwareversions_id_use"];
                            } else {
                                $version = $sl->fields["softwareversions_id_buy"];
                            }
                            if ($version > 0) {
                                $params = [
                                    'items_id'  => $csl->fields['items_id'],
                                    'itemtype'  => $csl->fields['itemtype'],
                                    'softwareversions_id' => $version,
                                ];
                                //Get software name and manufacturer
                                if ($csv->can(-1, CREATE, $params)) {
                                    //Process rules
                                    if ($csv->add($params)) {
                                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                                    } else {
                                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                    }
                                } else {
                                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                                }
                            } else {
                                Session::addMessageAfterRedirect(__s('A version is required!'), false, ERROR);
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    }
                }
                return;

            case 'add_item':
                $item_licence = new Item_SoftwareLicense();
                $input = $ma->getInput();

                foreach ($ids as $id) {
                    $license = new SoftwareLicense();
                    if ($license->getFromDB($id)) {
                        $number = Item_SoftwareLicense::countForLicense($license->getID());
                        $number += SoftwareLicense_User::countForLicense($license->getID());

                        if ($input['itemtype'] == User::class) {
                            $item_licence = new SoftwareLicense_User();
                            if (
                                $license->getField('number') != -1
                                && $number >= $license->getField('number')
                                && !$license->getField('allow_overquota')
                            ) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage(sprintf(__s('Maximum number of items reached for license "%s".'), htmlescape($license->getName())));
                                continue;
                            }

                            $input_data = [
                                'softwarelicenses_id'   => $id,
                                'users_id'             => $input['items_id'],
                            ];
                        } else {
                            $input_data = [
                                'softwarelicenses_id'   => $id,
                                'items_id'        => $input['items_id'],
                                'itemtype'        => $input['itemtype'],
                            ];
                        }

                        if ($item_licence->can(-1, UPDATE, $input_data)) {
                            if ($item_licence->add($input_data)) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * Get number of installed licenses of a license
     *
     * @param int $softwarelicenses_id license ID
     * @param int|string $entity       to search for item in (default = all entities)
     *                                     (default '') -1 means no entity restriction
     * @param string $itemtype             Item type to filter on. Use null for all itemtypes
     *
     * @return int number of installations
     **/
    public static function countForLicense($softwarelicenses_id, $entity = '', $itemtype = null)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT'    => ['itemtype'],
            'DISTINCT'  => true,
            'FROM'      => static::getTable(),
            'WHERE'     => [
                'softwarelicenses_id'   => $softwarelicenses_id,
            ],
        ]);

        $target_types = [];
        if ($itemtype !== null) {
            $target_types = [$itemtype];
        } else {
            foreach ($iterator as $data) {
                $target_types[] = $data['itemtype'];
            }
        }

        $count = 0;
        foreach ($target_types as $taget_itemtype) {
            if (!is_a($taget_itemtype, CommonDBTM::class, true)) {
                continue;
            }
            $itemtable = $taget_itemtype::getTable();
            $request = [
                'FROM'         => 'glpi_items_softwarelicenses',
                'COUNT'        => 'cpt',
                'INNER JOIN'   => [
                    $itemtable  => [
                        'FKEY'   => [
                            $itemtable                    => 'id',
                            'glpi_items_softwarelicenses' => 'items_id', [
                                'AND' => [
                                    'glpi_items_softwarelicenses.itemtype' => $taget_itemtype,
                                ],
                            ],
                        ],
                    ],
                ],
                'WHERE'        => [
                    'glpi_items_softwarelicenses.softwarelicenses_id'     => $softwarelicenses_id,
                    'glpi_items_softwarelicenses.is_deleted'              => 0,
                ],
            ];
            if ($entity !== -1) {
                $request['WHERE'] += getEntitiesRestrictCriteria($itemtable, '', $entity);
            }
            $item = new $taget_itemtype();
            if ($item->maybeDeleted()) {
                $request['WHERE']["$itemtable.is_deleted"] = 0;
            }
            if ($item->maybeTemplate()) {
                $request['WHERE']["$itemtable.is_template"] = 0;
            }
            $count += $DB->request($request)->current()['cpt'];
        }
        return $count;
    }

    /**
     * Get number of installed licenses of a software
     *
     * @param int $softwares_id software ID
     *
     * @return int number of installations
     **/
    public static function countForSoftware($softwares_id)
    {
        global $DB;

        $license_table = SoftwareLicense::getTable();
        $item_license_table = self::getTable(self::class);

        $iterator = $DB->request([
            'SELECT'    => ['itemtype'],
            'DISTINCT'  => true,
            'FROM'      => $item_license_table,
            'LEFT JOIN' => [
                $license_table => [
                    'FKEY'   => [
                        $license_table       => 'id',
                        $item_license_table  => 'softwarelicenses_id',
                    ],
                ],
            ],
            'WHERE'     => [
                'softwares_id'   => $softwares_id,
            ],
        ]);

        $target_types = [];
        foreach ($iterator as $data) {
            if (is_a($data['itemtype'], CommonDBTM::class, true)) {
                $target_types[] = $data['itemtype'];
            }
        }

        $count = 0;
        foreach ($target_types as $itemtype) {
            $itemtable = $itemtype::getTable();
            $request = [
                'FROM'         => 'glpi_softwarelicenses',
                'COUNT'        => 'cpt',
                'INNER JOIN'   => [
                    'glpi_items_softwarelicenses' => [
                        'FKEY'   => [
                            'glpi_softwarelicenses'          => 'id',
                            'glpi_items_softwarelicenses'    => 'softwarelicenses_id',
                        ],
                    ],
                    $itemtable  => [
                        'FKEY'   => [
                            $itemtable                    => 'id',
                            'glpi_items_softwarelicenses' => 'items_id', [
                                'AND' => [
                                    'glpi_items_softwarelicenses.itemtype' => $itemtype,
                                ],
                            ],
                        ],
                    ],
                ],
                'WHERE'        => [
                    'glpi_softwarelicenses.softwares_id'      => $softwares_id,
                    'glpi_items_softwarelicenses.is_deleted'  => 0,
                ] + getEntitiesRestrictCriteria($itemtable),
            ];
            $item = new $itemtype();
            if ($item->maybeDeleted()) {
                $request['WHERE']["$itemtable.is_deleted"] = 0;
            }
            if ($item->maybeTemplate()) {
                $request['WHERE']["$itemtable.is_template"] = 0;
            }
            $count += $DB->request($request)->current()['cpt'];
        }
        return $count;
    }

    /**
     * Update license associated on a computer
     *
     * @param int $licID               ID of the install software lienk
     * @param int $softwarelicenses_id ID of the new license
     *
     * @return void
     **/
    public function upgrade($licID, $softwarelicenses_id)
    {
        if ($this->getFromDB($licID)) {
            $items_id = $this->fields['items_id'];
            $itemtype = $this->fields['itemtype'];
            $this->delete(['id' => $licID]);
            $this->add([
                'items_id'              => $items_id,
                'itemtype'              => $itemtype,
                'softwarelicenses_id'   => $softwarelicenses_id,
            ]);
        }
    }

    /**
     * Get licenses list corresponding to an installation
     *
     * @param string $itemtype          Type of item
     * @param int $items_id         ID of the item
     * @param int $softwareversions_id ID of the version
     *
     * @return array
     **/
    public static function getLicenseForInstallation($itemtype, $items_id, $softwareversions_id)
    {
        global $DB;

        $lic = [];
        $item_license_table = self::getTable(self::class);

        $iterator = $DB->request([
            'SELECT'       => [
                'glpi_softwarelicenses.*',
                'glpi_softwarelicensetypes.name AS type',
            ],
            'FROM'         => 'glpi_softwarelicenses',
            'INNER JOIN'   => [
                $item_license_table  => [
                    'FKEY'   => [
                        $item_license_table     => 'softwarelicenses_id',
                        'glpi_softwarelicenses' => 'id',
                    ],
                ],
            ],
            'LEFT JOIN'    => [
                'glpi_softwarelicensetypes'   => [
                    'FKEY'   => [
                        'glpi_softwarelicenses'       => 'softwarelicensetypes_id',
                        'glpi_softwarelicensetypes'   => 'id',
                    ],
                ],
            ],
            'WHERE'        => [
                $item_license_table . '.itemtype'  => $itemtype,
                $item_license_table . '.items_id'  => $items_id,
                'OR'                                => [
                    'glpi_softwarelicenses.softwareversions_id_use' => $softwareversions_id,
                    'glpi_softwarelicenses.softwareversions_id_buy' => $softwareversions_id,
                ],
            ],
        ]);

        foreach ($iterator as $data) {
            $lic[$data['id']] = $data;
        }
        return $lic;
    }

    /**
     * Count number of licenses for a software
     *
     * @since 0.85
     *
     * @param int $softwares_id Software ID
     *
     * @return int
     **/
    public static function countLicenses($softwares_id)
    {
        global $DB;

        $result = $DB->request([
            'FROM'   => 'glpi_softwarelicenses',
            'COUNT'  => 'cpt',
            'WHERE'  => [
                'softwares_id' => $softwares_id,
            ] + getEntitiesRestrictCriteria('glpi_softwarelicenses'),
        ])->current();
        return $result['cpt'];
    }
}
