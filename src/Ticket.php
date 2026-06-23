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

use Glpi\Application\View\TemplateRenderer;
use Glpi\ContentTemplates\Parameters\CommonITILObjectParameters;
use Glpi\ContentTemplates\Parameters\TicketParameters;
use Glpi\ContentTemplates\ParametersPreset;
use Glpi\ContentTemplates\TemplateManager;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Glpi\DBAL\QuerySubQuery;
use Glpi\Event;
use Glpi\RichText\RichText;
use Glpi\RichText\UserMention;
use Glpi\Search\DefaultSearchRequestInterface;
use Glpi\Urgency;
use Safe\DateTime;

use function Safe\preg_match;
use function Safe\preg_match_all;
use function Safe\preg_replace_callback;
use function Safe\strtotime;

/**
 * Ticket Class
 **/
class Ticket extends CommonITILObject implements DefaultSearchRequestInterface
{
    // From CommonDBTM
    public $dohistory                   = true;
    protected static $forward_entity_to = [TicketValidation::class, TicketCost::class];

    // From CommonITIL
    public $userlinkclass               = Ticket_User::class;
    public $grouplinkclass              = Group_Ticket::class;
    public $supplierlinkclass           = Supplier_Ticket::class;

    public static $rightname                   = 'ticket';

    /**
     * @var bool
     */
    protected $userentity_oncreate      = true;

    public const MATRIX_FIELD                  = 'priority_matrix';
    public const URGENCY_MASK_FIELD            = 'urgency_mask';
    public const IMPACT_MASK_FIELD             = 'impact_mask';
    public const STATUS_MATRIX_FIELD           = 'ticket_status';

    // Specific ones
    /**
     * Hardware datas used by getFromDBwithData
     * @var array
     */
    public $hardwaredatas = [];
    /**
     * Is a hardware found in getHardwareData / getFromDBwithData : hardware link to the job
     * @var int
     */
    public $computerfound = 0;

    // Request type
    public const INCIDENT_TYPE = 1;
    // Demand type
    public const DEMAND_TYPE   = 2;

    public const READGROUP        =   2048;
    public const READASSIGN       =   4096;
    public const ASSIGN           =   8192;
    public const STEAL            =  16384;
    public const OWN              =  32768;
    public const CHANGEPRIORITY   =  65536;
    public const READNEWTICKET    = 262144;

    #[Override]
    public static function supportHelpdeskDisplayPreferences(): bool
    {
        return true;
    }

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden = parent::getForbiddenStandardMassiveAction();

        if (!Session::haveRightsOr(self::$rightname, [DELETE, PURGE])) {
            $forbidden[] = 'delete';
            $forbidden[] = 'purge';
            $forbidden[] = 'restore';
        }

