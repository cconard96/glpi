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

namespace Glpi\Dashboard;

use Dropdown;
use Glpi\Application\Environment;
use Glpi\Debug\Profiler;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Plugin\Hooks;
use Html;
use Item_Devices;
use Plugin;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Reminder;
use Session;
use Telemetry;
use Ticket;
use Toolbox;
use function Safe\json_encode;
use function Safe\preg_replace;

class Grid
{
    /** @var int */
    protected $cell_margin     = 3;
    /** @var int */
    protected $grid_cols       = 26;
    /** @var int */
    protected $grid_rows       = 24;
    /** @var string */
    protected $current         = "";
    /** @var Dashboard|null */
    protected $dashboard       = null;
    /** @var array */
    protected $items           = [];
    /** @var string */
    protected $context            = '';

    /** @var bool */
    public static $embed              = false;
    /** @var array */
    public static $all_dashboards     = [];

    public function __construct(string $dashboard_key = "central", int $grid_cols = 26, int $grid_rows = 24, string $context = 'core')
    {

        $this->current   = $dashboard_key;
        $this->grid_cols = $grid_cols;
        $this->grid_rows = $grid_rows;

        $this->dashboard = new Dashboard($dashboard_key);
        $this->context   = $context;
    }

    /**
     * Return the instance of current dasbhoard
     *
     * @return Dashboard
     */
    public function getDashboard()
    {
        return $this->dashboard;
    }

    /**
     * Return the context used for this Grid instance
     * @return string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * load all existing dashboards from DB into a static property for caching data
     *
     * @param bool $force, if false, don't use cache
     *
     * @return bool
     */
    public static function loadAllDashboards(bool $force = true): bool
    {
        if (
            !is_array(self::$all_dashboards)
            || count(self::$all_dashboards) === 0
            || $force
        ) {
            self::$all_dashboards = Dashboard::getAll($force, !self::$embed, '');
        }

        return is_array(self::$all_dashboards);
    }

    /**
     * Do we have the right to view at least one dashboard int the current collection
     *
     * @return bool
     */
    public function canViewCurrent(): bool
    {
        // check global (admin) right
        if (Dashboard::canView()) {
            return true;
        }

        return $this->dashboard->canViewCurrent();
    }

    /**
     * Do we have the right to view at least one dashboard?
     *
     * This can be optionally restricted to a specific context.
     *
     * @param ?string $context
     *
     * @return bool
     */
    public static function canViewOneDashboard($context = null): bool
    {
        if (Dashboard::canView()) {
            return true;
        }

        self::loadAllDashboards();

        $viewable = self::$all_dashboards;
        if ($context) {
            $viewable = array_filter(self::$all_dashboards, fn($dashboard) => $dashboard['context'] === $context);
        }
        return (count($viewable) > 0);
    }

    /**
     * Do we have the right to view the specified dashboard int the current collection
     *
     * @param string $key the dashboard to check
     * @param bool   $canViewAll Right to view all dashboards
     *
     * @return bool
     */
    public static function canViewSpecificicDashboard($key, $canViewAll = false): bool
    {
        self::loadAllDashboards();

        $dashboard = new Dashboard($key);
        $dashboard->load();
        // check global (admin) right
        if (Dashboard::canView() && !$dashboard->isPrivate()) {
            return true;
        }

        return isset(self::$all_dashboards[$key]);
    }

    /**
     * Init embed session
     *
     * @param array $params
     *
     * @return void
     */
    public function initEmbedSession(array $params = [])
    {
        // load minimal session
        Session::start();
        $_SESSION["glpiactive_entity"]           = $params['entities_id'];
        $_SESSION["glpiactive_entity_recursive"] = $params['is_recursive'];
        $_SESSION["glpiname"]                    = 'embed_dashboard';
        $_SESSION['glpigroups']                  = [];
        if ($params['is_recursive']) {
            $entities = getSonsOf("glpi_entities", $params['entities_id']);
        } else {
            $entities = [$params['entities_id']];
        }
        $_SESSION['glpiactiveentities']        = $entities;
        $_SESSION['glpiactiveentities_string'] = "'" . implode("', '", $entities) . "'";

        $_SESSION['glpi_use_mode'] = Session::NORMAL_MODE;
    }

