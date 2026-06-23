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

/**
 * UserEmail class
 **/
class UserEmail extends CommonDBChild
{
    // From CommonDBTM
    public $auto_message_on_action = false;

    // From CommonDBChild
    public static $itemtype = User::class;
    public static $items_id        = 'users_id';
    public $dohistory              = true;


    public static function getTypeName($nb = 0)
    {
        return _n('Email', 'Emails', $nb);
    }

    public function canChildItem($methodItem, $methodNotItem)
    {
        $users_id = $this->input['users_id'] ?? $this->fields['users_id'] ?? null;
        if ($users_id !== null && !$this->canAlterUserEmails((int) $users_id)) {
            return false;
        }

        return parent::canChildItem($methodItem, $methodNotItem);
    }

    /**
     * Indicates whether the current user can alter the email addresses from the target user.
     *
     * @param int $target_user_id
     * @return bool
     */
    private function canAlterUserEmails(int $target_user_id): bool
    {
        $session_user_id = Session::getLoginUserID();

        if ($session_user_id === false) {
            // No active user session, action is made by a cron or a system routine, no need to check.
            return true;
        }

        if ($target_user_id === $session_user_id) {
            // Email is attached to the current user, no need to check.
            return true;
        }

        // Current user can alter target user's emails only if he has more rights.
        $user = new User();
        return $user->currentUserHaveMoreRightThan($target_user_id);
    }

    /**
     * Get default email for user. If no default email get first one
     *
     * @param int $users_id user ID
     *
     * @return string default email, empty if no email set
     **/
    public static function getDefaultForUser($users_id)
    {
        global $DB;

        // Get default one
        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'users_id'     => $users_id,
            ],
            'ORDER'  => 'is_default DESC',
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            return $row['email'];
        }

        return '';
    }


    /**
     * Get all emails for user.
     *
     * @param int $users_id user ID
     *
     * @return array of emails
     **/
    public static function getAllForUser($users_id)
    {
        global $DB;

        $emails = [];

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'users_id'     => $users_id,
            ],
        ]);

        foreach ($iterator as $row) {
            $emails[] = $row['email'];
        }

        return $emails;
    }


    /**
     * is an email of the user
     *
     * @param int    $users_id user ID
     * @param string $email    email to check user ID
     *
     * @return bool is this email set for the user ?
     **/
    public static function isEmailForUser($users_id, $email)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'users_id'  => $users_id,
                'email'     => $email,
            ],
            'LIMIT'  => 1,
        ]);

        if (count($iterator)) {
            return true;
        }

        return false;
    }

    public function prepareInputForAdd($input)
    {
        if (!$this->checkInputEmailValidity($input)) {
            return false;
        }

        // First email is default
        if (countElementsInTable($this->getTable(), ['users_id' => $input['users_id']]) == 0) {
            $input['is_default'] = 1;
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        if (array_key_exists('email', $input) && !$this->checkInputEmailValidity($input)) {
            return false;
        }

        return parent::prepareInputForUpdate($input);
    }

    /**
     * Check validity of email passed in input.
     *
     * @param array $input
     *
     * @return bool
     */
    private function checkInputEmailValidity(array $input): bool
    {
        return isset($input['email']) && !empty($input['email']) && GLPIMailer::validateAddress($input['email']);
    }

    /**
     * @since 0.84
     *
     * @see CommonDBTM::getNameField
     *
     * @return string
     **/
    public static function getNameField()
    {
        return 'email';
    }


    public function post_updateItem($history = true)
    {
        global $DB;

        // if default is set : unsed others for the users
        if (
            in_array('is_default', $this->updates)
            && ($this->input["is_default"] == 1)
        ) {
            $DB->update(
                $this->getTable(),
                [
                    'is_default' => 0,
                ],
                [
                    'id'        => ['<>', $this->input['id']],
                    'users_id'  => $this->fields['users_id'],
                ]
            );
        }

        parent::post_updateItem($history);
    }


    public function post_addItem()
    {
        global $DB;

        // if default is set : unset others for the users
        if (isset($this->fields['is_default']) && ($this->fields["is_default"] == 1)) {
            $DB->update(
                $this->getTable(),
                [
                    'is_default' => 0,
                ],
                [
                    'id'        => ['<>', $this->fields['id']],
                    'users_id'  => $this->fields['users_id'],
                ]
            );
        }

        parent::post_addItem();
    }


    public function post_deleteFromDB()
    {
        global $DB;

        // if default is set : set default to another one
        if ($this->fields["is_default"] == 1) {
            $DB->update(
                $this->getTable(),
                [
                    'is_default'   => 1,
                ],
                [
                    'WHERE'  => [
                        'id'        => ['<>', $this->fields['id']],
                        'users_id'  => $this->fields['users_id'],
                    ],
                    'LIMIT'  => 1,
                ]
            );
        }

        parent::post_deleteFromDB();
    }
}