        return $forbidden;
    }


    public static function getTypeName($nb = 0)
    {
        return _n('Ticket', 'Tickets', $nb);
    }

    public static function getAdditionalMenuContent()
    {

        if (static::canCreate()) {
            $menu = [
                'create_ticket' => [
                    'title' => __('Create ticket'),
                    'page'  => static::getFormURL(false),
                    'icon'  => 'ti ti-plus',
                ],
            ];
            return $menu;
        } else {
            return self::getAdditionalMenuOptions();
        }
    }


    public function canAssign()
    {
        if ($this->isDeleted() || (!$this->isNewItem() && $this->isClosed())) {
            return false;
        }
        return Session::haveRight(static::$rightname, self::ASSIGN);
    }


    public function canAssignToMe()
    {

        if (
            $this->isDeletedOrClosed()
        ) {
            return false;
        }
        return (Session::haveRight(self::$rightname, self::STEAL)
              || (Session::haveRight(self::$rightname, self::OWN)
                  && ($this->countUsers(CommonITILActor::ASSIGN) == 0)));
    }


    /**
     * Check whether a specific user has the right to assign themselves to this ticket,
     * based on their own profile rights rather than the current session.
     *
     * @since 11.0.8
     * @param int $user_id
     *
     * @return bool
     */
    public function canAssignToUser(int $user_id): bool
    {
        if (
            $this->isDeletedOrClosed()
        ) {
            return false;
        }

        $entity_id = $this->fields['entities_id'] ?? 0;

        return (
            Profile::haveUserRight($user_id, self::$rightname, self::STEAL, $entity_id)
            || (
                Profile::haveUserRight($user_id, self::$rightname, self::OWN, $entity_id)
                && $this->countUsers(CommonITILActor::ASSIGN) == 0
            )
        );
    }

    private function isDeletedOrClosed(): bool
    {
        return (isset($this->fields['is_deleted']) && $this->fields['is_deleted'] == 1
            || isset($this->fields['status']) && in_array($this->fields['status'], static::getClosedStatusArray()));
    }

    /**
     * @param int $ticket_id
     * @param int $user_id
     *
     * @return void
     */
    public static function assignToMe($ticket_id, $user_id)
    {
        $ticket = new Ticket();
        if ($ticket->getFromDB($ticket_id)) {
            $ticket_user = new Ticket_User();
            $ticket_user = $ticket_user->find([
                'tickets_id' => $ticket_id,
                'users_id'   => $user_id,
            ]);
            if (!count($ticket_user) && $ticket->canAssignToUser($user_id)) {
                $ticket->update([
                    'id' => $ticket_id,
                    '_users_id_assign' => $user_id,
                ]);
            }
        }
    }


    public static function canUpdate(): bool
    {

        // To allow update of urgency and category for post-only
        if (Session::getCurrentInterface() == "helpdesk") {
            return Session::haveRight(self::$rightname, CREATE);
        }

        return Session::haveRightsOr(
            self::$rightname,
            [UPDATE,
                self::ASSIGN,
                self::STEAL,
                self::OWN,
                self::CHANGEPRIORITY,
            ]
        );
    }


    public static function canView(): bool
    {
        return (Session::haveRightsOr(
            self::$rightname,
            [self::READALL, self::READMY, UPDATE, self::READASSIGN,
                self::READGROUP,
                self::OWN,
                self::READNEWTICKET,
            ]
        )
              || Session::haveRightsOr('ticketvalidation', TicketValidation::getValidateRights()));
    }


    /**
     * Is the current user have right to show the current ticket ?
     *
     * @return bool
     **/
    public function canViewItem(): bool
    {
        if (!Session::haveAccessToEntity($this->getEntityID())) {
            return false;
        }

        // Can see all tickets
        if (Session::haveRight(self::$rightname, self::READALL)) {
            return true;
        }

        // Can see my tickets
        if (
            Session::haveRight(self::$rightname, self::READMY)
            && (
                $this->fields["users_id_recipient"] === Session::getLoginUserID()
                || $this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
                || $this->isUser(CommonITILActor::OBSERVER, Session::getLoginUserID())
            )
        ) {
            return true;
        }

        // Can see my groups tickets
        if (
            Session::haveRight(self::$rightname, self::READGROUP)
            && isset($_SESSION["glpigroups"])
            && (
                $this->haveAGroup(CommonITILActor::REQUESTER, $_SESSION["glpigroups"])
                || $this->haveAGroup(CommonITILActor::OBSERVER, $_SESSION["glpigroups"])
            )
        ) {
            return true;
        }

        // Can see tickets considered as new (incoming status)
        if (
            Session::haveRight(self::$rightname, self::READNEWTICKET)
            && $this->fields["status"] == self::INCOMING
        ) {
            return true;
        }

        // Can see assigned tickets
        if (
            Session::haveRight(self::$rightname, self::READASSIGN)
            && (
                $this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
                || (
                    isset($_SESSION["glpigroups"])
                    && $this->haveAGroup(CommonITILActor::ASSIGN, $_SESSION["glpigroups"])
                )
            )
        ) {
            return true;
        }

        // Can validate tickets
        if (
            Session::haveRightsOr('ticketvalidation', TicketValidation::getValidateRights())
            && TicketValidation::canValidate($this->fields["id"])
        ) {
            return true;
        }

        return false;
    }


    /**
     * Is the current user have right to approve solution of the current ticket ?
     *
     * @return bool
     **/
    public function canApprove()
    {

        return ((($this->fields["users_id_recipient"] === Session::getLoginUserID())
               &&  Session::haveRight('ticket', Ticket::SURVEY))
              || $this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
              || (isset($_SESSION["glpigroups"])
                  && $this->haveAGroup(CommonITILActor::REQUESTER, $_SESSION["glpigroups"])));
    }


    public function canMassiveAction($action, $field, $value)
    {

        switch ($action) {
            case 'update':
                switch ($field) {
                    case 'itilcategories_id':
                        $cat = new ITILCategory();
                        if ($cat->getFromDB($value)) {
                            switch ($this->fields['type']) {
                                case self::INCIDENT_TYPE:
                                    if (!$cat->fields['is_incident']) {
                                        return false;
                                    }
                                    break;
                                case self::DEMAND_TYPE:
                                    if (!$cat->fields['is_request']) {
                                        return false;
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                        break;
                }
                break;
        }
        return parent::canMassiveAction($action, $field, $value);
    }

    /**
     * Check if current user can take into account the ticket.
     *
     * @return bool
     */
    public function canTakeIntoAccount()
    {

        // Can take into account if user is assigned user
        if (
            $this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID())
            || (isset($_SESSION["glpigroups"])
             && $this->haveAGroup(CommonITILActor::ASSIGN, $_SESSION['glpigroups']))
        ) {
            return true;
        }

        // Cannot take into account if user is a requester (and not assigned)
        if (
            $this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
            || (isset($_SESSION["glpigroups"])
             && $this->haveAGroup(CommonITILActor::REQUESTER, $_SESSION['glpigroups']))
        ) {
            return false;
        }

        $canAddTask = Session::haveRight("task", CommonITILTask::ADDALLITEM);
        $canAddFollowup = Session::haveRightsOr(
            'followup',
            [
                ITILFollowup::ADDALLITEM,
                ITILFollowup::ADDMY,
                ITILFollowup::ADD_AS_GROUP,
            ]
        );

        // Can take into account if user has rights to add tasks or followups,
        // assuming that users that does not have those rights cannot treat the ticket.
        return $canAddTask || $canAddFollowup;
    }

    /**
     * Check if ticket has already been taken into account.
     *
     * @return bool
     */
    public function isAlreadyTakenIntoAccount()
    {

        return array_key_exists('takeintoaccount_delay_stat', $this->fields)
          && $this->fields['takeintoaccount_delay_stat'] != 0;
    }

    /**
     * Get Datas to be added for SLA add
     *
     * @param int    $slas_id      SLA id
     * @param int    $entities_id  entity ID of the ticket
     * @param string $date         begin date of the ticket
     * @param int    $type         type of SLA
     *
     * @since 9.1 (before getDatasToAddSla without type parameter)
     *
     * @return array of datas to add in ticket
     **/
    public function getDatasToAddSLA($slas_id, $entities_id, $date, $type)
    {

        [$dateField, $slaField] = SLA::getFieldNames($type);

        $data         = [];

        $sla = new SLA();
        if ($sla->getFromDB($slas_id)) {
            $calendars_id = Entity::getUsedConfig(
                'calendars_strategy',
                $entities_id,
                'calendars_id',
                0
            );
            $sla->setTicketCalendar($calendars_id);
            if ($sla->fields['type'] == SLM::TTR) {
                $data["slalevels_id_ttr"] = SlaLevel::getFirstSlaLevel($slas_id);
            }
            // Compute time_to_resolve
            $data['sla_waiting_duration'] = (int) ($this->fields['sla_waiting_duration'] ?? 0);
            $data[$dateField]             = $sla->computeDate($date, $data['sla_waiting_duration']);
        } else {
            $data["slalevels_id_ttr"]     = 0;
            $data[$slaField]              = 0;
            $data['sla_waiting_duration'] = 0;
        }
        return $data;
    }

    /**
     * Get Datas to be added for OLA add
     *
     * @param int    $olas_id      OLA id
     * @param int    $entities_id  entity ID of the ticket
     * @param string $date         begin date of the ticket
     * @param int    $type         type of OLA
     *
     * @since 9.2 (before getDatasToAddOla without type parameter)
     *
     * @return array of datas to add in ticket
     **/
    public function getDatasToAddOLA($olas_id, $entities_id, $date, $type)
    {

        [$dateField, $olaField] = OLA::getFieldNames($type);

        $data         = [];

        $ola = new OLA();
        if ($ola->getFromDB($olas_id)) {
            $calendars_id = Entity::getUsedConfig(
                'calendars_strategy',
                $entities_id,
                'calendars_id',
                0
            );
            $ola->setTicketCalendar($calendars_id);
            if ($ola->fields['type'] == SLM::TTR) {
                $data["olalevels_id_ttr"] = OlaLevel::getFirstOlaLevel($olas_id);
                $data['ola_ttr_begin_date'] = $date;
            } elseif ($ola->fields['type'] == SLM::TTO) {
                $data['ola_tto_begin_date'] = $date;
            }
            // Compute time_to_own
            $data['ola_waiting_duration'] = (int) ($this->fields['ola_waiting_duration'] ?? 0);
            $data[$dateField]             = $ola->computeDate($date, $data['ola_waiting_duration']);
        } else {
            $data["olalevels_id_ttr"]     = 0;
            $data[$olaField]              = 0;
            $data['ola_waiting_duration'] = 0;
        }
        return $data;
    }


    /**
     * Delete Level Agreement for the ticket
     *
     * @since 9.2
     *
     * @param string  $laType (SLA | OLA)
     * @param int $la_id the sla/ola id
     * @param SLM::TTR|SLM::TTO $subtype (SLM::TTR | SLM::TTO) TODO: use a real type (enum)
     * @param bool    $delete_date (default false)
     *
     * @return bool
     **/
    public function deleteLevelAgreement($laType, $la_id, $subtype, $delete_date = false)
    {
        switch ($laType) {
            case "SLA":
                $prefix        = "sla";
                $prefix_ticket = "";
                $level_ticket  = new SlaLevel_Ticket();
                break;
            case "OLA":
                $prefix        = "ola";
                $prefix_ticket = "internal_";
                $level_ticket  = new OlaLevel_Ticket();
                break;
            default:
                return false;
        }

        $input = [];
        switch ($subtype) {
            case SLM::TTR:
                $input[$prefix . 's_id_ttr'] = 0;
                if ($delete_date) {
                    $input[$prefix_ticket . 'time_to_resolve'] = '';
                }
                break;

            case SLM::TTO:
                $input[$prefix . 's_id_tto'] = 0;
                if ($delete_date) {
                    $input[$prefix_ticket . 'time_to_own'] = '';
                }
                break;
            default:
                return false;
        }

        $input[$prefix . '_waiting_duration'] = 0;
        $input['id'] = $la_id;

        $level_ticket->deleteForTicket($la_id, $subtype);

        return $this->update($input);
    }


    /**
     * Is the current user have right to create the current ticket ?
     *
     * @return bool
     **/
    public function canCreateItem(): bool
    {

        if (!Session::haveAccessToEntity($this->getEntityID())) {
            return false;
        }
        return self::canCreate();
    }


    /**
     * Is the current user have right to update the current ticket ?
     *
     * @return bool
     **/
    public function canUpdateItem(): bool
    {
        if (!$this->checkEntity()) {
            return false;
        }

        // for all, if no modification in ticket return true
        if ($this->canRequesterUpdateItem()) {
            return true;
        }

        // for self-service only, if modification in ticket, we can't update the ticket
        if (Session::getCurrentInterface() == "helpdesk") {
            return false;
        }

        // if we don't have global UPDATE right, maybe we can own the current ticket
        if (
            !Session::haveRight(self::$rightname, UPDATE)
            && !$this->ownItem()
        ) {
            //we always return false, as ownItem() = true is managed by below self::canUpdate
            return false;
        }

        return self::canupdate();
    }

    #[Override]
    public function canRequesterUpdateItem()
    {
        return ($this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
               || $this->fields["users_id_recipient"] === Session::getLoginUserID())
              && $this->fields['status'] != self::SOLVED
              && $this->fields['status'] != self::CLOSED
              && $this->numberOfFollowups() == 0
              && $this->numberOfTasks() == 0;
    }

    /**
     * Is the current user have OWN right and is the assigned to the ticket
     *
     * @return bool
     */
    public function ownItem()
    {
        return Session::haveRight(self::$rightname, self::OWN)
             && $this->isUser(CommonITILActor::ASSIGN, Session::getLoginUserID());
    }

    public static function canDelete(): bool
    {

        // to allow delete for self-service only if no action on the ticket
        if (Session::getCurrentInterface() == "helpdesk") {
            return Session::haveRight(self::$rightname, CREATE);
        }
        return Session::haveRight(self::$rightname, DELETE);
    }

    /**
     * is the current user could reopen the current ticket
     * @since  9.2
     * @return bool
     */
    public function canReopen()
    {
        return Session::haveRight('followup', CREATE)
             && in_array($this->fields["status"], static::getClosedStatusArray())
             && ($this->isAllowedStatus($this->fields['status'], self::INCOMING)
                 || $this->isAllowedStatus($this->fields['status'], self::ASSIGNED));
    }

    /**
     * Is the current user have right to delete the current ticket ?
     *
     * @return bool
     **/
    public function canDeleteItem(): bool
    {

        if (!Session::haveAccessToEntity($this->getEntityID())) {
            return false;
        }

        // user can delete his ticket if no action on it
        if (
            Session::getCurrentInterface() == "helpdesk"
            && (!($this->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
               || $this->fields["users_id_recipient"] === Session::getLoginUserID())
             || $this->numberOfFollowups() > 0
             || $this->numberOfTasks() > 0
             || $this->fields["date"] != $this->fields["date_mod"])
        ) {
            return false;
        }

        return static::canDelete();
    }

    /**
     * @see CommonITILObject::getDefaultActorRightSearch()
     **/
    public function getDefaultActorRightSearch($type)
    {

        $right = "all";
        if ($type == CommonITILActor::ASSIGN) {
            $right = "own_ticket";
            if (!Session::haveRight(self::$rightname, self::ASSIGN)) {
                $right = 'id';
            }
        }
        return $right;
    }

    public function pre_deleteItem()
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI['use_notifications']) {
            NotificationEvent::raiseEvent('delete', $this);
        }
        return true;
    }

    /**
     * Retrieve data of the hardware linked to the ticket if exists
     *
     * @return void
     **/
    public function getAdditionalDatas()
    {
        $this->hardwaredatas = [];

        if (!empty($this->fields["id"])) {
            $item_ticket = new Item_Ticket();
            $data = $item_ticket->find(['tickets_id' => $this->fields["id"]]);

            foreach ($data as $val) {
                if (!empty($val["itemtype"]) && ($item = getItemForItemtype($val["itemtype"]))) {
                    if ($item->getFromDB($val["items_id"])) {
                        $this->hardwaredatas[] = $item;
                    }
                }
            }
        }
    }

    public function cleanDBonPurge()
    {
        global $DB;

        // OlaLevel_Ticket does not extends CommonDBConnexity
        $olaLevel_ticket = new OlaLevel_Ticket();
        $olaLevel_ticket->deleteForTicket($this->fields['id'], SLM::TTO);
        $olaLevel_ticket->deleteForTicket($this->fields['id'], SLM::TTR);

        // SlaLevel_Ticket does not extends CommonDBConnexity
        $slaLevel_ticket = new SlaLevel_Ticket();
        $slaLevel_ticket->deleteForTicket($this->fields['id'], SLM::TTO);
        $slaLevel_ticket->deleteForTicket($this->fields['id'], SLM::TTR);

        // TicketSatisfaction does not extends CommonDBConnexity
        $tf = new TicketSatisfaction();
        $tf->deleteByCriteria(['tickets_id' => $this->fields['id']]);

        // CommonITILTask does not extends CommonDBConnexity
        $tt = new TicketTask();
        $tt->deleteByCriteria(['tickets_id' => $this->fields['id']]);

        // sourceof_items_id / sourceitems_id are not named properly for foreign keys so they cannot be handled by relation.constant.php
        $DB->update(
            ITILFollowup::getTable(),
            ['sourceof_items_id' => 0],
            ['sourceof_items_id' => $this->fields['id']]
        );
        $DB->update(
            ITILFollowup::getTable(),
            ['sourceitems_id' => 0],
            ['sourceitems_id' => $this->fields['id']]
        );
        $DB->update(
            TicketTask::getTable(),
            ['sourceof_items_id' => 0],
            ['sourceof_items_id' => $this->fields['id']]
        );
        $DB->update(
            TicketTask::getTable(),
            ['sourceitems_id' => 0],
            ['sourceitems_id' => $this->fields['id']]
        );

        $this->deleteChildrenAndRelationsFromDb(
            [
                Change_Ticket::class,
                Item_Ticket::class,
                Problem_Ticket::class,
                ProjectTask_Ticket::class,
                TicketCost::class,
                Ticket_Contract::class,
                Ticket_Ticket::class,
                TicketValidationStep::class,
                TicketValidation::class,
            ]
        );

        parent::cleanDBonPurge();
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->transformActorsInput($input);

        // Get ticket : need for comparison
        $this->getFromDB($input['id']);

        // Clean new lines before passing to rules
        if (isset($input["content"])) {
            $input["content"] = str_replace("\r\n", "\n", $input['content']);
        }

        // automatic recalculate if user changes urgency or technician change impact
        $canpriority               = Session::haveRight(self::$rightname, self::CHANGEPRIORITY);
        if (
            (
                (isset($input['urgency']) && $input['urgency'] != $this->fields['urgency'])
                || (isset($input['impact']) && $input['impact'] != $this->fields['impact'])
            )
            && (!$canpriority || !isset($input['priority']))
        ) {
            if (!isset($input['urgency'])) {
                $input['urgency'] = $this->fields['urgency'];
            }
            if (!isset($input['impact'])) {
                $input['impact'] = $this->fields['impact'];
            }
            $input['priority'] = self::computePriority((int) $input['urgency'], (int) $input['impact']);
        }

        // Security checks
        if (
            !Session::isCron()
            && !Session::haveRight(self::$rightname, self::ASSIGN)
        ) {
            if (
                isset($input["_itil_assign"])
                && isset($input['_itil_assign']['_type'])
                && ($input['_itil_assign']['_type'] == 'user')
            ) {
                // must own_ticket to grab a non assign ticket
                if ($this->countUsers(CommonITILActor::ASSIGN) == 0) {
                    if (
                        (!Session::haveRightsOr(self::$rightname, [self::STEAL, self::OWN]))
                        || !isset($input["_itil_assign"]['users_id'])
                        || ($input["_itil_assign"]['users_id'] != Session::getLoginUserID())
                    ) {
                        unset($input["_itil_assign"]);
                    }
                } else {
                    // Can not steal or can steal and not assign to me
                    if (
                        !Session::haveRight(self::$rightname, self::STEAL)
                        || !isset($input["_itil_assign"]['users_id'])
                        || ($input["_itil_assign"]['users_id'] != Session::getLoginUserID())
                    ) {
                        unset($input["_itil_assign"]);
                    }
                }
            }

            // No supplier assign
            if (
                isset($input["_itil_assign"])
                && isset($input['_itil_assign']['_type'])
                && ($input['_itil_assign']['_type'] == 'supplier')
            ) {
                unset($input["_itil_assign"]);
            }

            // No group
            if (
                isset($input["_itil_assign"])
                && isset($input['_itil_assign']['_type'])
                && ($input['_itil_assign']['_type'] == 'group')
            ) {
                unset($input["_itil_assign"]);
            }
        }

        $can_assign_slm = Session::haveRight(SLM::$rightname, SLM::RIGHT_ASSIGN);
        if (!$can_assign_slm) {
            foreach ([SLM::TTR, SLM::TTO] as $slmType) {
                [$dateField, $slaField] = SLA::getFieldNames($slmType);
                unset($input[$dateField], $input[$slaField]);
                [$dateField, $olaField] = OLA::getFieldNames($slmType);
                unset($input[$dateField], $input[$olaField]);
            }
        }

        //must be handled here for tickets. @see CommonITILObject::prepareInputForUpdate()
        $input = $this->handleTemplateFields($input);
        if ($input === false) {
            return false;
        }

        if (isset($input['entities_id'])) {
            $entid = $input['entities_id'];
        } else {
            $entid = $this->fields['entities_id'];
        }

        // Set _contract_type for rules
        $input['_contract_types'] = [];
        $contracts_link = Ticket_Contract::getListForItem($this);
        foreach ($contracts_link as $contract_link) {
            // Load linked contract
            $contract = Contract::getById($contract_link['id']);
            if (!$contract) {
                continue;
            }

            // Check if contract has a linked type
            $contract_type_id = $contract->fields[ContractType::getForeignKeyField()];
            if (!$contract_type_id) {
                continue;
            }

            $input['_contract_types'][$contract_type_id] = $contract_type_id;
        }

        $this->processRules(RuleTicket::ONUPDATE, $input, $entid);

        if (isset($input['content'])) {
            if (isset($input['_filename']) || isset($input['_content'])) {
                $input['_disablenotif'] = true;
            }
        }

        $input = parent::prepareInputForUpdate($input);
        if (is_array($input) && array_key_exists('_skip_rules', $input)) {
            unset($input['_skip_rules']);
        }
        return $input;
    }

    /**
     *  SLA affect by rules : reset time_to_resolve and time_to_own
     *  Manual SLA defined : reset time_to_resolve and time_to_own
     *  No manual SLA and due date defined : reset auto SLA
     *
     *  @since 9.1
     *
     * @param int $type
     * @param array $input
     * @param array $manual_slas_id
     *
     * @return void
     */
    public function slaAffect($type, &$input, $manual_slas_id)
    {

        [$dateField, $slaField] = SLA::getFieldNames($type);

        // Restore slas
        if (
            isset($manual_slas_id[$type])
            && !isset($input['_' . $slaField])
        ) {
            $input[$slaField] = $manual_slas_id[$type];
        }

        // Ticket update
        if (isset($this->fields['id']) && $this->fields['id'] > 0) {
            if (
                !isset($manual_slas_id[$type])
                && isset($input[$slaField]) && ($input[$slaField] > 0)
                && ($input[$slaField] != $this->fields[$slaField])
            ) {
                if (isset($input[$dateField])) {
                    // Unset due date
                    unset($input[$dateField]);
                }
            }

            if (
                isset($input[$slaField]) && ($input[$slaField] > 0)
                && ($input[$slaField] != $this->fields[$slaField])
            ) {
                $date = $this->fields['date'];
                /// Use updated date if also done
                if (isset($input["date"])) {
                    $date = $input["date"];
                }
                // Get datas to initialize SLA and set it
                $sla_data = $this->getDatasToAddSLA(
                    $input[$slaField],
                    $this->fields['entities_id'],
                    $date,
                    $type
                );
                if (count($sla_data)) {
                    foreach ($sla_data as $key => $val) {
                        $input[$key] = $val;
                    }
                }
            }
        } else { // Ticket add
            if (
                !isset($manual_slas_id[$type])
                && isset($input[$dateField]) && ($input[$dateField] != 'NULL')
            ) {
                // Valid due date
                if ($input[$dateField] >= $input['date']) {
                    if (isset($input[$slaField])) {
                        unset($input[$slaField]);
                    }
                } else {
                    // Unset due date
                    unset($input[$dateField]);
                }
            }

            if (isset($input[$slaField]) && ($input[$slaField] > 0)) {
                // Get datas to initialize SLA and set it
                $sla_data = $this->getDatasToAddSLA(
                    $input[$slaField],
                    $input['entities_id'],
                    $input['date'],
                    $type
                );
                if (count($sla_data)) {
                    foreach ($sla_data as $key => $val) {
                        $input[$key] = $val;
                    }
                }
            }
        }
    }

    /**
     *  OLA affect by rules : reset internal_time_to_resolve and internal_time_to_own
     *  Manual OLA defined : reset internal_time_to_resolve and internal_time_to_own
     *  No manual OLA and due date defined : reset auto OLA
     *
     *  @since 9.1
     *
     * @param int $type
     * @param array $input
     * @param array $manual_olas_id
     *
     * @return void
     */
    public function olaAffect($type, &$input, $manual_olas_id)
    {

        [$dateField, $olaField] = OLA::getFieldNames($type);

        // Restore olas
        if (
            isset($manual_olas_id[$type])
            && !isset($input['_' . $olaField])
        ) {
            $input[$olaField] = $manual_olas_id[$type];
        }

        // Ticket update
        if (isset($this->fields['id']) && $this->fields['id'] > 0) {
            if (
                !isset($manual_olas_id[$type])
                && isset($input[$olaField]) && ($input[$olaField] > 0)
                && ($input[$olaField] != $this->fields[$olaField])
            ) {
                if (isset($input[$dateField])) {
                    // Unset due date
                    unset($input[$dateField]);
                }
            }

            if (
                isset($input[$olaField]) && ($input[$olaField] > 0)
                && ($input[$olaField] != $this->fields[$olaField]
                 || isset($input['_' . $olaField]))
            ) {
                $date = $_SESSION['glpi_currenttime'];

                // Get datas to initialize OLA and set it
                $ola_data = $this->getDatasToAddOLA(
                    $input[$olaField],
                    $this->fields['entities_id'],
                    $date,
                    $type
                );
                if (count($ola_data)) {
                    foreach ($ola_data as $key => $val) {
                        $input[$key] = $val;
                    }
                }
            }
        } else { // Ticket add
            if (
                !isset($manual_olas_id[$type])
                && isset($input[$dateField]) && ($input[$dateField] != 'NULL')
            ) {
                // Valid due date
                if ($input[$dateField] >= $input['date']) {
                    if (isset($input[$olaField])) {
                        unset($input[$olaField]);
                    }
                } else {
                    // Unset due date
                    unset($input[$dateField]);
                }
            }

            if (isset($input[$olaField]) && ($input[$olaField] > 0)) {
                // Get datas to initialize OLA and set it
                $ola_data = $this->getDatasToAddOLA(
                    $input[$olaField],
                    $input['entities_id'],
                    $input['date'],
                    $type
                );
                if (count($ola_data)) {
                    foreach ($ola_data as $key => $val) {
                        $input[$key] = $val;
                    }
                }
            }
        }
    }

    /**
     * Manage SLA level escalation
     *
     * @since 9.1
     *
     * @param int $slas_id
     *
     * @return void
     **/
    public function manageSlaLevel($slas_id)
    {

        // Add first level in working table
        $slalevels_id = SlaLevel::getFirstSlaLevel($slas_id);

        $sla = new SLA();
        $in_db = $sla->getFromDB($slas_id);
        if (!$in_db) {
            return;
        }

        if (!in_array($this->fields['status'], static::getReopenableStatusArray())) {
            $sla->clearInvalidLevels($this->fields['id']);
            $calendars_id = Entity::getUsedConfig(
                'calendars_strategy',
                $this->fields['entities_id'],
                'calendars_id',
                0
            );
            $sla->setTicketCalendar($calendars_id);
            $sla->addLevelToDo($this, $slalevels_id);
        }
        SlaLevel_Ticket::replayForTicket($this->getID(), $sla->getField('type'));
    }

    /**
     * Manage OLA level escalation
     *
     * @since 9.1
     *
     * @param int $slas_id
     *
     * @return void
     */
    public function manageOlaLevel($slas_id)
    {

        // Add first level in working table
        $olalevels_id = OlaLevel::getFirstOlaLevel($slas_id);

        $ola = new OLA();
        $in_db = $ola->getFromDB($slas_id);
        if (!$in_db) {
            return;
        }

        if (!in_array($this->fields['status'], static::getReopenableStatusArray())) {
            $ola->clearInvalidLevels($this->fields['id']);
            $calendars_id = Entity::getUsedConfig(
                'calendars_strategy',
                $this->fields['entities_id'],
                'calendars_id',
                0
            );
            $ola->setTicketCalendar($calendars_id);
            $ola->addLevelToDo($this, $olalevels_id);
        }
        OlaLevel_Ticket::replayForTicket($this->getID(), $ola->getField('type'));
    }

    public function pre_updateInDB()
    {

        if (
            !$this->isTakeIntoAccountComputationBlocked($this->input)
            && !$this->isAlreadyTakenIntoAccount()
            && $this->canTakeIntoAccount()
            && !$this->isNew()
        ) {
            $this->updates[]                            = "takeintoaccountdate";
            $this->fields['takeintoaccountdate']        = $_SESSION["glpi_currenttime"];
            $this->updates[]                            = "takeintoaccount_delay_stat";
            $this->fields['takeintoaccount_delay_stat'] = $this->computeTakeIntoAccountDelayStat();
        }

        if (
            in_array("takeintoaccount_delay_stat", $this->updates)
            && $this->fields['takeintoaccount_delay_stat'] == 0
        ) {
            if (!in_array("takeintoaccountdate", $this->updates)) {
                $this->updates[] = "takeintoaccountdate";
            }
            $this->fields["takeintoaccountdate"] = null;
        }

        parent::pre_updateInDB();
    }

    /**
     * Compute take into account stat of the current ticket
     *
     * @return int
     */
    public function computeTakeIntoAccountDelayStat()
    {

        if (
            isset($this->fields['id'])
            && !empty($this->fields['date'])
        ) {
            // Use SLA TTO calendar
            $calendars_id = $this->getCalendar(SLM::TTO);
            $calendar     = new Calendar();
            // Using calendar
            if (($calendars_id > 0) && $calendar->getFromDB($calendars_id)) {
                return max(1, $calendar->getActiveTimeBetween(
                    $this->fields['date'],
                    $_SESSION["glpi_currenttime"]
                ));
            }
            // Not calendar defined
            return max(1, strtotime($_SESSION["glpi_currenttime"]) - strtotime($this->fields['date']));
        }
        return 0;
    }

    private function handleContractInputs(): void
    {
        $contracts_id = $this->input['_contracts_id'] ?? 0;
        if (!is_array($contracts_id)) {
            $contracts_id = [$contracts_id];
        }
        $contracts_id = array_filter($contracts_id, static fn($val) => ((int) $val > 0));
        $ticketcontract = new Ticket_Contract();
        foreach ($contracts_id as $contract_id) {
            $ticketcontract->add([
                'contracts_id' => $contract_id,
                'tickets_id'   => $this->getID(),
            ]);
        }
    }

    public function post_updateItem($history = true)
    {
        global $CFG_GLPI;

        parent::post_updateItem($history);

        // Put same status on duplicated tickets when solving or closing (autoclose on solve)
        if (
            isset($this->input['status'])
            && in_array('status', $this->updates)
            && (in_array($this->input['status'], static::getSolvedStatusArray())
              || in_array($this->input['status'], static::getClosedStatusArray()))
        ) {
            CommonITILObject_CommonITILObject::manageLinksOnChange(self::class, $this->getID(), [
                'status'       => $this->input['status'],
            ]);
        }

        $donotif = count($this->updates);

        if (isset($this->input['_forcenotif'])) {
            $donotif = true;
        }

        // Manage SLA / OLA Level : add actions
        foreach ([SLM::TTR, SLM::TTO] as $slmType) {
            [$dateField, $slaField] = SLA::getFieldNames($slmType);
            if (
                in_array($slaField, $this->updates)
                && ($this->fields[$slaField] > 0)
            ) {
                $this->manageSlaLevel($this->fields[$slaField]);
            }

            [$dateField, $olaField] = OLA::getFieldNames($slmType);
            if (
                in_array($olaField, $this->updates)
                && ($this->fields[$olaField] > 0)
            ) {
                $this->manageOlaLevel($this->fields[$olaField]);
            }
        }

        if (count($this->updates)) {
            // Update Ticket Tco
            if (
                in_array("actiontime", $this->updates)
                || in_array("cost_time", $this->updates)
                || in_array("cost_fixed", $this->updates)
                || in_array("cost_material", $this->updates)
            ) {
                if (!empty($this->input["items_id"])) {
                    foreach ($this->input["items_id"] as $itemtype => $items) {
                        foreach ($items as $items_id) {
                            if ($itemtype && ($item = getItemForItemtype($itemtype))) {
                                if ($item->getFromDB($items_id)) {
                                    $newinput               = [];
                                    $newinput['id']         = $items_id;
                                    $newinput['ticket_tco'] = self::computeTco($item);
                                    $item->update($newinput);
                                }
                            }
                        }
                    }
                }
            }

            $donotif                 = true;
        }

        if (isset($this->input['_disablenotif'])) {
            $donotif = false;
        }

        if ($donotif && $CFG_GLPI["use_notifications"]) {
            $mailtype = "update";

            if (
                isset($this->input["status"])
                && $this->input["status"]
                && in_array("status", $this->updates)
                && in_array($this->input["status"], static::getSolvedStatusArray())
            ) {
                $mailtype = "solved";
            }

            if (
                isset($this->input["status"])
                && $this->input["status"]
                && in_array("status", $this->updates)
                && in_array($this->input["status"], static::getClosedStatusArray())
            ) {
                $mailtype = "closed";
            }
            // to know if a solution is approved or not
            if (
                (isset($this->input['solvedate']) && ($this->input['solvedate'] == 'NULL')
                && isset($this->oldvalues['solvedate']) && $this->oldvalues['solvedate'])
                && (isset($this->input['status'])
                 && ($this->input['status'] != $this->oldvalues['status'])
                 && ($this->oldvalues['status'] == self::SOLVED))
            ) {
                $mailtype = "rejectsolution";
            }

            // Read again ticket to be sure that all data are up to date
            $this->getFromDB($this->fields['id']);
            $trigger = $this->input['_trigger'] ?? null;
            NotificationEvent::raiseEvent($mailtype, $this, [], $trigger);
        }

        $this->handleSatisfactionSurveyOnUpdate();

        // Add linked contract
        $this->handleContractInputs();

        // Add linked project
        $projects_ids = $this->input['_projects_id'] ?? [];
        foreach ($projects_ids as $projects_id) {
            if ($projects_id) {
                $itil_project = new Itil_Project();
                $itil_project->add([
                    'projects_id' => $projects_id,
                    'itemtype'   => Ticket::class,
                    'items_id'   => $this->getID(),
                ]);
            }
        }
    }

    public function prepareInputForAdd($input)
    {
        // Standard clean datas
        $input =  parent::prepareInputForAdd($input);
        if ($input === false) {
            return false;
        }

        if (!isset($input["requesttypes_id"])) {
            $input["requesttypes_id"] = RequestType::getDefault('helpdesk');
        }

        if (!isset($input['global_validation'])) {
            $input['global_validation'] = CommonITILValidation::NONE;
        }

        // Set additional default dropdown
        $dropdown_fields = ['_locations_id_of_requester', '_locations_id_of_item'];
        foreach ($dropdown_fields as $field) {
            if (!isset($input[$field])) {
                $input[$field] = 0;
            }
        }
        if (!isset($input['itemtype']) || !isset($input['items_id']) || $input['items_id'] <= 0) {
            $input['itemtype'] = '';
        }

        // Get first item location
        $item = null;
        if (
            isset($input["items_id"])
            && is_array($input["items_id"])
            && (count($input["items_id"]) > 0)
        ) {
            $infocom = new Infocom();
            foreach ($input["items_id"] as $itemtype => $items) {
                foreach ($items as $items_id) {
                    if ($item = getItemForItemtype($itemtype)) {
                        $item->getFromDB($items_id);
                        $input['_states_id_of_item']    = $item->fields['states_id'] ?? null;
                        $input['_locations_id_of_item'] = $item->fields['locations_id'] ?? null;
                        if ($infocom->getFromDBforDevice($itemtype, $items_id)) {
                            $input['items_businesscriticities']
                             = Dropdown::getDropdownName(
                                 'glpi_businesscriticities',
                                 $infocom->fields['businesscriticities_id']
                             );
                        }
                        break(2);
                    }
                }
            }
        }

        $can_assign_slm = Session::haveRight(SLM::$rightname, SLM::RIGHT_ASSIGN);
        if (!$can_assign_slm) {
            foreach ([SLM::TTR, SLM::TTO] as $slmType) {
                [$dateField, $slaField] = SLA::getFieldNames($slmType);
                unset($input[$dateField], $input[$slaField]);
                [$dateField, $olaField] = OLA::getFieldNames($slmType);
                unset($input[$dateField], $input[$olaField]);
            }
        }

        // Set default contract if not specified
        if (
            (!isset($input['_contracts_id']) || (int) $input['_contracts_id'] == 0)
            && (!isset($input['_skip_default_contract']) || $input['_skip_default_contract'] === false)
        ) {
            $input['_contracts_id'] = Entity::getDefaultContract($this->input['entities_id'] ?? 0);
        }

        // Set _contract_type for rules
        $contracts_id = $input['_contracts_id'];
        if ($contracts_id) {
            $contract = Contract::getById($contracts_id);

            if ($contract && $contract_type_id = $contract->fields[ContractType::getForeignKeyField()]) {
                $input['_contract_types'][$contract_type_id] = $contract_type_id;
            }
        }

        $this->processRules(RuleTicket::ONADD, $input);

        if (
            isset($input['_users_id_requester_notif'])
            && isset($input['_users_id_requester_notif']['alternative_email'])
            && is_array($input['_users_id_requester_notif']['alternative_email'])
        ) {
            foreach ($input['_users_id_requester_notif']['alternative_email'] as $email) {
                if ($email && !NotificationMailing::isUserAddressValid($email)) {
                    Session::addMessageAfterRedirect(
                        htmlescape(sprintf(__('Invalid email address %s'), $email)),
                        false,
                        ERROR
                    );
                    return false;
                }
            }
        }

        if (!isset($input['_skip_auto_assign']) || $input['_skip_auto_assign'] === false) {
            // Manage auto assign
            $auto_assign_mode = Entity::getUsedConfig('auto_assign_mode', $input['entities_id']);

            switch ($auto_assign_mode) {
                case Entity::CONFIG_NEVER:
                    break;

                case Entity::AUTO_ASSIGN_HARDWARE_CATEGORY:
                    // Auto assign tech/group from hardware
                    $input = $this->setTechAndGroupFromHardware($input, $item);
                    // Auto assign tech/group from Category
                    $input = $this->setTechAndGroupFromItilCategory($input);
                    break;

                case Entity::AUTO_ASSIGN_CATEGORY_HARDWARE:
                    // Auto assign tech/group from Category
                    $input = $this->setTechAndGroupFromItilCategory($input);
                    // Auto assign tech/group from hardware
                    $input = $this->setTechAndGroupFromHardware($input, $item);
                    break;
            }
        }

        // auto set type if not set
        if (!isset($input["type"])) {
            $input['type'] = Entity::getUsedConfig(
                'tickettype',
                $input['entities_id'],
                '',
                Ticket::INCIDENT_TYPE
            );
        }

        return $input;
    }

    public function post_addItem()
    {
        // Log this event
        $username = 'anonymous';
        if (isset($_SESSION["glpiname"])) {
            $username = $_SESSION["glpiname"];
        }
        Event::log(
            $this->fields['id'],
            "ticket",
            4,
            "tracking",
            sprintf(
                __('%1$s adds the item %2$s'),
                $username,
                $this->fields['id']
            )
        );

        if (
            isset($this->input["_followup"])
            && is_array($this->input["_followup"])
            && isset($this->input["_followup"]['content'])
            && ((string) $this->input["_followup"]['content'] !== '')
        ) {
            $fup  = new ITILFollowup();
            $type = "new";
            if (isset($this->fields["status"]) && ($this->fields["status"] == self::SOLVED)) {
                $type = "solved";
            }
            $toadd = ['type'       => $type,
                'items_id' => $this->fields['id'],
                'itemtype' => Ticket::class,
            ];

            $toadd["content"] = $this->input["_followup"]['content'];

            if (isset($this->input["_followup"]['is_private'])) {
                $toadd["is_private"] = $this->input["_followup"]['is_private'];
            }

            $fup->add($toadd);
        }

        if (
            (isset($this->input["plan"]) && count($this->input["plan"]))
            || (isset($this->input["actiontime"]) && ($this->input["actiontime"] > 0))
        ) {
            $task = new TicketTask();
            $type = "new";
            if (isset($this->fields["status"]) && ($this->fields["status"]  == self::SOLVED)) {
                $type = "solved";
            }
            $toadd = ["type"        => $type,
                "tickets_id"   => $this->fields['id'],
                "actiontime"   => $this->input["actiontime"],
                "state"        => Planning::DONE,
                "content"      => __("Auto-created task"),
            ];

            if (isset($this->input["plan"]) && count($this->input["plan"])) {
                $toadd["plan"] = $this->input["plan"];
            }

            if (isset($_SESSION['glpitask_private'])) {
                $toadd['is_private'] = $_SESSION['glpitask_private'];
            }

            $task->add($toadd);
        }

        $ticket_ticket = new Ticket_Ticket();
        // From mailcollector : do not check rights
        if (isset($this->input["_linkedto"])) {
            $input2 = [
                'tickets_id_1' => $this->fields['id'],
                'tickets_id_2' => $this->input["_linkedto"],
                'link'         => CommonITILObject_CommonITILObject::LINK_TO,
            ];
            $ticket_ticket->add($input2);
        }

        // Manage SLA / OLA Level : add actions
        foreach ([SLM::TTR, SLM::TTO] as $slmType) {
            [$dateField, $slaField] = SLA::getFieldNames($slmType);
            if (isset($this->input[$slaField]) && ($this->input[$slaField] > 0)) {
                $this->manageSlaLevel($this->input[$slaField]);
            }
            [$dateField, $olaField] = OLA::getFieldNames($slmType);
            if (isset($this->input[$olaField]) && ($this->input[$olaField] > 0)) {
                $this->manageOlaLevel($this->input[$olaField]);
            }
        }

        // Add project task link if needed
        if (isset($this->input['_projecttasks_id'])) {
            $projecttask = new ProjectTask();
            if ($projecttask->getFromDB($this->input['_projecttasks_id'])) {
                $pt = new ProjectTask_Ticket();
                $pt->add(['projecttasks_id' => $this->input['_projecttasks_id'],
                    'tickets_id'      => $this->fields['id'],
                ]);
            }
        }

        if (isset($this->input['_promoted_fup_id']) && $this->input['_promoted_fup_id'] > 0) {
            $fup = new ITILFollowup();
            $fup->getFromDB($this->input['_promoted_fup_id']);
            $fup->update([
                'id'                 => $this->input['_promoted_fup_id'],
                'sourceof_items_id'  => $this->getID(),
            ]);
            Event::log(
                $this->getID(),
                "ticket",
                4,
                "tracking",
                sprintf(__('%s promotes a followup from ticket %s'), $_SESSION["glpiname"], $fup->fields['items_id'])
            );
        }

        if (isset($this->input['_promoted_task_id']) && $this->input['_promoted_task_id'] > 0) {
            $tickettask = new TicketTask();
            $tickettask->getFromDB($this->input['_promoted_task_id']);
            $tickettask->update([
                'id'                => $this->input['_promoted_task_id'],
                'sourceof_items_id' => $this->getID(),
            ]);
            Event::log(
                $this->getID(),
                "ticket",
                4,
                "tracking",
                sprintf(__('%s promotes a task from ticket %s'), $_SESSION["glpiname"], $tickettask->fields['tickets_id'])
            );
        }

        // Add linked contract
        $this->handleContractInputs();

        // Add linked project
        $projects_ids = $this->input['_projects_id'] ?? [];
        foreach ($projects_ids as $projects_id) {
            if ($projects_id) {
                $itil_project = new Itil_Project();
                $itil_project->add([
                    'projects_id' => $projects_id,
                    'itemtype'   => Ticket::class,
                    'items_id'   => $this->getID(),
                ]);
            }
        }

        parent::post_addItem();

        $this->handleNewItemNotifications();
    }

    /**
     * Get active or solved tickets for a hardware last X days
     *
     * @param class-string<CommonDBTM> $itemtype Item type
     * @param int $items_id  ID of the Item
     * @param int $days day number
     *
     * @return array
     */
    public function getActiveOrSolvedLastDaysTicketsForItem($itemtype, $items_id, $days)
    {
        return $this->getActiveOrSolvedLastDaysForItem($itemtype, $items_id, $days);
    }

    /**
     * Get active tickets for an item
     *
     * @since 9.5
     *
     * @param string $itemtype     Item type
     * @param int $items_id    ID of the Item
     * @param int $type         Type of the tickets (incident or request)
     *
     * @return DBmysqlIterator
     */
    public function getActiveTicketsForItem($itemtype, $items_id, $type)
    {
        global $DB;

        return $DB->request([
            'SELECT'    => [
                $this->getTable() . '.id',
                $this->getTable() . '.name',
                $this->getTable() . '.priority',
            ],
            'FROM'      => $this->getTable(),
            'LEFT JOIN' => [
                'glpi_items_tickets' => [
                    'ON' => [
                        'glpi_items_tickets' => 'tickets_id',
                        $this->getTable()    => 'id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_items_tickets.itemtype'    => $itemtype,
                'glpi_items_tickets.items_id'    => $items_id,
                $this->getTable() . '.is_deleted' => 0,
                $this->getTable() . '.type'      => $type,
                'NOT'                         => [
                    $this->getTable() . '.status' => array_merge(
                        static::getSolvedStatusArray(),
                        static::getClosedStatusArray()
                    ),
                ],
            ],
        ]);
    }

    #[Override]
    public function updateDateMod($ID, $no_stat_computation = false, $users_id_lastupdater = 0)
    {

        if ($this->getFromDB($ID)) {
            if (
                !$no_stat_computation
                && !$this->isAlreadyTakenIntoAccount()
                && ($this->canTakeIntoAccount() || isCommandLine())
            ) {
                $this->update(
                    [
                        'id'                         => $ID,
                        'takeintoaccount_delay_stat' => $this->computeTakeIntoAccountDelayStat(),
                        'takeintoaccountdate'        => $_SESSION["glpi_currenttime"],
                        '_disablenotif'              => true,
                    ]
                );
            }

            parent::updateDateMod($ID, $no_stat_computation, $users_id_lastupdater);
        }
    }

    public function canAddItem(string $type): bool
    {
        if ($type == Document::class) {
            return $this->canAddDocuments();
        }

        // as self::canUpdate & $this->canUpdateItem checks more general rights
        // (like STEAL or OWN),
        // we specify only the rights needed for this action
        return $this->checkEntity()
             && (Session::haveRight(self::$rightname, UPDATE)
                 || $this->canRequesterUpdateItem());
    }

    /**
     * Check if user can add followups to the ticket.
     *
     * @param int $user_id
     *
     * @return bool
     */
    public function canUserAddFollowups($user_id)
    {

        $entity_id = $this->fields['entities_id'];

        $group_user = new Group_User();
        $user_groups = $group_user->getUserGroups($user_id, ['entities_id' => $entity_id]);
        $user_groups_ids = [];
        foreach ($user_groups as $user_group) {
            $user_groups_ids[] = $user_group['id'];
        }

        $rightname = ITILFollowup::$rightname;

        return (
            (
                Profile::haveUserRight($user_id, $rightname, ITILFollowup::ADDMY, $entity_id)
                && (
                    $this->isUser(CommonITILActor::REQUESTER, $user_id)
                    || (
                        isset($this->fields['users_id_recipient'])
                        && ($this->fields['users_id_recipient'] == $user_id)
                    )
                )
            )
            || (
                Profile::haveUserRight($user_id, $rightname, ITILFollowup::ADD_AS_OBSERVER, $entity_id)
                && $this->isUser(CommonITILActor::OBSERVER, $user_id)
            )
            || Profile::haveUserRight($user_id, $rightname, ITILFollowup::ADDALLITEM, $entity_id)
            || (
                Profile::haveUserRight($user_id, $rightname, ITILFollowup::ADD_AS_GROUP, $entity_id)
                && (
                    (
                        $this->haveAGroup(CommonITILActor::REQUESTER, $user_groups_ids)
                        && Profile::haveUserRight($user_id, $rightname, ITILFollowup::ADDMY, $entity_id)
                    )
                    || (
                        $this->haveAGroup(CommonITILActor::OBSERVER, $user_groups_ids)
                        && Profile::haveUserRight($user_id, $rightname, ITILFollowup::ADD_AS_OBSERVER, $entity_id)
                    )
                )
            )
            || (
                Profile::haveUserRight($user_id, $rightname, ITILFollowup::ADD_AS_TECHNICIAN, $entity_id)
                && (
                    $this->isUser(CommonITILActor::ASSIGN, $user_id)
                    || $this->haveAGroup(CommonITILActor::ASSIGN, $user_groups_ids)
                )
            )
            || $this->isUserValidationRequested($user_id, true)
        );
    }

    /**
     * Check current user can create a ticket for another given user
     *
     * @since 9.5.4
     *
     * @param int $requester_id the user for which we want to create the ticket
     * @param int $entity_restrict check entity when search users
     *            (keep null to check with current session entities)
     *
     * @return bool
     */
    public static function canDelegateeCreateTicket(int $requester_id, ?int $entity_restrict = null): bool
    {
        // if the user is a technician, no need to check delegates
        if (Session::getCurrentInterface() == "central") {
            return true;
        }

        // if the connected user is the ticket requester, we can create
        if ($requester_id == $_SESSION['glpiID']) {
            return true;
        }

        if ($entity_restrict === null) {
            $entity_restrict = $_SESSION["glpiactive_entity"] ?? 0;
        }

        // if user has no delegate groups, he can't create ticket for another user
        $delegate_groups = User::getDelegateGroupsForUser($entity_restrict);
        if (count($delegate_groups) == 0) {
            return false;
        }

        // retrieve users to check if given requester is part of them
        $users_delegatee_iterator = User::getSqlSearchResult(false, 'delegate', $entity_restrict);
        foreach ($users_delegatee_iterator as $user_data) {
            if ($user_data['id'] == $requester_id) {
                // user found
                return true;
            }
        }

        // user not found
        return false;
    }

    #[Override]
    public static function getDefaultSearchRequest(): array
    {
        // Technician don't want to be bothered by already solved items.
        // On the other hand, it make sense for helpdesk users to see their
        // own solved tickets.
        $value = Session::getCurrentInterface() == 'central' ? 'notold' : 'notclosed';
        $request = [
            'criteria' => [
                [
                    'field'      => 12, // Status
                    'searchtype' => 'equals',
                    'value'      => $value,
                ],
            ],
            'sort'     => 19, // Last update
            'order'    => 'DESC',
        ];

        return $request;
    }

    public function getSpecificMassiveActions($checkitem = null)
    {

        $actions = parent::getSpecificMassiveActions($checkitem);

        if (Session::getCurrentInterface() === 'central') {
            if (Ticket::canUpdate() && Ticket::canDelete()) {
                $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'merge_as_followup']
                 = "<i class='ti ti-git-merge'></i>"
                 . __s('Merge as Followup');
            }

            if (Item_Ticket::canCreate()) {
                $actions['Item_Ticket' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_item']
                = "<i class='ti ti-plus'></i>"
                 . _sx('button', 'Add an item');
            }

            if (ITILFollowup::canCreate()) {
                $icon = ITILFollowup::getIcon();
                $actions['ITILFollowup' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_followup']
                = "<i class='" . htmlescape($icon) . "'></i>"
                 . __s('Add a new followup');
            }

            if (TicketTask::canCreate()) {
                $icon = TicketTask::getIcon();
                $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_task']
                = "<i class='" . htmlescape($icon) . "'></i>"
                 . __s('Add a new task');
            }

            if (TicketValidation::canCreate()) {
                $icon = TicketValidation::getIcon();
                $actions['TicketValidation' . MassiveAction::CLASS_ACTION_SEPARATOR . 'submit_validation']
                = "<i class='" . htmlescape($icon) . "'></i>"
                 . __s('Approval request');
            }

            if (Item_Ticket::canDelete()) {
                $actions['Item_Ticket' . MassiveAction::CLASS_ACTION_SEPARATOR . 'delete_item']
                = _sx('button', 'Remove an item');
            }

            if (Session::haveRight(self::$rightname, UPDATE)) {
                $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_actor'] = "<i class='ti ti-user'></i>" . __s('Add an actor');
                $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'update_notif'] = __s('Set notifications for all actors');
                if (ProjectTask_Ticket::canCreate()) {
                    $actions['ProjectTask_Ticket' . MassiveAction::CLASS_ACTION_SEPARATOR . 'add']
                        = "<i class='ti ti-link'></i>"
                        . _sx('button', 'Link project task');
                }
                if (Ticket_Contract::canCreate()) {
                    $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_contract']
                        = "<i class='" . Contract::getIcon() . "'></i>"
                        . _sx('button', 'Add contract');
                }

                KnowbaseItem_Item::getMassiveActionsForItemtype($actions, self::class, false, $checkitem);
            }

            if (self::canUpdate()) {
                $actions[self::getType() . MassiveAction::CLASS_ACTION_SEPARATOR . 'resolve_tickets']
                = "<i class='ti ti-check'></i>"
                . __s("Resolve selected tickets");
            }
        }

        $actions += parent::getSpecificMassiveActions($checkitem);

        return $actions;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        switch ($ma->getAction()) {
            case 'merge_as_followup':
                $input = $ma->getInput();
                $status = [];
                $mergeparams = [
                    'linktypes' => [],
                    'link_type'  => $input['link_type'],
                ];

                if ($input['with_followups']) {
                    $mergeparams['linktypes'][] = 'ITILFollowup';
                }
                if ($input['with_tasks']) {
                    $mergeparams['linktypes'][] = 'TicketTask';
                }
                if ($input['with_documents']) {
                    $mergeparams['linktypes'][] = 'Document';
                }
                if ($input['with_actors']) {
                    $mergeparams['append_actors'] = [
                        CommonITILActor::REQUESTER,
                        CommonITILActor::OBSERVER,
                        CommonITILActor::ASSIGN,
                    ];
                } else {
                    $mergeparams['append_actors'] = [];
                }

                Ticket::merge($input['_mergeticket'], $ids, $status, $mergeparams);
                foreach ($status as $id => $status_code) {
                    if ($status_code == 0) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } elseif ($status_code == 2) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                    }
                }
                return;

            case 'link_to_problem':
                Toolbox::deprecated('Ticket "link_to_problem" massive action is deprecated. Use CommonITILObject_CommonITILObject "add" massive action.');
                // Skip if not tickets
                if ($item::getType() !== Ticket::getType()) {
                    $ma->addMessage($item->getErrorMessage(ERROR_COMPAT));
                    return;
                }

                // Skip if missing update rights on problems
                if (!Problem::canUpdate()) {
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    return;
                }

                // Check input
                $input = $ma->getInput();
                if (!isset($input['problems_id'])) {
                    $ma->addMessage(__s("Missing input: no Problem selected"));
                    return;
                }

                $problem = new Problem();
                if (!$problem->getFromDB($input['problems_id'])) {
                    $ma->addMessage(__s("Selected Problem can't be loaded"));
                    return;
                }

                $em = new Problem_Ticket();
                foreach ($ids as $id) {
                    // Add new link
                    $res = $em->add([
                        'problems_id' => $input['problems_id'],
                        'tickets_id'  => $id,
                    ]);

                    // Check if creation was successful
                    if ($res) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                    }
                }

                return;

            case 'resolve_tickets':
                // Skip if not tickets
                if ($item::getType() !== self::getType()) {
                    $ma->addMessage($item->getErrorMessage(ERROR_COMPAT));
                    return;
                }

                // Skip if missing update rights on problems
                if (!self::canUpdate()) {
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    return;
                }

                // Check input
                $input = $ma->getInput();
                $mandatory_fields = [
                    'solutiontypes_id',
                    'content',
                ];
                $check_mandatory = array_intersect($mandatory_fields, array_keys($input));
                if (count($check_mandatory) != count($mandatory_fields)) {
                    $ma->addMessage(__s("Missing mandatory field in input"));
                    return;
                }

                $ticket = new self();
                $em = new ITILSolution();
                foreach ($ids as $id) {
                    // Try to load ticket
                    if (!$ticket->getFromDB($id)) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                    }

                    // Check ticket is not already resolved or closed
                    $invalid_status = [
                        CommonITILObject::SOLVED,
                        CommonITILObject::CLOSED,
                    ];
                    if (in_array($ticket->fields['status'], $invalid_status)) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                    }

                    // Add reference to ticket in input
                    $input['itemtype'] = self::getType();
                    $input['items_id'] = $id;

                    // Insert new solution
                    $res = $em->add($input);

                    // Check if creation was successful
                    if ($res) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                    }
                }
                return;

            case 'add_contract':
                // Skip if wrong itemtype
                if ($item::getType() !== self::getType()) {
                    $ma->addMessage($item->getErrorMessage(ERROR_COMPAT));
                    return;
                }

                // Skip if missing update rights
                if (!self::canUpdate()) {
                    $ma->addMessage($item->getErrorMessage(ERROR_RIGHT));
                    return;
                }

                // Check input
                $input = $ma->getInput();
                $contracts_id = $input['contracts_id'] ?? 0;
                if (!$contracts_id) {
                    $ma->addMessage(__s("No contract specified"));
                    return;
                }

                $em = new Ticket_Contract();
                foreach ($ids as $id) {
                    $links = $em->find([
                        'contracts_id' => $contracts_id,
                        'tickets_id'   => $id,
                    ]);

                    // Link already exist, skip
                    if (count($links)) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        continue;
                    }

                    // Add link
                    $res = $em->add([
                        'contracts_id' => $contracts_id,
                        'tickets_id'   => $id,
                    ]);

                    // Check if creation was successful
                    if ($res) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage($item->getErrorMessage(ERROR_ON_ACTION));
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    public function rawSearchOptions()
    {
        global $DB;

        $tab = [];

        $tab = array_merge($tab, $this->getSearchOptionsMain());

        $tab[] = [
            'id'                 => '70',
            'table'              => $this->getTable(),
            'field'              => '_virtual_age',
            'datatype'           => 'specific',
            'name'               => __('Time since opening'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'nosort'             => true,
            'additionalfields'   => ['entities_id', 'date'],
        ];

        $tab[] = [
            'id'                 => '87',
            'table'              => $this->getTable(),
            'field'              => 'externalid',
            'datatype'           => 'string',
            'name'               =>  __('External ID'),
        ];

        $tab[] = [
            'id'                 => '155',
            'table'              => $this->getTable(),
            'field'              => 'time_to_own',
            'name'               => __('Time to own'),
            'datatype'           => 'datetime',
            'maybefuture'        => true,
            'massiveaction'      => false,
            'additionalfields'   => ['date', 'status', 'takeintoaccount_delay_stat', 'takeintoaccountdate'],
        ];

        $tab[] = [
            'id'                 => '158',
            'table'              => $this->getTable(),
            'field'              => 'time_to_own',
            'name'               => __('Time to own + Progress'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'additionalfields'   => ['status'],
        ];

        $tab[] = [
            'id'                 => '159',
            'table'              => 'glpi_tickets',
            'field'              => 'sla_tto_is_late',
            'name'               => __('Time to own exceeded'),
            'datatype'           => 'bool',
            'massiveaction'      => false,
            'computation'        => self::generateSLAOLAComputation('time_to_own'),
        ];

        $tab[] = [
            'id'                 => '180',
            'table'              => $this->getTable(),
            'field'              => 'internal_time_to_resolve',
            'name'               => __('Internal time to resolve'),
            'datatype'           => 'datetime',
            'maybefuture'        => true,
            'massiveaction'      => false,
            'additionalfields'   => ['solvedate', 'status'],
        ];

        $tab[] = [
            'id'                 => '181',
            'table'              => $this->getTable(),
            'field'              => 'internal_time_to_resolve',
            'name'               => __('Internal time to resolve + Progress'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'additionalfields'   => ['status'],
        ];

        $tab[] = [
            'id'                 => '182',
            'table'              => $this->getTable(),
            'field'              => 'ola_ttr_is_late',
            'name'               => __('Internal time to resolve exceeded'),
            'datatype'           => 'bool',
            'massiveaction'      => false,
            'computation'        => self::generateSLAOLAComputation('internal_time_to_resolve'),
        ];

        $tab[] = [
            'id'                 => '185',
            'table'              => $this->getTable(),
            'field'              => 'internal_time_to_own',
            'name'               => __('Internal time to own'),
            'datatype'           => 'datetime',
            'maybefuture'        => true,
            'massiveaction'      => false,
            'additionalfields'   => ['date', 'status', 'takeintoaccount_delay_stat', 'takeintoaccountdate'],
        ];

        $tab[] = [
            'id'                 => '186',
            'table'              => $this->getTable(),
            'field'              => 'internal_time_to_own',
            'name'               => __('Internal time to own + Progress'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'additionalfields'   => ['status'],
        ];

        $tab[] = [
            'id'                 => '187',
            'table'              => 'glpi_tickets',
            'field'              => 'ola_tto_is_late',
            'name'               => __('Internal time to own exceeded'),
            'datatype'           => 'bool',
            'massiveaction'      => false,
            'computation'        => self::generateSLAOLAComputation('internal_time_to_own'),
        ];

        $max_date = new QueryExpression('99999999');
        $tab[] = [
            'id'                 => '188',
            'table'              => $this->getTable(),
            'field'              => 'next_escalation_level',
            'name'               => __('Next escalation level'),
            'datatype'           => 'datetime',
            'usehaving'          => true,
            'maybefuture'        => true,
            'massiveaction'      => false,
            // Get least value from TTO/TTR fields:
            // - use TTO fields only if ticket not already taken into account,
            // - use TTR fields only if ticket not already solved,
            // - replace NULL or not kept values with 99999999 to be sure that they will not be returned by the LEAST function,
            // - replace 99999999 by empty string to keep only valid values.
            'computation'        => QueryFunction::replace(
                expression: QueryFunction::least([
                    QueryFunction::if(
                        condition: ['TABLE.takeintoaccount_delay_stat' => ['<=', 0]],
                        true_expression: QueryFunction::coalesce(['TABLE.time_to_own', $max_date]),
                        false_expression: $max_date
                    ),
                    QueryFunction::if(
                        condition: ['TABLE.takeintoaccount_delay_stat' => ['<=', 0]],
                        true_expression: QueryFunction::coalesce(['TABLE.internal_time_to_own', $max_date]),
                        false_expression: $max_date
                    ),
                    QueryFunction::if(
                        condition: ['TABLE.solvedate' => null],
                        true_expression: QueryFunction::coalesce(['TABLE.time_to_resolve', $max_date]),
                        false_expression: $max_date
                    ),
                    QueryFunction::if(
                        condition: ['TABLE.solvedate' => null],
                        true_expression: QueryFunction::coalesce(['TABLE.internal_time_to_resolve', $max_date]),
                        false_expression: $max_date
                    ),
                ]),
                search: new QueryExpression($DB::quoteValue($max_date)),
                replace: new QueryExpression($DB::quoteValue(''))
            ),
        ];

        $tab[] = [
            'id'                 => '14',
            'table'              => $this->getTable(),
            'field'              => 'type',
            'name'               => _n('Type', 'Types', 1),
            'searchtype'         => 'equals',
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => 'glpi_items_tickets',
            'field'              => 'items_id',
            'name'               => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'datatype'           => 'specific',
            'comments'           => true,
            'nosort'             => true,
            'nosearch'           => true,
            'additionalfields'   => ['itemtype'],
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'forcegroupby'       => true,
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '131',
            'table'              => 'glpi_items_tickets',
            'field'              => 'itemtype',
            'name'               => _n('Associated item type', 'Associated item types', Session::getPluralNumber()),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'ticket_types',
            'nosort'             => true,
            'additionalfields'   => ['itemtype'],
            'joinparams'         => [
                'jointype'           => 'child',
            ],
            'forcegroupby'       => true,
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '9',
            'table'              => 'glpi_requesttypes',
            'field'              => 'name',
            'name'               => RequestType::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        $tab = array_merge($tab, $this->getSearchOptionsActors());

        $tab[] = [
            'id'                 => 'sla',
            'name'               => __('SLA'),
        ];

        $tab[] = [
            'id'                 => '37',
            'table'              => 'glpi_slas',
            'field'              => 'name',
            'linkfield'          => 'slas_id_tto',
            'name'               => __('SLA') . ' ' . __('Time to own'),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'condition'          => ['NEWTABLE.type' => SLM::TTO],
            ],
            'condition'          => ['glpi_slas.type' => SLM::TTO],
        ];

        $tab[] = [
            'id'                 => '30',
            'table'              => 'glpi_slas',
            'field'              => 'name',
            'linkfield'          => 'slas_id_ttr',
            'name'               => __('SLA') . ' ' . __('Time to resolve'),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'condition'          => ['NEWTABLE.type' => SLM::TTR],
            ],
            'condition'          => ['glpi_slas.type' => SLM::TTR],
        ];

        $tab[] = [
            'id'                 => '32',
            'table'              => 'glpi_slalevels',
            'field'              => 'name',
            'name'               => __('SLA') . ' ' . _n('Escalation level', 'Escalation levels', 1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_slalevels_tickets',
                    'joinparams'         => [
                        'jointype'           => 'child',
                    ],
                ],
            ],
            'forcegroupby'       => true,
        ];

        $tab[] = [
            'id'                 => 'ola',
            'name'               => __('OLA'),
        ];

        $tab[] = [
            'id'                 => '190',
            'table'              => 'glpi_olas',
            'field'              => 'name',
            'linkfield'          => 'olas_id_tto',
            'name'               => __('OLA') . ' ' . __('Internal time to own'),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'condition'          => ['NEWTABLE.type' => SLM::TTO],
            ],
            'condition'          => ['glpi_olas.type' => SLM::TTO],
        ];

        $tab[] = [
            'id'                 => '191',
            'table'              => 'glpi_olas',
            'field'              => 'name',
            'linkfield'          => 'olas_id_ttr',
            'name'               => __('OLA') . ' ' . __('Internal time to resolve'),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'condition'          => ['NEWTABLE.type' => SLM::TTR],
            ],
            'condition'          => ['glpi_olas.type' => SLM::TTR],
        ];

        $tab[] = [
            'id'                 => '192',
            'table'              => 'glpi_olalevels',
            'field'              => 'name',
            'name'               => __('OLA') . ' ' . _n('Escalation level', 'Escalation levels', 1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => 'glpi_olalevels_tickets',
                    'joinparams'         => [
                        'jointype'           => 'child',
                    ],
                ],
            ],
            'forcegroupby'       => true,
        ];

        $validation_options = TicketValidation::rawSearchOptionsToAdd();
        if (
            !Session::haveRightsOr(
                'ticketvalidation',
                [
                    TicketValidation::CREATEINCIDENT,
                    TicketValidation::CREATEREQUEST,
                ]
            )
        ) {
            foreach ($validation_options as &$validation_option) {
                if (isset($validation_option['table'])) {
                    $validation_option['massiveaction'] = false;
                }
            }
        }
        $tab = array_merge($tab, $validation_options);

        $tab = array_merge($tab, TicketSatisfaction::rawSearchOptionsToAdd());

        $tab = array_merge($tab, ITILFollowup::rawSearchOptionsToAdd());

        $tab = array_merge($tab, TicketTask::rawSearchOptionsToAdd());

        $tab = array_merge($tab, $this->getSearchOptionsStats());

        $tab[] = [
            'id'                 => '150',
            'table'              => $this->getTable(),
            'field'              => 'takeintoaccount_delay_stat',
            'name'               => __('Take into account time'),
            'datatype'           => 'timestamp',
            'forcegroupby'       => true,
            'massiveaction'      => false,
        ];

        if (
            Session::haveRightsOr(
                self::$rightname,
                [self::READALL, self::READASSIGN, self::OWN]
            )
        ) {
            $tab[] = [
                'id'                 => 'linktickets',
                'name'               => _n('Linked ticket', 'Linked tickets', Session::getPluralNumber()),
            ];

            $tab[] = [
                'id'                 => '40',
                'table'              => 'glpi_tickets_tickets',
                'field'              => 'tickets_id_1',
                'name'               => __('All linked tickets'),
                'massiveaction'      => false,
                'forcegroupby'       => true,
                'searchtype'         => 'equals',
                'joinparams'         => [
                    'jointype' => 'item_item',
                ],
                'additionalfields'   => ['tickets_id_2'],
            ];

            $tab[] = [
                'id'                 => '47',
                'table'              => 'glpi_tickets_tickets',
                'field'              => 'tickets_id_1',
                'name'               => __('Duplicated tickets'),
                'massiveaction'      => false,
                'searchtype'         => 'equals',
                'joinparams'         => [
                    'jointype'           => 'item_item',
                    'condition'          => ['NEWTABLE.link' => CommonITILObject_CommonITILObject::DUPLICATE_WITH],
                ],
                'additionalfields'   => ['tickets_id_2'],
                'forcegroupby'       => true,
            ];

            $tab[] = [
                'id'                 => '41',
                'table'              => 'glpi_tickets_tickets',
                'field'              => 'id',
                'name'               => __('Number of all linked tickets'),
                'massiveaction'      => false,
                'datatype'           => 'count',
                'usehaving'          => true,
                'joinparams'         => [
                    'jointype'           => 'item_item',
                ],
            ];

            $tab[] = [
                'id'                 => '46',
                'table'              => 'glpi_tickets_tickets',
                'field'              => 'id',
                'name'               => __('Number of duplicated tickets'),
                'massiveaction'      => false,
                'datatype'           => 'count',
                'usehaving'          => true,
                'joinparams'         => [
                    'jointype'           => 'item_item',
                    'condition'          => ['NEWTABLE.link' => CommonITILObject_CommonITILObject::DUPLICATE_WITH],
                ],
            ];

            $tab[] = [
                'id'                 => '50',
                'table'              => 'glpi_tickets',
                'field'              => 'id',
                'linkfield'          => 'tickets_id_2',
                'name'               => __('Parent tickets'),
                'massiveaction'      => false,
                'searchtype'         => 'equals',
                'datatype'           => 'itemlink',
                'usehaving'          => true,
                'joinparams'         => [
                    'beforejoin'         => [
                        'table'              => 'glpi_tickets_tickets',
                        'joinparams'         => [
                            'jointype'           => 'child',
                            'linkfield'          => 'tickets_id_1',
                            'condition'          => ['NEWTABLE.link' => CommonITILObject_CommonITILObject::SON_OF],
                        ],
                    ],
                ],
                'forcegroupby'       => true,
            ];

            $tab[] = [
                'id'                 => '67',
                'table'              => 'glpi_tickets',
                'field'              => 'id',
                'linkfield'          => 'tickets_id_1',
                'name'               => __('Child tickets'),
                'massiveaction'      => false,
                'searchtype'         => 'equals',
                'datatype'           => 'itemlink',
                'usehaving'          => true,
                'joinparams'         => [
                    'beforejoin'         => [
                        'table'              => 'glpi_tickets_tickets',
                        'joinparams'         => [
                            'jointype'           => 'child',
                            'linkfield'          => 'tickets_id_2',
                            'condition'          => ['NEWTABLE.link' => CommonITILObject_CommonITILObject::SON_OF],
                        ],
                    ],
                ],
                'forcegroupby'       => true,
            ];

            $tab[] = [
                'id'                 => '68',
                'table'              => 'glpi_tickets_tickets',
                'field'              => 'id',
                'name'               => __('Number of sons tickets'),
                'massiveaction'      => false,
                'datatype'           => 'count',
                'usehaving'          => true,
                'joinparams'         => [
                    'linkfield'          => 'tickets_id_2',
                    'jointype'           => 'child',
                    'condition'          => ['NEWTABLE.link' => CommonITILObject_CommonITILObject::SON_OF],
                ],
                'forcegroupby'       => true,
            ];

            $tab[] = [
                'id'                 => '69',
                'table'              => 'glpi_tickets_tickets',
                'field'              => 'id',
                'name'               => __('Number of parent tickets'),
                'massiveaction'      => false,
                'datatype'           => 'count',
                'usehaving'          => true,
                'joinparams'         => [
                    'linkfield'          => 'tickets_id_1',
                    'jointype'           => 'child',
                    'condition'          => ['NEWTABLE.link' => CommonITILObject_CommonITILObject::SON_OF],
                ],
                'additionalfields'   => ['tickets_id_2'],
            ];

            $tab = array_merge($tab, $this->getSearchOptionsSolution());

            if (Session::haveRight('ticketcost', READ)) {
                $tab = array_merge($tab, TicketCost::rawSearchOptionsToAdd());
            }
        }

        if (Session::haveRight('problem', READ)) {
            $tab = array_merge(
                $tab,
                Problem::rawSearchOptionsToAdd(self::class)
            );
        }

        if (Session::haveRight('change', READ)) {
            $tab = array_merge($tab, Change::rawSearchOptionsToAdd(self::class));
        }

        $tab[] = [
            'id'                 => 'tools',
            'name'               => __('Tools'),
        ];

        $tab[] = [
            'id'                 => '193',
            'table'              => Contract::getTable(),
            'field'              => 'name',
            'linkfield'          => 'contracts_id',
            'name'               => Contract::getTypeName(1),
            'massiveaction'      => false,
            'searchtype'         => ['equals', 'contains'],
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => Ticket_Contract::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                        'linkfield'          => 'tickets_id',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '194',
            'table'              => ContractType::getTable(),
            'field'              => 'name',
            'linkfield'          => 'contracttypes_id',
            'name'               => ContractType::getTypeName(1),
            'massiveaction'      => false,
            'searchtype'         => ['equals', 'contains'],
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => Contract::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'empty',
                        'linkfield'          => 'contracts_id',
                        'beforejoin'   => [
                            'table'        => Ticket_Contract::getTable(),
                            'joinparams'   => [
                                'jointype'   => 'child',
                                'linkfield'  => 'tickets_id',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Filter search fields for helpdesk
        if (
            !Session::isCron() // no filter for cron
            && (Session::getCurrentInterface() != 'central')
        ) {
            $tokeep = ['common', 'requester','satisfaction'];
            if (
                Session::haveRightsOr(
                    'ticketvalidation',
                    array_merge(
                        TicketValidation::getValidateRights(),
                        TicketValidation::getCreateRights()
                    )
                )
            ) {
                $tokeep[] = 'validation';
            }
            $keep = false;
            foreach ($tab as $key => &$val) {
                if (!isset($val['table'])) {
                    $keep = in_array($val['id'], $tokeep);
                }
                if (!$keep) {
                    if (isset($val['table'])) {
                        $val['nosearch'] = true;
                    }
                }
            }
        }

        if (Session::haveRight(ProjectTask::$rightname, READ)) {
            $tab[] = [
                'id' => '111',
                'table' => ProjectTask::getTable(),
                'field' => 'name',
                'name' => ProjectTask::getTypeName(1),
                'datatype' => 'dropdown',
                'massiveaction' => false,
                'forcegroupby' => true,
                'joinparams' => [
                    'beforejoin' => [
                        'table' => ProjectTask_Ticket::getTable(),
                        'joinparams' => [
                            'jointype' => 'child',
                        ],
                    ],
                ],
            ];
        }

        return $tab;
    }

    /**
     * Dropdown of ticket type
     *
     * @param string $name     Select name
     * @param array  $options  Array of options:
     *    - value     : integer / preselected value (default 0)
     *    - toadd     : array / array of specific values to add at the beginning
     *    - on_change : string / value to transmit to "onChange"
     *    - display   : boolean / display or get string (default true)
     *
     * @return string id of the select
     **/
    public static function dropdownType($name, $options = [])
    {

        $params = [
            'value'     => 0,
            'toadd'     => [],
            'on_change' => '',
            'display'   => true,
        ];

        if (is_array($options) && count($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        $items = [];
        if (count($params['toadd']) > 0) {
            $items = $params['toadd'];
        }

        $items += self::getTypes();

        return Dropdown::showFromArray($name, $items, $params);
    }

    /**
     * Get ticket types
     *
     * @return array Array of types
     **/
    public static function getTypes()
    {

        $options = [
            self::INCIDENT_TYPE => __('Incident'),
            self::DEMAND_TYPE   => __('Request'),
        ];

        return $options;
    }


    /**
     * Get ticket type Name
     *
     * @param int $value Type ID
     *
     * @return string|int
     */
    public static function getTicketTypeName($value)
    {

        switch ($value) {
            case self::INCIDENT_TYPE:
                return __('Incident');

            case self::DEMAND_TYPE:
                return __('Request');

            default:
                // Return $value if not defined
                return $value;
        }
    }

    public static function getAllStatusArray($withmetaforsearch = false)
    {
        $tab = [
            self::INCOMING => _x('status', 'New'),
            self::APPROVAL => _n('Approval', 'Approvals', 1),
            self::ASSIGNED => _x('status', 'Processing (assigned)'),
            self::PLANNED  => _x('status', 'Processing (planned)'),
            self::WAITING  => __('Pending'),
            self::SOLVED   => _x('status', 'Solved'),
            self::CLOSED   => _x('status', 'Closed'),
        ];

        if ($withmetaforsearch) {
            $tab['notold']    = _x('status', 'Not solved');
            $tab['notclosed'] = _x('status', 'Not closed');
            $tab['process']   = __('Processing');
            $tab['old']       = _x('status', 'Solved + Closed');
            $tab['all']       = __('All');
        }

        return $tab;
    }


    /**
     * Get the ITIL object closed status list
     *
     * @since 0.83
     *
     * @return array
     **/
    public static function getClosedStatusArray()
    {
        return [self::CLOSED];
    }


    /**
     * Get the ITIL object solved status list
     *
     * @since 0.83
     *
     * @return array
     **/
    public static function getSolvedStatusArray()
    {
        return [self::SOLVED];
    }

    /**
     * Get the ITIL object new status list
     *
     * @since 0.83.8
     *
     * @return array
     **/
    public static function getNewStatusArray()
    {
        return [self::INCOMING];
    }

    /**
     * Get the ITIL object assign or plan status list
     *
     * @since 0.83
     *
     * @return array
     **/
    public static function getProcessStatusArray()
    {
        return [self::ASSIGNED, self::PLANNED];
    }

    /**
     * Calculate Ticket TCO for an item
     *
     *@param CommonDBTM $item  Object of the item
     *
     *@return float
     **/
    public static function computeTco(CommonDBTM $item)
    {
        global $DB;

        $totalcost = 0;

        $iterator = $DB->request([
            'SELECT'    => 'glpi_ticketcosts.*',
            'FROM'      => 'glpi_ticketcosts',
            'LEFT JOIN' => [
                'glpi_items_tickets' => [
                    'ON' => [
                        'glpi_items_tickets' => 'tickets_id',
                        'glpi_ticketcosts'   => 'tickets_id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_items_tickets.itemtype' => get_class($item),
                'glpi_items_tickets.items_id' => $item->getField('id'),
                'OR'                          => [
                    'glpi_ticketcosts.cost_time'     => ['>', 0],
                    'glpi_ticketcosts.cost_fixed'    => ['>', 0],
                    'glpi_ticketcosts.cost_material' => ['>', 0],
                ],
            ],
        ]);

        foreach ($iterator as $data) {
            $totalcost += TicketCost::computeTotalCost(
                $data["actiontime"],
                $data["cost_time"],
                $data["cost_fixed"],
                $data["cost_material"]
            );
        }
        return $totalcost;
    }

    public static function getDefaultValues($entity = 0)
    {
        global $CFG_GLPI;

        if (is_numeric(Session::getLoginUserID(false))) {
            $users_id_requester = Session::getLoginUserID();
            $users_id_assign    = Session::getLoginUserID();
            $requester_notification_enable = $_SESSION['glpiis_notif_enable_default'];
            $assignee_notification_enable  = $_SESSION['glpiis_notif_enable_default'];

            // No default requester if own ticket right = tech and update_ticket right to update requester
            if (Session::haveRightsOr(self::$rightname, [UPDATE, self::OWN]) && !$_SESSION['glpiset_default_requester']) {
                $users_id_requester = 0;
                $requester_notification_enable = 1; // no default requester reset to true
            }
            if (!Session::haveRight(self::$rightname, self::OWN) || !$_SESSION['glpiset_default_tech']) {
                $users_id_assign = 0;
                $assignee_notification_enable = 1; // no default assign reset to true
            }
            $entity      = $_SESSION['glpiactive_entity'];
            $requesttype = $_SESSION['glpidefault_requesttypes_id'];
        } else {
            $requester_notification_enable = 1;
            $assignee_notification_enable = 1;
            $users_id_requester = 0;
            $users_id_assign    = 0;
            $requesttype        = $CFG_GLPI['default_requesttypes_id'];
        }

        $type = Entity::getUsedConfig('tickettype', $entity, '', Ticket::INCIDENT_TYPE);

        $default_use_notif = Entity::getUsedConfig('is_notif_enable_default', $entity, '', 1);

        // Set default values...
        return  ['_users_id_requester'       => $users_id_requester,
            '_users_id_requester_notif' => ['use_notification'  => [(string) ($default_use_notif & $requester_notification_enable)],
                'alternative_email' => [''],
            ],
            '_groups_id_requester'      => 0,
            '_users_id_assign'          =>  $users_id_assign,
            '_users_id_assign_notif'    => ['use_notification'  => [(string) ($default_use_notif & $assignee_notification_enable)],
                'alternative_email' => [''],
            ],
            '_groups_id_assign'         => 0,
            '_users_id_observer'        => 0,
            '_users_id_observer_notif'  => ['use_notification'  => [$default_use_notif],
                'alternative_email' => [''],
            ],
            '_groups_id_observer'       => 0,
            '_link'                     => [
                'itemtype_1' => Ticket::class,
                'items_id_1' => 0,
                'link'       => '',
            ],
            '_suppliers_id_assign'      => 0,
            '_suppliers_id_assign_notif' => ['use_notification'  => [$default_use_notif],
                'alternative_email' => [''],
            ],
            'name'                      => '',
            'content'                   => '',
            'itilcategories_id'         => 0,
            'urgency'                   => Urgency::MEDIUM->value,
            'impact'                    => 3,
            'priority'                  => self::computePriority(3, 3),
            'requesttypes_id'           => $requesttype,
            'actiontime'                => 0,
            'date'                      => 'NULL',
            'entities_id'               => $entity,
            'status'                    => self::INCOMING,
            'followup'                  => [],
            'itemtype'                  => '',
            'items_id'                  => 0,
            'locations_id'              => 0,
            'plan'                      => [],
            'time_to_resolve'           => 'NULL',
            'time_to_own'               => 'NULL',
            'slas_id_tto'               => 0,
            'slas_id_ttr'               => 0,
            'internal_time_to_resolve'  => 'NULL',
            'internal_time_to_own'      => 'NULL',
            'olas_id_tto'               => 0,
            'olas_id_ttr'               => 0,
            '_add_validation'           => 0,
            '_validation_targets'       => [],
            'type'                      => $type,
            '_documents_id'             => [],
            '_tasktemplates_id'         => [],
            '_content'                  => [],
            '_tag_content'              => [],
            '_filename'                 => [],
            '_tag_filename'             => [],
            '_actors'                   => [],
            '_contracts_id'             => 0,
            'externalid'                => '',
        ];
    }

    /**
     * Check if the category is valid for the given type and entity.
     *
     * @param array $input An associative array containing 'itilcategories_id', 'type', and 'entities_id'.
     *
     * @return bool
     */
    public static function isCategoryValid(array $input): bool
    {
        $cat = new ITILCategory();
        if ($cat->getFromDB($input['itilcategories_id'])) {
            switch ($input['type']) {
                case self::INCIDENT_TYPE:
                    if (!$cat->fields['is_incident']) {
                        return false;
                    }
                    break;

                case self::DEMAND_TYPE:
                    if (!$cat->fields['is_request']) {
                        return false;
                    }
                    break;

                default:
                    break;
            }
            // Check category / entity validity
            if (
                $cat->fields['entities_id'] != $input['entities_id']
                && !(
                    $cat->isRecursive()
                    && in_array($input['entities_id'], getSonsOf('glpi_entities', $cat->fields['entities_id']))
                )
            ) {
                return false;
            }
        }
        return true;
    }

    public static function getCommonCriteria()
    {
        $criteria = parent::getCommonCriteria();

        $criteria['LEFT JOIN']['glpi_tickettasks'] = [
            'ON' => [
                self::getTable()     => 'id',
                'glpi_tickettasks'   => 'tickets_id',
            ],
        ];

        $criteria['LEFT JOIN']['glpi_ticketvalidations'] = [
            'ON' => [
                self::getTable()         => 'id',
                'glpi_ticketvalidations' => 'tickets_id',
            ],
        ];

        return $criteria;
    }

    /**
     * Give cron information
     *
     * @param string $name  Task's name
     *
     * @return array Array of information
     **/
    public static function cronInfo($name)
    {

        switch ($name) {
            case 'closeticket':
                return ['description' => __('Automatic tickets closing')];

            case 'alertnotclosed':
                return ['description' => __('Not solved tickets')];

            case 'purgeticket':
                return [
                    'description' => __('Automatic closed tickets purge'),
                    'parameter' => __('Maximum number of tickets purged per entity (0 = unlimited)'),
                ];
        }
        return parent::cronInfo($name);
    }

    /**
     * Cron for ticket's automatic close
     *
     * @param CronTask $task
     *
     * @return int (0 : nothing done - 1 : done)
     **/
    public static function cronCloseTicket($task)
    {
        global $DB;

        $ticket = new self();

        // Recherche des entit??s
        $tot = 0;

        $entities = $DB->request(
            [
                'SELECT' => 'id',
                'FROM'   => Entity::getTable(),
            ]
        );
        foreach ($entities as $entity) {
            $delay  = Entity::getUsedConfig('autoclose_delay', $entity['id'], '', Entity::CONFIG_NEVER);
            if ($delay >= 0) {
                $criteria = [
                    'FROM'   => self::getTable(),
                    'WHERE'  => [
                        'entities_id'  => $entity['id'],
                        'status'       => self::SOLVED,
                        'is_deleted'   => 0,
                    ],
                ];

                if ($delay > 0) {
                    $calendars_id = Entity::getUsedConfig(
                        'calendars_strategy',
                        $entity['id'],
                        'calendars_id',
                        0
                    );
                    $calendar = new Calendar();
                    if ($calendars_id > 0 && $calendar->getFromDB($calendars_id) && $calendar->hasAWorkingDay()) {
                        $end_date = $calendar->computeEndDate(
                            date('Y-m-d H:i:s'),
                            - $delay * DAY_TIMESTAMP,
                            0,
                            true
                        );
                        $criteria['WHERE']['solvedate'] = ['<=', $end_date];
                    } else {
                        // no calendar, remove all days
                        $criteria['WHERE'][] = new QueryExpression(
                            QueryFunction::dateAdd(
                                date: 'solvedate',
                                interval: $delay,
                                interval_unit: 'DAY'
                            ) . ' < ' . QueryFunction::now()
                        );
                    }
                }

                $nb = 0;
                $iterator = $DB->request($criteria);
                foreach ($iterator as $tick) {
                    $ticket->update([
                        'id'           => $tick['id'],
                        'status'       => self::CLOSED,
                        '_auto_update' => true,
                    ]);
                    $nb++;
                }

                if ($nb) {
                    $tot += $nb;
                    $task->addVolume($nb);
                    $task->log(Dropdown::getDropdownName('glpi_entities', $entity['id']) . " : $nb");
                }
            }
        }

        return ($tot > 0 ? 1 : 0);
    }

    /**
     * Cron for alert old tickets which are not solved
     *
     * @param CronTask $task
     *
     * @return int (0 : nothing done - 1 : done)
     **/
    public static function cronAlertNotClosed($task)
    {
        global $CFG_GLPI, $DB;

        if (!$CFG_GLPI["use_notifications"]) {
            return 0;
        }
        // Recherche des entit??s
        $tot = 0;
        foreach (Entity::getEntitiesToNotify('notclosed_delay') as $entity => $value) {
            $iterator = $DB->request([
                'FROM'   => self::getTable(),
                'WHERE'  => [
                    'entities_id'  => $entity,
                    'is_deleted'   => 0,
                    'status'       => [
                        self::INCOMING,
                        self::ASSIGNED,
                        self::PLANNED,
                        self::WAITING,
                    ],
                    'closedate'    => null,
                    new QueryExpression(
                        QueryFunction::dateAdd(
                            date: 'date',
                            interval: $value,
                            interval_unit: 'DAY'
                        ) . ' < ' . QueryFunction::now()
                    ),
                ],
            ]);
            $tickets = [];
            foreach ($iterator as $tick) {
                $tickets[] = $tick;
            }

            if ($tickets !== []) {
                if (
                    NotificationEvent::raiseEvent(
                        'alertnotclosed',
                        new self(),
                        ['items'       => $tickets,
                            'entities_id' => $entity,
                        ]
                    )
                ) {
                    $tot += count($tickets);
                    $task->addVolume(count($tickets));
                    $task->log(sprintf(
                        __('%1$s: %2$s'),
                        Dropdown::getDropdownName('glpi_entities', $entity),
                        count($tickets)
                    ));
                }
            }
        }

        return ($tot > 0 ? 1 : 0);
    }

    /**
     * Cron for ticket's automatic purge
     *
     * @param CronTask $task CronTask object
     *
     * @return int (0 : nothing done - 1 : done)
     **/
    public static function cronPurgeTicket(CronTask $task)
    {
        global $DB;

        $ticket = new self();

        //search entities
        $tot = 0;

        $entities = $DB->request(
            [
                'SELECT' => 'id',
                'FROM'   => Entity::getTable(),
            ]
        );

        $max = (int) ($task->fields['param'] ?? 0);

        foreach ($entities as $entity) {
            $delay  = Entity::getUsedConfig('autopurge_delay', $entity['id'], '', Entity::CONFIG_NEVER);
            if ($delay >= 0) {
                $criteria = [
                    'FROM'   => $ticket->getTable(),
                    'WHERE'  => [
                        'entities_id'  => $entity['id'],
                        'status'       => $ticket->getClosedStatusArray(),
                    ],
                ];

                if ($delay > 0) {
                    // remove all days
                    $criteria['WHERE'][] = new QueryExpression(
                        QueryFunction::dateAdd(
                            date: 'closedate',
                            interval: $delay,
                            interval_unit: 'DAY'
                        ) . ' < ' . QueryFunction::now()
                    );
                }

                if ($max > 0) {
                    $criteria['LIMIT'] = $max;
                }

                $iterator = $DB->request($criteria);
                $nb = 0;

                foreach ($iterator as $tick) {
                    $ticket->delete(
                        [
                            'id'           => $tick['id'],
                            '_auto_update' => true,
                        ],
                        true
                    );
                    $nb++;
                }

                if ($nb) {
                    $tot += $nb;
                    $task->addVolume($nb);
                    $task->log(Dropdown::getDropdownName('glpi_entities', $entity['id']) . " : $nb");
                }
            }
        }

        return ($tot > 0 ? 1 : 0);
    }

    /**
     * @since 0.85
     *
     * @see commonDBTM::getRights()
     **/
    public function getRights($interface = 'central')
    {

        $values = parent::getRights();
        unset($values[READ]);
        $values[self::READMY]    = __('See my ticket');
        //TRANS: short for : See tickets created by my groups
        $values[self::READGROUP] = ['short' => __('See group ticket'),
            'long'  => __('See tickets created by my groups'),
        ];
        if ($interface == 'central') {
            $values[self::READALL]        = __('See all tickets');
            //TRANS: short for : See assigned tickets (group associated)
            $values[self::READASSIGN]     = ['short' => __('See assigned'),
                'long'  => __('See assigned tickets'),
            ];
            //TRANS: short for : Assign a ticket
            $values[self::ASSIGN]         = ['short' => __('Assign'),
                'long'  => __('Assign a ticket'),
            ];
            //TRANS: short for : Steal a ticket
            $values[self::STEAL]          = ['short' => __('Steal'),
                'long'  => __('Steal a ticket'),
            ];
            //TRANS: short for : To be in charge of a ticket
            $values[self::OWN]            = ['short' => __('Be in charge'),
                'long'  => __('To be in charge of a ticket'),
            ];
            $values[self::CHANGEPRIORITY] = __('Change the priority');
            $values[self::SURVEY]         = ['short' => __('Approve solution/Reply survey (my ticket)'),
                'long'  => __('Approve solution and reply to survey for ticket created by me'),
            ];
            $values[self::READNEWTICKET]       = __('View new tickets');
        }
        if ($interface == 'helpdesk') {
            unset($values[UPDATE], $values[DELETE], $values[PURGE]);
        }
        return $values;
    }

    /**
     * Convert img of the collector for ticket
     *
     * @since 0.85
     *
     * @param string $html  html content of input
     * @param array  $files filenames
     * @param array  $tags  image tags
     *
     * @return string html content
     **/
    public static function convertContentForTicket($html, $files, $tags)
    {
        $src_patterns = [
            'src\s*=\s*"[^"]+"',    // src="image.png"
            "src\s*=\s*'[^']+'",    // src='image.png'
            'src\s*=[^\s>]+',       // src=image.png
        ];
        $matches = [];
        if (preg_match_all('/(' . implode('|', $src_patterns) . ')/', $html, $matches, PREG_PATTERN_ORDER) > 0) {
            foreach ($matches[0] as $src_attr) {
                // Set tag if image matches
                foreach ($files as $data => $filename) {
                    if (preg_match("/" . preg_quote($data, '/') . "/i", $src_attr)) {
                        $html = preg_replace_callback(
                            "/<img[^>]*" . preg_quote($src_attr, '/') . "[^>]*>/s",
                            function ($img_matches) use ($tags, $filename) {
                                $img_tag = $img_matches[0];

                                // Extract width attribute if present
                                $width_attr = '';
                                if (preg_match('/\bwidth\s*=\s*["\']?(\d+)["\']?/i', $img_tag, $w_matches)) {
                                    $width_attr = ' width="' . (int) $w_matches[1] . '"';
                                }

                                // Extract height attribute if present
                                $height_attr = '';
                                if (preg_match('/\bheight\s*=\s*["\']?(\d+)["\']?/i', $img_tag, $h_matches)) {
                                    $height_attr = ' height="' . (int) $h_matches[1] . '"';
                                }

                                // Return an img tag with the tag as id, preserving width/height
                                return '<img id="' . htmlescape($tags[$filename]) . '"' . $width_attr . $height_attr . '>';
                            },
                            $html
                        );
                    }
                }
            }
        }

        return $html;
    }

    /**
     * Get correct Calendar: Entity or Sla
     *
     * @since 0.90.4
     * @since 10.0.4 $slm_type parameter added
     *
     * @param int $slm_type Type of SLA, can be SLM::TTO or SLM::TTR
     *
     **/
    public function getCalendar(int $slm_type = SLM::TTR)
    {
        [$date_field, $sla_field] = SLA::getFieldNames($slm_type);

        if (isset($this->fields[$sla_field]) && $this->fields[$sla_field] > 0) {
            $sla = new SLA();
            if ($sla->getFromDB($this->fields[$sla_field])) {
                if (!$sla->fields['use_ticket_calendar']) {
                    return $sla->fields['calendars_id'];
                }
            }
        }
        return parent::getCalendar();
    }

    public function getValueToSelect($field_id_or_search_options, $name = '', $values = '', $options = [])
    {
        if (isset($field_id_or_search_options['linkfield'])) {
            switch ($field_id_or_search_options['linkfield']) {
                case 'requesttypes_id':
                    if (isset($field_id_or_search_options['joinparams']) && Toolbox::in_array_recursive('glpi_itilfollowups', $field_id_or_search_options['joinparams'])) {
                        $opt = ['is_itilfollowup' => 1];
                    } else {
                        $opt = [
                            'OR' => [
                                'is_mail_default' => 1,
                                'is_ticketheader' => 1,
                            ],
                        ];
                    }
                    if ($field_id_or_search_options['linkfield']  == $name) {
                        $opt['is_active'] = 1;
                    }
                    if (isset($options['condition'])) {
                        if (!is_array($options['condition'])) {
                            $options['condition'] = [$options['condition']];
                        }
                        $opt = array_merge($opt, $options['condition']);
                    }
                    $options['condition'] = $opt;
                    break;
            }
        }
        return parent::getValueToSelect($field_id_or_search_options, $name, $values, $options);
    }

    protected function fillInputForBusinessRules(array &$input)
    {
        parent::fillInputForBusinessRules($input);

        // add SLA/OLA (for business rules)
        if (!$this->isNewItem()) {
            foreach ([SLM::TTR, SLM::TTO] as $slmType) {
                [$dateField, $slaField] = SLA::getFieldNames($slmType);
                if (!isset($input[$slaField]) && isset($this->fields[$slaField]) && $this->fields[$slaField] > 0) {
                    $input[$slaField] = $this->fields[$slaField];
                }
                [$dateField, $olaField] = OLA::getFieldNames($slmType);
                if (!isset($input[$olaField]) && isset($this->fields[$olaField]) && $this->fields[$olaField] > 0) {
                    $input[$olaField] = $this->fields[$olaField];
                }
            }
        }
    }

    /**
     * Build parent condition for search
     *
     * @param string $fieldID field used in the condition: tickets_id, items_id
     *
     * @return string
     */
    public static function buildCanViewCondition($fieldID)
    {

        $condition = "";
        $user   = Session::getLoginUserID();
        $groups = "'" . implode("','", $_SESSION['glpigroups']) . "'";

        $requester = CommonITILActor::REQUESTER;
        $assign    = CommonITILActor::ASSIGN;
        $obs       = CommonITILActor::OBSERVER;

        // Avoid empty IN ()
        if ($groups == "''") {
            $groups = '-1';
        }

        if (Session::haveRight("ticket", Ticket::READMY)) {
            // Add tickets where the users is requester, observer or recipient
            // Subquery for requester/observer user
            $user_query = "SELECT `tickets_id`
            FROM `glpi_tickets_users`
            WHERE `users_id` = '$user' AND type IN ($requester, $obs)";
            $condition .= "OR `$fieldID` IN ($user_query) ";

            // Subquery for recipient
            $recipient_query = "SELECT `id`
            FROM `glpi_tickets`
            WHERE `users_id_recipient` = '$user'";
            $condition .= "OR `$fieldID` IN ($recipient_query) ";
        }

        if (Session::haveRight("ticket", Ticket::READGROUP)) {
            // Add tickets where the users is in a requester or observer group
            // Subquery for requester/observer group
            $group_query = "SELECT `tickets_id`
            FROM `glpi_groups_tickets`
            WHERE `groups_id` IN ($groups) AND type IN ($requester, $obs)";
            $condition .= "OR `$fieldID` IN ($group_query) ";
        }

        if (
            Session::haveRightsOr("ticket", [
                Ticket::OWN,
                Ticket::READASSIGN,
            ])
        ) {
            // Add tickets where the users is assigned
            // Subquery for assigned user
            $user_query = "SELECT `tickets_id`
            FROM `glpi_tickets_users`
            WHERE `users_id` = '$user' AND type = $assign";
            $condition .= "OR `$fieldID` IN ($user_query) ";
        }

        if (Session::haveRight("ticket", Ticket::READASSIGN)) {
            // Add tickets where the users is part of an assigned group
            // Subquery for assigned group
            $group_query = "SELECT `tickets_id`
            FROM `glpi_groups_tickets`
            WHERE `groups_id` IN ($groups) AND type = $assign";
            $condition .= "OR `$fieldID` IN ($group_query) ";

            if (Session::haveRight('ticket', Ticket::READNEWTICKET)) {
                // Add new tickets
                $tickets_query = "SELECT `id`
               FROM `glpi_tickets`
               WHERE `status` = '" . CommonITILObject::INCOMING . "'";
                $condition .= "OR `$fieldID` IN ($tickets_query) ";
            }
        }

        if (
            Session::haveRightsOr('ticketvalidation', [
                TicketValidation::VALIDATEINCIDENT,
                TicketValidation::VALIDATEREQUEST,
            ])
        ) {
            // Add tickets where the users is the validator
            // Subquery for validator
            $validation_query = "SELECT `tickets_id`
            FROM `glpi_ticketvalidations`
            WHERE (`itemtype_target` = 'User' AND `items_id_target` = '$user')
                OR (`itemtype_target` = 'Group' AND `items_id_target` IN (SELECT `glpi_groups_users`.`groups_id` FROM `glpi_groups_users` WHERE `glpi_groups_users`.`users_id` = '$user'))";
            $condition .= "OR `$fieldID` IN ($validation_query) ";
        }

        return $condition;
    }

    public function getForbiddenSingleMassiveActions()
    {
        $excluded = parent::getForbiddenSingleMassiveActions();
        if (in_array($this->fields['status'], static::getClosedStatusArray())) {
            //for closed Tickets, only keep transfer and unlock
            $excluded[] = 'TicketValidation:submit_validation';
            $excluded[] = 'Ticket:*';
            $excluded[] = 'ITILFollowup:*';
            $excluded[] = 'Document_Item:*';
        }

        $excluded[] = 'Ticket_Ticket:add';
        $excluded[] = 'Ticket:resolve_tickets';

        return $excluded;
    }

    public function getWhitelistedSingleMassiveActions()
    {
        $whitelist = parent::getWhitelistedSingleMassiveActions();

        if (!in_array($this->fields['status'], static::getClosedStatusArray())) {
            $whitelist[] = 'Item_Ticket:add_item';
        }

        return $whitelist;
    }

    /**
     * Merge one or more tickets into another existing ticket.
     * Optionally sub-items like followups, documents, and tasks can be copied into the merged ticket.
     * If a ticket cannot be merged, the process continues on to the next ticket.
     * @param int   $merge_target_id The ID of the ticket that the other tickets will be merged into
     * @param array $ticket_ids Array of IDs of tickets to merge into the ticket with ID $merge_target_id
     * @param array $params Array of parameters for the ticket merge.
     *       linktypes - Array of itemtypes that will be duplicated into the ticket $merge_target_id.
     *                By default, no sub-items are copied. Currently supported link types are ITILFollowup, Document, and TicketTask.
     *       full_transaction - Boolean value indicating if the entire merge must complete successfully, or if partial merges are allowed.
     *                By default, the full merge must complete. On failure, all database operations performed are rolled back.
     *       link_type - Integer indicating the link type of the merged tickets (See types in Ticket_Ticket).
     *                By default, this is CommonITILObject_CommonITILObject::SON_OF. To disable linking, use 0 or a negative value.
     *       append_actors - Array of actor types to migrate into the ticket $merge_ticket. See types in CommonITILActor.
     *                By default, all actors are added to the ticket.
     * @param array $status Reference array that this function uses to store the status of each ticket attempted to be merged.
     *                   id => status (0 = Success, 1 = Error, 2 = Insufficient Rights).
     * @return bool  True if the merge was successful if "full_transaction" is true.
     *                      Otherwise, true if any ticket was successfully merged.
     * @since 9.5.0
     */
    public static function merge(int $merge_target_id, array $ticket_ids, array &$status, array $params = [])
    {
        global $DB;
        $p = [
            'linktypes'          => [],
            'full_transaction'   => true,
            'link_type'          => CommonITILObject_CommonITILObject::SON_OF,
            'append_actors'      => [CommonITILActor::REQUESTER, CommonITILActor::OBSERVER, CommonITILActor::ASSIGN],
        ];
        $p = array_replace($p, $params);
        $ticket = new Ticket();
        $merge_target = new Ticket();
        $merge_target->getFromDB($merge_target_id);
        $fup = new ITILFollowup();
        $document_item = new Document_Item();
        $task = new TicketTask();

        if (!$merge_target->canAddFollowups()) {
            foreach ($ticket_ids as $id) {
                Toolbox::logDebug(sprintf(__('Not enough rights to merge tickets %d and %d'), $merge_target_id, $id));
                // Set status = 2 : Rights issue
                $status[$id] = 2;
            }
            return false;
        }

        if ($p['full_transaction']) {
            $DB->beginTransaction();
        }
        foreach ($ticket_ids as $id) {
            try {
                if (!$p['full_transaction']) {
                    $DB->beginTransaction();
                }
                if ($merge_target->canUpdateItem() && $ticket->can($id, DELETE)) {
                    if (!$ticket->getFromDB($id)) {
                        //Cannot retrieve ticket. Abort/fail the merge
                        throw new RuntimeException(sprintf(__('Failed to load ticket %d'), $id), 1);
                    }
                    if ($ticket->fields['id'] == $merge_target_id) {
                        // Cannot merge a ticket into itself. Abort/fail the merge
                        throw new RuntimeException(sprintf(__('Cannot merge ticket %d into itself'), $id), 1);
                    }
                    if ($ticket->isDeleted()) {
                        //ignore deleted tickets
                        continue;
                    }

                    $tt = new Ticket_Ticket();
                    if ($p['link_type'] > 0 && $p['link_type'] < 5) {
                        // Clean up any existing links between the tickets to avoid duplicates
                        $tt->deleteByCriteria([
                            'OR' => [
                                [
                                    'AND' => [
                                        'tickets_id_1' => $merge_target_id,
                                        'tickets_id_2' => $id,
                                    ],
                                ],
                                [
                                    'AND' => [
                                        'tickets_id_2' => $merge_target_id,
                                        'tickets_id_1' => $id,
                                    ],
                                ],
                            ],
                        ]);
                    }
                    //Build followup from the original ticket
                    $input = [
                        'itemtype'        => Ticket::class,
                        'items_id'        => $merge_target_id,
                        'content'         => htmlescape($ticket->fields['name']) . "<br /><br />" . $ticket->fields['content'],
                        'users_id'        => $ticket->fields['users_id_recipient'],
                        'date_creation'   => $ticket->fields['date_creation'],
                        'date_mod'        => $ticket->fields['date_mod'],
                        'date'            => $ticket->fields['date_creation'],
                        'sourceitems_id'  => $ticket->getID(),
                    ];
                    if (!$fup->add($input)) {
                        //Cannot add followup. Abort/fail the merge
                        throw new RuntimeException(sprintf(__('Failed to add followup to ticket %d'), $merge_target_id), 1);
                    }
                    if (in_array('ITILFollowup', $p['linktypes'])) {
                        // Copy any followups to the ticket
                        $tomerge = $fup->find([
                            'items_id' => $id,
                            'itemtype' => Ticket::class,
                        ]);
                        foreach ($tomerge as $fup2) {
                            $fup2['items_id'] = $merge_target_id;
                            $fup2['sourceitems_id'] = $id;
                            $fup2['content'] = $fup2['content'];
                            unset($fup2['id']);
                            if (!$fup->add($fup2)) {
                                // Cannot add followup. Abort/fail the merge
                                throw new RuntimeException(sprintf(__('Failed to add followup to ticket %d'), $merge_target_id), 1);
                            }
                        }
                    }
                    if (in_array('TicketTask', $p['linktypes'])) {
                        $merge_tmp = ['tickets_id' => $merge_target_id];
                        if (!$task->can(-1, CREATE, $merge_tmp)) {
                            throw new RuntimeException(sprintf(__('Not enough rights to merge tickets %d and %d'), $merge_target_id, $id), 2);
                        }
                        // Copy any tasks to the ticket
                        $tomerge = $task->find([
                            'tickets_id' => $id,
                        ]);
                        foreach ($tomerge as $task2) {
                            $task2['tickets_id'] = $merge_target_id;
                            $task2['sourceitems_id'] = $id;
                            $task2['content'] = $task2['content'];
                            unset($task2['id']);
                            unset($task2['uuid']);
                            if (!$task->add($task2)) {
                                //Cannot add followup. Abort/fail the merge
                                throw new RuntimeException(sprintf(__('Failed to add task to ticket %d'), $merge_target_id), 1);
                            }
                        }
                    }
                    if (in_array('Document', $p['linktypes'])) {
                        if (!$merge_target->canAddItem('Document')) {
                            throw new RuntimeException(sprintf(__('Not enough rights to merge tickets %d and %d'), $merge_target_id, $id), 2);
                        }
                        $tomerge = $document_item->find([
                            'itemtype' => Ticket::class,
                            'items_id' => $id,
                            'NOT' => [
                                'documents_id' => new QuerySubQuery([
                                    'SELECT' => 'documents_id',
                                    'FROM'   => $document_item->getTable(),
                                    'WHERE'  => [
                                        'itemtype' => Ticket::class,
                                        'items_id' => $merge_target_id,
                                    ],
                                ]),
                            ],
                        ]);

                        foreach ($tomerge as $document_item2) {
                            $document_item2['items_id'] = $merge_target_id;
                            unset($document_item2['id']);
                            if (!$document_item->add($document_item2)) {
                                //Cannot add document. Abort/fail the merge
                                throw new RuntimeException(sprintf(__('Failed to add document to ticket %d'), $merge_target_id), 1);
                            }
                        }
                    }
                    if ($p['link_type'] > 0 && $p['link_type'] < 5) {
                        //Add relation (this is parent of merge target)
                        $linkparams = [
                            'link'         => $p['link_type'],
                            'tickets_id_1' => $id,
                            'tickets_id_2' => $merge_target_id,
                        ];
                        if (!$tt->add($linkparams)) {
                            //Cannot link tickets. Abort/fail the merge
                            throw new RuntimeException(sprintf(__('Failed to link tickets %d and %d'), $merge_target_id, $id), 1);
                        }
                    }
                    if (isset($p['append_actors'])) {
                        $tu = new Ticket_User();
                        $existing_users = $tu->find(['tickets_id' => $merge_target_id]);
                        $gt = new Group_Ticket();
                        $existing_groups = $gt->find(['tickets_id' => $merge_target_id]);
                        $st = new Supplier_Ticket();
                        $existing_suppliers = $st->find(['tickets_id' => $merge_target_id]);

                        foreach ($p['append_actors'] as $actor_type) {
                            $users = $tu->find([
                                'tickets_id' => $id,
                                'type' => $actor_type,
                            ]);
                            $groups = $gt->find([
                                'tickets_id' => $id,
                                'type' => $actor_type,
                            ]);
                            $suppliers = $st->find([
                                'tickets_id' => $id,
                                'type' => $actor_type,
                            ]);
                            $users = array_filter($users, function ($user) use ($existing_users) {
                                foreach ($existing_users as $existing_user) {
                                    if (
                                        $existing_user['users_id'] > 0 && $user['users_id'] > 0
                                        && $existing_user['users_id'] === $user['users_id']
                                        && $existing_user['type'] === $user['type']
                                    ) {
                                        // Internal users
                                        return false;
                                    } elseif (
                                        $existing_user['users_id'] == 0 && $user['users_id'] == 0
                                        && $existing_user['alternative_email'] === $user['alternative_email']
                                        && $existing_user['type'] === $user['type']
                                    ) {
                                        // External users
                                        return false;
                                    }
                                }
                                return true;
                            });
                            $groups = array_filter($groups, function ($group) use ($existing_groups) {
                                foreach ($existing_groups as $existing_group) {
                                    if (
                                        $existing_group['groups_id'] === $group['groups_id']
                                        && $existing_group['type'] === $group['type']
                                    ) {
                                        return false;
                                    }
                                }
                                return true;
                            });
                            $suppliers = array_filter($suppliers, function ($supplier) use ($existing_suppliers) {
                                foreach ($existing_suppliers as $existing_supplier) {
                                    if (
                                        $existing_supplier['suppliers_id'] > 0 && $supplier['suppliers_id'] > 0
                                        && $existing_supplier['suppliers_id'] === $supplier['suppliers_id']
                                        && $existing_supplier['type'] === $supplier['type']
                                    ) {
                                        // Internal suppliers
                                        return false;
                                    } elseif (
                                        $existing_supplier['suppliers_id'] == 0 && $supplier['suppliers_id'] == 0
                                        && $existing_supplier['alternative_email'] === $supplier['alternative_email']
                                        && $existing_supplier['type'] === $supplier['type']
                                    ) {
                                        // External suppliers
                                        return false;
                                    }
                                }
                                return true;
                            });
                            foreach ($users as $user) {
                                $user['tickets_id'] = $merge_target_id;
                                unset($user['id']);
                                $tu->add($user);
                            }
                            foreach ($groups as $group) {
                                $group['tickets_id'] = $merge_target_id;
                                unset($group['id']);
                                $gt->add($group);
                            }
                            foreach ($suppliers as $supplier) {
                                $supplier['tickets_id'] = $merge_target_id;
                                unset($supplier['id']);
                                $st->add($supplier);
                            }
                        }
                    }
                    //Delete this ticket
                    if (!$ticket->delete(['id' => $id, '_disablenotif' => true])) {
                        throw new RuntimeException(sprintf(__('Failed to delete ticket %d'), $id), 1);
                    }
                    if (!$p['full_transaction']) {
                        $DB->commit();
                    }
                    $status[$id] = 0;
                    Event::log(
                        $merge_target_id,
                        'ticket',
                        4,
                        'tracking',
                        sprintf(
                            __('%s merges ticket %s into %s'),
                            $_SESSION['glpiname'],
                            $id,
                            $merge_target_id
                        )
                    );
                } else {
                    throw new RuntimeException(sprintf(__('Not enough rights to merge tickets %d and %d'), $merge_target_id, $id), 2);
                }
            } catch (RuntimeException $e) {
                if ($e->getCode() < 1 || $e->getCode() > 2) {
                    $status[$id] = 1;
                } else {
                    $status[$id] = $e->getCode();
                }
                Toolbox::logDebug($e->getMessage());
                $DB->rollBack();
                if ($p['full_transaction']) {
                    return false;
                }
            }
        }
        if ($p['full_transaction']) {
            $DB->commit();
        }
        return true;
    }

    /**
     * Get the list of tickets in which the ticket has been merged
     *
     * @param int $id The ID of the ticket
     *
     * @return array The list of tickets that have ticket with ID $id as son
     */
    public static function getMergedTickets(int $id): array
    {
        global $DB;

        //look for merged tickets (only when the source ticket is deleted, i.e. actually merged)
        $merged = [];
        $iterator = $DB->request(
            [
                'FROM' => Ticket_Ticket::getTable(),
                'SELECT' => [Ticket_Ticket::getTable() . '.tickets_id_2'],
                'DISTINCT' => true,
                'INNER JOIN' => [
                    Ticket::getTable() => [
                        'ON' => [
                            Ticket_Ticket::getTable() => 'tickets_id_1',
                            Ticket::getTable()        => 'id',
                        ],
                    ],
                ],
                'WHERE' => [
                    Ticket_Ticket::getTable() . '.tickets_id_1' => $id,
                    Ticket_Ticket::getTable() . '.link'         => Ticket_Ticket::SON_OF,
                    Ticket::getTable() . '.is_deleted'          => 1,
                ],
            ]
        );
        foreach ($iterator as $data) {
            $merged[] = $data['tickets_id_2'];
        }
        return $merged;
    }

    /**
     * Check profiles and detect where criteria from existing rights
     *
     * @return array criteria to apply to an iterator query
     */
    public static function getCriteriaFromProfile()
    {
        if (Session::haveRight("ticket", Ticket::READALL)) {
            return [];
        }

        $users  = false;
        $groups = false;
        $valid  = false;

        $where_profile = [];
        if (Session::haveRight("ticket", Ticket::READMY)) {
            $users = true;
            $where_profile[] = [
                'OR' => [
                    [
                        'tu.users_id' => Session::getLoginUserID(),
                        'OR' => [
                            ['tu.type' => CommonITILActor::REQUESTER],
                            ['tu.type' => CommonITILActor::OBSERVER],
                        ],
                    ],
                    "glpi_tickets.users_id_recipient" => Session::getLoginUserID(),
                ],
            ];
        }

        if (Session::haveRight("ticket", Ticket::READGROUP) && count($_SESSION['glpigroups'])) {
            $groups = true;
            $where_profile[] = [
                'gt.groups_id' => $_SESSION['glpigroups'],
                'OR' => [
                    ['gt.type' => CommonITILActor::REQUESTER],
                    ['gt.type' => CommonITILActor::OBSERVER],
                ],
            ];
        }

        if (Session::haveRight("ticket", Ticket::OWN)) {
            $users = true;
            $where_profile[] = [
                'tu.users_id' => Session::getLoginUserID(),
                'tu.type'     => CommonITILActor::ASSIGN,
            ];
        }

        if (Session::haveRight("ticket", Ticket::READASSIGN)) {
            $users = true;
            $temp = [
                'OR' => [
                    [
                        'tu.users_id' => Session::getLoginUserID(),
                        'tu.type'     => CommonITILActor::ASSIGN,
                    ],
                ],
            ];

            if (count($_SESSION['glpigroups'])) {
                $groups = true;
                $temp['OR'][] = [
                    'gt.groups_id' => $_SESSION['glpigroups'],
                    'gt.type'      => CommonITILActor::ASSIGN,
                ];
            }

            if (Session::haveRight('ticket', Ticket::READNEWTICKET)) {
                $temp['OR'][] = [
                    ['glpi_tickets.status' => CommonITILObject::INCOMING],
                ];
            }

            $where_profile[] = $temp;
        }

        if (
            Session::haveRightsOr('ticketvalidation', [
                TicketValidation::VALIDATEINCIDENT,
                TicketValidation::VALIDATEREQUEST,
            ])
        ) {
            $valid = true;
            $where_profile[] = TicketValidation::getTargetCriteriaForUser(Session::getLoginUserID());
        }

        // joins needed tables
        $join_profile  = [];
        if ($users) {
            $join_profile['glpi_tickets_users AS tu'] = [
                'ON' => [
                    'tu'           => 'tickets_id',
                    'glpi_tickets' => 'id',
                ],
            ];
        }
        if ($groups) {
            $join_profile['glpi_groups_tickets AS gt'] = [
                'ON' => [
                    'gt'           => 'tickets_id',
                    'glpi_tickets' => 'id',
                ],
            ];
        }
        if ($valid) {
            $join_profile['glpi_ticketvalidations'] = [
                'ON' => [
                    'glpi_ticketvalidations' => 'tickets_id',
                    'glpi_tickets' => 'id',
                ],
            ];
        }

        $criteria = [];
        if (count($where_profile)) {
            $criteria['WHERE'] = [['OR' => $where_profile]];
        }
        if (count($join_profile)) {
            $criteria['LEFT JOIN'] = $join_profile;
        }

        return $criteria;
    }

    public static function getItemLinkClass(): string
    {
        return Item_Ticket::class;
    }

    public static function getContentTemplatesParametersClassInstance(): CommonITILObjectParameters
    {
        return new TicketParameters();
    }

    public function processRules(int $condition, array &$input, int $entid = -1): void
    {
        if (isset($input['_skip_rules']) && $input['_skip_rules'] !== false) {
            return;
        }

        $initial_requester = isset($input['_users_id_requester']) && !is_array($input['_users_id_requester']) && (int) $input['_users_id_requester'] > 0
            ? $input['_users_id_requester']
            : 0;

        // Business Rules do not override manual SLA and OLA
        $manual_slas_id = [];
        $manual_olas_id = [];
        foreach ([SLM::TTR, SLM::TTO] as $slmType) {
            [$dateField, $slaField] = SLA::getFieldNames($slmType);
            if (isset($input[$slaField]) && ($input[$slaField] > 0)) {
                $manual_slas_id[$slmType] = $input[$slaField];
            }

            [$dateField, $olaField] = OLA::getFieldNames($slmType);
            if (isset($input[$olaField]) && ($input[$olaField] > 0)) {
                $manual_olas_id[$slmType] = $input[$olaField];
            }
        }

        parent::processRules($condition, $input, $entid);

        if ($condition === RuleCommonITILObject::ONADD) {
            if (
                isset($input['_users_id_requester'])
                && !is_array($input['_users_id_requester'])
                && ($input['_users_id_requester'] != $initial_requester)
            ) {
                // if requester set by rule, clear address from mailcollector
                unset($input['_users_id_requester_notif']);
            }
        }
        if (!isset($input['_skip_sla_assign']) || $input['_skip_sla_assign'] === false) {
            // Manage SLA / OLA asignment
            // Manual SLA / OLA defined : reset due date
            // No manual SLA / OLA and due date defined : reset auto SLA / OLA
            foreach ([SLM::TTR, SLM::TTO] as $slmType) {
                $this->slaAffect($slmType, $input, $manual_slas_id);
                $this->olaAffect($slmType, $input, $manual_olas_id);
            }
        }
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return array
     */
    public static function rawSearchOptionsToAdd($itemtype)
    {
        global $CFG_GLPI;

        $options = [];

        if (in_array($itemtype, $CFG_GLPI["ticket_types"])) {
            $options[] = [
                'id'            => 60,
                'table'         => self::getTable(),
                'field'         => "id",
                'datatype'      => "count",
                'name'          => _x('quantity', 'Number of tickets'),
                'forcegroupby'  => true,
                'usehaving'     => true,
                'massiveaction' => false,
                'joinparams'    => [
                    'beforejoin' => [
                        'table' => self::getItemLinkClass()::getTable(),
                        'joinparams' => [
                            'jointype' => 'itemtype_item',
                        ],
                    ],
                    'condition' => getEntitiesRestrictCriteria('NEWTABLE'),
                ],
            ];
        }
        return $options;
    }

    /**
     * @param CommonDBTM $item
     *
     * @return array
     */
    public static function getListForItemRestrict(CommonDBTM $item)
    {
        $restrict = [];

        switch (true) {
            case $item instanceof User:
                $restrict['glpi_tickets_users.users_id'] = $item->getID();
                $restrict['glpi_tickets_users.type'] = CommonITILActor::REQUESTER;
                break;

            case $item instanceof SLA:
                $restrict[] = [
                    'OR' => [
                        'slas_id_tto'  => $item->getID(),
                        'slas_id_ttr'  => $item->getID(),
                    ],
                ];
                break;

            case $item instanceof OLA:
                $restrict[] = [
                    'OR' => [
                        'olas_id_tto'  => $item->getID(),
                        'olas_id_ttr'  => $item->getID(),
                    ],
                ];
                break;

            case $item instanceof Supplier:
                $restrict['glpi_suppliers_tickets.suppliers_id'] = $item->getID();
                $restrict['glpi_suppliers_tickets.type'] = CommonITILActor::ASSIGN;
                break;

            case $item instanceof Group:
                if ($item->haveChildren()) {
                    $tree = Session::getSavedOption(self::class, 'tree', 0);
                } else {
                    $tree = 0;
                }
                $restrict['glpi_groups_tickets.groups_id'] = ($tree ? getSonsOf('glpi_groups', $item->getID()) : $item->getID());
                $restrict['glpi_groups_tickets.type'] = CommonITILActor::REQUESTER;
                break;

            default:
                $restrict['glpi_items_tickets.items_id'] = $item->getID();
                $restrict['glpi_items_tickets.itemtype'] = $item->getType();
                // you can only see your tickets
                if (!Session::haveRight(self::$rightname, self::READALL)) {
                    $or = [
                        'glpi_tickets.users_id_recipient'   => Session::getLoginUserID(),
                        [
                            'AND' => [
                                'glpi_tickets_users.tickets_id'  => new QueryExpression('glpi_tickets.id'),
                                'glpi_tickets_users.users_id'    => Session::getLoginUserID(),
                            ],
                        ],
                    ];
                    if (Session::haveRightsOr(TicketValidation::$rightname, [TicketValidation::VALIDATEINCIDENT, TicketValidation::VALIDATEREQUEST])) {
                        $or[] = [
                            'AND' => [
                                'glpi_ticketvalidations.tickets_id'        => new QueryExpression('glpi_tickets.id'),
                                'glpi_ticketvalidations.itemtype_target'   => User::class,
                                'glpi_ticketvalidations.items_id_target' => Session::getLoginUserID(),
                            ],
                        ];
                    }
                    if (count($_SESSION['glpigroups'])) {
                        $or['glpi_groups_tickets.groups_id'] = $_SESSION['glpigroups'];
                    }
                    $restrict[] = ['OR' => $or];
                }
        }

        return $restrict;
    }

}
