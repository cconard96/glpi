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

class Item_SoftwareVersion extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = 'itemtype';
    public static $items_id_1 = 'items_id';
    public static $itemtype_2 = SoftwareVersion::class;
    public static $items_id_2 = 'softwareversions_id';


    public static $log_history_1_add    = Log::HISTORY_INSTALL_SOFTWARE;
    public static $log_history_1_delete = Log::HISTORY_UNINSTALL_SOFTWARE;

    public static $log_history_2_add    = Log::HISTORY_INSTALL_SOFTWARE;
    public static $log_history_2_delete = Log::HISTORY_UNINSTALL_SOFTWARE;


    public static function getTypeName($nb = 0)
    {
        return _n('Installation', 'Installations', $nb);
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
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'items_id',
            'name'               => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'massiveaction'      => false,
            'nosort'             => true,
            'datatype'           => 'specific',
            'additionalfields'   => ['itemtype'],
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => 'glpi_softwareversions',
            'field'              => 'name',
            'name'               => _n('Version', 'Versions', 1),
            'datatype'           => 'dropdown',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'itemtype',
            'name'               => _x('software', 'Request source'),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    private function prepareInputForAddAndUpdate(array $input, bool $is_add): array|false
    {
        if (!isset($input['itemtype'], $input['items_id'])) {
            return $is_add ? false : $input;
        }
        $itemtype = $input['itemtype'];
        /** @var CommonDBTM $item */
        $item = getItemForItemtype($itemtype);
        if (
            (!isset($input['is_template_item']) && $item->maybeTemplate())
            || (!isset($input['is_deleted_item']) && $item->maybeDeleted())
        ) {
            if ($item->getFromDB($input['items_id'])) {
                if ($item->maybeTemplate()) {
                    $input['is_template_item'] = $item->getField('is_template');
                }
                if ($item->maybeDeleted()) {
                    $input['is_deleted_item']  = $item->getField('is_deleted');
                }
            } else {
                return false;
            }
        }
        return $input;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->prepareInputForAddAndUpdate($input, true);
        if ($input === false) {
            return false;
        }
        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->prepareInputForAddAndUpdate($input, false);
        if ($input === false) {
            return false;
        }
        return parent::prepareInputForUpdate($input);
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'move_version':
                $input = $ma->getInput();
                if (isset($input['softwareversions_id'])) {
                    foreach ($ids as $id) {
                        if ($item->can($id, UPDATE)) {
                            //Process rules
                            if (
                                $item->update(['id' => $id,
                                    'softwareversions_id'
                                                  => $input['softwareversions_id'],
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

            case 'add':
                $itemtoadd = new Item_SoftwareVersion();
                if (isset($_POST['peer_softwareversions_id'])) {
                    foreach ($ids as $id) {
                        if ($item->can($id, UPDATE)) {
                            //Process rules
                            if (
                                $itemtoadd->add([
                                    'items_id'              => $id,
                                    'itemtype'              => $item::getType(),
                                    'softwareversions_id'   => $_POST['peer_softwareversions_id'],
                                ])
                            ) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($itemtoadd->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($itemtoadd->getErrorMessage(ERROR_RIGHT));
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }
                return;
        }

        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param int $items_id
     *
     * @return bool
     */
    public function updateDatasForItem($itemtype, $items_id)
    {
        global $DB;

        $item = getItemForItemtype($itemtype);
        if ($item->getFromDB($items_id)) {
            return $DB->update(
                static::getTable(),
                [
                    'is_template_item'  => $item->maybeTemplate() ? $item->getField('is_template') : 0,
                    'is_deleted_item'   => $item->maybeDeleted() ? $item->getField('is_deleted') : 0,
                ],
                [
                    'items_id' => $items_id,
                    'itemtype' => $itemtype,
                ]
            );
        }
        return false;
    }

    /**
     * Get number of installed licenses of a version
     *
     * @param int          $softwareversions_id version ID
     * @param string|int[] $entity              to search for item in ('' = all active entities)
     *
     * @return int number of installations
     **/
    public static function countForVersion($softwareversions_id, $entity = '')
    {
        global $DB;

        $item_version_table = self::getTable(self::class);
        $iterator = $DB->request([
            'SELECT'    => ['itemtype'],
            'DISTINCT'  => true,
            'FROM'      => $item_version_table,
            'WHERE'     => [
                'softwareversions_id'   => $softwareversions_id,
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
                'FROM'         => 'glpi_items_softwareversions',
                'COUNT'        => 'cpt',
                'INNER JOIN'   => [
                    $itemtable  => [
                        'FKEY'   => [
                            $itemtable                    => 'id',
                            'glpi_items_softwareversions' => 'items_id', [
                                'AND' => [
                                    'glpi_items_softwareversions.itemtype' => $itemtype,
                                ],
                            ],
                        ],
                    ],
                ],
                'WHERE'        => [
                    'glpi_items_softwareversions.softwareversions_id'     => $softwareversions_id,
                    'glpi_items_softwareversions.is_deleted'              => 0,
                ] + getEntitiesRestrictCriteria($itemtable, '', $entity),
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
     * Get number of installed versions of a software
     *
     * @param int $softwares_id software ID
     *
     * @return number of installations
     **/
    public static function countForSoftware($softwares_id)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT'    => ['itemtype'],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_softwareversions',
            'INNER JOIN'   => [
                'glpi_items_softwareversions'   => [
                    'FKEY'   => [
                        'glpi_items_softwareversions' => 'softwareversions_id',
                        'glpi_softwareversions'       => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'softwares_id' => $softwares_id,
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
            if (!getItemForItemtype($itemtype)) {
                trigger_error(
                    "Itemtype $itemtype not found",
                    E_USER_WARNING
                );
                continue;
            }
            $itemtable = $itemtype::getTable();
            $request = [
                'FROM'         => 'glpi_softwareversions',
                'COUNT'        => 'cpt',
                'INNER JOIN'   => [
                    'glpi_items_softwareversions'   => [
                        'FKEY'   => [
                            'glpi_items_softwareversions' => 'softwareversions_id',
                            'glpi_softwareversions'       => 'id',
                        ],
                    ],
                    $itemtable  => [
                        'FKEY'   => [
                            $itemtable                    => 'id',
                            'glpi_items_softwareversions' => 'items_id', [
                                'AND' => [
                                    'glpi_items_softwareversions.itemtype' => $itemtype,
                                ],
                            ],
                        ],
                    ],
                ],
                'WHERE'        => [
                    'glpi_softwareversions.softwares_id'      => $softwares_id,
                    'glpi_items_softwareversions.is_deleted'  => 0,
                ] + getEntitiesRestrictCriteria($itemtable, '', '', true),
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
     * Get software related to a given item
     *
     * @param CommonDBTM $item  Item instance
     * @param ?string     $sort  Field to sort on
     * @param ?string     $order Sort order
     * @param array       $filters
     *
     * @return DBmysqlIterator
     */
    public static function getFromItem(CommonDBTM $item, $sort = null, $order = null, array $filters = []): DBmysqlIterator
    {
        global $DB;

        $selftable     = self::getTable(self::class);

        $select = [
            'glpi_softwares.softwarecategories_id',
            'glpi_softwares.name AS softname',
            "glpi_items_softwareversions.id",
            'glpi_states.name as state',
            'glpi_softwareversions.id AS verid',
            'glpi_softwareversions.softwares_id',
            'glpi_softwareversions.name AS version',
            'glpi_softwareversions.arch AS arch',
            'glpi_softwares.is_valid AS softvalid',
            'glpi_items_softwareversions.date_install AS dateinstall',
            "$selftable.is_dynamic",
        ];

        $request = [
            'SELECT'    => $select,
            'FROM'      => $selftable,
            'LEFT JOIN' => [
                'glpi_softwareversions' => [
                    'FKEY'   => [
                        $selftable              => 'softwareversions_id',
                        'glpi_softwareversions' => 'id',
                    ],
                ],
                'glpi_states'  => [
                    'FKEY'   => [
                        'glpi_softwareversions' => 'states_id',
                        'glpi_states'           => 'id',
                    ],
                ],
                'glpi_softwares'  => [
                    'FKEY'   => [
                        'glpi_softwareversions' => 'softwares_id',
                        'glpi_softwares'        => 'id',
                    ],
                ],
                'glpi_softwarecategories' => [
                    'FKEY'   => [
                        'glpi_softwares'          => 'softwarecategories_id',
                        'glpi_softwarecategories' => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                "{$selftable}.items_id" => $item->getField('id'),
                "{$selftable}.itemtype" => $item->getType(),
            ] + getEntitiesRestrictCriteria('glpi_softwares', '', '', true),
            'ORDER'     => ['softname', 'version'],
        ];

        if (count($filters)) {
            if (($filters['name'] ?? "") !== '') {
                $request['WHERE']['glpi_softwares.name'] = ['LIKE', '%' . $filters['name'] . '%'];
            }
            if (($filters['state'] ?? "") !== '') {
                $request['WHERE']['glpi_states.name'] = ['LIKE', '%' . $filters['state'] . '%'];
            }
            if (($filters['version'] ?? "") !== '') {
                $request['WHERE']['glpi_softwareversions.name'] = ['LIKE', '%' . $filters['version'] . '%'];
            }
            if (($filters['arch'] ?? "") !== '') {
                $request['WHERE']['glpi_softwareversions.arch'] = ['LIKE', '%' . $filters['arch'] . '%'];
            }
            if (isset($filters['is_dynamic']) && $filters['is_dynamic'] !== '') {
                $request['WHERE']["$selftable.is_dynamic"] = $filters['is_dynamic'];
            }
            if (($filters['software_category'] ?? "") !== '') {
                $request['WHERE']['glpi_softwarecategories.name'] = ['LIKE', '%' . $filters['software_category'] . '%'];
            }
            if (($filters['date_install'] ?? "") !== '') {
                $request['WHERE']['glpi_items_softwareversions.date_install'] = $filters['date_install'];
            }
        }

        if ($item->maybeDeleted()) {
            $request['WHERE']["{$selftable}.is_deleted"] = 0;
        }

        $crit = Session::getSavedOption(self::class, 'criterion', -1);
        if ($crit > -1) {
            $request['WHERE']['glpi_softwares.softwarecategories_id'] = (int) $crit;
        }

        return $DB->request($request);
    }

    /**
     * Display a installed software for a category
     *
     * @param array   $data         data used to display
     * @param string  $itemtype     Type of the item
     * @param int $items_id     ID of the item
     * @param int $withtemplate template case of the view process
     * @param bool $canedit      user can edit software ?
     * @param bool $display      display and calculate if true or just calculate
     *
     * @return int[] Found licenses ids
     **/
    private static function softwareByCategory(
        $data,
        $itemtype,
        $items_id,
        $withtemplate,
        $canedit,
        $display
    ) {
        global $DB;

        $ID    = $data["id"];
        $verid = $data["verid"];

        if ($display) {
            echo "<tr class='tab_bg_1'>";
            if ($canedit) {
                echo "<td>";
                Html::showMassiveActionCheckBox(self::class, $ID);
                echo "</td>";
            }
            echo "<td>";
            echo "<a href='" . htmlescape(Software::getFormURLWithID($data['softwares_id'])) . "'>";
            echo  htmlescape(
                $_SESSION["glpiis_ids_visible"]
                ? sprintf(__('%1$s (%2$s)'), $data["softname"], $data['softwares_id'])
                : $data["softname"]
            );
            echo "</a></td>";
            echo "<td>" . htmlescape($data["state"]) . "</td>";

            echo "<td>" . htmlescape($data["version"]);
            echo "</td><td>";
        }

        $iterator = $DB->request([
            'SELECT'       => [
                'glpi_softwarelicenses.*',
                'glpi_softwarelicensetypes.name AS type',
            ],
            'FROM'         => 'glpi_items_softwarelicenses',
            'INNER JOIN'   => [
                'glpi_softwarelicenses' => [
                    'FKEY'   => [
                        'glpi_items_softwarelicenses'   => 'softwarelicenses_id',
                        'glpi_softwarelicenses'             => 'id',
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
                "glpi_items_softwarelicenses.items_id"    => $items_id,
                'glpi_items_softwarelicenses.itemtype'    => $itemtype,
                'OR'                                            => [
                    'glpi_softwarelicenses.softwareversions_id_use' => $verid,
                    [
                        'glpi_softwarelicenses.softwareversions_id_use' => 0,
                        'glpi_softwarelicenses.softwareversions_id_buy' => $verid,
                    ],
                ],
            ],
        ]);

        $licids = [];
        foreach ($iterator as $licdata) {
            $licids[]  = $licdata['id'];
            $licserial = $licdata['serial'];

            if (!empty($licdata['type'])) {
                $licserial = sprintf(__('%1$s (%2$s)'), $licserial, $licdata['type']);
            }

            if ($display) {
                echo "<span class='b'>" . htmlescape($licdata['name']) . "</span> - " . htmlescape($licserial);

                $link_item = Toolbox::getItemTypeFormURL('SoftwareLicense');
                $link      = $link_item . "?id=" . $licdata['id'];
                $comment   = "<table><tr><td>" . __s('Name') . "</td><td>" . htmlescape($licdata['name']) . "</td></tr>"
                         . "<tr><td>" . __s('Serial number') . "</td><td>" . htmlescape($licdata['serial']) . "</td></tr>"
                         . "<tr><td>" . __s('Comments') . '</td><td>' . htmlescape($licdata['comment']) . "</td></tr>"
                         . "</table>";

                Html::showToolTip($comment, ['link' => $link]);
                echo "<br>";
            }
        }

        if ($display) {
            if (!count($licids)) {
                echo "&nbsp;";
            }

            echo "</td>";

            echo "<td>" . htmlescape(Html::convDate($data['dateinstall'])) . "</td>";
            echo "<td>" . htmlescape($data['arch']) . "</td>";

            if (isset($data['is_dynamic'])) {
                echo "<td>" . htmlescape(Dropdown::getYesNo($data['is_dynamic'])) . "</td>";
            }

            echo "<td>" . htmlescape(Dropdown::getDropdownName("glpi_softwarecategories", $data['softwarecategories_id']));
            echo "</td>";
            echo "<td>" . htmlescape(Dropdown::getYesNo($data["softvalid"])) . "</td>";
            echo "<td></td>"; // empty td for filter column
            echo "</tr>\n";
        }

        return $licids;
    }

    /**
     * Update version installed on a item
     *
     * @param int $instID              ID of the installed software link
     * @param int $softwareversions_id ID of the new version
     * @param bool $dohistory           Do history ? (default 1)
     *
     * @return void
     **/
    public function upgrade($instID, $softwareversions_id, $dohistory = true)
    {
        if ($this->getFromDB($instID)) {
            $items_id = $this->fields['items_id'];
            $itemtype = $this->fields['itemtype'];
            $this->delete(['id' => $instID]);
            $this->add([
                'itemtype'              => $itemtype,
                'items_id'              => $items_id,
                'softwareversions_id'   => $softwareversions_id,
            ]);
        }
    }

    protected static function getListForItemParams(CommonDBTM $item, $noent = false)
    {
        $table = self::getTable(self::class);

        $params = parent::getListForItemParams($item);
        unset($params['SELECT'], $params['ORDER']);
        $params['WHERE'] = [
            $table . '.items_id'   => $item->getID(),
            $table . '.itemtype'   => $item::getType(),
            $table . '.is_deleted' => 0,
        ];
        if ($noent === false) {
            $params['WHERE'] += getEntitiesRestrictCriteria($table, '', '', 'auto');
        }
        return $params;
    }

    public static function countForItem(CommonDBTM $item)
    {
        global $DB;

        $params = self::getListForItemParams($item);
        unset($params['SELECT'], $params['ORDER']);
        $params['COUNT'] = 'cpt';
        $iterator = $DB->request($params);
        return $iterator->current()['cpt'];
    }
}
