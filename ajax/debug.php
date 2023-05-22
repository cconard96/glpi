<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
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

include('../inc/includes.php');
Html::header_nocache();

Session::checkLoginUser();

if ($_SESSION['glpi_use_mode'] !== Session::DEBUG_MODE) {
    http_response_code(403);
    die();
}

\Glpi\Debug\Profiler::getInstance()->disable();

if (isset($_GET['ajax_id'])) {
    // Get debug data for a specific ajax call
    $ajax_id = $_GET['ajax_id'];
    $profile = \Glpi\Debug\Profile::load($ajax_id);
    if ($profile) {
        $data = $profile->getDebugInfo();
        if ($data) {
            header('Content-Type: application/json');
            echo json_encode($data);
            die();
        }
    }
    http_response_code(404);
    die();
}

if (isset($_GET['reflection'])) {
    header('Content-Type: application/json');
    $itemtype = $_GET['reflection']['itemtype'] ?? \Glpi\Debug\Reflection::getInstance()->getItemtypeForPage($_GET['reflection']['page_url']);
    if ($itemtype === null) {
        http_response_code(404);
        die();
    }
    if ($_GET['reflection']['data'] === 'class_info') {
        echo json_encode(\Glpi\Debug\Reflection::getInstance()->getClassInfo($itemtype));
        die();
    }
    if ($_GET['reflection']['data'] === 'search_options') {
        echo json_encode(\Glpi\Debug\Reflection::getInstance()->getSearchOptions($itemtype));
        die();
    }
    if ($_GET['reflection']['data'] === 'db_table') {
        $table = getTableForItemType($itemtype);
        echo json_encode(\Glpi\Debug\Reflection::getInstance()->getTableSchema($table));
        die();
    }
}

http_response_code(400);
die();
