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

use Glpi\Plugin\Hooks;
use Glpi\Toolbox\ArrayNormalizer;
use Symfony\Component\HttpFoundation\Request;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\parse_url;
use function Safe\preg_match;
use function Safe\preg_replace;

/**
 *  Config class
 **/
class Config extends CommonDBTM
{
    public const DELETE_ALL = -1;
    public const KEEP_ALL = 0;

    public const UNIT_MANAGEMENT = 0;
    public const GLOBAL_MANAGEMENT = 1;
    public const NO_MANAGEMENT = 2;

    public const TIMELINE_ACTION_BTN_MERGED = 0;
    public const TIMELINE_ACTION_BTN_SPLITTED = 1;

    public const TIMELINE_RELATIVE_DATE = 0;
    public const TIMELINE_ABSOLUTE_DATE = 1;

    // From CommonDBTM
    public $auto_message_on_action = false;

    public static $rightname              = 'config';

    public static $undisclosedFields      = [
        'proxy_passwd',
        'smtp_passwd',
        'smtp_oauth_client_id',
        'smtp_oauth_client_secret',
        'smtp_oauth_options',
        'smtp_oauth_refresh_token',
        'glpinetwork_registration_key',
        'ldap_pass', // this one should not exist anymore, but may be present when admin restored config dump after migration
    ];

    /** @var string[] */
    public static $saferUndisclosedFields = ['admin_email', 'replyto_email'];

    /**
     * Indicates whether the GLPI configuration has been loaded.
     * @var bool
     */
    private static $loaded = false;

