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
 * Database Class
 **/
class Database extends CommonDBChild
{
    // From CommonDBTM
    public $auto_message_on_action = true;
    public static $rightname       = 'database';
    public static $mustBeAttached  = false;

    // From CommonDBChild
    public static $itemtype = DatabaseInstance::class;
    public static $items_id = 'databaseinstances_id';

    public static function getTypeName($nb = 0)
    {
        return _n('Database', 'Databases', $nb);
    }

    public function getCloneRelations(): array
    {
        return [
            Appliance_Item::class,
            Domain_Item::class,
        ];
    }

    public function rawSearchOptions()
    {

        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => static::getTypeName(1),
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => static::getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => 2,
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'datatype'           => 'number',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'is_active',
            'name'               => __('Active'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'size',
            'unit'               => 'auto',
            'name'               => __('Global size'),
            'datatype'           => 'number',
            'width'              => 1000,
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => static::getTable(),
            'field'              => 'is_recursive',
            'name'               => __('Child entities'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => static::getTable(),
            'field'              => 'is_onbackup',
            'name'               => __('Is on backup'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '10',
            'table'              => static::getTable(),
            'field'              => 'date_lastbackup',
            'name'               => __('Last backup date'),
            'datatype'           => 'date',
        ];

        $tab[] = [
            'id'                 => '11',
            'table'              => DatabaseInstance::getTable(),
            'field'              => 'name',
            'linkfield'          => '',
            'name'               => DatabaseInstance::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => Computer::getTable(),
            'field'              => 'name',
            'datatype'           => 'itemlink',
            'linkfield'          => 'items_id',
            'name'               => Computer::getTypeName(0),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => DatabaseInstance::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'item_itemtype',
                        'specific_itemtype'  => 'Computer',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => static::getTable(),
            'field'              => 'is_dynamic',
            'name'               => __('Dynamic'),
            'datatype'           => 'bool',
        ];

        return $tab;
    }

    /**
     * @return array
     */
    public static function rawSearchOptionsToAdd()
    {
        $tab = [];
        $name = self::getTypeName(Session::getPluralNumber());

        $tab[] = [
            'id'                 => 'database',
            'name'               => $name,
        ];

        $tab[] = [
            'id'                 => '167',
            'table'              => self::getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '166',
            'table'              => self::getTable(),
            'field'              => 'size',
            'name'               => sprintf(__('%1$s (%2$s)'), __('Size'), __('Mio')),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'integer',
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '169',
            'table'              => self::getTable(),
            'field'              => 'is_active',
            'linkfield'          => '',
            'name'               => __('Active'),
            'datatype'           => 'bool',
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'massiveaction'      => false,
            'forcegroupby'       => true,
            'searchtype'         => ['equals'],
        ];

        $tab[] = [
            'id'                 => '170',
            'table'              => self::getTable(),
            'field'              => 'is_onbackup',
            'linkfield'          => '',
            'name'               => __('Is on backup'),
            'datatype'           => 'bool',
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'massiveaction'      => false,
            'forcegroupby'       => true,
            'searchtype'         => ['equals'],
        ];

        $tab[] = [
            'id'                 => '172',
            'table'              => self::getTable(),
            'field'              => 'date_lastbackup',
            'name'               => __('Last backup date'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'date',
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '174',
            'table'              => self::getTable(),
            'field'              => 'is_dynamic',
            'linkfield'          => '',
            'name'               => __('Dynamic'),
            'datatype'           => 'bool',
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'massiveaction'      => false,
            'forcegroupby'       => true,
            'searchtype'         => ['equals'],
        ];

        return $tab;
    }

    public function prepareInputForAdd($input)
    {
        if (isset($input['date_lastbackup']) && empty($input['date_lastbackup'])) {
            unset($input['date_lastbackup']);
        }

        if (isset($input['size']) && empty($input['size'])) {
            unset($input['size']);
        }

        return parent::prepareInputForAdd($input);
    }

    public function useDeletedToLockIfDynamic()
    {
        return false;
    }
}
