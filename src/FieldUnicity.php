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

use Glpi\Application\View\TemplateRenderer;

/**
 * FieldUnicity Class
 **/
class FieldUnicity extends CommonDropdown
{
    // From CommonDBTM
    public $dohistory          = true;

    public $can_be_translated  = false;

    public static $rightname          = 'config';


    public static function getTypeName($nb = 0)
    {
        return __('Fields unicity');
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
        return [
            [
                'name'  => 'is_active',
                'label' => __('Active'),
                'type'  => 'bool',
            ],
            [
                'name'  => 'itemtype',
                'label' => _n('Type', 'Types', 1),
                'type'  => 'unicity_itemtype',
            ],
            [
                'name'  => 'fields',
                'label' => __('Unique fields'),
                'type'  => 'unicity_fields',
            ],
            [
                'name'  => 'action_refuse',
                'label' => __('Record into the database denied'),
                'type'  => 'bool',
            ],
            [
                'name'  => 'action_notify',
                'label' => __('Send a notification'),
                'type'  => 'bool',
            ],
        ];
    }

    /**
     * Return criteria unicity for an itemtype, in an entity
     *
     * @param string  $itemtype       the itemtype for which unicity must be checked
     * @param int $entities_id    the entity for which configuration must be retrivied
     * @param bool $check_active
     *
     * @return array an array of fields to check, or an empty array if no
     **/
    public static function getUnicityFieldsConfig($itemtype, $entities_id = 0, $check_active = true)
    {
        global $DB;

        // Get the first active configuration for this itemtype
        $request = [
            'FROM'   => 'glpi_fieldunicities',
            'WHERE'  => [
                'itemtype'  => $itemtype,
            ] + getEntitiesRestrictCriteria('glpi_fieldunicities', '', $entities_id, true),
            'ORDER'  => ['entities_id DESC'],
        ];

        if ($check_active) {
            $request['WHERE']['is_active'] = 1;
        }
        $iterator = $DB->request($request);

        $current_entity = false;
        $return         = [];
        foreach ($iterator as $data) {
            // First row processed
            if (!$current_entity) {
                $current_entity = $data['entities_id'];
            }
            // Process only for one entity, not more
            if ($current_entity !== $data['entities_id']) {
                break;
            }
            $return[] = $data;
        }
        return $return;
    }

    /** Dropdown fields for a specific itemtype
     *
     * @since 0.84
     *
     * @param class-string<CommonDBTM> $itemtype
     * @param array  $options
     *
     * @return string|int|false
     */
    public static function dropdownFields($itemtype, $options = [])
    {
        global $DB;

        $p = [
            'name'    => 'fields',
            'display' => true,
            'values'  => [],
        ];

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        //Search option for this type
        if ($target = getItemForItemtype($itemtype)) {
            //Construct list
            $values = [];
            foreach ($DB->listFields(getTableForItemType($itemtype)) as $field) {
                $searchOption = $target->getSearchOptionByField('field', $field['Field']);
                if (
                    !empty($searchOption)
                    && !in_array($field['Field'], $target->getUnallowedFieldsForUnicity(), true)
                ) {
                    $values[$field['Field']] = $searchOption['name'];
                }
            }
            $p['multiple'] = 1;
            $p['size']     = 15;

            return Dropdown::showFromArray($p['name'], $values, $p);
        }
        return false;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => self::getTypeName(),
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
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'datatype'           => 'number',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'fields',
            'name'               => __('Unique fields'),
            'massiveaction'      => false,
            'datatype'           => 'specific',
            'additionalfields'   => ['itemtype'],
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'massiveaction'      => false,
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'unicity_types',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => static::getTable(),
            'field'              => 'action_refuse',
            'name'               => __('Record into the database denied'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => static::getTable(),
            'field'              => 'action_notify',
            'name'               => __('Send a notification'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '86',
            'table'              => static::getTable(),
            'field'              => 'is_recursive',
            'name'               => __('Child entities'),
            'datatype'           => 'bool',
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '30',
            'table'              => static::getTable(),
            'field'              => 'is_active',
            'name'               => __('Active'),
            'datatype'           => 'bool',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    private function prepareInput(array $input): array|false
    {
        if (array_key_exists('_fields', $input) && !empty($input['_fields'])) {
            // Convert multiple values received from the UI into a coma separated list
            $input['fields'] = implode(',', $input['_fields']);
            unset($input['_fields']);
        }

        if (
            (
                ($this->isNewItem() || array_key_exists('itemtype', $input))
                && empty($input['itemtype'])
            )
            || (
                ($this->isNewItem() || array_key_exists('fields', $input))
                && empty($input['fields'])
            )
        ) {
            Session::addMessageAfterRedirect(
                __s("It's mandatory to select a type and at least one field"),
                true,
                ERROR
            );
            return false;
        }

        return $input;
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }
    /**
     * Delete all criterias for an itemtype
     *
     * @param string $itemtype
     *
     * @return void
     **/
    public static function deleteForItemtype($itemtype)
    {
        global $DB;

        $DB->delete(
            self::getTable(),
            [
                'itemtype'  => ['LIKE', "%Plugin$itemtype%"],
            ]
        );
    }
}
