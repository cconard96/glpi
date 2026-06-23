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

use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryFunction;
use Glpi\Error\ErrorHandler;
use Glpi\Plugin\Hooks;

/**
 * Saved search alerts
 **/
class SavedSearch_Alert extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype = SavedSearch::class;
    public static $items_id = 'savedsearches_id';
    public $dohistory       = true;

    public const OP_LESS     = 0;
    public const OP_LESSEQ   = 1;
    public const OP_EQ       = 2;
    public const OP_NOT      = 3;
    public const OP_GREATEQ  = 4;
    public const OP_GREAT    = 5;

    public static function getTypeName($nb = 0)
    {
        return _n('Saved search alert', 'Saved searches alerts', $nb);
    }

    /**
     * Get operators
     *
     * @param int $id ID for the operator to retrieve, or null for the full list
     *
     * @return string|array
     */
    public static function getOperators($id = null)
    {
        $ops = [
            self::OP_LESS     => '<',
            self::OP_LESSEQ   => '<=',
            self::OP_EQ       => '=',
            self::OP_NOT      => '!=',
            self::OP_GREATEQ  => '>=',
            self::OP_GREAT    => '>',
        ];
        return ($id === null ? $ops : $ops[$id]);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'send':
                return ['description' => __('Saved searches alerts')];
        }
        return [];
    }

    /**
     * Summary of saveContext
     *
     * Save $_SESSION and $CFG_GLPI into the returned array
     *
     * @return array[] which contains a copy of $_SESSION and $CFG_GLPI
     */
    private static function saveContext(): array
    {
        global $CFG_GLPI;
        $context = [];
        $context['$_SESSION'] = $_SESSION;
        $context['$CFG_GLPI'] = $CFG_GLPI;
        return $context;
    }

    /**
     * Summary of restoreContext
     *
     * restore former $_SESSION and $CFG_GLPI
     * to be sure that logs will be in GLPI default datetime and language
     * and that session is restored for the next crontaskaction
     *
     * @param array $context is the array returned by saveContext
     */
    private static function restoreContext(array $context): void
    {
        global $CFG_GLPI;
        $_SESSION = $context['$_SESSION'];
        $CFG_GLPI = $context['$CFG_GLPI'];
        Session::loadLanguage();
        Plugin::doHook(Hooks::INIT_SESSION);
    }

    /**
     * Send saved searches alerts
     *
     * @param CronTask $task CronTask instance
     *
     * @return int : <0 : need to run again, 0:nothing to do, >0:ok
     */
    public static function cronSavedSearchesAlerts($task)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => [
                'glpi_savedsearches_alerts.*',
            ],
            'FROM'   => self::getTable(),
            'LEFT JOIN' => [
                'glpi_alerts' => [
                    'FKEY'   => [
                        'glpi_alerts'                => 'items_id',
                        'glpi_savedsearches_alerts'  => 'id',
                        [
                            'AND' => [
                                'glpi_alerts.itemtype' => SavedSearch_Alert::class,
                                'glpi_alerts.type'     => Alert::PERIODICITY,
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_savedsearches_alerts.is_active' => true,
                'OR' => [
                    ['glpi_alerts.date' => null],
                    [
                        'glpi_alerts.date' => ['<',
                            QueryFunction::dateSub(
                                date: QueryFunction::now(),
                                interval: new QueryExpression($DB::quoteName('glpi_savedsearches_alerts.frequency')),
                                interval_unit: 'SECOND'
                            ),
                        ],
                    ],
                ],
            ],
        ]);

        if ($iterator->numrows()) {
            $savedsearch = new SavedSearch();

            if (!isset($_SESSION['glpiname'])) {
                //required from search class
                $_SESSION['glpiname'] = 'crontab';
            }

            // Will save $_SESSION and $CFG_GLPI cron context into an array
            $context = self::saveContext();

            foreach ($iterator as $row) {
                //execute saved search to get results
                try {
                    $savedsearch->getFromDB($row['savedsearches_id']);
                    if (isCommandLine()) {
                        //search requires a logged in user...
                        $user = new User();
                        $user->getFromDB($savedsearch->fields['users_id']);
                        $auth = new Auth();
                        $auth->user = $user;
                        $auth->auth_succeded = true;
                        Session::init($auth);
                    }

                    $count = null;
                    if ($data = $savedsearch->execute(true)) {
                        $count = (int) $data['data']['totalcount'];
                    } else {
                        $data = [];
                    }
                    $value = (int) $row['value'];

                    $notify = false;
                    $tr_op = null;

                    switch ($row['operator']) {
                        case self::OP_LESS:
                            $notify = $count < $value;
                            $tr_op = __('less than');
                            break;
                        case self::OP_LESSEQ:
                            $notify = $count <= $value;
                            $tr_op = __('less or equals than');
                            break;
                        case self::OP_EQ:
                            $notify = $count == $value;
                            $tr_op = __('equals to');
                            break;
                        case self::OP_NOT:
                            $notify = $count != $value;
                            $tr_op = __('not equals to');
                            break;
                        case self::OP_GREATEQ:
                            $notify = $count >= $value;
                            $tr_op = __('greater or equals than');
                            break;
                        case self::OP_GREAT:
                            $notify = $count > $value;
                            $tr_op = __('greater than');
                            break;
                        default:
                            throw new RuntimeException("Unknown operator '{$row['operator']}'");
                    }

                    //TRANS : %1$s is the name of the saved search,
                    //        %2$s is the comparison translated text
                    //        %3$s is the value compared to
                    $data['msg'] = sprintf(
                        __('Results count for %1$s is %2$s %3$s'),
                        $savedsearch->getName(),
                        $tr_op,
                        $value
                    );

                    // Will restore previously saved $_SESSION and $CFG_GLPI:
                    //  To be sure that logs will be in GLPI with default datetime and language
                    //  and that notifications are sent even if $_SESSION['glpinotification_to_myself'] is false
                    //  and to restore default cron $_SESSION and $CFG_GLPI global variables for next cron task
                    self::restoreContext($context);

                    if ($notify) {
                        $event = 'alert' . ($savedsearch->getField('is_private') ? '' : '_' . $savedsearch->getID());
                        $savedsearch_alert = new self();
                        $savedsearch_alert->getFromDB($row['id']);
                        $data['savedsearch'] = $savedsearch;
                        NotificationEvent::raiseEvent($event, $savedsearch_alert, $data);
                        $task->addVolume(1);

                        $alert = new Alert();
                        $alert->deleteByCriteria([
                            'itemtype' => SavedSearch_Alert::class,
                            'items_id' => $row['id'],
                        ], true);
                        $alert->add([
                            'type'     => Alert::PERIODICITY,
                            'itemtype' => SavedSearch_Alert::class,
                            'items_id' => $row['id'],
                        ]);
                    }
                } catch (Throwable $e) {
                    self::restoreContext($context);
                    ErrorHandler::logCaughtException($e);
                    ErrorHandler::displayCaughtExceptionMessage($e);
                }
            }
            return 1;
        }
        return 0;
    }

    public function getItemsForLog($itemtype, $items_id)
    {
        return ['new' => $this];
    }
}