    public static function getTypeName($nb = 0)
    {
        return __('Setup');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public function canViewItem(): bool
    {
        if (
            isset($this->fields['context'])
            && (
                in_array($this->fields['context'], ['core', 'inventory'], true) // GLPI config contexts
                || Plugin::isPluginActive($this->fields['context'])
            )
        ) {
            return true;
        }
        return false;
    }

    public function prepareInputForUpdate($input)
    {
        global $CFG_GLPI;

        // Unset _no_history to not save it as a configuration value
        unset($input['_no_history']);

        // Update only an item
        if (isset($input['context'])) {
            return $input;
        }

        // Process configuration for plugins
        if (!empty($input['config_context'])) {
            $config_context = $input['config_context'];
            unset($input['id']);
            unset($input['_glpi_csrf_token']);
            unset($input['_update']);
            unset($input['config_context']);
            if (
                (!empty($input['config_class']))
                && (class_exists($input['config_class']))
                && (method_exists($input['config_class'], 'configUpdate'))
            ) {
                $config_method = $input['config_class'] . '::configUpdate';
                unset($input['config_class']);
                $input = call_user_func($config_method, $input);
            }
            static::setConfigurationValues($config_context, $input);
            return false;
        }

        // Trim automatically ending slash for url_base config as, for all existing occurrences,
        // this URL will be prepended to something that starts with a slash.
        if (isset($input["url_base"]) && !empty($input["url_base"])) {
            if (Toolbox::isValidWebUrl($input["url_base"])) {
                $input["url_base"] = rtrim($input["url_base"], '/');
            } else {
                Session::addMessageAfterRedirect(__s('Invalid base URL!'), false, ERROR);
                return false;
            }
        }

        if (isset($input['ssologout_url']) && !empty($input['ssologout_url'])) {
            if (!Toolbox::isValidWebUrl($input['ssologout_url'])) {
                Session::addMessageAfterRedirect(__s('Invalid SSO logout URL.'), false, ERROR);
                return false;
            }
        }

        $input = $this->handleSmtpInput($input);

        if (isset($input["proxy_passwd"]) && empty($input["proxy_passwd"])) {
            unset($input["proxy_passwd"]);
        }
        if (isset($input["_blank_proxy_passwd"]) && $input["_blank_proxy_passwd"]) {
            $input['proxy_passwd'] = '';
        }

        // Manage DB Slave process
        if (isset($input['_dbslave_status'])) {
            $already_active = DBConnection::isDBSlaveActive();

            if ($input['_dbslave_status']) {
                DBConnection::changeCronTaskStatus(true);

                if (!$already_active) {
                    // Activate Slave from the "system" tab
                    DBConnection::createDBSlaveConfig();
                } elseif (isset($input["_dbreplicate_dbhost"])) {
                    // Change parameter from the "replicate" tab
                    DBConnection::saveDBSlaveConf(
                        $input["_dbreplicate_dbhost"],
                        $input["_dbreplicate_dbuser"],
                        $input["_dbreplicate_dbpassword"],
                        $input["_dbreplicate_dbdefault"]
                    );
                }
            }

            if (!$input['_dbslave_status'] && $already_active) {
                DBConnection::deleteDBSlaveConfig();
                DBConnection::changeCronTaskStatus(false);
            }
        }

        // Matrix for Impact / Urgence / Priority
        if (isset($input['_matrix'])) {
            $tab = [];

            for ($urgency = 1; $urgency <= 5; $urgency++) {
                for ($impact = 1; $impact <= 5; $impact++) {
                    $priority               = $input["_matrix_{$urgency}_{$impact}"];
                    $tab[$urgency][$impact] = $priority;
                }
            }

            $input['priority_matrix'] = exportArrayToDB($tab);
            $input['urgency_mask']    = 0;
            $input['impact_mask']     = 0;

            for ($i = 1; $i <= 5; $i++) {
                if ($input["_urgency_{$i}"]) {
                    $input['urgency_mask'] += (1 << $i);
                }

                if ($input["_impact_{$i}"]) {
                    $input['impact_mask'] += (1 << $i);
                }
            }
        }

        if (isset($input['devices_in_menu'])) {
            $input['devices_in_menu'] = exportArrayToDB(
                ArrayNormalizer::normalizeValues($input['devices_in_menu'] ?: [], 'strval')
            );
        }

        if (isset($input['proxy_exclusions'])) {
            $input['proxy_exclusions'] = exportArrayToDB(
                ArrayNormalizer::normalizeValues($input['proxy_exclusions'] ?: [], 'strval')
            );
        }

        // lock mechanism update
        if (isset($input['lock_use_lock_item']) && isset($input['lock_item_list'])) {
            $input['lock_item_list'] = exportArrayToDB(
                ArrayNormalizer::normalizeValues($input['lock_item_list'] ?: [], 'strval')
            );
        }

        if (isset($input[Impact::CONF_ENABLED])) {
            $input[Impact::CONF_ENABLED] = exportArrayToDB(
                ArrayNormalizer::normalizeValues($input[Impact::CONF_ENABLED] ?: [], 'strval')
            );
        }

        if (isset($input['planning_work_days'])) {
            $input['planning_work_days'] = exportArrayToDB(
                ArrayNormalizer::normalizeValues($input['planning_work_days'] ?: [], 'intval')
            );
        }

        // Beware : with new management system, we must update each value
        unset($input['id']);
        unset($input['_glpi_csrf_token']);
        unset($input['_update']);

        // Add skipMaintenance if maintenance mode update
        if (isset($input['maintenance_mode']) && $input['maintenance_mode']) {
            $_SESSION['glpiskipMaintenance'] = 1;
            $url = htmlescape($CFG_GLPI['root_doc'] . "/index.php?skipMaintenance=1");
            Session::addMessageAfterRedirect(
                sprintf(
                    __s('Maintenance mode activated. Backdoor using: %s'),
                    "<a href='$url'>$url</a>"
                ),
                false,
                WARNING
            );
        }

        // Automatically trim whitespaces around registration key.
        if (array_key_exists('glpinetwork_registration_key', $input) && !empty($input['glpinetwork_registration_key'])) {
            $input['glpinetwork_registration_key'] = trim($input['glpinetwork_registration_key']);
        }

        // Prevent invalid profile to be set as the lock profile.
        // User updating the config from GLPI's UI should not be able to send
        // invalid values but API or manual HTTP requests might be invalid.
        if (isset($input['lock_lockprofile_id'])) {
            $profile = Profile::getById($input['lock_lockprofile_id']);
            if (!$profile || $profile->fields['interface'] !== 'central') {
                // Invalid profile
                Session::addMessageAfterRedirect(
                    __s("The specified profile doesn't exist or is not allowed to access the central interface."),
                    false,
                    ERROR
                );
                unset($input['lock_lockprofile_id']);
            }
        }

        // Check the validity of `pdffont`
        if (isset($input['pdffont']) && !in_array($input['pdffont'], array_keys(GLPIPDF::getFontList()), true)) {
            Session::addMessageAfterRedirect(
                sprintf(
                    __s('The following field has an incorrect value: "%s".'),
                    __s('PDF export font')
                ),
                false,
                ERROR
            );
            unset($input['pdffont']);
        }

        $tfa_enforced_changed = isset($input['2fa_enforced']) && $input['2fa_enforced'] !== $CFG_GLPI['2fa_enforced'];
        $tfa_grace_days_changed = isset($input['2fa_grace_days']) && $input['2fa_grace_days'] !== $CFG_GLPI['2fa_grace_days'];
        if ($tfa_grace_days_changed || $tfa_enforced_changed) {
            $enforced = $input['2fa_enforced'] ?? $CFG_GLPI['2fa_enforced'];
            $grace_period = $input['2fa_grace_days'] ?? $CFG_GLPI['2fa_grace_days'];
            if ($enforced && $grace_period > 0) {
                $input['2fa_grace_date_start'] = $_SESSION['glpi_currenttime'];
            } else {
                $input['2fa_grace_date_start'] = null;
            }
        }

        // Prevent some input values to be saved in DB
        $values_to_filter = [
            '_dbslave_status',
            '_dbreplicate_dbhost',
            '_dbreplicate_dbuser',
            '_dbreplicate_dbpassword',
            '_dbreplicate_dbdefault',
        ];

        $input = array_filter($input, fn($key) => !in_array($key, $values_to_filter), ARRAY_FILTER_USE_KEY);

        static::setConfigurationValues('core', $input);

        return false;
    }

    /**
     * Handle SMTP input values.
     *
     * @param array $input
     *
     * @return array
     */
    private function handleSmtpInput(array $input): array
    {
        global $CFG_GLPI;

        if (array_key_exists('smtp_mode', $input) && in_array($input['smtp_mode'], [MAIL_SMTPSSL, MAIL_SMTPTLS], true)) {
            $input['smtp_mode'] = MAIL_SMTP;
            Toolbox::deprecated('Usage of "MAIL_SMTPSSL" and "MAIL_SMTPTLS" SMTP mode is deprecated. Switch to "MAIL_SMTP" mode.');
        }

        if (isset($input['smtp_passwd']) && empty($input['smtp_passwd'])) {
            unset($input['smtp_passwd']);
        }

        if (isset($input["_blank_smtp_passwd"]) && $input["_blank_smtp_passwd"]) {
            $input['smtp_passwd'] = '';
        }

        if (array_key_exists('smtp_mode', $input) && (int) $input['smtp_mode'] === MAIL_SMTPOAUTH) {
            $input['smtp_check_certificate'] = 1;
            $input['smtp_passwd']          = '';

            if (array_key_exists('smtp_oauth_client_secret', $input) && $input['smtp_oauth_client_secret'] === '') {
                // form does not contains existing password value for security reasons
                // prevent password to be overriden by an empty value
                unset($input['smtp_oauth_client_secret']);
            }

            if (array_key_exists('smtp_oauth_options', $input)) {
                if (is_array($input['smtp_oauth_options'])) {
                    $input['smtp_oauth_options'] = json_encode($input['smtp_oauth_options']);
                } else {
                    $input['smtp_oauth_options'] = '';
                }
            }

            $has_oauth_settings_changed = (array_key_exists('smtp_oauth_provider', $input) && $input['smtp_oauth_provider'] !== $CFG_GLPI['smtp_oauth_provider'])
                || (array_key_exists('smtp_oauth_client_id', $input) && $input['smtp_oauth_client_id'] !== $CFG_GLPI['smtp_oauth_client_id'])
                || (array_key_exists('smtp_oauth_options', $input) && $input['smtp_oauth_options'] !== $CFG_GLPI['smtp_oauth_options']);

            if ($has_oauth_settings_changed) {
                // clean credentials, they will have to be replaced by new ones
                $input['smtp_oauth_refresh_token'] = '';
                $input['smtp_username']            = '';
            }

            // remember whether the SMTP Oauth flow has to be triggered
            $_SESSION['redirect_to_smtp_oauth'] = (bool) ($input['_force_redirect_to_smtp_oauth'] ?? false) === true
                || $has_oauth_settings_changed
                || (string) $CFG_GLPI['smtp_oauth_refresh_token'] === '';

            // ensure value is not saved in DB
            unset($input['_force_redirect_to_smtp_oauth']);
        } elseif (array_key_exists('smtp_mode', $input) && (int) $input['smtp_mode'] !== MAIL_SMTPOAUTH) {
            // clean oauth related information
            $input['smtp_oauth_provider'] = '';
            $input['smtp_oauth_client_id'] = '';
            $input['smtp_oauth_client_secret'] = '';
            $input['smtp_oauth_options'] = '{}';
            $input['smtp_oauth_refresh_token'] = '';
        }

        return $input;
    }

    public static function unsetUndisclosedFields(&$fields)
    {
        if (isset($fields['context']) && isset($fields['name'])) {
            if (
                $fields['context'] == 'core'
                && in_array($fields['name'], self::$undisclosedFields)
            ) {
                unset($fields['value']);
            } else {
                $fields = Plugin::doHookFunction(Hooks::UNDISCLOSED_CONFIG_VALUE, $fields);
            }
        }
    }

    /**
     * Check if the "use_password_security" parameter is enabled
     *
     * @return bool
     */
    public static function arePasswordSecurityChecksEnabled(): bool
    {
        global $CFG_GLPI;

        return $CFG_GLPI["use_password_security"];
    }

    /**
     * Get language in GLPI associated with the value coming from LDAP/SSO
     * Value can be, for example : English, en_EN, en-EN or en
     *
     * @param string $lang the value coming from LDAP/SSO
     *
     * @return string locale's php page in GLPI or '' is no language associated with the value
     **/
    public static function getLanguage($lang)
    {
        global $CFG_GLPI;

        // Alternative language code: en-EN --> en_EN
        $altLang = str_replace("-", "_", $lang);

        // Search in order : ID or extjs dico or tinymce dico / native lang / english name
        //                   / extjs dico / tinymce dico
        // ID  or extjs dico or tinymce dico
        foreach ($CFG_GLPI["languages"] as $ID => $language) {
            if (
                (strcasecmp($lang, $ID) == 0)
                || (strcasecmp($altLang, $ID) == 0)
                || (strcasecmp($lang, $language[2]) == 0)
                || (strcasecmp($lang, $language[3]) == 0)
            ) {
                return $ID;
            }
        }

        // native lang
        foreach ($CFG_GLPI["languages"] as $ID => $language) {
            if (strcasecmp($lang, $language[0]) == 0) {
                return $ID;
            }
        }

        // english lang name
        foreach ($CFG_GLPI["languages"] as $ID => $language) {
            if (strcasecmp($lang, $language[4]) == 0) {
                return $ID;
            }
        }

        return "";
    }

    /**
     * Display database engine checks report
     *
     * @since 9.3
     *
     * @param bool $fordebug display for debug (no html required) (false by default)
     * @param string  $version  Version to check (mainly from install), defaults to null
     *
     * @return int 2: missing extension,  1: missing optional extension, 0: OK,
     **/
    public static function displayCheckDbEngine($fordebug = false, $version = null)
    {
        global $CFG_GLPI;

        $error = 0;
        $result = self::checkDbEngine($version);
        $version = key($result);
        $db_ver = $result[$version];

        $ok_message = sprintf(__s('Database version seems correct (%s) - Perfect!'), $version);
        $ko_message = sprintf(__s('Your database engine version seems too old: %s.'), $version);

        if (!$db_ver) {
            $error = 2;
        }
        $message = $error > 0 ? $ko_message : $ok_message;

        if (isCommandLine()) {
            echo $message . "\n";
        } else {
            $img = "<img src='" . htmlescape($CFG_GLPI['root_doc']) . "/pics/";
            $img .= ($error > 0 ? "ko_min" : "ok_min") . ".png' alt='" . htmlescape($message) . "' title='" . htmlescape($message) . "'/>";

            if ($fordebug) {
                echo $img . htmlescape($message) . "\n";
            } else {
                $html = "<td";
                if ($error > 0) {
                    $html .= " class='red'";
                }
                $html .= ">";
                $html .= $img;
                $html .= '</td>';
                echo $html;
            }
        }
        return $error;
    }

    /**
     * Check for needed extensions
     *
     * @since 9.3
     *
     * @param string $raw Raw version to check (mainly from install), defaults to null
     *
     * @return array
     **/
    public static function checkDbEngine($raw = null)
    {
        if ($raw === null) {
            global $DB;
            $raw = $DB->getVersion();
        }

        $server  = preg_match('/-MariaDB/', $raw) ? 'MariaDB' : 'MySQL';
        $version = preg_replace('/^((\d+\.?)+).*$/', '$1', $raw);

        // MySQL >= 8.0 || MariaDB >= 10.6
        $is_supported = $server === 'MariaDB'
            ? version_compare($version, '10.6', '>=')
            : version_compare($version, '8.0', '>=');

        return [$version => $is_supported];
    }

    /**
     * Check for needed extensions
     *
     * @since 9.2 Method signature and return has changed
     *
     * @param null|array $list     Extensions list (from plugins)
     *
     * @return array [
     *                'error'     => integer 2: missing extension,  1: missing optionnal extension, 0: OK,
     *                'good'      => [ext => message],
     *                'missing'   => [ext => message],
     *                'may'       => [ext => message]
     *               ]
     **/
    public static function checkExtensions($list = null)
    {
        if ($list === null) {
            $extensions_to_check = [
                'mysqli'   => [
                    'required'  => true,
                ],
                'fileinfo' => [
                    'required'  => true,
                    'class'     => 'finfo',
                ],
                'json'     => [
                    'required'  => true,
                    'function'  => 'json_encode',
                ],
                'zlib'     => [
                    'required'  => true,
                ],
                'curl'      => [
                    'required'  => true,
                ],
                'gd'       => [
                    'required'  => true,
                ],
                'simplexml' => [
                    'required'  => true,
                ],
                'bcmath' => [
                    'required'  => true,
                ],
                //to sync/connect from LDAP
                'ldap'       => [
                    'required'  => false,
                ],
                //to enhance perfs
                'Zend OPcache' => [
                    'required'  => false,
                ],
                //for CAS lib
                'CAS'     => [
                    'required' => false,
                    'class'    => 'phpCAS',
                ],
                'exif' => [
                    'required'  => false,
                ],
                'intl' => [
                    'required' => true,
                ],
                'sodium' => [
                    'required' => false,
                ],
            ];
        } else {
            $extensions_to_check = $list;
        }

        $report = [
            'error'     => 0,
            'good'      => [],
            'missing'   => [],
            'may'       => [],
        ];

        //check for PHP extensions
        foreach ($extensions_to_check as $ext => $params) {
            $success = true;

            if (isset($params['call'])) {
                $success = call_user_func($params['call']);
            } elseif (isset($params['function'])) {
                if (!function_exists($params['function'])) {
                    $success = false;
                }
            } elseif (isset($params['class'])) {
                if (!class_exists($params['class'])) {
                    $success = false;
                }
            } else {
                if (!extension_loaded($ext)) {
                    $success = false;
                }
            }

            if ($success) {
                $msg = sprintf(__('%s extension is installed'), $ext);
                $report['good'][$ext] = $msg;
            } else {
                if (isset($params['required']) && $params['required'] === true) {
                    if ($report['error'] < 2) {
                        $report['error'] = 2;
                    }
                    $msg = sprintf(__('%s extension is missing'), $ext);
                    $report['missing'][$ext] = $msg;
                } else {
                    if ($report['error'] < 1) {
                        $report['error'] = 1;
                    }
                    $msg = sprintf(__('%s extension is not present'), $ext);
                    $report['may'][$ext] = $msg;
                }
            }
        }

        return $report;
    }

    /**
     * Get config values
     *
     * @since 0.85
     *
     * @param string $context context to get values (default for glpi is core)
     * @param array  $names   config names to get
     *
     * @return array of config values
     **/
    public static function getConfigurationValues($context, array $names = [])
    {
        global $DB;

        $query = [
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'context'   => $context,
            ],
        ];

        if (count($names) > 0) {
            $query['WHERE']['name'] = $names;
        }

        $iterator = $DB->request($query);
        $result = [];
        foreach ($iterator as $line) {
            $result[$line['name']] = $line['value'];
        }
        return $result;
    }

