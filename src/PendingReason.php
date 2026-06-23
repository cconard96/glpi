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
 * ITILCategory class
 **/
class PendingReason extends CommonDropdown
{
    // From CommonDBTM
    public $dohistory = true;

    // From CommonDBTM
    public $can_be_translated = true;

    // Rights managment
    public static $rightname = 'pendingreason';

    public static function getTypeName($nb = 0)
    {
        return _n('Pending reason', 'Pending reasons', $nb);
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name' => 'is_default',
                'label' => __('Default pending reason'),
                'type' => '',
            ],
            [
                'name' => 'is_pending_per_default',
                'label' => __('Pending per default'),
                'type' => 'bool',
                'form_params' => [
                    'disabled' => !$this->fields['is_default'],
                ],
            ],
            [
                'name' => 'calendars_id',
                'label' => Calendar::getTypeName(1),
                'type' => 'dropdownValue',
                'list' => true,
            ],
            [
                'name'  => 'followup_frequency',
                'label' => __('Automatic follow-up/solution frequency'),
                'type'  => '',
                'list'  => true,
            ],
            [
                'name'      => 'itilfollowuptemplates_id',
                'label'     => ITILFollowupTemplate::getTypeName(1),
                'type'      => 'dropdownValue',
                'list'      => true,
            ],
            [
                'name'  => 'followups_before_resolution',
                'label' => __('Follow-ups before automatic resolution'),
                'type'  => '',
                'list'  => true,
            ],
            [
                'name'      => 'solutiontemplates_id',
                'label'     => SolutionTemplate::getTypeName(1),
                'type'      => 'dropdownValue',
                'list'      => true,
            ],
        ];
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '200',
            'table'              => $this->getTable(),
            'field'              => 'followup_frequency',
            'name'               => __('Automatic follow-up frequency'),
            'searchtype'         => ['equals', 'notequals'],
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '201',
            'table'              => $this->getTable(),
            'field'              => 'followups_before_resolution',
            'name'               => __('Follow-ups before automatic resolution'),
            'searchtype'         => ['equals', 'notequals'],
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '202',
            'table'              => ITILFollowupTemplate::getTable(),
            'field'              => 'name',
            'linkfield'          => ITILFollowupTemplate::getForeignKeyField(),
            'name'               => __('Follow-up template'),
            'massiveaction'      => false,
            'searchtype'         => ['equals', 'notequals'],
            'datatype'           => 'dropdown',
        ];

        $tab[] = [
            'id'                 => '203',
            'table'              => SolutionTemplate::getTable(),
            'field'              => 'name',
            'linkfield'          => SolutionTemplate::getForeignKeyField(),
            'name'               => SolutionTemplate::getTypeName(1),
            'massiveaction'      => false,
            'searchtype'         => ['equals', 'notequals'],
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    /**
     * Get possibles followup frequency values for pending reasons
     * @return array timestamp before each bump => label
     */
    public static function getFollowupFrequencyValues(): array
    {
        $formatter = new NumberFormatter($_SESSION['glpilanguage'], NumberFormatter::SPELLOUT);
        return [
            DAY_TIMESTAMP      => __("Every day"),
            2 * DAY_TIMESTAMP  => sprintf(__("Every %s days"), $formatter->format(2)),
            3 * DAY_TIMESTAMP  => sprintf(__("Every %s days"), $formatter->format(3)),
            4 * DAY_TIMESTAMP  => sprintf(__("Every %s days"), $formatter->format(4)),
            5 * DAY_TIMESTAMP  => sprintf(__("Every %s days"), $formatter->format(5)),
            6 * DAY_TIMESTAMP  => sprintf(__("Every %s days"), $formatter->format(6)),
            WEEK_TIMESTAMP     => __("Every week"),
            2 * WEEK_TIMESTAMP => sprintf(__("Every %s weeks"), $formatter->format(2)),
            3 * WEEK_TIMESTAMP => sprintf(__("Every %s weeks"), $formatter->format(3)),
            4 * WEEK_TIMESTAMP => sprintf(__("Every %s weeks"), $formatter->format(4)),
        ];
    }

    /**
     * Display specific "followups_before_resolution" field
     *
     * @param ?string $value
     * @param string $name
     * @param array $options
     * @param bool $long_label If false give less details in the default label
     *
     * @return int|string
     */
    public static function displayFollowupsNumberBeforeResolutionField(
        $value = null,
        $name = "",
        $options = [],
        $long_label = true
    ) {
        $values = self::getFollowupsBeforeResolutionValues();

        // Short label for forms with input labels
        $label = __("Disabled");

        if ($long_label) {
            // Long default value label for forms with icons instead of labels
            $label = __("Automatic resolution disabled");
        }

        if ($value) {
            if (!isset($values[$value])) {
                $value = null;
            }
        }

        if (empty($name)) {
            $name = "followups_before_resolution";
        }

        $options['value']               = $value;
        $options['emptylabel']          = $label;
        $options['display_emptychoice'] = true;
        $options['display']             = false;
        $options['width']               = '95%';

        return Dropdown::showFromArray($name, $values, $options);
    }

    /**
     * Get possibles values for 'followups_before_resolution' field of pending reasons
     * @return array number of bump before resolution => label
     */
    public static function getFollowupsBeforeResolutionValues(): array
    {
        return [
            -1 => __("No follow-up"),
            1 => __("After one follow-up"),
            2 => __("After two follow-ups"),
            3 => __("After three follow-ups"),
        ];
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                PendingReason_Item::class,
            ]
        );
    }

    public static function getDefault(): ?PendingReason
    {
        $pending_reason = new PendingReason();
        if (
            $pending_reason->getFromDBByCrit([
                'is_default' => 1,
            ])
        ) {
            return $pending_reason;
        }

        return null;
    }

    /**
     * @return void
     */
    public function updateDefaultPendingReason()
    {
        if (isset($this->input['is_default']) && $this->input['is_default']) {
            $previous_default = self::getDefault();
            if ($previous_default !== null) {
                $previous_default->update(['id' => $previous_default->getId()] + ['is_default' => 0]);
            }
        }
    }

    public function pre_addInDB()
    {
        $this->updateDefaultPendingReason();
    }

    public function pre_updateInDB()
    {
        $this->updateDefaultPendingReason();
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInput($input)
    {
        $input['is_pending_per_default'] = isset($input['is_default']) && $input['is_default']
            ? ($input['is_pending_per_default'] ?? 0) : 0;

        return $input;
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput($input);
    }
}
