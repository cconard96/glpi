<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
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

namespace Glpi\Features;

use Group_Item;
use QueryExpression;
use QuerySubQuery;
use Session;

trait AssignableItem
{
    public static function canView()
    {
        return Session::haveRightsOr(static::$rightname, [READ, READ_ASSIGNED]);
    }

    public function canViewItem()
    {
        if (!parent::canViewItem()) {
            return false;
        }

        $is_assigned = false;
        if ($this->isField('users_id_tech')) {
            $is_assigned = $this->fields['users_id_tech'] === $_SESSION['glpiID'];
        }
        if (!$is_assigned && $this->isField('groups_id_tech')) {
            $is_assigned = count(array_intersect($this->fields['groups_id_tech'] ?? [], $_SESSION['glpigroups'] ?? [])) > 0;
        }

        if (!Session::haveRight(static::$rightname, READ)) {
            return $is_assigned && Session::haveRight(static::$rightname, READ_ASSIGNED);
        }

        // Has global READ right
        return true;
    }

    public static function canUpdate()
    {
        return Session::haveRightsOr(static::$rightname, [UPDATE, UPDATE_ASSIGNED]);
    }

    public function canUpdateItem()
    {
        if (!parent::canUpdateItem()) {
            return false;
        }

        $is_assigned = false;
        if ($this->isField('users_id_tech')) {
            $is_assigned = $this->fields['users_id_tech'] === $_SESSION['glpiID'];
        }
        if (!$is_assigned && $this->isField('groups_id_tech')) {
            $is_assigned = count(array_intersect($this->fields['groups_id_tech'] ?? [], $_SESSION['glpigroups'] ?? [])) > 0;
        }

        if (!Session::haveRight(static::$rightname, UPDATE)) {
            return $is_assigned && Session::haveRight(static::$rightname, UPDATE_ASSIGNED);
        }

        // Has global UPDATE right
        return true;
    }

    public static function getAssignableVisiblityCriteria()
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (Session::haveRight(static::$rightname, READ)) {
            $condition = [new QueryExpression('1')];
        } elseif (Session::haveRight(static::$rightname, READ_ASSIGNED)) {
            $item_table     = static::getTable();
            $relation_table = Group_Item::getTable();

            $condition = [
                $item_table . '.users_id_tech' => $_SESSION['glpiID'],
            ];
            if (count($_SESSION['glpigroups']) > 0) {
                $condition = [
                    'OR' => [
                        $condition,
                        $item_table . '.id' => new QuerySubQuery([
                            'SELECT'     => $relation_table . '.items_id',
                            'FROM'       => $relation_table,
                            'WHERE' => [
                                'itemtype'  => static::class,
                                'groups_id' => $_SESSION['glpigroups'],
                                'type'      => Group_Item::GROUP_TYPE_TECH,
                            ]
                        ]),
                    ]
                ];
            }
        } else {
            $condition = [new QueryExpression('0')];
        }

