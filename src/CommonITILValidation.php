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

use Glpi\DBAL\QueryFunction;
use Glpi\DBAL\QuerySubQuery;

/**
 * CommonITILValidation Class
 *
 * @since 0.85
 **/
abstract class CommonITILValidation extends CommonDBChild
{
    // From CommonDBTM
    public $auto_message_on_action    = false;

    public static $log_history_add    = Log::HISTORY_LOG_SIMPLE_MESSAGE;
    public static $log_history_update = Log::HISTORY_LOG_SIMPLE_MESSAGE;
    public static $log_history_delete = Log::HISTORY_LOG_SIMPLE_MESSAGE;

    public const VALIDATE               = 1024;


    // STATUSES
    public const NONE      = 1; // used for ticket.global_validation
    public const WAITING   = 2;
    public const ACCEPTED  = 3;
    public const REFUSED   = 4;

    /**
     * @return class-string<CommonITILObject>
     */
    public static function getItilObjectItemType()
    {
        return str_replace('Validation', '', static::class);
    }

    public static function getItilObjectItemInstance(): CommonITILObject
    {
        $class = static::getItilObjectItemType();

        if (!is_a($class, CommonITILObject::class, true)) {
            throw new LogicException();
        }

        return new $class();
    }

    /**
     * @return class-string<ITIL_ValidationStep>|null
     */
    public static function getValidationStepClassName(): ?string
    {
        $validation_class = static::class . 'Step';
        if (class_exists($validation_class)) {
            return $validation_class;
        }

        return null;
    }

    public static function getValidationStepInstance(): ?ITIL_ValidationStep
    {
        $class = self::getValidationStepClassName();

        return $class ? getItemForItemtype($class) : null;
    }

    /**
     * @return int[]
     */
    public static function getCreateRights()
    {
        return [CREATE];
    }

    /**
     * @return int[]
     */
    public static function getPurgeRights()
    {
        return [PURGE];
    }

