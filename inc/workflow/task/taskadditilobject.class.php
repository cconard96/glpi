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
 * TaskAddITILObject class
 *
 */
class TaskAddITILObject extends WorkflowTask {

   public function execute(WorkflowState $run): void {
      
   }

   public function getCaption(WorkflowState $run): string {
      return __('Create ITIL Object');
   }

   public function getDescription(WorkflowRun $run): string {
      return __('Creates a ticket, change, or problem');
   }

   public function getInputDefinitions(array $input): array {
      $inputs = [];
      $inputs['itemtype'] = [
          'name' => __('Item type'),
          'type' => 'string',
          'allowedvalues' => ['Ticket', 'Change', 'Problem']
      ];
      switch ($input['itemtype']) {
         case 'Ticket':
            break;
         case 'Change':
            break;
         case 'Problem':
            break;
         default:
            break;
      }
   }

   public function getOutputDefinitions(array $input): array {
      
   }

}
