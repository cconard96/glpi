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
 * SLM Class
 * @since 9.2
 **/
class SLM extends CommonDBTM
{
    // From CommonDBTM
    public $dohistory                   = true;

    protected static $forward_entity_to = ['SLA', 'OLA'];

    public static $rightname                   = 'slm';

    public const TTR = 0; // Time to resolve
    public const TTO = 1; // Time to own

    public const RIGHT_ASSIGN = 256;

    public static function getTypeName($nb = 0)
    {
        return _n('Service level', 'Service levels', $nb);
    }

    public static function getLogDefaultServiceName(): string
    {
        return 'setup';
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->handleCalendarStrategy($input);

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->handleCalendarStrategy($input);

        return parent::prepareInputForAdd($input);
    }

    /**
     * Handle negative input in `calendars_id`.
     * This method is usefull to be able to propose a `-1` special value in Calendar dropdown.
     *
     * @param array $input
     *
     * @return array
     */
    private function handleCalendarStrategy(array $input): array
    {
        if (array_key_exists('calendars_id', $input)) {
            if ((int) $input['calendars_id'] === -1) {
                $input['calendars_id'] = 0;
                $input['use_ticket_calendar'] = 1;
            } else {
                $input['use_ticket_calendar'] = 0;
            }
        }

        return $input;
    }

    public function post_updateItem($history = true)
    {
        global $DB;

        if (in_array('use_ticket_calendar', $this->updates, true) || in_array('calendars_id', $this->updates, true)) {
            // Propagate calendar settings to children
            foreach ([OLA::class, SLA::class] as $child_class) {
                $child_iterator = $DB->request(
                    [
                        'SELECT' => 'id',
                        'FROM'   => $child_class::getTable(),
                        'WHERE'  => [
                            static::getForeignKeyField() => $this->getID(),
                        ],
                    ]
                );
                foreach ($child_iterator as $child_data) {
                    $child = new $child_class();
                    $child->update(
                        [
                            'id'                  => $child_data['id'],
                            'use_ticket_calendar' => $this->fields['use_ticket_calendar'],
                            'calendars_id'        => $this->fields['calendars_id'],
                        ]
                    );
                }
            }
        }

        parent::post_updateItem($history);
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                SLA::class,
                OLA::class,
            ]
        );
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics'),
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => static::getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false,
            'datatype'           => 'number',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => 'glpi_calendars',
            'field'              => 'name',
            'name'               => _n('Calendar', 'Calendars', 1),
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '16',
            'table'              => static::getTable(),
            'field'              => 'comment',
            'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
            'datatype'           => 'text',
        ];

        $tab[] = [
            'id'              => '80',
            'table'           => Entity::getTable(),
            'field'           => 'completename',
            'name'            => Entity::getTypeName(1),
            'massiveaction'   => false,
            'datatype'        => 'dropdown',
        ];

        $tab[] = [
            'id'            => '86',
            'table'         => static::getTable(),
            'field'         => 'is_recursive',
            'name'          => __('Child entities'),
            'datatype'      => 'bool',
            'massiveaction' => false,
        ];

        return $tab;
    }

    public function getRights($interface = 'central')
    {
        $values = parent::getRights();
        $values[self::RIGHT_ASSIGN]  = [
            'short' => __('Assign'),
            'long'  => __('Search result user display'),
        ];

        return $values;
    }
}
