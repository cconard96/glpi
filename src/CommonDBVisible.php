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
 * Common DataBase visibility for items
 */
abstract class CommonDBVisible extends CommonDBTM
{
    /**
     * Entities on which item is visible.
     * Keys are ID, values are DB fields values.
     * @var array
     */
    protected $entities = [];

    /**
     * Groups for whom item is visible.
     * Keys are ID, values are DB fields values.
     * @var array
     */
    protected $groups = [];

    /**
     * Profiles for whom item is visible.
     * Keys are ID, values are DB fields values.
     * @var array
     */
    protected $profiles = [];

    /**
     * Users for whom item is visible.
     * Keys are ID, values are DB fields values.
     * @var array
     */
    protected $users = [];

    /**
     * Is the login user have access to item based on visibility configuration
     *
     * @since 0.83
     * @since 9.2 moved from each class to parent class
     *
     * @return bool
     **/
    public function haveVisibilityAccess()
    {
        // Author
        if ($this->fields['users_id'] == Session::getLoginUserID()) {
            return true;
        }
        // Users
        if (isset($this->users[Session::getLoginUserID()])) {
            return true;
        }

        // Groups
        if (
            count($this->groups)
            && isset($_SESSION["glpigroups"]) && count($_SESSION["glpigroups"])
        ) {
            foreach ($this->groups as $data) {
                foreach ($data as $group) {
                    if (in_array($group['groups_id'], $_SESSION["glpigroups"])) {
                        // All the group
                        if ($group['no_entity_restriction']) {
                            return true;
                        }
                        // Restrict to entities
                        if (Session::haveAccessToEntity($group['entities_id'], $group['is_recursive'])) {
                            return true;
                        }
                    }
                }
            }
        }

        // Entities
        if (
            count($this->entities)
            && isset($_SESSION["glpiactiveentities"]) && count($_SESSION["glpiactiveentities"])
        ) {
            foreach ($this->entities as $data) {
                foreach ($data as $entity) {
                    if (Session::haveAccessToEntity($entity['entities_id'], $entity['is_recursive'])) {
                        return true;
                    }
                }
            }
        }

        // Profiles
        if (
            count($this->profiles)
            && isset($_SESSION["glpiactiveprofile"])
            && isset($_SESSION["glpiactiveprofile"]['id'])
        ) {
            if (isset($this->profiles[$_SESSION["glpiactiveprofile"]['id']])) {
                foreach ($this->profiles[$_SESSION["glpiactiveprofile"]['id']] as $profile) {
                    // All the profile
                    if ($profile['no_entity_restriction']) {
                        return true;
                    }
                    // Restrict to entities
                    if (Session::haveAccessToEntity($profile['entities_id'], $profile['is_recursive'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Count visibilities
     *
     * @since 0.83
     * @since 9.2 moved from each class to parent class
     *
     * @return int
     */
    public function countVisibilities()
    {

        return (count($this->entities)
              + count($this->users)
              + count($this->groups)
              + count($this->profiles));
    }

    /**
     * Get dropdown parameters from showVisibility method
     *
     * @return array{type: string, right: string, entity?: int, is_recursive?: bool}
     */
    protected function getShowVisibilityDropdownParams()
    {
        $params = [
            'type'          => '__VALUE__',
            'right'         => strtolower($this::getType()) . '_public',
        ];
        if (isset($this->fields['entities_id'])) {
            $params['entity'] = $this->fields['entities_id'];
        }
        if (isset($this->fields['is_recursive'])) {
            $params['is_recursive'] = $this->fields['is_recursive'];
        }
        return $params;
    }
}
