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

namespace Glpi\Workflow;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * WorkflowTrigger class
 * @since 10.0.0
 */
abstract class WorkflowTrigger extends WorkflowCard {

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
      global $GLPI_CACHE;

      //TODO Should plugins use their own cache keys and get called in a similar getTriggers function?
      if (!$GLPI_CACHE->has('workflow_triggers')) {
         $supported_itemtypes = array_merge($CFG_GLPI['asset_types'],
                 ['Ticket', 'Change', 'Problem', 'Project']);
         $triggers = [];
         $triggers['manual'] = [
            'name' => __('Manually initiated'),
            'description' => __('Manually initiated by a user')
         ];
         foreach ($supported_itemtypes as $itemtype) {
            $item = new $itemtype();
            $item->getEmpty();
            $triggers[strtolower($itemtype).'_add'] = [
                'name' => sprintf(__('%1Ss added'), $itemtype::getTypeName(1)),
                 'description' => __('Initiated when an item is created or added'),
                 'outputs' => [
                     'items_id' => [
                        'name' => __('ID'),
                        'description' => __('The ID of the item that was added'),
                        'type' => 'int'
                     ]
                 ]
            ];
            $triggers[strtolower($itemtype).'_update'] = [
                'name' => sprintf(__('%1Ss updated'), $itemtype::getTypeName(1)),
                 'description' => __('Initiated when an item is updated'),
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
            ];
            if ($item->maybeDeleted()) {
               $triggers[strtolower($itemtype).'_delete'] = [
                   'name' => sprintf(__('%1Ss deleted'), $itemtype::getTypeName(1)),
                    'description' => __('Initiated when an item is deleted'),
                    'outputs' => [
                        'items_id' => [
                           'name' => __('ID'),
                           'description' => __('The ID of the item that was deleted'),
                           'type' => 'int'
                        ]
                    ]
               ];
            }
            $triggers[strtolower($itemtype).'_purge'] = [
                'name' => sprintf(__('%1Ss purged'), $itemtype::getTypeName(1)),
                 'description' => __('Initiated when an item is purged'),
                 'outputs' => [
                     'items_id' => [
                        'name' => __('ID'),
                        'description' => __('The ID of the item that was purged'),
                        'type' => 'int'
                     ]
                 ]
            ];
            $triggers[strtolower($itemtype).'_restore'] = [
                'name' => sprintf(__('%1Ss restored'), $itemtype::getTypeName(1)),
                 'description' => __('Initiated when an item is restored'),
                 'outputs' => [
                     'items_id' => [
                        'name' => __('ID'),
                        'description' => __('The ID of the item that was restored'),
                        'type' => 'int'
                     ]
                 ]
            ];
         }
         return $GLPI_CACHE->set('workflow_triggers', $triggers);
      }
      return $GLPI_CACHE->get('workflow_triggers');
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
}