    /**
     * Get config value
     *
     * @param $context  string   context to get values (default for glpi is core)
     * @param $name     string   config name
     *
     * @return mixed
     *
     * @since 10.0.0
     */
    public static function getConfigurationValue(string $context, string $name)
    {
        return self::getConfigurationValues($context, [$name])[$name] ?? null;
    }

    /**
     * Load legacy configuration into $CFG_GLPI global variable.
     *
     * @return bool True for success, false if an error occurred
     *
     * @since 10.0.0 Parameter $older_to_latest is no longer used.
     */
    public static function loadLegacyConfiguration()
    {
        global $CFG_GLPI, $DB;

        // Compute URLs base path.
        $root_doc = '';
        if (isset($_SERVER['REQUEST_URI'])) {
            // $_SERVER['REQUEST_URI'] is set, meaning that GLPI is accessed from web server.
            $root_doc = Request::createFromGlobals()->getBasePath();
        }
        $CFG_GLPI['root_doc'] = $root_doc;
        $CFG_GLPI['typedoc_icon_dir'] = $root_doc . '/pics/icones';

        if (
            !DBConnection::isDbAvailable()
            || !$DB->tableExists('glpi_configs')
        ) {
            return false;
        }

        $iterator = $DB->request(['FROM' => 'glpi_configs']);

        if ($iterator->count() === 0) {
            return false;
        }

        $values = [];
        $allowed_context = ['core', 'inventory'];
        foreach ($iterator as $row) {
            if (!in_array($row['context'], $allowed_context)) {
                continue;
            }
            $values[$row['name']] = $row['value'];
        }

        $CFG_GLPI = array_merge($CFG_GLPI, $values);

        if (isset($CFG_GLPI['priority_matrix'])) {
            $CFG_GLPI['priority_matrix'] = importArrayFromDB($CFG_GLPI['priority_matrix']);
        }

        if (isset($CFG_GLPI['devices_in_menu'])) {
            $CFG_GLPI['devices_in_menu'] = importArrayFromDB($CFG_GLPI['devices_in_menu']);
        }

        if (isset($CFG_GLPI['lock_item_list'])) {
            $CFG_GLPI['lock_item_list'] = importArrayFromDB($CFG_GLPI['lock_item_list']);
        }

        if (isset($CFG_GLPI['proxy_exclusions'])) {
            $CFG_GLPI['proxy_exclusions'] = importArrayFromDB($CFG_GLPI['proxy_exclusions']);
        } else {
            $CFG_GLPI['proxy_exclusions'] = [];
        }

        if (
            isset($CFG_GLPI['lock_lockprofile_id'])
            && $CFG_GLPI['lock_use_lock_item']
            && $CFG_GLPI['lock_lockprofile_id'] > 0
            && !isset($CFG_GLPI['lock_lockprofile'])
        ) {
            $prof = new Profile();
            $prof->getFromDB($CFG_GLPI['lock_lockprofile_id']);
            $prof->cleanProfile();
            $CFG_GLPI['lock_lockprofile'] = $prof->fields;
        }

        if (isset($CFG_GLPI['planning_work_days'])) {
            $CFG_GLPI['planning_work_days'] = importArrayFromDB($CFG_GLPI['planning_work_days']);
        }

        if (isset($CFG_GLPI[Impact::CONF_ENABLED])) {
            $CFG_GLPI[Impact::CONF_ENABLED] = importArrayFromDB($CFG_GLPI[Impact::CONF_ENABLED]);
        }

        if (!isset($_SERVER['REQUEST_URI'])) {
            // $_SERVER['REQUEST_URI'] is not set, meaning that GLPI is probably access from CLI.
            // In this case, `$CFG_GLPI['root_doc']` has to be extracted from `$CFG_GLPI['url_base']`,
            // and it can only be done once configuration is loaded.

            // `$CFG_GLPI['root_doc']` is not supposed to be used in a CLI context,
            // but it is likely to be used indirectly by cron tasks.

            if (isset($CFG_GLPI['url_base'])) {
                $root_doc = parse_url($CFG_GLPI['url_base'], PHP_URL_PATH) ?: '';
                $CFG_GLPI['root_doc'] = $root_doc;
                $CFG_GLPI['typedoc_icon_dir'] = $root_doc . '/pics/icones';
            }
        }

        self::$loaded = true;

        return true;
    }

