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
 * Workflow class
 * @since 10.0.0
 */
class Workflow extends CommonDBTM {

   public $dohistory          = true;
   static $rightname          = 'workflow';


   static function getTypeName($nb = 0) {
      return _n('Workflow', 'Workflows', $nb);
   }

   public function showForm($ID, $options = []) {
      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'>";
      echo "<div class='workflow_container'>";
      echo "<div class='workflow_decks'>";
      echo "<div class='workflow_deck_triggers'>";
      
      echo "</div";
      echo "<div class='workflow_deck_tasks'>";
      
      echo "</div";
      echo "</div";
      echo "<div id='workflow_designer' class='workflow_designer'>";
      
      echo "</div";
      echo "</div";
      echo "</tr>";
      $this->showFormButtons();
      return true;
   }
}
