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
 * WorkflowTask class.
 * A WorkflowTask is a WorkflowCard that executes an action or task given some input.
 * @since 10.0.0
 */
abstract class WorkflowTask extends WorkflowCard
{
   /**
    * Can this task contain other tasks?
    * An example would be a try/catch or loop
    * @return bool True if this task can contain other tasks
    */
   public function isContainerTask() : bool {
      return false;
   }

   /**
    * Does this task block until criteria are met? (Ex: wait for approval response)
    * @return bool True if this task blocks until criteria are met
    */
   public function isBlocking() : bool {
      return false;
   }

   public function isReady() : bool {
      return true;
   }
}