    /**
     * Indicates whether the legacy configuration has been correctly loaded.
     */
    public static function isLegacyConfigurationLoaded(): bool
    {
        return self::$loaded;
    }

    /**
     * Set config values : create or update entry
     *
     * @since 0.85
     *
     * @param string $context context to get values (default for glpi is core)
     * @param array  $values  config names to set
     *
     * @return void
     */
    public static function setConfigurationValues($context, array $values = [])
    {

        $glpikey = new GLPIKey();

        $config = new self();
        foreach ($values as $name => $value) {
            // Encrypt config values according to list declared to GLPIKey service
            if (!empty($value) && $glpikey->isConfigSecured($context, $name)) {
                $value = $glpikey->encrypt($value);
            }

            if (
                $config->getFromDBByCrit([
                    'context'   => $context,
                    'name'      => $name,
                ])
            ) {
                $input = [
                    'id'        => $config->getID(),
                    'name'      => $name,
                    'context'   => $context,
                    'value'     => $value,
                ];

                $config->update($input);
            } else {
                $input = [
                    'context'   => $context,
                    'name'      => $name,
                    'value'     => $value,
                ];

                $config->add($input);
            }
        }

        //reload config for logged user
        if ($_SESSION['glpiID'] ?? false) {
            $user = new User();
            if ($user->getFromDB($_SESSION['glpiID'])) {
                $user->loadPreferencesInSession();
            }
        }
    }