        // Use a unique key for condition to make result usage safe for `array + array` and `array_merge` operations.
        return [crc32(serialize($condition)) => $condition];
    }

    /**
     * @param string $interface
     * @phpstan-param 'central'|'helpdesk' $interface
     * @return array
     * @phpstan-return array<integer, string|array>
     */
    public function getRights($interface = 'central')
    {
        $rights = parent::getRights($interface);
        $rights[READ] = __('View all');
        $rights[READ_ASSIGNED] = __('View assigned');
        $rights[UPDATE] = __('Update all');
        $rights[UPDATE_ASSIGNED] = __('Update assigned');
        return $rights;
    }

    protected function prepareGroupFields(array $input)
    {
        $fields = ['groups_id', 'groups_id_tech'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                if (!is_array($input[$field])) {
                    $input[$field] = [$input[$field]];
                }
                $input['_' . $field] = array_filter(array_map('intval', $input[$field] ?? []), static fn ($v) => $v > 0);
                unset($input[$field]);
            }
        }
        return $input;
    }

    public function prepareInputForAdd($input)
    {
        if ($input === false) {
            return false;
        }
        $input = parent::prepareInputForAdd($input);
        if ($input === false) {
            return false;
        }
        return $this->prepareGroupFields($input);
    }

    public function prepareInputForUpdate($input)
    {
        if ($input === false) {
            return false;
        }
        $input = parent::prepareInputForUpdate($input);
        if ($input === false) {
            return false;
        }
        return $this->prepareGroupFields($input);
    }

    /**
     * Update the values in the 'glpi_groups_items' link table as needed based on the groups set in the 'groups_id' and 'groups_id_tech' fields.
     */
    private function updateGroupFields()
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Find existing links
        $existing_links = [];
        if (!$this->isNewItem()) {
            $it = $DB->request([
                'SELECT' => ['id', 'groups_id', 'type'],
                'FROM' => 'glpi_groups_items',
                'WHERE' => [
                    'itemtype' => static::class,
                    'items_id' => $this->getID(),
                ],
            ]);
            $existing_links = iterator_to_array($it);
        }

        // Group fields are changed to have a '_' prefix to avoid trying to update non-existent fields in the database
        $fields = [
            Group_Item::GROUP_TYPE_NORMAL => '_groups_id',
            Group_Item::GROUP_TYPE_TECH   => '_groups_id_tech',
        ];
        foreach ($fields as $type => $field) {
            $existing_for_type = array_column(array_filter($existing_links, static fn($link) => $link['type'] === $type), 'groups_id');
            if (array_key_exists($field, $this->input)) {
                $new_links = array_diff($this->input[$field], $existing_for_type);
                $old_links = array_diff($existing_for_type, $this->input[$field]);
                foreach ($new_links as $group_id) {
                    $group_item = new Group_Item();
                    $group_item->add(
                        [
                            'itemtype' => static::class,
                            'items_id' => $this->getID(),
                            'groups_id' => $group_id,
                            'type' => $type,
                        ]
                    );
                }
                foreach ($old_links as $group_id) {
                    $group_item = new Group_Item();
                    $group_item->deleteByCriteria(
                        [
                            'itemtype' => static::class,
                            'items_id' => $this->getID(),
                            'groups_id' => $group_id,
                            'type' => $type,
                        ],
                        true
                    );
                }
            }
        }

        $this->loadGroupFields();
    }

    public function post_addItem()
    {
        parent::post_addItem();
        $this->updateGroupFields();
    }

    public function post_updateItem($history = true)
    {
        parent::post_updateItem($history);
        $this->updateGroupFields();
    }

    public function getEmpty()
    {
        if (!parent::getEmpty()) {
            return false;
        }
        $group_fields = ['groups_id', 'groups_id_tech'];
        foreach ($group_fields as $field) {
            $this->fields[$field] = [];
        }
        return true;
    }

    private function loadGroupFields()
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Find existing links
        $it = $DB->request([
            'SELECT' => ['id', 'groups_id', 'type'],
            'FROM' => 'glpi_groups_items',
            'WHERE' => [
                'itemtype' => static::class,
                'items_id' => $this->getID(),
            ],
        ]);
        $existing_links = iterator_to_array($it);

        $group_fields = [
            Group_Item::GROUP_TYPE_NORMAL => 'groups_id',
            Group_Item::GROUP_TYPE_TECH   => 'groups_id_tech',
        ];
        foreach ($group_fields as $type => $field) {
            if (in_array($type, $this->getGroupTypes(), true)) {
                $this->fields[$field] = array_column(array_filter($existing_links, static fn($link) => $link['type'] === $type), 'groups_id');
            }
        }
    }

    public function post_getFromDB()
    {
        $this->loadGroupFields();
    }

    /**
     * Get the types of groups supported by the asset.
     * @return array<Group_Item::GROUP_TYPE_*>
     */
    final public function getGroupTypes(): array
    {
        /**
         * @var array $CFG_GLPI
         */
        global $CFG_GLPI;

        $types = [];
        if (in_array(static::class, $CFG_GLPI['linkgroup_types'], true)) {
            $types[] = Group_Item::GROUP_TYPE_NORMAL;
        }
        if (in_array(static::class, $CFG_GLPI['linkgroup_tech_types'], true)) {
            $types[] = Group_Item::GROUP_TYPE_TECH;
        }

        return $types;
    }
}
