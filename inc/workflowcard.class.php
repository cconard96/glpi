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
 * WorkflowCard class.
 * A workflow card is any step in a workflow such as a trigger or task.
 * It contains common fields such as the name, caption, and description of a step.
 * @since 10.0.0
 */
abstract class WorkflowCard extends CommonDBChild {

   //public $dohistory          = true;
   static $rightname          = 'workflow';

   /**
    * Gets the unique name of the card object (object_add, set_field, link_ticket, etc)
    * @return string Unique card name
    * @since 10.0.0
    */
   public abstract function getCardName();

   /**
    * Gets the unique name of the card instance (object_add_0, object_add_1, object_add_2, etc)
    * @return string Unique card name for the workflow run
    * @since 10.0.0
    */
   public abstract function getCardInstanceName();

   /**
    * Gets the caption of the card. This is what is shown at the top of the card.
    * @return string Card caption
    * @since 10.0.0
    */
   public abstract function getCardCaption();

   /**
    * Gets the description of the card
    * @return string Card description
    * @since 10.0.0
    */
   public abstract function getCardDescription();

   /**
    * Gets an array of inputs that this card accepts
    * @return array Array of accepted inputs
    * @since 10.0.0
    */
   public abstract function getCardInputs();

   /**
    * Gets an array of outputs that this card outputs
    * @return array Array of expected outputs
    * @since 10.0.0
    */
   public abstract function getCardOutputs();

   /**
    * Calls a workflow card under a given workflow run and given an input mapping array.
    * It is expected that the card stores its output in the workflow run state 
    * rather than return them directly.
    * @param WorkflowRun $workflow_run The workflow run (state) that this card is run for
    * @param array $input An associative array if input mappings
    * @return array An associative array of output mappings
    */
   public abstract function callCard(WorkflowRun $workflow_run, array $input);
}