    /**
     * Init embeded dashboard context.
     * This checks token validity to avoid displaying dashboard to invalid users
     * and initialize related session variables.
     *
     * @param array $params contains those keys:
     * - dashboard: the dashboard system name
     * - entities_id: entity to init in session
     * - is_recursive: do we need to display sub entities
     * - token: the token to check
     *
     * @return void (display)
     */
    public function initEmbed(array $params = [])
    {
        $defaults = [
            'dashboard'    => '',
            'entities_id'  => 0,
            'is_recursive' => 0,
            'token'        => '',
        ];
        $params = array_merge($defaults, $params);

        if (!self::checkToken($params)) {
            throw new AccessDeniedHttpException();
        }

        self::$embed = true;
        $this->initEmbedSession($params);
    }

    public static function getToken(string $dasboard = "", int $entities_id = 0, int $is_recursive = 0): string
    {
        $seed         = $dasboard . $entities_id . $is_recursive . Telemetry::getInstanceUuid();
        $uuid         = Uuid::uuid5(Uuid::NAMESPACE_OID, $seed);
        $token        = $uuid->toString();

        return $token;
    }

    /**
     * Check token variables (compare it to `dashboard`, `entities_id` and `is_recursive` paramater)
     *
     * @param array $params contains theses keys:
     * - dashboard: the dashboard system name
     * - entities_id: entity to init in session
     * - is_recursive: do we need to display sub entities
     * - token: the token to check
     *
     * @return bool
     */
    public static function checkToken(array $params = []): bool
    {
        $defaults = [
            'dashboard'    => '',
            'entities_id'  => 0,
            'is_recursive' => 0,
            'token'        => '',
        ];
        $params = array_merge($defaults, $params);

        $token = self::getToken(
            $params['dashboard'],
            $params['entities_id'],
            $params['is_recursive']
        );

        if ($token !== $params['token']) {
            return false;
        }

        return true;
    }

    /**
     * Return all itemtypes possible for constructing cards.
     * User in @see self::getAllDasboardCards
     *
     * @return array [itemtype1, itemtype2]
     */
    protected function getMenuItemtypes(): array
    {
        $menu_itemtypes = [];
        $exclude   = [
            'Config',
        ];

        $menu = Html::getMenuInfos();
        array_walk($menu, static function ($firstlvl) use (&$menu_itemtypes) {
            $key = $firstlvl['title'];
            if (isset($firstlvl['types'])) {
                $menu_itemtypes[$key] = array_merge($menu_itemtypes[$key] ?? [], $firstlvl['types']);
            }
        });

        foreach ($menu_itemtypes as &$firstlvl) {
            $firstlvl = array_filter($firstlvl, static function ($itemtype) use ($exclude) {
                if (
                    in_array($itemtype, $exclude)
                    || !is_subclass_of($itemtype, 'CommonDBTM')
                ) {
                    return false;
                }

                $testClass = new ReflectionClass($itemtype);
                return !$testClass->isAbstract();
            });
        }

        return $menu_itemtypes;
    }

    /**
     * Get cache key for the "getAllDasboardCards" function data.
     * The data will contain some translated strings and thus must be kept in a
     * separate cache entry for each languages
     *
     * @return string
     */
    public static function getAllDashboardCardsCacheKey(?string $language = null): string
    {
        if ($language === null) {
            $language = Session::getLanguage() ?? '';
        }

        return sprintf(
            'getAllDashboardCards_%s_%s',
            sha1(json_encode(Filter::getRegisteredFilterClasses())),
            $language
        );
    }

