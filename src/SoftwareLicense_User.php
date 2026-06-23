<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
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
 * SoftwareLicense_User Class
 *
 * Relation between SoftwareLicense and Users
 **/
class SoftwareLicense_User extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = User::class;
    public static $items_id_1 = 'users_id';

    public static $itemtype_2 = SoftwareLicense::class;
    public static $items_id_2 = 'softwarelicenses_id';

    public static $checkItem_1_Rights = self::DONT_CHECK_ITEM_RIGHTS;

    public static $checkItem_2_Rights = self::HAVE_SAME_RIGHT_ON_ITEM;

    public function prepareInputForAdd($input)
    {
        if (
            !isset($input['softwarelicenses_id'])
            || !is_numeric($input['softwarelicenses_id'])
            || !isset($input['users_id'])
        ) {
            trigger_error('softwarelicenses_id and users_id are mandatory', E_USER_WARNING);
            return false;
        }

        $softwarelicenses_id = (int) $input['softwarelicenses_id'];

        $license = new SoftwareLicense();
        if (!$license->getFromDB($softwarelicenses_id)) {
            trigger_error(sprintf('Unable to load software license %d', $softwarelicenses_id), E_USER_WARNING);
            return false;
        }

        // Check quota if not unlimited (-1) and over-quota not allowed
        if (
            $license->getField('number') != -1
            && !$license->getField('allow_overquota')
        ) {
            // Count current assignments (users + items)
            $count = self::countForLicense($softwarelicenses_id);
            $count += Item_SoftwareLicense::countForLicense($softwarelicenses_id);

            if ($count >= $license->getField('number')) {
                Session::addMessageAfterRedirect(
                    __s('Maximum number of items reached for this license.'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        return parent::prepareInputForAdd($input);
    }

    public static function getTypeName($nb = 0)
    {
        return SoftwareLicense::getTypeName($nb);
    }

    public static function countForLicense(int $softwarelicenses_id): int
    {
        global $DB;

        $iterator = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => static::getTable(),
            'INNER JOIN' => [
                User::getTable() => [
                    'FKEY' => [
                        static::getTable() => 'users_id',
                        User::getTable() => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                static::getTable() . '.softwarelicenses_id' => $softwarelicenses_id,
                User::getTable() . '.is_deleted' => 0,
            ],
        ]);

        return $iterator->current()['cpt'];
    }
}
