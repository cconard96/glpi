<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

require_once(__DIR__ . '/_check_webserver_config.php');

use Glpi\Event;
use Glpi\Exception\Http\BadRequestHttpException;

/**
 * @since 0.84
 */

Session::checkCentralAccess();
$contractsupplier = new Contract_Supplier();
if (isset($_POST["add"])) {
    if (!isset($_POST['contracts_id']) || empty($_POST['contracts_id'])) {
        $message = sprintf(
            __('Mandatory fields are not filled. Please correct: %s'),
            _n('Contract', 'Contracts', 1)
        );
        Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);
        Html::back();
    }
    $contractsupplier->check(-1, CREATE, $_POST);

    if (
        isset($_POST["contracts_id"]) && ($_POST["contracts_id"] > 0)
        && isset($_POST["suppliers_id"]) && ($_POST["suppliers_id"] > 0)
    ) {
        if ($contractsupplier->add($_POST)) {
            Event::log(
                $_POST["contracts_id"],
                "contracts",
                4,
                "financial",
                //TRANS: %s is the user login
                sprintf(__('%s adds a link with a supplier'), $_SESSION["glpiname"])
            );
        }
    }
    Html::back();
}

throw new BadRequestHttpException();
