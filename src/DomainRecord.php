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

class DomainRecord extends CommonDBChild implements AssignableItemInterface
{
    use AssignableItem {
        canUpdate as canUpdateAssignableItem;
        canUpdateItem as canUpdateItemAssignableItem;
    }

    public const DEFAULT_TTL = 3600;

    public static $rightname              = 'domain';
    // From CommonDBChild
    public static $itemtype = Domain::class;
    public static $items_id        = 'domains_id';
    public $dohistory              = true;
    public static $mustBeAttached  = false;

    public static function getTypeName($nb = 0)
    {
        return _n('Domain record', 'Domains records', $nb);
    }

    /**
     * @param Domain $item
     *
     * @return int
     */
    public static function countForDomain(Domain $item)
    {
        return countElementsInTable(
            self::getTable(),
            [
                "domains_id"   => $item->getID(),
            ]
        );
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab = array_merge($tab, parent::rawSearchOptions());

        $tab[] = [
            'id'                 => '2',
            'table'              => 'glpi_domains',
            'field'              => 'name',
            'name'               => Domain::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => DomainRecordType::getTable(),
            'field'              => 'name',
            'name'               => DomainRecordType::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'ttl',
            'name'               => __('TTL'),
        ];

        $tab[] = [
            'id'                 => '11',
            'table'              => static::getTable(),
            'field'              => 'data',
            'name'               => __('Data'),
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'linkfield'          => 'users_id_tech',
            'name'               => __('Technician in charge'),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => static::getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'date',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => 'glpi_groups',
            'field'              => 'name',
            'linkfield'          => 'groups_id',
            'name'               => __('Group in charge'),
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
            'id'                 => '10',
            'table'              => static::getTable(),
            'field'              => 'date_mod',
            'massiveaction'      => false,
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
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

    public static function canCreate(): bool
    {
        if (count($_SESSION['glpiactiveprofile']['managed_domainrecordtypes'])) {
            return true;
        }
        return parent::canCreate();
    }

    public static function canUpdate(): bool
    {
        if (!self::canUpdateAssignableItem()) {
            return false;
        }
        if (count($_SESSION['glpiactiveprofile']['managed_domainrecordtypes'])) {
            return true;
        }
        return parent::canUpdate();
    }

    public static function canDelete(): bool
    {
        if (count($_SESSION['glpiactiveprofile']['managed_domainrecordtypes'])) {
            return true;
        }
        return parent::canDelete();
    }

    public static function canPurge(): bool
    {
        if (count($_SESSION['glpiactiveprofile']['managed_domainrecordtypes'])) {
            return true;
        }
        return parent::canPurge();
    }

    public function canCreateItem(): bool
    {
        return count($_SESSION['glpiactiveprofile']['managed_domainrecordtypes']) > 0;
    }

    public function canUpdateItem(): bool
    {
        if (!$this->canUpdateItemAssignableItem()) {
            return false;
        }
        return parent::canUpdateItem()
         && (
             $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'] === [-1]
         || in_array($this->fields['domainrecordtypes_id'], $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'], true)
         );
    }

    public function canDeleteItem(): bool
    {
        return parent::canDeleteItem()
         && (
             $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'] === [-1]
         || in_array($this->fields['domainrecordtypes_id'], $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'], true)
         );
    }

    public function canPurgeItem(): bool
    {
        return parent::canPurgeItem()
         && (
             $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'] === [-1]
         || in_array($this->fields['domainrecordtypes_id'], $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'], true)
         );
    }

    /**
     * Prepare input for add and update
     *
     * @param array   $input Input values
     * @param bool $add   True when we're adding a record
     *
     * @return array|false
     */
    private function prepareInput($input, $add = false)
    {
        if (($add && empty($input['domains_id'])) || (isset($input['domains_id']) && empty($input['domains_id']))) {
            Session::addMessageAfterRedirect(
                __s('A domain is required'),
                true,
                ERROR
            );
            return false;
        }

        if ($add) {
            if (isset($input['date_creation']) && empty($input['date_creation'])) {
                $input['date_creation'] = 'NULL';
            }

            if (empty($input['ttl'])) {
                $input['ttl'] = self::DEFAULT_TTL;
            }
        }

        //search entity
        if ($add && !isset($input['entities_id'])) {
            $input['entities_id'] = $_SESSION['glpiactive_entity'] ?? 0;
            $input['is_recursive'] = $_SESSION['glpiactive_entity_recursive'] ?? 0;
            $domain = new Domain();
            if (isset($input['domains_id']) && $domain->getFromDB($input['domains_id'])) {
                $input['entities_id'] = $domain->fields['entities_id'];
                $input['is_recursive'] = $domain->fields['is_recursive'];
            }
        }

        if (!Session::isCron() && (isset($input['domainrecordtypes_id']) || isset($this->fields['domainrecordtypes_id']))) {
            if ($_SESSION['glpiactiveprofile']['managed_domainrecordtypes'] !== [-1]) {
                if (isset($input['domainrecordtypes_id']) && !(in_array($input['domainrecordtypes_id'], $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'], true))) {
                    //no right to use selected type
                    Session::addMessageAfterRedirect(
                        __s('You are not allowed to use this type of records'),
                        true,
                        ERROR
                    );
                    return false;
                }
                if ($add === false && !(in_array($this->fields['domainrecordtypes_id'], $_SESSION['glpiactiveprofile']['managed_domainrecordtypes'], true))) {
                    //no right to change existing type
                    Session::addMessageAfterRedirect(
                        __s('You are not allowed to edit this type of records'),
                        true,
                        ERROR
                    );
                    return false;
                }
            }
        }

        return $input;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->prepareGroupFields($input);
        if ($input === false) {
            return false;
        }
        return $this->prepareInput($input, true);
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->prepareGroupFields($input);
        if ($input === false) {
            return false;
        }
        return $this->prepareInput($input);
    }

    public function pre_updateInDB()
    {

        if (
            (in_array('data', $this->updates, true) || in_array('domainrecordtypes_id', $this->updates, true))
            && !array_key_exists('data_obj', $this->input)
        ) {
            // Remove data stored as obj if "data" or "record type" changed" and "data_obj" is not part of input.
            // It ensure that updates that "data_obj" will not contains obsolete values.
            $this->fields['data_obj'] = 'NULL';
            $this->updates[]          = 'data_obj';
        }
    }

    /**
     * @param Domain $domain
     * @param string $name
     *
     * @return string
     */
    public static function getDisplayName(Domain $domain, $name)
    {
        $name_txt = rtrim(
            str_replace(
                rtrim($domain->getCanonicalName(), '.'),
                '',
                $name
            ),
            '.'
        );
        if (empty($name_txt)) {
            //dns root
            $name_txt = '@';
        }
        return $name_txt;
    }
}
