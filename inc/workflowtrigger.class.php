<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
*/

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * WorkflowTrigger class
 * @since 10.0.0
 */
class WorkflowTrigger extends WorkflowCard {

   static function getTypeName($nb = 0) {
      return _n('Workflow trigger', 'Workflow triggers', $nb);
   }

   /**
    * Gets an associative array of workflow trigger definitions.
    * The keys must be a unique string id for the trigger.
    * The valid properties for triggers are: name, description, inputs, and outputs.
    * Input properties may be: name, description, type, and allowedValues.
    * Output properties may be: name, description, and type.
    * 
    * @return array Array of workflow trigger definitions
    * @since 10.0.0
    */
   static function getTriggers() {
      global $CFG_GLPI;

      return [
          'manual' => [
              'name' => __('Manually initiated'),
              'description' => __('Manually initiated by a user')
          ],
          'item_add' => [
              'name' => __('Item added'),
              'description' => __('Initiated when an item is created or added'),
              'inputs' => [
                  'itemtype' => [
                      'name' => __('Item type'),
                      'description' => __('The type of item'),
                      'type' => 'string',
                      'allowedValues' => $CFG_GLPI['globalsearch_types']
                  ]
              ],
              'outputs' => [
                  'items_id' => [
                     'name' => __('ID'),
                     'description' => __('The ID of the item that was added'),
                     'type' => 'int'
                  ]
              ]
          ],
          'item_update' => [
              'name' => __('Item updated'),
              'description' => __('Initiated when an item is updated'),
              'inputs' => [
                  'itemtype' => [
                      'name' => __('Item type'),
                      'description' => __('The type of item'),
                      'type' => 'string',
                      'allowedValues' => $CFG_GLPI['globalsearch_types']
                  ]
              ],
              'outputs' => [
                  'items_id' => [
                     'name' => __('ID'),
                     'description' => __('The ID of the item that was updated'),
                     'type' => 'int'
                  ],
                  'changes' => [
                     'name' => __('Changes'),
                     'description' => __('The changes made to the item'),
                     'type' => 'array'
                  ]
              ]
          ],
          'item_delete' => [
              'name' => __('Item deleted'),
              'description' => __('Initiated when an item is deleted'),
              'inputs' => [
                  'itemtype' => [
                      'name' => __('Item type'),
                      'description' => __('The type of item'),
                      'type' => 'string',
                      'allowedValues' => $CFG_GLPI['globalsearch_types']
                  ]
              ],
              'outputs' => [
                 'items_id' => [
                     'name' => __('ID'),
                     'description' => __('The ID of the item that was deleted'),
                     'type' => 'int'
                  ]
              ]
          ],
          'item_purge' => [
              'name' => __('Item purged'),
              'description' => __('Initiated when an item is purged'),
              'inputs' => [
                  'itemtype' => [
                      'name' => __('Item type'),
                      'description' => __('The type of item'),
                      'type' => 'string',
                      'allowedValues' => $CFG_GLPI['globalsearch_types']
                  ]
              ],
              'outputs' => [
                  'items_id' => [
                     'name' => __('ID'),
                     'description' => __('The ID of the item that was purged'),
                     'type' => 'int'
                  ]
              ]
          ]
      ];
   }

   /**
    * Helper function to search available triggers.
    * Triggers are matched based on both the name and description properties.
    * @param string $searchterm The term to use to search available triggers
    */
   static function searchTriggers(string $searchterm) {
      $alltriggers = self::getTriggers();
      $matches = [];
      foreach ($alltriggers as $id => $trigger) {
         if (strpos($trigger['name'], $searchterm) ||
                 (isset($trigger['description']) && strpos($trigger['description'], $searchterm))) {
            $matches[$id] = $trigger;
         }
      }
      return $matches;
   }

   public function callCard(WorkflowRun $workflow_run, array $input): array {
      
   }

   public function getCardName(): string {
      return $this->fields['name'];
   }

   public function getCardInstanceName(): string {
      
   }

   public function getCardCaption(): string {
      return self::getTriggers()[$this->getCardName()]['name'];
   }

   public function getCardDescription(): string {
      return self::getTriggers()[$this->getCardName()]['description'];
   }

   public function getCardInputs(): array {
      return self::getTriggers()[$this->getCardName()]['inputs'];
   }

   public function getCardOutputs(): array {
      return self::getTriggers()[$this->getCardName()]['outputs'];
   }

}