    /**
     * Construct catalog of all possible cards addable in a dashboard.
     *
     * @param bool $force Force rebuild the catalog of cards
     *
     * @return array
     */
    public function getAllDasboardCards($force = false): array
    {
        global $CFG_GLPI, $GLPI_CACHE;

        Profiler::getInstance()->start(__METHOD__);
        $cards = $GLPI_CACHE->get(self::getAllDashboardCardsCacheKey());

        if ($cards === null || $force) {
            $cards = [];
            $menu_itemtypes = $this->getMenuItemtypes();

            foreach ($menu_itemtypes as $firstlvl => $itemtypes) {
                foreach ($itemtypes as $itemtype) {
                    $clean_itemtype = str_replace('\\', '_', $itemtype);
                    $cards["bn_count_$clean_itemtype"] = [
                        'widgettype' => ["bigNumber"],
                        'group'      => $firstlvl,
                        'itemtype'   => "\\$itemtype",
                        'label'      => sprintf(__("Number of %s"), $itemtype::getTypeName()),
                        'provider'   => "Glpi\\Dashboard\\Provider::bigNumber$itemtype",
                        'filters'    => Filter::getAppliableFilters($itemtype::getTable()),
                    ];
                }
            }

            foreach (Item_Devices::getDeviceTypes() as $itemtype) {
                $fk_itemtype = $itemtype::getDeviceType();
                $label = sprintf(
                    __("Number of %s by type"),
                    $itemtype::getTypeName(Session::getPluralNumber()),
                    $fk_itemtype::getTypeName(),
                );

                $cards["count_" . $itemtype . "_" . $fk_itemtype] = [
                    'widgettype' => ['summaryNumbers', 'multipleNumber', 'pie', 'donut', 'halfpie', 'halfdonut', 'bar', 'hbar'],
                    'itemtype'   => "\\$itemtype",
                    'group'      =>  _n('Device', 'Devices', 1),
                    'label'      => $label,
                    'provider'   => "Glpi\\Dashboard\\Provider::multipleNumber" . $itemtype . "By" . $fk_itemtype,
                    'filters'    => Filter::getAppliableFilters($itemtype::getTable()),
                ];

                $clean_itemtype = str_replace('\\', '_', $itemtype);
                $cards["bn_count_$clean_itemtype"] = [
                    'widgettype' => ["bigNumber"],
                    'group'      => _n('Device', 'Devices', 1),
                    'itemtype'   => "\\$itemtype",
                    'label'      => sprintf(__("Number of %s"), $itemtype::getTypeName()),
                    'provider'   => "Glpi\\Dashboard\\Provider::bigNumber$itemtype",
                    'filters'    => Filter::getAppliableFilters($itemtype::getTable()),
                ];
            }

            foreach ($CFG_GLPI['device_types'] as $itemtype) {
                $clean_itemtype = str_replace('\\', '_', $itemtype);
                $cards["bn_count_$clean_itemtype"] = [
                    'widgettype' => ["bigNumber"],
                    'group'      => _n('Device', 'Devices', 1),
                    'itemtype'   => "\\$itemtype",
                    'label'      => sprintf(__("Number of type of %s"), $itemtype::getTypeName()),
                    'provider'   => "Glpi\\Dashboard\\Provider::bigNumber$itemtype",
                    'filters'    => Filter::getAppliableFilters($itemtype::getTable()),
                ];
            }

            // add multiple width for Assets itemtypes grouped by their foreign keys
            $assets = array_merge($CFG_GLPI['asset_types'], ['Software']);
            foreach ($assets as $itemtype) {
                $fk_itemtypes = [
                    'State',
                    'Entity',
                    'Manufacturer',
                    'Location',
                ];

                if (class_exists($itemtype . 'Type')) {
                    $fk_itemtypes[] = $itemtype . 'Type';
                }
                if (class_exists($itemtype . 'Model')) {
                    $fk_itemtypes[] = $itemtype . 'Model';
                }

                foreach ($fk_itemtypes as $fk_itemtype) {
                    $label = sprintf(
                        __("%s by %s"),
                        $itemtype::getTypeName(Session::getPluralNumber()),
                        $fk_itemtype::getTypeName()
                    );

                    $cards["count_" . $itemtype . "_" . $fk_itemtype] = [
                        'widgettype' => ['summaryNumbers', 'multipleNumber', 'pie', 'donut', 'halfpie', 'halfdonut', 'bar', 'hbar'],
                        'itemtype'   => "\\Computer",
                        'group'      => _n('Asset', 'Assets', Session::getPluralNumber()),
                        'label'      => $label,
                        'provider'   => "Glpi\\Dashboard\\Provider::multipleNumber" . $itemtype . "By" . $fk_itemtype,
                        'filters'    => Filter::getAppliableFilters($itemtype::getTable()),
                    ];
                }
            }

            $tickets_cases = [
                'late'               => __("Late tickets"),
                'waiting_validation' => __("Tickets waiting for your approval"),
                'notold'             => __('Not solved tickets'),
                'incoming'           => __("New tickets"),
                'waiting'            => __('Pending tickets'),
                'assigned'           => __('Assigned tickets'),
                'planned'            => __('Planned tickets'),
                'solved'             => __('Solved tickets'),
                'closed'             => __('Closed tickets'),
            ];
            foreach ($tickets_cases as $case => $label) {
                $cards["bn_count_tickets_$case"] = [
                    'widgettype' => ["bigNumber"],
                    'itemtype'   => "\\Ticket",
                    'group'      => __('Assistance'),
                    'label'      => sprintf(__("Number of %s"), $label),
                    'provider'   => "Glpi\\Dashboard\\Provider::nbTicketsGeneric",
                    'args'       => [
                        'case'   => $case,
                        'params' => [
                            'validation_check_user' => true,
                        ],
                    ],
                    'cache'      => false,
                    'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
                ];

                $cards["table_count_tickets_$case"] = [
                    'widgettype' => ["searchShowList"],
                    'itemtype'   => "\\Ticket",
                    'group'      => __('Assistance'),
                    'label'      => sprintf(__("List of %s"), $label),
                    'provider'   => "Glpi\\Dashboard\\Provider::nbTicketsGeneric",
                    'args'       => [
                        'case'   => $case,
                        'params' => [
                            'validation_check_user' => true,
                        ],
                    ],
                    'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
                ];
            }

            // add specific ticket's cases
            $cards["nb_opened_ticket"] = [
                'widgettype' => ['line', 'area', 'bar'],
                'itemtype'   => "\\Ticket",
                'group'      => __('Assistance'),
                'label'      => __("Number of tickets by month"),
                'provider'   => "Glpi\\Dashboard\\Provider::ticketsOpened",
                'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
            ];

            $cards["ticket_evolution"] = [
                'widgettype' => ['lines', 'areas', 'bars', 'stackedbars'],
                'itemtype'   => "\\Ticket",
                'group'      => __('Assistance'),
                'label'      => __("Evolution of ticket in the past year"),
                'provider'   => "Glpi\\Dashboard\\Provider::getTicketsEvolution",
                'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
            ];

            $cards["ticket_status"] = [
                'widgettype' => ['lines', 'areas', 'bars', 'stackedbars'],
                'itemtype'   => "\\Ticket",
                'group'      => __('Assistance'),
                'label'      => __("Tickets status by month"),
                'provider'   => "Glpi\\Dashboard\\Provider::getTicketsStatus",
                'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
            ];

            $cards["ticket_times"] = [
                'widgettype' => ['lines', 'areas', 'bars', 'stackedbars'],
                'itemtype'   => "\\Ticket",
                'group'      => __('Assistance'),
                'label'      => __("Tickets times (in hours)"),
                'provider'   => "Glpi\\Dashboard\\Provider::averageTicketTimes",
                'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
            ];

            $cards["tickets_summary"] = [
                'widgettype' => ['summaryNumbers', 'multipleNumber', 'bar', 'hbar'],
                'itemtype'   => "\\Ticket",
                'group'      => __('Assistance'),
                'label'      => __("Tickets summary"),
                'provider'   => "Glpi\\Dashboard\\Provider::getTicketSummary",
                'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
            ];

            $case = '';
            $cards["bn_count_tickets_expired_by_tech"] = [
                'widgettype' => ['hBars', 'stackedHBars'],
                'itemtype'   => "\\Ticket",
                'group'      => __('Assistance'),
                'label'      => __("Number of tickets by SLA status and technician"),
                'provider'   => "Glpi\\Dashboard\\Provider::nbTicketsByAgreementStatusAndTechnician",
                'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
            ];

            $cards["bn_count_tickets_expired_by_tech_group"] = [
                'widgettype' => ['hBars', 'stackedHBars'],
                'itemtype'   => "\\Ticket",
                'group'      => __('Assistance'),
                'label'      => __("Number of tickets by SLA status and technician group"),
                'provider'   => "Glpi\\Dashboard\\Provider::nbTicketsByAgreementStatusAndTechnicianGroup",
                'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
            ];

            foreach (
                [
                    'ITILCategory' => __("Top ticket's categories"),
                    'Entity'       => __("Top ticket's entities"),
                    'RequestType'  => __("Top ticket's request sources"),
                    'Location'     => __("Top ticket's locations"),
                ] as $itemtype => $label
            ) {
                $cards["top_ticket_$itemtype"] = [
                    'widgettype' => ['summaryNumbers', 'pie', 'donut', 'halfpie', 'halfdonut', 'multipleNumber', 'bar', 'hbar'],
                    'itemtype'   => "\\Ticket",
                    'group'      => __('Assistance'),
                    'label'      => $label,
                    'provider'   => "Glpi\\Dashboard\\Provider::multipleNumberTicketBy$itemtype",
                    'filters'    => Filter::getAppliableFilters($itemtype::getTable()),
                ];
            }

            foreach (
                [
                    'user_requester'  => __("Top ticket's requesters"),
                    'group_requester' => __("Top ticket's requester groups"),
                    'user_observer'   => __("Top ticket's observers"),
                    'group_observer'  => __("Top ticket's observer groups"),
                    'user_assign'     => __("Top ticket's assignees"),
                    'group_assign'    => __("Top ticket's assignee groups"),
                ] as $type => $label
            ) {
                $cards["top_ticket_$type"] = [
                    'widgettype' => ['pie', 'donut', 'halfpie', 'halfdonut', 'summaryNumbers', 'multipleNumber', 'bar', 'hbar'],
                    'itemtype'   => "\\Ticket",
                    'group'      => __('Assistance'),
                    'label'      => $label,
                    'provider'   => "Glpi\\Dashboard\\Provider::nbTicketsActor",
                    'args'       => [
                        'case' => $type,
                    ],
                    'filters'    => Filter::getAppliableFilters(Ticket::getTable()),
                ];
            }

            $cards["RemindersList"] = [
                'widgettype' => ["articleList"],
                'label'      => sprintf(__('List of %s'), Reminder::getTypeName(Session::getPluralNumber())),
                'group'      => __('Tools'),
                'provider'   => "Glpi\\Dashboard\\Provider::getArticleListReminder",
                'filters'    => Filter::getAppliableFilters(Reminder::getTable()),
            ];

            $cards["markdown_editable"] = [
                'widgettype'   => ["markdown"],
                'label'        => __("Editable markdown card"),
                'group'        => __('Others'),
                'card_options' => [
                    'content' => __("Toggle edit mode to edit content"),
                ],
            ];

            if (!Environment::get()->shouldExpectResourcesToChange()) {
                // Do not cache dashboard cards on `development` envs
                $GLPI_CACHE->set(self::getAllDashboardCardsCacheKey(), $cards);
            }
        }

        $more_cards = Plugin::doHookFunction(Hooks::DASHBOARD_CARDS);
        if (is_array($more_cards)) {
            $cards = array_merge($cards, $more_cards);
        }
        Profiler::getInstance()->stop(__METHOD__);

        return $cards;
    }

