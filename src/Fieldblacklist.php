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
 * Fieldblacklist Class
 **/
class Fieldblacklist extends CommonDropdown
{
    public static $rightname         = 'config';

    public $can_be_translated = false;


    public static function getTypeName($nb = 0)
    {
        return _n('Ignored value for the unicity', 'Ignored values for the unicity', $nb);
    }

    public static function canCreate(): bool
    {
        return static::canUpdate();
    }

    public static function canPurge(): bool
    {
        return static::canUpdate();
    }

    public function getAdditionalFields()
    {

        return [['name'  => 'itemtype',
            'label' => _n('Type', 'Types', 1),
            'type'  => 'blacklist_itemtype',
        ],
            ['name'  => 'field',
                'label' => _n('Field', 'Fields', 1),
                'type'  => 'blacklist_field',
            ],
            ['name'  => 'value',
                'label' => __('Value'),
                'type'  => 'blacklist_value',
            ],
        ];
    }


    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '4',
            'table'              => $this->getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'massiveaction'      => false,
            'datatype'           => 'itemtypename',
            'forcegroupby'       => true,
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'field',
            'name'               => _n('Field', 'Fields', 1),
            'massiveaction'      => false,
            'datatype'           => 'specific',
            'additionalfields'   => [
                '0'                  => 'itemtype',
            ],
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => $this->getTable(),
            'field'              => 'value',
            'name'               => __('Value'),
            'datatype'           => 'specific',
            'additionalfields'   => [
                '0'                  => 'itemtype',
                '1'                  => 'field',
            ],
            'massiveaction'      => false,
        ];

        return $tab;
    }

    public function prepareInputForAdd($input)
    {

        $input = parent::prepareInputForAdd($input);
        return $input;
    }


    public function prepareInputForUpdate($input)
    {

        $input = parent::prepareInputForUpdate($input);
        return $input;
    }

    /** Dropdown fields for a specific itemtype
     *
     * @since 0.84
     *
     * @param string $itemtype
     * @param array  $options
     *
     * @return string|int|false
     */
    public static function dropdownField($itemtype, $options = [])
    {
        global $DB;

        $p['name']    = 'field';
        $p['display'] = true;
        $p['value']   = '';

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        if ($target = getItemForItemtype($itemtype)) {
            $criteria = [];
            foreach ($DB->listFields($target->getTable()) as $field) {
                $searchOption = $target->getSearchOptionByField('field', $field['Field']);

                // MoYo : do not know why  this part ?
                // if (empty($searchOption)) {
                //    if ($table = getTableNameForForeignKeyField($field['Field'])) {
                //       $searchOption = $target->getSearchOptionByField('field', 'name', $table);
                //    }
                // }

                if (
                    !empty($searchOption)
                    && !in_array($field['Type'], $target->getUnallowedFieldsForUnicity())
                    && !in_array($field['Field'], $target->getUnallowedFieldsForUnicity())
                ) {
                    $criteria[$field['Field']] = $searchOption['name'];
                }
            }
            return Dropdown::showFromArray($p['name'], $criteria, $p);
        }
        return false;
    }

    /**
     * Check if a field & value are blacklisted or not
     *
     * @param string $itemtype      itemtype of the blacklisted field
     * @param int $entities_id   the entity in which the field must be saved
     * @param string $field         the field to check
     * @param string $value         the field's value
     *
     * @return bool true is value if blacklisted, false otherwise
     **/
    public static function isFieldBlacklisted($itemtype, $entities_id, $field, $value)
    {
        global $DB;

        $result = $DB->request([
            'COUNT'  => 'cpt',
            'FROM'   => 'glpi_fieldblacklists',
            'WHERE'  => [
                'itemtype'  => $itemtype,
                'field'     => $field,
                'value'     => $value,
            ] + getEntitiesRestrictCriteria('glpi_fieldblacklists', 'entities_id', $entities_id, true),
        ])->current();
        return $result['cpt'] > 0;
    }
}