    /**
     * Delete config entries
     *
     * @since 0.85
     *
     * @param string $context context to get values (default for glpi is core)
     * @param array  $values  config names to delete
     *
     * @return void
     */
    public static function deleteConfigurationValues($context, array $values = [])
    {

        $config = new self();
        foreach ($values as $value) {
            if (
                $config->getFromDBByCrit([
                    'context'   => $context,
                    'name'      => $value,
                ])
            ) {
                $config->delete(['id' => $config->getID()]);
            }
        }
    }

    public function getRights($interface = 'central')
    {

        $values = parent::getRights();
        unset(
            $values[CREATE],
            $values[DELETE],
            $values[PURGE]
        );

        return $values;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => __('Characteristics'),
        ];

        $tab[] = [
            'id'            => 1,
            'table'         => $this->getTable(),
            'field'         => 'value',
            'name'          => __('Value'),
            'massiveaction' => false,
        ];

        return $tab;
    }

    public function getLogTypeID()
    {
        return [$this->getType(), 1];
    }

    public function post_addItem()
    {
        $this->logConfigChange($this->fields['context'], $this->fields['name'], (string) $this->fields['value'], '');
    }

    public function post_updateItem($history = true)
    {
        global $CFG_GLPI, $DB;
        // Check if password expiration mechanism has been activated
        if (
            $this->fields['name'] == 'password_expiration_delay'
            && array_key_exists('value', $this->oldvalues)
            && (int) $this->oldvalues['value'] === -1
        ) {
            // As passwords will now expire, consider that "now" is the reference date of expiration delay
            $DB->update(
                User::getTable(),
                ['password_last_update' => $_SESSION['glpi_currenttime']],
                ['authtype' => Auth::DB_GLPI]
            );

            // Activate passwordexpiration automated task
            $DB->update(
                CronTask::getTable(),
                ['state' => 1,],
                ['name' => 'passwordexpiration']
            );
        }

        // If the `devices_in_menu` option changed, we should regenerate the menu
        if ($this->fields['name'] === 'devices_in_menu') {
            $CFG_GLPI['devices_in_menu'] = json_decode($this->fields['value']) ?? [];
            Html::generateMenuSession(true);
        }

        if (array_key_exists('value', $this->oldvalues)) {
            $newvalue = (string) $this->fields['value'];
            $oldvalue = (string) $this->oldvalues['value'];

            if ($newvalue === $oldvalue) {
                return;
            }

            // Ensure post update actions and hook that are using `$CFG_GLPI` will use the new value
            $array_fields = [
                'priority_matrix',
                'devices_in_menu',
                'lock_item_list',
                'planning_work_days',
                Impact::CONF_ENABLED,
                'proxy_exclusions',
            ];
            if (in_array($this->fields['name'], $array_fields, true)) {
                $CFG_GLPI[$this->fields['name']] = importArrayFromDB($newvalue);
            } else {
                $CFG_GLPI[$this->fields['name']] = $newvalue;
            }

            // avoid inserting truncated json in logs
            if (strlen($newvalue) > 255 && Toolbox::isJSON($newvalue)) {
                $newvalue = "{...}";
            }
            if (strlen($oldvalue) > 255 && Toolbox::isJSON($oldvalue)) {
                $oldvalue = "{...}";
            }

            $this->logConfigChange(
                $this->fields['context'],
                $this->fields['name'],
                $newvalue,
                $oldvalue
            );
        }
    }

    public function post_purgeItem()
    {
        $this->logConfigChange($this->fields['context'], $this->fields['name'], '', (string) $this->fields['value']);
    }

    /**
     * Log config change in history.
     *
     * @param string $context
     * @param string $name
     * @param string $newvalue
     * @param string $oldvalue
     *
     * @return void
     */
    private function logConfigChange(string $context, string $name, string $newvalue, string $oldvalue): void
    {
        $glpi_key = new GLPIKey();
        if ($glpi_key->isConfigSecured($context, $name)) {
            $newvalue = $oldvalue = '********';
        }
        $oldvalue = $name . ($context !== 'core' ? ' (' . $context . ') ' : ' ') . $oldvalue;

        if (in_array($name, NotificationMailingSetting::getRelatedConfigKeys(), true)) {
            // Specific case for email notification settings
            Log::history(
                1,
                NotificationMailingSetting::class,
                [1, $oldvalue, $newvalue]
            );
        } else {
            Log::constructHistory($this, ['value' => $oldvalue], ['value' => $newvalue]);
        }
    }

    /**
     * Get the GLPI Config without unsafe keys like passwords and emails (true on $safer)
     *
     * @param bool $safer do we need to clean more (avoid emails disclosure)
     * @return array of $CFG_GLPI without unsafe keys
     *
     * @since 9.5
     */
    public static function getSafeConfig($safer = false)
    {
        global $CFG_GLPI;

        $excludedKeys = array_flip(self::$undisclosedFields);
        $safe_config  = array_diff_key($CFG_GLPI, $excludedKeys);

        if ($safer) {
            $excludedKeys = array_flip(self::$saferUndisclosedFields);
            $safe_config = array_diff_key($safe_config, $excludedKeys);
        }

        // override with session values
        foreach ($safe_config as $key => &$value) {
            $value = $_SESSION['glpi' . $key] ?? $value;
        }

        return $safe_config;
    }

    /**
     * Get UUID
     *
     * @param string $type UUID type (e.g. 'instance' or 'registration')
     *
     * @return string
     */
    final public static function getUuid($type)
    {
        $conf = self::getConfigurationValues('core', [$type . '_uuid']);
        $uuid = null;
        if (!isset($conf[$type . '_uuid']) || empty($conf[$type . '_uuid'])) {
            $uuid = self::generateUuid($type);
        } else {
            $uuid = $conf[$type . '_uuid'];
        }
        return $uuid;
    }

    /**
     * Generates an unique identifier and store it
     *
     * @param string $type UUID type (e.g. 'instance' or 'registration')
     *
     * @return string
     */
    final public static function generateUuid($type)
    {
        $uuid = Toolbox::getRandomString(40);
        self::setConfigurationValues('core', [$type . '_uuid' => $uuid]);
        return $uuid;
    }

    /**
     * Try to find a valid sender email from the GLPI configuration
     *
     * @param int|null $entities_id  Entity configuration to be used, default to
     *                               global configuration
     * @param bool     $no_reply     Should the configured "noreply" address be
     *                               used (default: false)
     *
     * @return array [email => sender address, name => sender name]
     */
    public static function getEmailSender(
        ?int $entities_id = null,
        bool $no_reply = false
    ): array {
        // Try to use the configured noreply address if no response is expected
        // for this notification
        if ($no_reply) {
            $sender = Config::getNoReplyEmailSender($entities_id);
            if ($sender['email'] !== null) {
                return $sender;
            }
        }

        // Try to use the configured "from" email address
        $sender = Config::getFromEmailSender($entities_id);
        if ($sender['email'] !== null) {
            return $sender;
        }

        // Try to use the configured "admin" email address
        $sender = Config::getAdminEmailSender($entities_id);
        if ($sender['email'] !== null) {
            return $sender;
        }

        // No valid email was found
        trigger_error(
            'No email address is defined in configuration.',
            E_USER_WARNING
        );

        // No values found
        return [
            'email' => null,
            'name'  => null,
        ];
    }

    /**
     * Try to find a valid "from" email from the GLPI configuration
     *
     * @param int|null $entities_id  Entity configuration to be used, default to
     *                               global configuration
     *
     * @return array [email => sender address, name => sender name]
     */
    public static function getFromEmailSender(?int $entities_id = null): array
    {
        return self::getEmailSenderFromEntityOrConfig('from_email', $entities_id);
    }

    /**
     * Try to find a valid "admin_email" email from the GLPI configuration
     *
     * @param int|null $entities_id  Entity configuration to be used, default to
     *                               global configuration
     *
     * @return array [email => sender address, name => sender name]
     */
    public static function getAdminEmailSender(?int $entities_id = null): array
    {
        return self::getEmailSenderFromEntityOrConfig('admin_email', $entities_id);
    }

    /**
     * Try to find a valid noreply email from the GLPI configuration
     *
     * @param int|null $entities_id  Entity configuration to be used, default to
     *                               global configuration
     *
     * @return array [email => noreply address, name => noreply name]
     */
    public static function getNoReplyEmailSender(?int $entities_id = null): array
    {
        return self::getEmailSenderFromEntityOrConfig('noreply_email', $entities_id);
    }

    /**
     * Try to find a valid replyto email from the GLPI configuration
     *
     * @param int|null $entities_id  Entity configuration to be used, default to
     *                               global configuration
     *
     * @return array [email => replyto address, name => replyto name]
     */
    public static function getReplyToEmailSender(?int $entities_id = null): array
    {
        return self::getEmailSenderFromEntityOrConfig('replyto_email', $entities_id);
    }

    /**
     * Try to find a valid email from the GLPI configuration
     *
     * @param string   $config_name  Configuration name
     * @param int|null $entities_id  Entity configuration to be used, default to
     *                               global configuration
     *
     * @return array [email => address, name => name]
     */
    private static function getEmailSenderFromEntityOrConfig(string $config_name, ?int $entities_id = null): array
    {
        global $CFG_GLPI;

        $email_config_name = $config_name;
        $name_config_name  = $config_name . '_name';

        // Check admin email in specified entity
        if (!is_null($entities_id)) {
            $entity_sender_email = trim(
                Entity::getUsedConfig($email_config_name, $entities_id, '', '')
            );
            $entity_sender_name = trim(
                Entity::getUsedConfig($name_config_name, $entities_id, '', '')
            );

            if (NotificationMailing::isUserAddressValid($entity_sender_email)) {
                return [
                    'email' => $entity_sender_email,
                    'name'  => $entity_sender_name,
                ];
            } elseif ($entity_sender_email !== '') {
                trigger_error(
                    sprintf(
                        'Invalid email address "%s" configured for entity "%s". Default administrator email will be used.',
                        $entity_sender_email,
                        $entities_id
                    ),
                    E_USER_WARNING
                );
            }
        }

        // Fallback to global configuration
        $global_sender_email = $CFG_GLPI[$email_config_name] ?? "";
        $global_sender_name  = $CFG_GLPI[$name_config_name]  ?? "";

        if (NotificationMailing::isUserAddressValid($global_sender_email)) {
            return [
                'email' => $global_sender_email,
                'name'  => $global_sender_name,
            ];
        } elseif ($global_sender_email !== '') {
            trigger_error(
                sprintf('Invalid email address "%s" configured in "%s".', $global_sender_email, $config_name),
                E_USER_WARNING
            );
        }

        // No valid values found
        return [
            'email' => null,
            'name'  => null,
        ];
    }

    /**
     * Override parent: "{itemtype} - {header name}" -> "{itemtype}"
     * There is only one config, no need to display the item name
     *
     * @return string
     */
    public function getBrowserTabName(): string
    {
        return self::getTypeName(1);
    }

    /**
     * Gets the ID of a random record from the config table with the specified context.
     *
     * Used as a hacky workaround when we require a valid glpi_configs record for rights checks.
     * We cannot rely on something being in ID 1 for the core context for example, because some clustering solutions may change how autoincrement works.
     * @return ?int
     * @internal
     */
    public static function getConfigIDForContext(string $context)
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['MIN' => 'id AS id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'context' => $context,
            ],
        ]);
        if (count($iterator)) {
            return $iterator->current()['id'];
        }
        return null;
    }

    public static function allowUnauthenticatedUploads(): bool
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        return (bool) ($CFG_GLPI['allow_unauthenticated_uploads'] ?? false);
    }

    public static function isHlApiEnabled(): bool
    {
        global $CFG_GLPI;
        return (bool) ($CFG_GLPI['enable_hlapi'] ?? 0);
    }
}
