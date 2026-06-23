<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
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

use Glpi\Form\AccessControl\FormAccessControlManager;
use Glpi\Form\Migration\FormMigration;
use Glpi\Marketplace\Controller;
use Glpi\Migration\GenericobjectPluginMigration;
use Glpi\System\Requirement\PhpSupportedVersion;
use Glpi\System\Requirement\SessionsSecurityConfiguration;

/**
 * Central class
 **/
class Central extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {

        // No plural
        return __('Standard interface');
    }

    private static function getMessages(): array
    {
        global $CFG_GLPI, $DB;

        $messages = [];

        $user = new User();
        $user->getFromDB(Session::getLoginUserID());
        $expiration_msg = $user->getPasswordExpirationMessage();
        if ($expiration_msg !== null) {
            $messages['warnings'][] = htmlescape($expiration_msg)
             . ' '
             . '<a href="' . htmlescape($CFG_GLPI['root_doc']) . '/front/updatepassword.php">'
             . __s('Update my password')
             . '</a>';
        }

        if (Session::haveRight("config", UPDATE)) {
            $logins = User::checkDefaultPasswords();
            $user   = new User();
            if (!empty($logins)) {
                $accounts = [];
                foreach ($logins as $login) {
                    $user->getFromDBbyNameAndAuth($login, Auth::DB_GLPI, 0);
                    $accounts[] = $user->getLink();
                }
                $messages['warnings'][] = sprintf(
                    __s('For security reasons, please change the password for the default users: %s'),
                    implode(" ", $accounts)
                );
            }

            if (($myisam_count = $DB->getMyIsamTables()->count()) > 0) {
                $messages['warnings'][] = sprintf(__s('%d tables are using the deprecated MyISAM storage engine.'), $myisam_count)
                    . ' '
                    . sprintf(__s('Run the "%1$s" command to migrate them.'), 'php bin/console migration:myisam_to_innodb');
            }
            if (($datetime_count = $DB->getTzIncompatibleTables()->count()) > 0) {
                $messages['warnings'][] = sprintf(__s('%1$s columns are using the deprecated datetime storage field type.'), $datetime_count)
                    . ' '
                    . sprintf(__s('Run the "%1$s" command to migrate them.'), 'php bin/console migration:timestamps');
            }
            if (($non_utf8mb4_count = $DB->getNonUtf8mb4Tables()->count()) > 0) {
                $messages['warnings'][] = sprintf(__s('%1$s tables are using the deprecated utf8mb3 storage charset.'), $non_utf8mb4_count)
                    . ' '
                    . sprintf(__s('Run the "%1$s" command to migrate them.'), 'php bin/console migration:utf8mb4');
            }
            if (($signed_keys_col_count = $DB->getSignedKeysColumns()->count()) > 0) {
                $messages['warnings'][] = sprintf(__s('%d primary or foreign keys columns are using signed integers.'), $signed_keys_col_count)
                    . ' '
                    . sprintf(__s('Run the "%1$s" command to migrate them.'), 'php bin/console migration:unsigned_keys');
            }

            $form_migration = new FormMigration(
                $DB,
                FormAccessControlManager::getInstance(),
            );
            if (
                !$form_migration->hasBeenExecuted()
                && $form_migration->hasPluginData()
            ) {
                $messages['warnings'][] = __s("You have some forms from the 'Formcreator' plugin.")
                    . ' '
                    . sprintf(__s('Run the "%1$s" command to migrate them.'), 'php bin/console migration:formcreator_plugin_to_core');
            }

            $assets_migration = new GenericobjectPluginMigration($DB);
            if (
                !$assets_migration->hasBeenExecuted()
                && $assets_migration->hasPluginData()
            ) {
                $messages['warnings'][] = __s("You have some assets from the 'Generic object' plugin.")
                    . ' '
                    . sprintf(__s('Run the "%1$s" command to migrate them.'), 'php bin/console migration:genericobject_plugin_to_core');
            }

            /*
             * Check if there are pending reasons items and the notification is not active
             * If so, display a warning message
             */
            $notification = new Notification();
            if (
                Config::getConfigurationValue('core', 'use_notifications')
                && countElementsInTable('glpi_pendingreasons_items', ['pendingreasons_id' => ['>', 0]]) > 0
                && !count($notification->find([
                    'itemtype' => Ticket::class,
                    'event'     => 'auto_reminder',
                    'is_active'  => true,
                ]))
            ) {
                $criteria = [
                    'criteria' => [
                        0 => [
                            'link' => 'AND',
                            'field' => 2,
                            'searchtype' => 'equals',
                            'value' => 'Ticket$#$auto_reminder',
                        ],
                    ],
                ];
                $link = '<a href="' . htmlescape(Notification::getSearchURL() . '?' . Toolbox::append_params($criteria)) . '">' . __s('notification') . '</a>';

                $messages['warnings'][] = sprintf(
                    __s('You have defined pending reasons without any respective active %s.'),
                    $link
                );
            }

            // encrypt/decrypt key problems
            $messages['errors'] = (new GLPIKey())->getKeyFileReadErrors();

            $security_requirements = [
                new PhpSupportedVersion(),
                new SessionsSecurityConfiguration(),
            ];
            foreach ($security_requirements as $requirement) {
                if (!$requirement->isValidated()) {
                    foreach ($requirement->getValidationMessages() as $message) {
                        $messages['warnings'][] = htmlescape($message);
                    }
                }
            }

            // Check for available plugin updates
            $count = Controller::countUpdatablePlugins();

            if ($count > 0) {
                $messages['warnings'][] = sprintf(
                    _n('You have %d plugin to update', 'You have %d plugins to update', $count),
                    $count
                ) . ' <a href="' . htmlescape($CFG_GLPI['root_doc']) . '/front/marketplace.php">' . __s('View plugins') . '</a>';
            }
        }

        if ($DB->isSlave() && !$DB->first_connection) {
            $messages['warnings'][] = __s('SQL replica: read only');
        }

        return $messages;
    }
}
