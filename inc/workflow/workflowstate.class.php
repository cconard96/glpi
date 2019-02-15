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
 * WorkflowRun class.
 * This class stores state data for an instance or run of a workflow
 * @since 10.0.0
 */
class WorkflowRun extends CommonDBTM {

   /**
    * Internal state of a workflow run.
    * This state gets built at the start of a workflow run and contains both internal and custom variables.
    * 'steps' - Array of card IDs that are in the workflow. The order in the array does not have to correspond to the run order.
    * 'step_cursor' - The card ID of the current step
    * 'custom' - Associative array of custom variables
    * 'inputs' - Associative array of input bindings for cards
    * 'branches' - Array defining the valid branches of the workflow
    */
   private $variables = [];
   //TODO Validate variable types

   /**
    * Gets the current step's card ID for this workflow run
    * @return string Step card ID
    * @since 10.0.0
    */
   public function getStep() : string
   {
      return $this->fields['step_cursor'];
   }

   private function saveState()
   {
      global $DB;
      $DB->beginTransaction();
      try {
         $DB->deleteOrDie('glpi_workflowstates_variables', ['workflowstates_id' => $this->getID()]);
         foreach (self::variables as $name => $data) {
            $DB->insertOrDie('glpi_workflowstates_variables', [
                'workflowstates_id'   => $this->getID(),
                'name'              => $name,
                'value'             => $data['value'],
                'type'              => isset($data['type']) ? $data['type'] : 'string'
            ]);
         }
         $DB->commit();
      } catch (Exception $e) {
         $DB->rollBack();
      }
   }

   private function restoreState() : void
   {
      
   }

   public function executeNextStep(int $branch = 0) : void
   {
      //TODO If next step is a blocking card (Ex: Wait for approval). Save state and rely on cron task to resume when ready
      //Otherwise, non blocking calls should happen back-to-back
      $next = $this->variables['steps'][$this->getStep() + 1];
      if (is_array($next)) {
         //Branching path. Execute the step in the specfied branch if it exists
         //If branch does not exist, it could be considered an error or the end of the workflow
         
      } else if ($branch != 0) {
         //Non-zero branch could be considered invalid or end of the workflow.
         //TODO FInalize design decision on this condition
      } else {
         //Not a branching path. Execute the next step
      }
   }

   public function getBoundInputs(string $card_id) : array
   {
      return isset($this->variables['inputs'][$card_id]) ? $this->variables['inputs'][$card_id] : [];
   }
}