    /**
     * @return int[]
     */
    public static function getValidateRights()
    {
        return [static::VALIDATE];
    }


    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }


    public static function getTypeName($nb = 0)
    {
        return _n('Approval', 'Approvals', $nb);
    }


    public static function canCreate(): bool
    {
        return Session::haveRightsOr(static::$rightname, static::getCreateRights());
    }


    /**
     * Is the current user have right to delete the current validation ?
     *
     * @return bool
     **/
    public function canCreateItem(): bool
    {

        if (
            ($this->fields["users_id"] == Session::getLoginUserID())
            || Session::haveRightsOr(static::$rightname, static::getCreateRights())
        ) {
            return true;
        }
        return false;
    }


    public static function canView(): bool
    {

        return Session::haveRightsOr(
            static::$rightname,
            array_merge(
                static::getCreateRights(),
                static::getValidateRights(),
                static::getPurgeRights()
            )
        );
    }


    public static function canUpdate(): bool
    {

        return Session::haveRightsOr(
            static::$rightname,
            array_merge(
                static::getCreateRights(),
                static::getValidateRights()
            )
        );
    }


    /**
     * Is the current user have right to delete the current validation ?
     *
     * @return bool
     **/
    public function canDeleteItem(): bool
    {

        if (
            ($this->fields["users_id"] == Session::getLoginUserID())
            || Session::haveRight(static::$rightname, DELETE)
        ) {
            return true;
        }
        return false;
    }


    /**
     * Does the current user have the rights needed to update the current validation?
     *
     * @return bool
     */
    public function canUpdateItem(): bool
    {
        if (
            !$this->canAnswer()
            && !Session::haveRightsOr(static::$rightname, static::getCreateRights())
        ) {
            return false;
        }
        return (int) $this->fields['status'] === self::WAITING
            || (int) $this->fields['users_id_validate'] === Session::getLoginUserID();
    }

    /**
     * @param int $items_id ID of the item
     *
     * @return bool
     */
    public static function canValidate($items_id)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => [static::getTable() . '.id'],
            'FROM'   => static::getTable(),
            'WHERE'  => [
                static::$items_id => $items_id,
                static::getTargetCriteriaForUser(Session::getLoginUserID()),
            ],
            'START'  => 0,
            'LIMIT'  => 1,
        ]);
        return count($iterator) > 0;
    }

    /**
     * Indicates whether the current connected user can answer the validation.
     */
    final public function canAnswer(): bool
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => [static::getTable() . '.id'],
            'FROM'   => static::getTable(),
            'WHERE'  => [
                'id' => $this->getID(),
                static::getTargetCriteriaForUser(Session::getLoginUserID()),
            ],
            'START'  => 0,
            'LIMIT'  => 1,
        ]);
        return count($iterator) > 0;
    }

    public function post_getEmpty()
    {

        $this->fields["users_id"] = Session::getLoginUserID();
        $this->fields["status"]   = self::WAITING;
    }


    public function prepareInputForAdd($input)
    {
        // validation step is mandatory : add default value is not set
        if (!isset($input['itils_validationsteps_id']) && !isset($input['_validationsteps_id'])) {
            $input['_validationsteps_id'] = ValidationStep::getDefault()->getID();
        }

        $input = $this->addITILValidationStepFromInput($input);

        $input["users_id"] = 0;
        // Only set requester on manual action
        if (
            !isset($input['_auto_import'])
            && !isset($input['_auto_update'])
            && !Session::isCron()
        ) {
            $input["users_id"] = Session::getLoginUserID();
        }

        $input["submission_date"] = $_SESSION["glpi_currenttime"];
        $input["status"]          = self::WAITING;

        if (
            (!isset($input['itemtype_target']) || empty($input['itemtype_target']))
            && (isset($input['users_id_validate']) && !empty($input['users_id_validate']))
        ) {
            Toolbox::deprecated('Defining "users_id_validate" field during creation is deprecated in "CommonITILValidation".');
            $input['itemtype_target'] = User::class;
            $input['items_id_target'] = $input['users_id_validate'];
            unset($input['users_id_validate']);
        }

        if (
            !isset($input['itemtype_target']) || empty($input['itemtype_target'])
            || !isset($input["items_id_target"]) || $input["items_id_target"] <= 0
        ) {
            return false;
        }

        $itil_class = static::getItilObjectItemType();
        $itil_fkey  = $itil_class::getForeignKeyField();
        $input['timeline_position'] = $itil_class::getTimelinePosition($input[$itil_fkey], static::class, $input["users_id"]);

        return parent::prepareInputForAdd($input);
    }

    public function post_addItem()
    {
        global $CFG_GLPI;

        $itilobject = $this->getItem();
        $this->checkIsAnItilObject($itilobject);

        // Handle rich-text images
        foreach (['comment_submission', 'comment_validation'] as $content_field) {
            $this->input = $this->addFiles($this->input, [
                'force_update'  => true,
                'name'          => $content_field,
                'content_field' => $content_field,
            ]);
        }

        // Handle uploaded documents
        $this->input = $this->addFiles($this->input);

        // --- update item (ITILObject) handling the validation
        // always recompute global validation status on ticket
        $input = [
            'id' => $itilobject->getID(),
            'global_validation' => static::computeValidationStatus($itilobject),
            '_from_itilvalidation' => true,
            '_trigger' => $this,
        ];

        // to fix lastupdater
        if (isset($this->input['_auto_update'])) {
            $input['_auto_update'] = $this->input['_auto_update'];
        }
        // to know update by rules
        if (isset($this->input["_rule_process"])) {
            $input['_rule_process'] = $this->input["_rule_process"];
        }
        // No update ticket notif on ticket add
        if (isset($this->input["_ticket_add"])) {
            $input['_disablenotif'] = true;
        }
        $itilobject->update($input);

        // -- send email notification
        $mailsend = false;
        if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_notifications"]) {
            $options = ['validation_id' => $this->fields["id"],
                'validation_status' => $this->fields["status"],
            ];
            $mailsend = NotificationEvent::raiseEvent('validation', $itilobject, $options, $this);
        }
        if ($mailsend) {
            if ($this->fields['itemtype_target'] === 'User') {
                $user = new User();
                $user->getFromDB($this->fields["items_id_target"]);
                $email = $user->getDefaultEmail();
                if (!empty($email)) {
                    Session::addMessageAfterRedirect(htmlescape(sprintf(__('Approval request sent to %s'), $user->getName())));
                } else {
                    Session::addMessageAfterRedirect(
                        htmlescape(sprintf(
                            __('The selected user (%s) has no valid email address. The request has been created, without email confirmation.'),
                            $user->getName()
                        )),
                        false,
                        ERROR
                    );
                }
            } elseif (is_a($this->fields["itemtype_target"], CommonDBTM::class, true)) {
                $target = new $this->fields["itemtype_target"]();
                if ($target->getFromDB($this->fields["items_id_target"])) {
                    Session::addMessageAfterRedirect(htmlescape(sprintf(__('Approval request sent to %s'), $target->getName())));
                }
            }
        }
        parent::post_addItem();
    }


    public function prepareInputForUpdate($input)
    {
        $can_answer = $this->canAnswer();
        // Don't allow changing internal entity fields or change the item it is attached to
        $forbid_fields = ['entities_id', static::$items_id, 'is_recursive'];
        // The following fields shouldn't be changed by anyone after the approval is created
        $forbid_fields[] = 'itils_validationsteps_id';
        $forbid_fields[] = 'users_id';
        $forbid_fields[] = 'itemtype_target';
        $forbid_fields[] = 'items_id_target';
        $forbid_fields[] = 'submission_date';

        if (!$can_answer) {
            $forbid_fields[] = 'status';
            $forbid_fields[] = 'comment_validation';
            $forbid_fields[] = 'validation_date';
        }

        if ($this->fields["status"] !== self::WAITING) {
            // Cannot change the approval request comment after it has been answered
            $forbid_fields[] = 'comment_submission';
        }

        foreach ($forbid_fields as $key) {
            unset($input[$key]);
        }

        if (isset($input["status"])) {
            if (
                ($input["status"] == self::REFUSED)
                && (!isset($input["comment_validation"])
                 || ($input["comment_validation"] == ''))
            ) {
                Session::addMessageAfterRedirect(
                    __s('If approval is denied, specify a reason.'),
                    false,
                    ERROR
                );
                return false;
            }
            if ($input["status"] == self::WAITING) {
                // $input["comment_validation"] = '';
                $input["validation_date"] = 'NULL';
            } else {
                $input["validation_date"] = $_SESSION["glpi_currenttime"];
            }
        }

        return parent::prepareInputForUpdate($input);
    }

    public function post_purgeItem()
    {
        $this->recomputeItilStatus();
        $this->removeUnsedITILValidationStep();

        parent::post_purgeItem();
    }

    public function post_updateItem($history = true)
    {
        global $CFG_GLPI;

        $this->recomputeItilStatus();

        $donotif = $CFG_GLPI["use_notifications"];
        if (isset($this->input['_disablenotif'])) {
            $donotif = false;
        }

        // Handle rich-text images
        foreach (['comment_submission', 'comment_validation'] as $content_field) {
            $this->input = $this->addFiles($this->input, [
                'force_update'  => true,
                'name'          => $content_field,
                'content_field' => $content_field,
            ]);
        }

        // Handle uploaded documents
        $this->input = $this->addFiles($this->input);

        // -- notifications
        if (
            count($this->updates)
            && $donotif
        ) {
            $options  = ['validation_id'     => $this->fields["id"],
                'validation_status' => $this->fields["status"],
            ];
            NotificationEvent::raiseEvent('validation_answer', $this->getItem(), $options, $this);
        }

        parent::post_updateItem($history);
    }

    public function post_deleteItem()
    {
        $item = $this->getItem();
        if ($item instanceof CommonITILObject) {
            $input = [
                'id'                    => $item->getID(),
                'global_validation'     => static::computeValidationStatus($item),
                '_from_itilvalidation'  => true,
                '_trigger'              => $this,
            ];

            if (!$item->update($input)) {
                throw new RuntimeException(sprintf('Failed to update related `%s` approval status.', $item::class));
            }
        }
    }


    /**
     * @see CommonDBConnexity::getHistoryChangeWhenUpdateField
     **/
    public function getHistoryChangeWhenUpdateField($field)
    {
        $result = [];
        if ($field == 'status') {
            $result   = ['0', '', ''];
            if ($this->fields["status"] == self::ACCEPTED) {
                //TRANS: %s is the username
                $result[2] = sprintf(__('Approval granted by %s'), getUserName($this->fields["users_id_validate"]));
            } else {
                //TRANS: %s is the username
                $result[2] = sprintf(__('Update the approval request to %s'), $this->getTargetName());
            }
        }
        return $result;
    }


    /**
     * @see CommonDBChild::getHistoryNameForItem
     **/
    public function getHistoryNameForItem(CommonDBTM $item, $case)
    {
        $target_name = $this->getTargetName();

        switch ($case) {
            case 'add':
                return sprintf(__('Approval request sent to %s'), $target_name);

            case 'delete':
                return sprintf(__('Cancel the approval request to %s'), $target_name);
        }
        return '';
    }

    /**
     * Returns the target name.
     *
     * @return string
     */
    final protected function getTargetName(): string
    {
        $target_name = '';
        switch ($this->fields['itemtype_target']) {
            case User::class:
                $target_name = getUserName($this->fields['items_id_target']);
                break;
            default:
                if (!is_a($this->fields['itemtype_target'], CommonDBTM::class, true)) {
                    break;
                }
                $target_item = new $this->fields['itemtype_target']();
                if ($target_item->getFromDB($this->fields['items_id_target'])) {
                    $target_name = $target_item->getNameID();
                }
                break;
        }
        return $target_name;
    }


    /**
     * get the Ticket validation status list
     *
     * @param bool $withmetaforsearch  false by default
     * @param bool $global             true for global status, with "no validation" option, false by default
     *
     * @return array
     */
    public static function getAllStatusArray($withmetaforsearch = false, $global = false)
    {

        $tab = [
            self::WAITING  => __('Waiting for approval'),
            self::REFUSED  => _x('validation', 'Refused'),
            self::ACCEPTED => __('Granted'),
        ];
        if ($global) {
            $tab[self::NONE] = __('Not subject to approval');

            if ($withmetaforsearch) {
                $tab['can'] = __('Granted + Not subject to approval');
            }
        }

        if ($withmetaforsearch) {
            $tab['all'] = __('All');
        }
        return $tab;
    }


    /**
     * Dropdown of validation status
     *
     * @param string $name    select name
     * @param array  $options possible options:
     *      - value    : default value (default waiting)
     *      - all      : boolean display all (default false)
     *      - global   : for global validation (default false)
     *      - display  : boolean display or get string ? (default true)
     *
     * @return string|int Output string if display option is set to false,
     *                        otherwise random part of dropdown id
     **/
    public static function dropdownStatus($name, $options = [])
    {

        $p = [
            'value'             => self::WAITING,
            'global'            => false,
            'all'               => false,
            'display'           => true,
            'disabled'          => false,
            'templateResult'    => "templateValidation",
            'templateSelection' => "templateValidation",
            'width'             => '100%',
            'required'          => false,
        ];

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $p[$key] = $val;
            }
        }

        $tab = self::getAllStatusArray($p['all'], $p['global']);
        unset($p['all']);
        unset($p['global']);

        return Dropdown::showFromArray($name, $tab, $p);
    }


    /**
     * Get Ticket validation status Name
     *
     * @param int   $value
     * @param bool      $decorated
     *
     * @return string
     **/
    public static function getStatus($value, bool $decorated = false)
    {
        $statuses = self::getAllStatusArray(true, true);

        $label = $statuses[$value] ?? $value;

        if ($decorated) {
            $classes = null;
            switch ($value) {
                case self::WAITING:
                    $classes = 'waiting ti ti-clock';
                    break;
                case self::ACCEPTED:
                    $classes = 'accepted ti ti-check';
                    break;
                case self::REFUSED:
                    $classes = 'refused ti ti-x';
                    break;
            }

            return sprintf('<span><i class="validationstatus %s"></i> %s</span>', $classes, htmlescape($label));
        }

        return $label;
    }


    /**
     * Get Ticket validation status Color
     *
     * @param int $value status ID
     *
     * @return string
     **/
    public static function getStatusColor($value)
    {

        switch ($value) {
            case self::WAITING:
                $style = "#FFC65D";
                break;

            case self::REFUSED:
                $style = "#ff0000";
                break;

            case self::ACCEPTED:
                $style = "#43e900";
                break;

            default:
                $style = "#ff0000";
        }
        return $style;
    }

    /**
     * Get item validation demands count for a user
     *
     * @param int $users_id User ID
     *
     * @return int
     **/
    public static function getNumberToValidate($users_id)
    {
        global $DB;

        $itil_class = static::getItilObjectItemType();

        $it = $DB->request([
            'FROM'   => $itil_class::getTable(),
            'COUNT'  => 'cpt',
            'WHERE'  => [
                [
                    'id' => new QuerySubQuery([
                        'SELECT' => $itil_class::getForeignKeyField(),
                        'FROM'   => static::getTable(),
                        'WHERE'  => [
                            'status' => self::WAITING,
                            static::getTargetCriteriaForUser($users_id),
                        ],
                    ]),
                ],
                'NOT' => [
                    'status' => [...$itil_class::getSolvedStatusArray(), ...$itil_class::getClosedStatusArray()],
                ],
            ],
        ]);

        return (int) $it->current()['cpt'];
    }

    /**
     * Return criteria to apply to get only validations on which given user is targetted.
     *
     * @see self::getNumberToValidate()
     *
     * @param int $users_id
     * @param bool $search_in_groups
     *
     * @return array
     */
    final public static function getTargetCriteriaForUser(int $users_id, bool $search_in_groups = true): array
    {
        $substitute_subQuery = new QuerySubQuery([
            'SELECT'     => 'validator_users.id',
            'FROM'       => User::getTable() . ' as validator_users',
            'INNER JOIN' => [
                ValidatorSubstitute::getTable() => [
                    'ON' => [
                        ValidatorSubstitute::getTable() => User::getForeignKeyField(),
                        'validator_users' => 'id',
                        [
                            'AND' => [
                                [
                                    'OR' => [
                                        [
                                            'validator_users.substitution_start_date' => null,
                                        ],
                                        [
                                            'validator_users.substitution_start_date' => ['<=', QueryFunction::now()],
                                        ],
                                    ],
                                ],
                                [
                                    'OR' => [
                                        [
                                            'validator_users.substitution_end_date' => null,
                                        ],
                                        [
                                            'validator_users.substitution_end_date' => ['>=', QueryFunction::now()],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE'  => [
                ValidatorSubstitute::getTable() . '.users_id_substitute' => $users_id,
            ],
        ]);

        $target_criteria = [
            'OR' => [
                [
                    static::getTableField('itemtype_target') => User::class,
                    static::getTableField('items_id_target') => $users_id,
                ],
                [
                    static::getTableField('itemtype_target') => User::class,
                    static::getTableField('items_id_target') => $substitute_subQuery,
                ],
            ],
        ];
        if ($search_in_groups) {
            $target_criteria = [
                'OR' => [
                    $target_criteria,
                    [
                        static::getTableField('itemtype_target') => Group::class,
                        static::getTableField('items_id_target') => new QuerySubQuery([
                            'SELECT' => Group_User::getTableField('groups_id'),
                            'FROM'   => Group_User::getTable(),
                            'WHERE'  => [
                                'OR' => [
                                    [
                                        Group_User::getTableField('users_id') => $users_id,
                                    ],
                                    [
                                        Group_User::getTableField('users_id') => $substitute_subQuery,
                                    ],
                                ],
                            ],
                        ]),
                    ],
                ],
            ];
        }

        return $target_criteria;
    }

    /**
     * @since 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     **/
    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {

        switch ($ma->getAction()) {
            case 'submit_validation':
                $input = $ma->getInput();
                $valid = new static();
                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        $input2 = [static::$items_id      => $id,
                            'comment_submission'   => $input['comment_submission'],
                        ];
                        if ($valid->can(-1, CREATE, $input2)) {
                            if (array_key_exists('users_id_validate', $input)) {
                                Toolbox::deprecated('Usage of "users_id_validate" in input is deprecated. Use "itemtype_target"/"items_id_target" instead.');
                                $input['itemtype_target'] = User::class;
                                $input['items_id_target'] = $input['users_id_validate'];
                                unset($input['users_id_validate']);
                            }

                            $itemtype  = $input['itemtype_target'];
                            $items_ids = $input['items_id_target'];

                            if (!is_array($items_ids)) {
                                $items_ids = [$items_ids];
                            }
                            $ok = true;
                            foreach ($items_ids as $item_id) {
                                $input2["itemtype_target"] = $itemtype;
                                $input2["items_id_target"] = $item_id;
                                if (!$valid->add($input2)) {
                                    $ok = false;
                                }
                            }
                            if ($ok) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_NOT_FOUND));
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $table = static::getTable();

        $tab[] = [
            'id'                 => 'common',
            'name'               => CommonITILValidation::getTypeName(1),
        ];

        $tab[] = [
            'id'                 => 9,
            'table'              => $table,
            'field'              => 'id',
            'name'               => __('ID'),
            'datatype'           => 'number',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => $table,
            'field'              => 'comment_submission',
            'name'               => __('Request comments'),
            'datatype'           => 'text',
            'htmltext'           => true,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $table,
            'field'              => 'comment_validation',
            'name'               => __('Approval comments'),
            'datatype'           => 'text',
            'htmltext'           => true,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $table,
            'field'              => 'status',
            'name'               => __('Status'),
            'searchtype'         => 'equals',
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => $table,
            'field'              => 'submission_date',
            'name'               => __('Request date'),
            'datatype'           => 'datetime',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => $table,
            'field'              => 'validation_date',
            'name'               => __('Approval date'),
            'datatype'           => 'datetime',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'name'               => __('Approval requester'),
            'datatype'           => 'itemlink',
            'right'              => static::$itemtype == Ticket::class ? 'create_ticket_validate' : 'create_validate',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'linkfield'          => 'users_id_validate',
            'name'               => __('Approver'),
            'datatype'           => 'itemlink',
            'right'              => static::$itemtype == Ticket::class ? ['validate_request', 'validate_incident'] : 'validate',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => $table,
            'field'              => 'itemtype_target',
            'name'               => __('Requested approver type'),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    /**
     * @return array
     */
    public static function rawSearchOptionsToAdd()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'validation',
            'name'               => CommonITILValidation::getTypeName(1),
        ];

        $tab[] = [
            'id'                 => '52',
            'table'              => getTableForItemType(static::$itemtype),
            'field'              => 'global_validation',
            'name'               => CommonITILValidation::getTypeName(1),
            'searchtype'         => 'equals',
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '53',
            'table'              => static::getTable(),
            'field'              => 'comment_submission',
            'name'               => __('Request comments'),
            'datatype'           => 'text',
            'htmltext'           => true,
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '54',
            'table'              => static::getTable(),
            'field'              => 'comment_validation',
            'name'               => __('Approval comments'),
            'datatype'           => 'text',
            'htmltext'           => true,
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '55',
            'table'              => static::getTable(),
            'field'              => 'status',
            'datatype'           => 'specific',
            'name'               => __('Approval status'),
            'searchtype'         => 'equals',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '56',
            'table'              => static::getTable(),
            'field'              => 'submission_date',
            'name'               => __('Request date'),
            'datatype'           => 'datetime',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '57',
            'table'              => static::getTable(),
            'field'              => 'validation_date',
            'name'               => __('Approval date'),
            'datatype'           => 'datetime',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        $tab[] = [
            'id'                 => '58',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'name'               => _n('Requester', 'Requesters', 1),
            'datatype'           => 'itemlink',
            'right'              => (static::$itemtype == Ticket::class ? 'create_ticket_validate' : 'create_validate'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => static::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '59',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'linkfield'          => 'items_id_target',
            'name'               => __('Approver'),
            'datatype'           => 'itemlink',
            'right'              => static::$itemtype == Ticket::class ? ['validate_request', 'validate_incident'] : 'validate',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'condition'          => [
                    'REFTABLE.itemtype_target' => User::class,
                ],
                'beforejoin'         => [
                    'table'              => static::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '195',
            'table'              => User::getTable(),
            'field'              => 'name',
            'linkfield'          => 'users_id_substitute',
            'name'               => __('Approver substitute'),
            'datatype'           => 'itemlink',
            'right'              => (
                static::$itemtype == Ticket::class
                ? ['validate_request', 'validate_incident']
                : 'validate'
            ),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams' => [
                'beforejoin'         => [
                    'table'          => ValidatorSubstitute::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                        'condition'          => [
                            // same condition on search option 197, but with swapped expression
                            // This workarounds identical complex join ID if a search ise both search options 195 and 197
                            [
                                'OR' => [
                                    [
                                        'REFTABLE.substitution_start_date' => null,
                                    ], [
                                        'REFTABLE.substitution_start_date' => ['<=', QueryFunction::now()],
                                    ],
                                ],
                            ], [
                                'OR' => [
                                    [
                                        'REFTABLE.substitution_end_date' => null,
                                    ], [
                                        'REFTABLE.substitution_end_date' => ['>=', QueryFunction::now()],
                                    ],
                                ],
                            ],
                        ],
                        'beforejoin'         => [
                            'table'              => User::getTable(),
                            'linkfield'          => 'items_id_target',
                            'joinparams'             => [
                                'condition'                  => [
                                    'REFTABLE.itemtype_target' => User::class,
                                ],
                                'beforejoin'             => [
                                    'table'                  => static::getTable(),
                                    'joinparams'                 => [
                                        'jointype'                   => 'child',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '196',
            'table'              => 'glpi_groups',
            'field'              => 'completename',
            'linkfield'          => 'items_id_target',
            'name'               => __('Approver group'),
            'datatype'           => 'itemlink',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'condition'          => [
                    'REFTABLE.itemtype_target' => Group::class,
                ],
                'beforejoin'         => [
                    'table'              => static::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '197',
            'table'              => User::getTable(),
            'field'              => 'name',
            'linkfield'          => 'users_id_substitute',
            'name'               => __('Substitute of a member of approver group'),
            'datatype'           => 'itemlink',
            'right'              => (
                static::$itemtype == Ticket::class
                ? ['validate_request', 'validate_incident']
                : 'validate'
            ),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'          => ValidatorSubstitute::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                        'condition'          => [
                            // same condition on search option 195, but with swapped expression
                            // This workarounds identical complex join ID if a search ise both search options 195 and 197
                            [
                                'OR' => [
                                    [
                                        'REFTABLE.substitution_end_date' => null,
                                    ], [
                                        'REFTABLE.substitution_end_date' => ['>=', QueryFunction::now()],
                                    ],
                                ],
                            ], [
                                'OR' => [
                                    [
                                        'REFTABLE.substitution_start_date' => null,
                                    ], [
                                        'REFTABLE.substitution_start_date' => ['<=', QueryFunction::now()],
                                    ],
                                ],
                            ],
                        ],
                        'beforejoin'         => [
                            'table'          => User::getTable(),
                            'joinparams'         => [
                                'beforejoin'         => [
                                    'table'          => Group_User::getTable(),
                                    'joinparams'         => [
                                        'jointype'           => 'child',
                                        'beforejoin'         => [
                                            'table'              => Group::getTable(),
                                            'linkfield'          => 'items_id_target',
                                            'joinparams'         => [
                                                'condition'          => [
                                                    'REFTABLE.itemtype_target' => Group::class,
                                                ],
                                                'beforejoin'         => [
                                                    'table'              => static::getTable(),
                                                    'joinparams'         => [
                                                        'jointype'           => 'child',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '198',
            'table'              => static::getTable(),
            'field'              => 'status',
            'datatype'           => 'specific',
            'name'               => __('Approval status by users'),
            'searchtype'         => 'equals',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'additionalfields'   => ['itemtype_target', 'items_id_target'],
            'joinparams'         => [
                'jointype'           => 'child',
            ],
        ];

        return $tab;
    }

    /**
     * @see commonDBTM::getRights()
     **/
    public function getRights($interface = 'central')
    {

        $values = parent::getRights();
        unset($values[UPDATE], $values[READ]);

        $values[self::VALIDATE]  = __('Validate');

        return $values;
    }

    /**
     * Get list of users from a group which have validation rights
     *
     * @param $options   array   possible:
     *       groups_id
     *       right
     *       entity
     *
     * @return array
     **/
    public static function getGroupUserHaveRights(array $options = [])
    {
        $params = [
            'entity' => $_SESSION['glpiactive_entity'],
        ];
        if (static::$itemtype == Ticket::class) {
            $params['right']  = ['validate_request', 'validate_incident'];
        } else {
            $params['right']  = ['validate'];
        }
        $params['groups_id'] = 0;

        foreach ($options as $key => $val) {
            $params[$key] = $val;
        }

        $list       = [];
        $restrict   = [];

        $res = User::getSqlSearchResult(false, $params['right'], $params['entity']);
        foreach ($res as $data) {
            $list[] = $data['id'];
        }
        if (count($list) > 0) {
            $restrict = ['glpi_users.id' => $list];
        }
        $users = Group_User::getGroupUsers($params['groups_id'], $restrict);

        return $users;
    }

    /**
     * Compute the validation status
     *
     * Reduced all the Validations of an item to a single status
     *
     * @param $itil CommonITILObject
     **@return int CommonITILValidation::VALIDATE|CommonITILValidation::REFUSED|CommonITILValidation::WAITING|CommonITILValidation::NONE
     */
    public static function computeValidationStatus(CommonITILObject $itil): int
    {
        $vs = $itil->getValidationStepInstance();
        return $vs::getValidationStatusForITIL($itil);
    }

    /**
     * @param CommonITILObject $item
     * @param string $type
     *
     * Used in twig template
     *
     * @return void
     */
    public static function alertValidation(CommonITILObject $item, $type)
    {
        // No alert for new item
        if ($item->isNewID($item->getID())) {
            return;
        }
        $status  = array_merge($item->getClosedStatusArray(), $item->getSolvedStatusArray());

        switch ($type) {
            case 'status':
                $message = __("This item is waiting for approval, do you really want to resolve or close it?");
                $jsScript = "
               $(document).ready(
                  function() {
                     $('[name=\"status\"]').change(function() {
                        var status_ko = 0;
                        var input_status = $(this).val();
                        if (input_status != undefined) {
                           if ((";
                $first = true;
                foreach ($status as $val) {
                    if (!$first) {
                        $jsScript .= "||";
                    }
                    $jsScript .= "input_status == $val";
                    $first = false;
                }
                $jsScript .= "           )
                                 && input_status != " . $item->fields['status'] . "){
                              status_ko = 1;
                           }
                        }
                        if ((status_ko == 1)
                            && ('" . ($item->fields['global_validation'] ?? '') . "' == '" . self::WAITING . "')) {
                           alert('" . jsescape($message) . "');
                        }
                     });
                  }
               );";
                echo Html::scriptBlock($jsScript);
                break;

            case 'solution':
                if (
                    !in_array($item->fields['status'], $status)
                    && isset($item->fields['global_validation'])
                    && $item->fields['global_validation'] == self::WAITING
                ) {
                    $title   = __s("This item is waiting for approval.");
                    $message = __s("Do you really want to resolve or close it?");
                    ;
                    $html = <<<HTML
                  <div class="alert alert-warning" role="alert">
                     <div class="d-flex">
                        <div class="me-2">
                           <i class="ti ti-alert-triangle fs-2x"></i>
                        </div>
                        <div>
                           <h4 class="alert-title">$title</h4>
                           <div class="text-muted">$message</div>
                        </div>
                     </div>
                  </div>
HTML;
                    echo $html;
                }
                break;
        }
    }

    /**
     * Get the ITIL object can validation status list
     *
     * @since 0.85
     *
     * @return array
     **/
    public static function getCanValidationStatusArray()
    {
        return [self::NONE, self::ACCEPTED];
    }

    /**
     * Get the ITIL object all validation status list
     *
     * @since 0.85
     *
     * @return array
     **/
    public static function getAllValidationStatusArray()
    {
        return [self::NONE, self::WAITING, self::REFUSED, self::ACCEPTED];
    }

    /**
     * Associate the validation with an "itil validation step" created from an exiting "validation step"
     *
     * If no itils_validationsteps is defined for the itilobject, create it
     * else, refererence it.
     */
    private function addITILValidationStepFromInput(array $input): array
    {
        $itil_class = static::getItilObjectItemType(); // Change | Ticket
        $itil_fkey  = $itil_class::getForeignKeyField(); // changes_id | tickets_id

        if (!array_key_exists('_validationsteps_id', $input) || !array_key_exists($itil_fkey, $input)) {
            return $input;
        }

        $relation_fields = [
            'itemtype'           => $itil_class,
            'items_id'           => $input[$itil_fkey],
            'validationsteps_id' => $input['_validationsteps_id'],
        ];

        $itil_validationstep = $itil_class::getValidationStepInstance();
        if (!$itil_validationstep->getFromDBByCrit($relation_fields)) {
            $validationstep = new ValidationStep();
            if (!$validationstep->getFromDB($input['_validationsteps_id'])) {
                throw new RuntimeException('Failed to get validation step with id #' . $input['_validationsteps_id']);
            };

            $step_input = $relation_fields + [
                'minimal_required_validation_percent' => $validationstep->fields['minimal_required_validation_percent'],
            ];

            if (!$itil_validationstep->add($step_input)) {
                throw new RuntimeException('Failed to create approval step of type ' . get_class($itil_validationstep));
            }
        }

        $input['itils_validationsteps_id'] = $itil_validationstep->getID();
        unset($input['_validationsteps_id']);

        return $input;
    }

    /**
     * Delete, only if the itils_validationstep is not used anymore
     *
     * @return void
     */
    private function removeUnsedITILValidationStep(): void
    {
        $itils_validationsteps_id = $this->fields['itils_validationsteps_id'];

        $validations = (new static())->find(['itils_validationsteps_id' => $itils_validationsteps_id]);
        if (!empty($validations)) {
            // itils_validation is still used, do not delete
            return;
        }

        $itil_validationstep = static::getItilObjectItemType()::getValidationStepInstance();
        if (!$itil_validationstep->delete(['id' => $itils_validationsteps_id])) {
            throw new RuntimeException('Failed to delete unused approval step.');
        }
    }

    public function recomputeItilStatus(): void
    {
        $itil_object = $this->getItem();
        $this->checkIsAnItilObject($itil_object);

        // find validation_step name from $this->fields['itils_validationsteps_id']

        $itil_validationstep = static::getValidationStepInstance();

        if ($itil_validationstep && $itil_validationstep->getFromDB($this->fields['itils_validationsteps_id'])) {
            $validationstep_id = $itil_validationstep->fields['validationsteps_id'];
        }

        // update result not checked, there can be legit reasons to fails (e.g. ticket is closed)
        $itil_object->update(
            [
                'id' => $itil_object->getID(),
                'global_validation' => self::computeValidationStatus($itil_object),
                '_from_itilvalidation' => true,
                '_trigger' => $this,
                '_validationsteps_id' => $validationstep_id ?? null,
            ]
        );
    }

    /**
     * @throws RuntimeException
     * @phpstan-assert CommonITILObject $itilobject
     */
    private function checkIsAnItilObject(false|CommonDBTM $itilobject): void
    {
        if (!($itilobject instanceof CommonITILObject)) {
            throw new RuntimeException('Validation must be linked to an ITIL object. ' . ($itilobject === false ? 'false' : get_class($itilobject)) . ' given.');
        }
    }
}
