<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @copyright 2010-2022 by the FusionInventory Development Team.
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
use Glpi\Application\View\TemplateRenderer;
use Glpi\Features\AssignableItem;
use Glpi\Features\AssignableItemInterface;
use Glpi\Features\Inventoriable;

/**
 * Not managed devices from inventory
 */
class Unmanaged extends CommonDBTM implements AssignableItemInterface
{
    use Inventoriable;
    use Glpi\Features\State;
    use AssignableItem;

    // From CommonDBTM
    public $dohistory                   = true;
    public static $rightname                   = 'unmanaged';

    public static function getTypeName($nb = 0)
    {
        return _n('Unmanaged asset', 'Unmanaged assets', $nb);
    }

    public static function getSectorizedDetails(): array
    {
        return ['assets', self::class];
    }

    public static function getLogDefaultServiceName(): string
    {
        return 'inventory';
    }

    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong)
         ->addStandardTab(NetworkPort::class, $ong, $options)
         ->addStandardTab(Domain_Item::class, $ong, $options)
         ->addStandardTab(Lock::class, $ong, $options)
         ->addStandardTab(RuleMatchedLog::class, $ong, $options)
         ->addStandardTab(Log::class, $ong, $options);
        return $ong;
    }


    /**
     * Print the unmanaged form
     *
     * @param $ID integer ID of the item
     * @param $options array
     *     - target filename : where to go when done.
     *     - withtemplate boolean : template or basic item
     *
     * @return boolean item found
     **/
    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('pages/assets/unmanaged.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);
        return true;
    }


    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'        => '2',
            'table'     => $this->getTable(),
            'field'     => 'id',
            'name'      => __('ID'),
        ];

        $tab[] = [
            'id'        => '3',
            'table'     => 'glpi_locations',
            'field'     => 'name',
            'linkfield' => 'locations_id',
            'name'      => Location::getTypeName(1),
            'datatype'  => 'dropdown',
        ];

        $tab[] = [
            'id'           => '4',
            'table'        => $this->getTable(),
            'field'        => 'serial',
            'name'         => __('Serial Number'),
        ];

        $tab[] = [
            'id'           => '5',
            'table'        => $this->getTable(),
            'field'        => 'otherserial',
            'name'         => __('Inventory number'),
        ];

        $tab[] = [
            'id'           => '6',
            'table'        => $this->getTable(),
            'field'        => 'contact',
            'name'         => Contact::getTypeName(1),
        ];

        $tab[] = [
            'id'        => '7',
            'table'     => $this->getTable(),
            'field'     => 'hub',
            'name'      => __('Network hub'),
            'datatype'  => 'bool',
        ];

        $tab[] = [
            'id'        => '8',
            'table'     => 'glpi_entities',
            'field'     => 'completename',
            'linkfield' => 'entities_id',
            'name'      => Entity::getTypeName(1),
            'datatype'  => 'dropdown',
        ];

        $tab[] = [
            'id'        => '10',
            'table'     => $this->getTable(),
            'field'     => 'comment',
            'name'      => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'  => 'text',
        ];

        $tab[] = [
            'id'        => '13',
            'table'     => $this->getTable(),
            'field'     => 'itemtype',
            'name'      => _n('Type', 'Types', 1),
            'datatype'  => 'dropdown',
        ];

        $tab[] = [
            'id'        => '14',
            'table'     => $this->getTable(),
            'field'     => 'date_mod',
            'name'      => __('Last update'),
            'datatype'  => 'datetime',
        ];

        $tab[] = [
            'id'        => '15',
            'table'     => $this->getTable(),
            'field'     => 'sysdescr',
            'name'      => __('Sysdescr'),
            'datatype'  => 'text',
        ];

        $tab[] = [
            'id'           => '18',
            'table'        => $this->getTable(),
            'field'        => 'ip',
            'name'         => __('IP'),
        ];

        $tab[] = [
            'id'                 => '31',
            'table'              => State::getTable(),
            'field'              => 'completename',
            'name'               => __('Status'),
            'datatype'           => 'dropdown',
            'condition'          => $this->getStateVisibilityCriteria(),
        ];

        $tab[] = [
            'id'                 => '24',
            'table'              => User::getTable(),
            'field'              => 'name',
            'linkfield'          => 'users_id_tech',
            'name'               => __('Technician in charge'),
            'datatype'           => 'dropdown',
            'right'              => 'own_ticket',
        ];

        $tab[] = [
            'id'                 => '49',
            'table'              => Group::getTable(),
            'field'              => 'completename',
            'linkfield'          => 'groups_id',
            'name'               => __('Group in charge'),
            'condition'          => ['is_assign' => 1],
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_groups_items',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'condition'          => ['NEWTABLE.type' => Group_Item::GROUP_TYPE_TECH],
                    ],
                ],
            ],
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '70',
            'table'              => User::getTable(),
            'field'              => 'name',
            'name'               => User::getTypeName(1),
            'datatype'           => 'dropdown',
            'right'              => 'all',
        ];

        $tab[] = [
            'id'                 => '71',
            'table'              => Group::getTable(),
            'field'              => 'completename',
            'name'               => Group::getTypeName(1),
            'condition'          => ['is_itemgroup' => 1],
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_groups_items',
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                        'condition'          => ['NEWTABLE.type' => Group_Item::GROUP_TYPE_NORMAL],
                    ],
                ],
            ],
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    public static function getIcon()
    {
        return "ti ti-question-mark";
    }

    public function getSpecificMassiveActions($checkitem = null)
    {
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (self::canUpdate()) {
            $actions['Unmanaged' . MassiveAction::CLASS_ACTION_SEPARATOR . 'convert']    = __s('Convert');
        }
        return $actions;
    }

    public static function getMassiveActionsForItemtype(
        array &$actions,
        $itemtype,
        $is_deleted = false,
        ?CommonDBTM $checkitem = null
    ) {
        if (self::canUpdate()) {
            $actions['Unmanaged' . MassiveAction::CLASS_ACTION_SEPARATOR . 'convert']    = __s('Convert');
        }
    }

    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;
        switch ($ma->getAction()) {
            case 'convert':
                echo __('Select an itemtype: ') . ' ';
                Dropdown::showItemType($CFG_GLPI['inventory_types'], [
                    'display_emptychoice' => false,
                ]);
                break;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;
        switch ($ma->getAction()) {
            case 'convert':
                $unmanaged = new self();
                foreach ($ids as $id) {
                    $itemtype = $_POST['itemtype'];
                    $new_asset_id = $unmanaged->convert($id, $itemtype);
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    if ($ma->isFromSingleItem()) {
                        $ma->setRedirect($itemtype::getFormURLWithID($new_asset_id));
                    } else {
                        $ma->setRedirect($item::getSearchURL());
                    }
                }
                break;
        }
    }

    /**
     * Convert to a managed asset
     *
     * @param int         $items_id ID of Unmanaged equipment
     * @param string|null $itemtype Item type to convert to. Will take Unmanaged value if null
     */
    public function convert(int $items_id, ?string $itemtype = null): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $this->getFromDB($items_id);
        $netport = new NetworkPort();
        $rulematch = new RuleMatchedLog();
        $lockfield = new Lockedfield();

        $iterator_np = $DB->request([
            'SELECT' => ['id'],
            'FROM' => NetworkPort::getTable(),
            'WHERE' => [
                'itemtype' => self::getType(),
                'items_id' => $items_id,
            ],
        ]);

        $iterator_rml = $DB->request([
            'SELECT' => ['id'],
            'FROM' => RuleMatchedLog::getTable(),
            'WHERE' => [
                'itemtype' => self::getType(),
                'items_id' => $items_id,
            ],
        ]);

        $iterator_lf = $DB->request([
            'SELECT' => ['id'],
            'FROM' => Lockedfield::getTable(),
            'WHERE' => [
                'itemtype' => self::getType(),
                'items_id' => $items_id,
            ],
        ]);

        if (!empty($this->fields['itemtype'])) {
            $itemtype = $this->fields['itemtype'];
        }

        $asset = getItemForItemtype($itemtype);
        $asset_data = [
            'name'          => $this->fields['name'],
            'entities_id'   => $this->fields['entities_id'],
            'serial'        => $this->fields['serial'],
            'uuid'          => $this->fields['uuid'] ?? null,
            'is_dynamic'    => 1,
        ] + $this->fields;
        //do not keep Unmanaged ID
        unset($asset_data['id']);

        $assets_id = $asset->add($asset_data);

        foreach ($iterator_np as $row) {
            $row += [
                'items_id' => $assets_id,
                'itemtype' => $itemtype,
            ];
            $netport->update($row);
        }

        foreach ($iterator_rml as $row) {
            $row += [
                'items_id' => $assets_id,
                'itemtype' => $itemtype,
            ];
            $rulematch->update($row);
        }

        foreach ($iterator_lf as $row) {
            $row += [
                'items_id' => $assets_id,
                'itemtype' => $itemtype,
            ];
            $lockfield->update($row);
        }
        $this->deleteFromDB(true);
        return $assets_id;
    }

    public function useDeletedToLockIfDynamic()
    {
        return false;
    }
}