    /**
     * @param string $interface
     *
     * @return array
     */
    public function getRights($interface = 'central')
    {
        return [
            READ   => __('Read'),
            UPDATE => __('Update'),
            CREATE => __('Create'),
            PURGE  => [
                'short' => __('Purge'),
                'long'  => _x('button', 'Delete permanently'),
            ],
        ];
    }

    /**
     * Restore last viewed dashboard
     *
     * @return string the dashboard key
     */
    public function restoreLastDashboard(): string
    {
        global $CFG_GLPI;
        $new_key = "";
        $target = Toolbox::cleanTarget($_REQUEST['_target'] ?? $_SERVER['REQUEST_URI'] ?? "");
        if (isset($_SESSION['last_dashboards']) && $target !== '') {
            $target = preg_replace('/^' . preg_quote($CFG_GLPI['root_doc'], '/') . '/', '', $target);
            if (!isset($_SESSION['last_dashboards'][$target])) {
                return "";
            }

            $new_key   = $_SESSION['last_dashboards'][$target];
            $dashboard = new Dashboard($new_key);
            if (!$dashboard->canViewCurrent()) {
                return "";
            }

            $this->current = $new_key;
        }

        return $new_key;
    }

    /**
     * Retrieve the default dashboard for a specific menu entry
     * First try from session
     * then on config
     * And Fallback on the first dashboard found
     *
     * @param string $menu
     * @param bool $strict if true, do not provide a fallback
     *
     * @return string the dashboard key
     */
    public static function getDefaultDashboardForMenu(string $menu = "", bool $strict = false): string
    {
        global $CFG_GLPI;

        $grid = new self();
        // Try loading default from user preferences
        $config_key = 'default_dashboard_' . $menu;
        $default    = $_SESSION["glpi$config_key"] ?? "";
        if (strlen($default)) {
            // If default is "disabled", return empty string and skip default value from config
            if ($default == 'disabled') {
                return "";
            }

            $dasboard = new Dashboard($default);

            if ($dasboard->load() && $dasboard->canViewCurrent()) {
                return $default;
            }
        }

        // Try loading default from config
        $default = $CFG_GLPI[$config_key] ?? "";
        if (strlen($default)) {
            $dasboard = new Dashboard($default);

            if ($dasboard->load() && $dasboard->canViewCurrent()) {
                return $default;
            }
        }

        // if default not found, return first dashboard
        if (!$strict) {
            self::loadAllDashboards();
            $dashboards = $menu === 'mini_ticket'
                ? self::$all_dashboards
                : array_filter(self::$all_dashboards, static fn($d) => $d['key'] !== 'mini_tickets');

            return array_shift($dashboards)['key'] ?? "";
        }

        return "";
    }


    public static function dropdownDashboard(string $name = "", array $params = [], bool $disabled_option = false): string
    {
        $to_show = Dashboard::getAll(false, true, $params['context'] ?? 'core');
        $can_view_all = $params['can_view_all'] ?? false;

        $options_dashboards = [];
        foreach ($to_show as $key => $dashboard) {
            if (self::canViewSpecificicDashboard($key, $can_view_all)) {
                $options_dashboards[$key] = $dashboard['name'] ?? $key;
            }
        }

        if ($disabled_option) {
            $options_dashboards = ['disabled' => __('Disabled')] + $options_dashboards;
        }

        return Dropdown::showFromArray($name, $options_dashboards, $params);
    }
}
