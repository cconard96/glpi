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

use Glpi\Features\StateInterface;

/**
 * SoftwareVersion Class
 **/
class SoftwareVersion extends CommonDBChild implements StateInterface
{
    use Glpi\Features\State;

    // From CommonDBTM
    public $dohistory = true;

    // From CommonDBChild
    public static $itemtype = Software::class;
    public static $items_id  = 'softwares_id';


    public static function getTypeName($nb = 0)
    {
        return _n('Version', 'Versions', $nb);
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                Item_SoftwareVersion::class,
            ]
        );
    }

    public function getPreAdditionalInfosForName()
    {
        $soft = new Software();
        if ($soft->getFromDB($this->fields['softwares_id'])) {
            return $soft->getName();
        }
        return '';
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
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => OperatingSystem::getTable(),
            'field'              => 'name',
            'name'               => OperatingSystem::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
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
            'id'                 => '121',
            'table'              => static::getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false,
        ];

        return $tab;
    }

    /**
     * Make a select box for  software to install
     *
     * @param array $options Array of possible options:
     *    - name          : string / name of the select (default is softwareversions_id)
     *    - softwares_id  : integer / ID of the software (mandatory)
     *    - value         : integer / value of the selected version
     *    - used          : array / already used items
     *
     * @return int|string
     *    integer if option display=true (random part of elements id)
     *    string if option display=false (HTML code)
     **/
    public static function dropdownForOneSoftware($options = [])
    {
        global $DB;

        $p['softwares_id']          = 0;
        $p['value']                 = 0;
        $p['name']                  = 'softwareversions_id';
        $p['used']                  = [];
        $p['display_emptychoice']   = true;

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        // Make a select box
        $criteria = [
            'SELECT'    => [
                'glpi_softwareversions.*',
                'glpi_states.name AS sname',
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_softwareversions',
            'LEFT JOIN' => [
                State::getTable()  => [
                    'ON' => [
                        'glpi_softwareversions' => 'states_id',
                        State::getTable()           => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_softwareversions.softwares_id'   => $p['softwares_id'],
            ],
            'ORDERBY'   => 'name',
        ];

        if (count($p['used'])) {
            $criteria['WHERE']['NOT'] = ['glpi_softwareversions.id' => $p['used']];
        }

        $iterator = $DB->request($criteria);

        $values = [];
        foreach ($iterator as $data) {
            $ID     = $data['id'];
            $output = $data['name'];

            if (empty($output) || $_SESSION['glpiis_ids_visible']) {
                $output = sprintf(__('%1$s (%2$s)'), $output, $ID);
            }
            if (!empty($data['sname'])) {
                $output = sprintf(__('%1$s - %2$s'), $output, $data['sname']);
            }
            $values[$ID] = $output;
        }
        return Dropdown::showFromArray($p['name'], $values, $p);
    }
}
