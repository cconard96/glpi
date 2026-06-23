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

use Glpi\Features\AssignableItem;
use Glpi\Features\AssignableItemInterface;
use Glpi\Features\Clonable;
use Glpi\Features\Inventoriable;
use Glpi\Features\StateInterface;

class DatabaseInstance extends CommonDBTM implements AssignableItemInterface, StateInterface
{
    /** @use Clonable<static> */
    use Clonable;
    use Inventoriable;
    use Glpi\Features\State;
    use AssignableItem {
        prepareInputForAdd as prepareInputForAddAssignableItem;
    }

    // From CommonDBTM
    public $dohistory                   = true;
    public static $rightname            = 'database';
    protected $usenotepad               = true;
    protected static $forward_entity_to = ['Database'];

    public function getCloneRelations(): array
    {
        return [
            Appliance_Item::class,
            Contract_Item::class,
            Document_Item::class,
            Infocom::class,
            Notepad::class,
            KnowbaseItem_Item::class,
            Certificate_Item::class,
            Domain_Item::class,
            Database::class,
            ManualLink::class,
        ];
    }

    public function useDeletedToLockIfDynamic()
    {
        return false;
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Database instance', 'Database instances', $nb);
    }

    public function getDatabases(): array
    {
        global $DB;
        $dbs = [];

        $iterator = $DB->request([
            'FROM' => Database::getTable(),
            'WHERE' => [
                'databaseinstances_id' => $this->fields['id'],
                'is_deleted' => 0,
            ],
        ]);

        foreach ($iterator as $row) {
            $dbs[$row['id']] = $row;
        }

        return $dbs;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->prepareInputForAddAssignableItem($input);
        if ($input === false) {
            return false;
        }
        if (isset($input['date_lastbackup']) && empty($input['date_lastbackup'])) {
            unset($input['date_lastbackup']);
        }
        return $input;
    }

    public function rawSearchOptions()
    {

        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false, // implicit field is id
            'datatype'           => 'number',
        ];

        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
            'id'                 => '4',
            'table'              => DatabaseInstanceType::getTable(),
            'field'              => 'name',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '168',
            'table'              => self::getTable(),
            'field'              => 'port',
            'name'               => _n('Port', 'Ports', 1),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'integer',
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'               => '5',
            'table'            => DatabaseInstance::getTable(),
            'field'            => 'items_id',
            'name'             => _n('Item', 'Items', 1),
            'nosearch'         => true,
            'massiveaction'    => false,
            'forcegroupby'     => true,
            'datatype'         => 'specific',
            'searchtype'       => 'equals',
            'additionalfields' => ['itemtype'],
            'joinparams'       => ['jointype' => 'child'],
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => DatabaseInstance::getTable(),
            'field'              => 'version',
            'name'               => _n('Version', 'Versions', 1),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => DatabaseInstance::getTable(),
            'field'              => 'is_active',
            'name'               => __('Is active'),
            'massiveaction'      => false,
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '253',
            'table'              => DatabaseInstance::getTable(),
            'field'              => 'path',
            'name'               => __('Path'),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => DatabaseInstance::getTable(),
            'field'              => 'itemtype',
            'name'               => __('Item type'),
            'massiveaction'      => false,
            'datatype'           => 'itemtypename',
            'types'              => self::getTypes(),
        ];

        $tab[] = [
            'id'                 => '40',
            'table'              => DatabaseInstanceCategory::getTable(),
            'field'              => 'name',
            'name'               => _n('Category', 'Categories', 1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '41',
            'table'              => State::getTable(),
            'field'              => 'completename',
            'name'               => __('Status'),
            'datatype'           => 'dropdown',
            'condition'          => $this->getStateVisibilityCriteria(),
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => $this->getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '19',
            'table'              => $this->getTable(),
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '121',
            'table'              => $this->getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '171',
            'table'              => self::getTable(),
            'field'              => 'date_lastboot',
            'name'               => __('Last boot date'),
            'massiveaction'      => false,
            'datatype'           => 'date',
        ];

        $tab[] = [
            'id'                 => '23',
            'table'              => Manufacturer::getTable(),
            'field'              => 'name',
            'name'               => Manufacturer::getTypeName(1),
            'datatype'           => 'dropdown',
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

        $tab = array_merge($tab, Database::rawSearchOptionsToAdd());
        $tab = array_merge($tab, Notepad::rawSearchOptionsToAdd());

        return $tab;
    }

    /**
     * Get item types that can be linked to a database
     *
     * @param bool $all Get all possible types or only allowed ones
     *
     * @return array
     */
    public static function getTypes($all = false): array
    {
        global $CFG_GLPI;

        $types = $CFG_GLPI['databaseinstance_types'];

        foreach ($types as $key => $type) {
            if (!class_exists($type)) {
                continue;
            }

            if ($all === false && !$type::canView()) {
                unset($types[$key]);
            }
        }
        return $types;
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                Database::class,
            ]
        );
    }

    /**
     * @return bool
     */
    public function pre_purgeInventory()
    {
        return true;
    }
}
