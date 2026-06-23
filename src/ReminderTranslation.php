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
 * ReminderTranslation Class
 *
 * @since 9.5
 **/
class ReminderTranslation extends CommonDBChild
{
    public static $itemtype = Reminder::class;
    public static $items_id = 'reminders_id';
    public $dohistory       = true;
    public static $logs_for_parent = false;

    public static $rightname       = 'reminder_public';

    public static function getTypeName($nb = 0)
    {
        return _n('Translation', 'Translations', $nb);
    }

    public static function getIcon()
    {
        return 'ti ti-language';
    }

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    /**
     * Get a translation for a value
     *
     * @param Reminder $item   item to translate
     * @param string       $field  field to return (default 'name')
     *
     * @return string  the field translated if a translation is available, or the original field if not
     **/
    public static function getTranslatedValue(Reminder $item, $field = "name")
    {
        $obj   = new self();
        $found = $obj->find([
            'reminders_id'   => $item->getID(),
            'language'           => $_SESSION['glpilanguage'],
        ]);

        if (
            (count($found) > 0)
            && in_array($field, ['name', 'text'])
        ) {
            $first = array_shift($found);
            if ($first[$field] !== null && $first[$field] !== "") {
                return $first[$field];
            }
        }
        return $item->fields[$field] ?? "";
    }

    /**
     * Get already translated languages for item
     *
     * @param Reminder $item
     *
     * @return array of already translated languages
     **/
    public static function getAlreadyTranslatedForItem($item)
    {
        global $DB;

        $tab = [];

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => ['reminders_id' => $item->getID()],
        ]);

        foreach ($iterator as $data) {
            $tab[$data['language']] = $data['language'];
        }
        return $tab;
    }
}
