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
 * @since 11.0
 */
class ItemAntivirus extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';
    public $dohistory       = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Antivirus', 'Antiviruses', $nb);
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
            'table'              => $this->getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'antivirus_version',
            'name'               => _n('Version', 'Versions', 1),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'signature_version',
            'name'               => __('Signature database version'),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => $this->getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'itemantivirus_types',
            'massiveaction'      => false,
        ];

        return $tab;
    }

    /**
     * @return array
     */
    public static function rawSearchOptionsToAdd()
    {
        $tab = [];
        $name = _n('Antivirus', 'Antiviruses', Session::getPluralNumber());

        $tab[] = [
            'id'                 => 'antivirus',
            'name'               => $name,
        ];

        $tab[] = [
            'id'                 => '167',
            'table'              => static::getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
            'searchtype'         => ['contains'],
        ];

        $tab[] = [
            'id'                 => '168',
            'table'              => static::getTable(),
            'field'              => 'antivirus_version',
            'name'               => _n('Version', 'Versions', 1),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'datatype'           => 'text',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        $tab[] = [
            'id'                 => '169',
            'table'              => static::getTable(),
            'field'              => 'is_active',
            'linkfield'          => '',
            'name'               => __('Active'),
            'datatype'           => 'bool',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
            'massiveaction'      => false,
            'forcegroupby'       => true,
            'usehaving'          => true,
            'searchtype'         => ['equals'],
        ];

        $tab[] = [
            'id'                 => '170',
            'table'              => static::getTable(),
            'field'              => 'is_uptodate',
            'linkfield'          => '',
            'name'               => __('Is up to date'),
            'datatype'           => 'bool',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
            'massiveaction'      => false,
            'forcegroupby'       => true,
            'usehaving'          => true,
            'searchtype'         => ['equals'],
        ];

        $tab[] = [
            'id'                 => '171',
            'table'              => static::getTable(),
            'field'              => 'signature_version',
            'name'               => __('Signature database version'),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'datatype'           => 'text',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        $tab[] = [
            'id'                 => '172',
            'table'              => static::getTable(),
            'field'              => 'date_expiration',
            'name'               => __('Expiration date'),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'massiveaction'      => false,
            'datatype'           => 'date',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        return $tab;
    }

    public function prepareInputForAdd($input)
    {
        $input = parent::prepareInputForAdd($input);

        if (isset($input['date_expiration']) && empty($input['date_expiration'])) {
            $input['date_expiration'] = 'NULL';
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = parent::prepareInputForUpdate($input);

        if (isset($input['date_expiration']) && empty($input['date_expiration'])) {
            $input['date_expiration'] = 'NULL';
        }

        return $input;
    }
}
