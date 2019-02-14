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
 * WorkflowBuilder class
 * @since 10.0.0
 */
class WorkflowBuilder {

   private $trigger = "manual";
   private $tasks = [];

   public function setTrigger($trigger) {
      $this->trigger = $trigger;
   }

   public function addTask($task) {
      $this->tasks[] = $task;
   }

   public function createWorkflow($name) {
      global $DB;

      $DB->beginTransaction();
      $workflow = new Workflow();
      $workflowID = $workflow->add([
          'name' => $name,
          'trigger' => $this->trigger
      ]);
      if (!$workflowID) {
         $DB->rollBack();
         return false;
      }
      $workflowtask = new WorkflowTask();
      //TODO Handle input variable bindings
      for ($i = 0; $i < count($this->tasks); $i++) {
         $taskID = $workflowtask->add([
            'workflows_id'    => $workflowID,
            'name'            => $this->tasks[$i],
            'step'            => $i
         ]);
         if (!$taskID) {
            $DB->rollBack();
            return false;
         }
      }
      $DB->commit();
   }
}
