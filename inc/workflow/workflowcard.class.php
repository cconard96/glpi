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
 * WorkflowCard class.
 * A workflow card is any step in a workflow such as a trigger or task.
 * It contains common fields such as the caption and description of a step.
 * Most of the card's properties are dynamic and can be changed based on attributes of the current workflow run.
 * @since 10.0.0
 */
abstract class WorkflowCard extends CommonDBChild {

   //public $dohistory          = true;
   static $rightname          = 'workflow';
   private $card_id            = null;

   function __construct(string $card_id) {
      $this->card_id = $card_id;
   }

   public function getCardID() : string
   {
      return $this->card_id;
   }
   /**
    * 
    */
   abstract public function getCaption(WorkflowRun $run) : string;

   /**
    * 
    */
   abstract public function getDescription(WorkflowRun $run) : string;

   /**
    * 
    */
   abstract public function getInputDefinitions(array $input) : array;

   /**
    * 
    */
   abstract public function getOutputDefinitions(array $input) : array;

   /**
    * 
    */
   abstract public function execute(WorkflowRun $run) : void;
}