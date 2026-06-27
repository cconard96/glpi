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

/// Class Manufacturer
class Manufacturer extends CommonDropdown
{
    /** @use Clonable<static> */
    use Clonable;

    public $can_be_translated = false;

    public static function getTypeName($nb = 0)
    {
        return _n('Manufacturer', 'Manufacturers', $nb);
    }

    public function getAdditionalFields()
    {

        return [
            [
                'name'  => 'none',
                'label' => RegisteredID::getTypeName(Session::getPluralNumber()),
                'type'  => 'registeredIDChooser',
            ],
        ];
    }

    /**
     * @return void
     */
    public function post_workOnItem()
    {

        if (
            (isset($this->input['_registeredID']))
            && (is_array($this->input['_registeredID']))
        ) {
            $input = ['itemtype' => $this->getType(),
                'items_id' => $this->getID(),
            ];

            foreach ($this->input['_registeredID'] as $id => $registered_id) {
                $id_object     = new RegisteredID();
                $input['name'] = $registered_id;

                if (isset($this->input['_registeredID_type'][$id])) {
                    $input['device_type'] = $this->input['_registeredID_type'][$id];
                } else {
                    $input['device_type'] = '';
                }
                //$input['device_type'] = '';
                if ($id < 0) {
                    if (!empty($registered_id)) {
                        $id_object->add($input);
                    }
                } else {
                    if (!empty($registered_id)) {
                        $input['id'] = $id;
                        $id_object->update($input);
                        unset($input['id']);
                    } else {
                        $id_object->delete(['id' => $id]);
                    }
                }
            }
            unset($this->input['_registeredID']);
        }
    }

    public function post_addItem()
    {

        $this->post_workOnItem();
        parent::post_addItem();
    }

    public function post_updateItem($history = true)
    {

        $this->post_workOnItem();
        parent::post_updateItem($history);
    }

    /**
     * @param null|string $old_name  Old name
     *
     * @return null|string new name
     **/
    public static function processName($old_name)
    {

        if ($old_name == null) {
            return $old_name;
        }

        $rulecollection = new RuleDictionnaryManufacturerCollection();
        $output         = [];
        $output         = $rulecollection->processAllRules(
            ["name" => $old_name],
            $output,
            []
        );
        return $output["name"] ?? $old_name;
    }


    public function cleanDBonPurge()
    {
        // Rules use manufacturer intread of manufacturers_id
        Rule::cleanForItemAction($this, 'manufacturer');
    }

    public function getCloneRelations(): array
    {
        return [];
    }
}
