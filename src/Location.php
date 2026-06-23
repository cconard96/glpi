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

use Glpi\Features\Clonable;

/// Location class
class Location extends CommonTreeDropdown
{
    /** @use Clonable<static> */
    use Clonable;

    // From CommonDBTM
    public $dohistory          = true;
    public $can_be_translated  = true;

    public static $rightname          = 'location';


    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'code',
                'label' => __('Code'),
                'type'  => 'text',
                'list'  => true,
            ], [
                'name'  => 'alias',
                'label' => __('Alias'),
                'type'  => 'text',
                'list'  => true,
            ], [
                'name'  => self::getForeignKeyField(),
                'label' => __('As child of'),
                'type'  => 'parent',
                'list'  => false,
            ], [
                'name'   => 'address',
                'label'  => __('Address'),
                'type'   => 'text',
                'list'   => true,
            ], [
                'name'   => 'postcode',
                'label'  => __('Postal code'),
                'type'   => 'text',
                'list'   => true,
            ], [
                'name'   => 'town',
                'label'  => __('Town'),
                'type'   => 'text',
                'list'   => true,
            ], [
                'name'   => 'state',
                'label'  => _x('location', 'State'),
                'type'   => 'text',
                'list'   => true,
            ], [
                'name'   => 'country',
                'label'  => __('Country'),
                'type'   => 'text',
                'list'   => true,
            ], [
                'name'  => 'building',
                'label' => __('Building number'),
                'type'  => 'text',
                'list'  => true,
            ], [
                'name'  => 'room',
                'label' => __('Room number'),
                'type'  => 'text',
                'list'  => true,
            ], [
                'name'  => 'latitude',
                'label' => __('Latitude'),
                'type'  => 'text',
                'list'  => true,
            ], [
                'name'  => 'longitude',
                'label' => __('Longitude'),
                'type'  => 'text',
                'list'  => true,
            ], [
                'name'  => 'altitude',
                'label' => __('Altitude'),
                'type'  => 'text',
                'list'  => true,
            ], [
                'name'   => 'setlocation',
                'type'   => 'setlocation',
                'label'  => __('Location on map'),
                'list'   => false,
                'form_params' => [
                    'full_width' => true,
                ],
            ],
        ];
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Location', 'Locations', $nb);
    }

    /**
     * @return array
     */
    public static function rawSearchOptionsToAdd()
    {
        $tab = [];

        $tab[] = [
            'id'                 => '3',
            'table'              => 'glpi_locations',
            'field'              => 'completename',
            'name'               => self::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '101',
            'table'              => 'glpi_locations',
            'field'              => 'address',
            'name'               => __('Address'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '102',
            'table'              => 'glpi_locations',
            'field'              => 'postcode',
            'name'               => __('Postal code'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '103',
            'table'              => 'glpi_locations',
            'field'              => 'town',
            'name'               => __('Town'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '104',
            'table'              => 'glpi_locations',
            'field'              => 'state',
            'name'               => _x('location', 'State'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '105',
            'table'              => 'glpi_locations',
            'field'              => 'country',
            'name'               => __('Country'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '106',
            'table'              => 'glpi_locations',
            'field'              => 'code',
            'name'               => __('Location code'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '107',
            'table'              => 'glpi_locations',
            'field'              => 'alias',
            'name'               => __('Location alias'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '91',
            'table'              => 'glpi_locations',
            'field'              => 'building',
            'name'               => __('Building number'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '92',
            'table'              => 'glpi_locations',
            'field'              => 'room',
            'name'               => __('Room number'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '93',
            'table'              => 'glpi_locations',
            'field'              => 'comment',
            'name'               => __('Location comments'),
            'massiveaction'      => false,
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '998',
            'table'              => 'glpi_locations',
            'field'              => 'latitude',
            'name'               => __('Latitude'),
            'massiveaction'      => false,
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '999',
            'table'              => 'glpi_locations',
            'field'              => 'longitude',
            'name'               => __('Longitude'),
            'massiveaction'      => false,
            'datatype'           => 'text',
        ];

        return $tab;
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '11',
            'table'              => 'glpi_locations',
            'field'              => 'building',
            'name'               => __('Building number'),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => 'glpi_locations',
            'field'              => 'room',
            'name'               => __('Room number'),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '15',
            'table'              => 'glpi_locations',
            'field'              => 'address',
            'name'               => __('Address'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '17',
            'table'              => 'glpi_locations',
            'field'              => 'postcode',
            'name'               => __('Postal code'),
            'massiveaction'      => true,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '18',
            'table'              => 'glpi_locations',
            'field'              => 'town',
            'name'               => __('Town'),
            'massiveaction'      => true,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '21',
            'table'              => 'glpi_locations',
            'field'              => 'latitude',
            'name'               => __('Latitude'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '20',
            'table'              => 'glpi_locations',
            'field'              => 'longitude',
            'name'               => __('Longitude'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '22',
            'table'              => 'glpi_locations',
            'field'              => 'altitude',
            'name'               => __('Altitude'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '101',
            'table'              => 'glpi_locations',
            'field'              => 'address',
            'name'               => __('Address'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '102',
            'table'              => 'glpi_locations',
            'field'              => 'postcode',
            'name'               => __('Postal code'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '103',
            'table'              => 'glpi_locations',
            'field'              => 'town',
            'name'               => __('Town'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '104',
            'table'              => 'glpi_locations',
            'field'              => 'state',
            'name'               => _x('location', 'State'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '105',
            'table'              => 'glpi_locations',
            'field'              => 'country',
            'name'               => __('Country'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '106',
            'table'              => 'glpi_locations',
            'field'              => 'code',
            'name'               => __('Location code'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '107',
            'table'              => 'glpi_locations',
            'field'              => 'alias',
            'name'               => __('Location alias'),
            'datatype'           => 'string',
        ];

        return $tab;
    }

    public function cleanDBonPurge()
    {
        Rule::cleanForItemAction($this);
        Rule::cleanForItemCriteria($this, '_locations_id%');
    }

    /**
     * get item location
     *
     * @param CommonDBTM  $item
     *
     * @return Location|null
     **/
    final public static function getFromItem(CommonDBTM $item): ?Location
    {
        if ($item->maybeLocated()) {
            $loc = new self();
            if ($loc->getFromDB($item->fields['locations_id'])) {
                return $loc;
            }
        }
        return null;
    }

    public function prepareInputForAdd($input)
    {
        $input = parent::prepareInputForAdd($input);
        if (
            empty($input['latitude']) && empty($input['longitude']) && empty($input['altitude'])
            && !empty($input[static::getForeignKeyField()])
        ) {
            $parent = new static();
            $parent->getFromDB($input[static::getForeignKeyField()]);
            $input['latitude'] = $parent->fields['latitude'];
            $input['longitude'] = $parent->fields['longitude'];
            $input['altitude'] = $parent->fields['altitude'];
        }
        return $input;
    }

    public function getCloneRelations(): array
    {
        return [];
    }
}
