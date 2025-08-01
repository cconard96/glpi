<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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
 * Based on cacti plugin system
 */
use Composer\Autoload\ClassLoader;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Cache\CacheManager;
use Glpi\Dashboard\Grid;
use Glpi\Debug\Profiler;
use Glpi\Event;
use Glpi\Marketplace\Controller as MarketplaceController;
use Glpi\Marketplace\View as MarketplaceView;
use Glpi\Plugin\Hooks;
use Glpi\Toolbox\VersionParser;
use Laminas\I18n\Translator\Translator;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

use function Safe\ini_get;
use function Safe\ob_end_clean;
use function Safe\ob_start;
use function Safe\preg_grep;
use function Safe\preg_match;
use function Safe\scandir;

class Plugin extends CommonDBTM
{
    // Class constant : Plugin state
    /**
     * @var int Unknown plugin state
     */
    public const UNKNOWN        = -1;

    /**
     * @var int Plugin was discovered but not installed
     *
     * @note Plugins are never actually set to this status?
     */
    public const ANEW           = 0;

    /**
     * @var int Plugin is installed and enabled
     */
    public const ACTIVATED      = 1;

    /**
     * @var int Plugin is not installed
     */
    public const NOTINSTALLED   = 2;

    /**
     * @var int Plugin is installed but needs configured before it can be enabled
     */
    public const TOBECONFIGURED = 3;

    /**
     * @var int Plugin is installed but has not been activated yet, or has been deactivated by the user.
     */
    public const NOTACTIVATED   = 4;

    /**
     * @var int Plugin was previously discovered, but the plugin directory is missing now. The DB needs cleaned.
     */
    public const TOBECLEANED    = 5;

    /**
     * @var int The plugin's files are for a newer version than installed. An update is needed.
     */
    public const NOTUPDATED     = 6;

    /**
     * The plugin has been replaced by another one.
     */
    public const REPLACED       = 7;

    /**
     * Option used to indicates that auto installation of plugin should be disabled (bool value expected).
     *
     * @var string
     */
    public const OPTION_AUTOINSTALL_DISABLED = 'autoinstall_disabled';

    /**
     * Plugins execution is active.
     */
    public const EXECUTION_MODE_ON = 'on';

    /**
     * Plugins execution has been suspended by a GLPI codebase update.
     */
    public const EXECUTION_MODE_SUSPENDED_BY_UPDATE = 'suspended_by_update';

    /**
     * Plugins execution has been suspended manually by the administrator.
     */
    public const EXECUTION_MODE_SUSPENDED_MANUALLY = 'suspended_manually';

    /**
     * Plugin key validation pattern.
     */
    private const PLUGIN_KEY_PATTERN = '/^[a-z0-9]+$/i';

    public static $rightname = 'config';

    /**
     * Indicates whether plugins have been initialized.
     *
     * @var boolean
     */
    private static $plugins_initialized = false;

    /**
     * Booted plugin list
     *
     * @var string[]
     */
    private static $booted_plugins = [];

    /**
     * Activated plugin list
     *
     * @var string[]
     */
    private static $activated_plugins = [];


    /**
     * List of plugins having their autoloader already registered.
     *
     * @var string[]
     */
    private static $autoloaded_plugins = [];

    /**
     * Loaded plugin list
     *
     * @var string[]
     */
    private static $loaded_plugins = [];

    /**
     * Store additional infos for each plugins
     *
     * @var array
     */
    private array $plugins_information = [];

    /**
     * Store keys of plugins found on filesystem.
     *
     * @var array|null
     */
    private ?array $filesystem_plugin_keys = null;

    public static function getTypeName($nb = 0)
    {
        return _n('Plugin', 'Plugins', $nb);
    }


    public static function getMenuName()
    {
        return static::getTypeName(Session::getPluralNumber());
    }


    public static function getMenuContent()
    {
        $menu = parent::getMenuContent() ?: [];
        if (!MarketplaceController::isWebAllowed()) {
            return $menu;
        }

        if (static::canView()) {
            $redirect_mp = MarketplaceController::getPluginPageConfig();

            $menu['title'] = self::getMenuName();
            $menu['page']  = $redirect_mp == MarketplaceController::MP_REPLACE_YES
                           ? '/front/marketplace.php'
                           : '/front/plugin.php';
            $menu['icon']  = self::getIcon();
        }
        if (count($menu)) {
            return $menu;
        }
        return false;
    }


    public static function getAdditionalMenuLinks()
    {
        if (!static::canView()) {
            return false;
        }
        $mp_icon     = MarketplaceView::getIcon();
        $mp_title    = MarketplaceView::getTypeName();
        $marketplace = "<i class='$mp_icon pointer' title='$mp_title'></i><span class='d-none d-xxl-block'>$mp_title</span>";

        $cl_icon     = Plugin::getIcon();
        $cl_title    = Plugin::getTypeName(Session::getPluralNumber());
        $classic     = "<i class='$cl_icon pointer' title='$cl_title'></i><span class='d-none d-xxl-block'>$cl_title</span>";

        $links = [
            $classic     => Plugin::getSearchURL(false),
        ];
        if (MarketplaceController::isWebAllowed()) {
            $links[$marketplace] = MarketplaceView::getSearchURL(false);
        }
        return $links;
    }


    public static function getAdditionalMenuOptions()
    {
        if (!MarketplaceController::isWebAllowed()) {
            return false;
        }
        if (static::canView()) {
            return [
                'marketplace' => [
                    'icon'  => MarketplaceView::geticon(),
                    'title' => MarketplaceView::getTypeName(),
                    'page'  => MarketplaceView::getSearchURL(false),
                ],
            ];
        }
        return false;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->prepareInput($input);

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->prepareInput($input);

        return $input;
    }

    private function prepareInput(array $input)
    {
        if ($this->isNewItem() || array_key_exists('directory', $input)) {
            if (preg_match(self::PLUGIN_KEY_PATTERN, $input['directory'] ?? '') !== 1) {
                Session::addMessageAfterRedirect(
                    __s('Invalid plugin directory'),
                    false,
                    ERROR
                );
                return false;
            }
        }
        return $input;
    }

    /**
     * Retrieve an item from the database using its directory
     *
     * @param string $dir directory of the plugin
     *
     * @return boolean
     **/
    public function getFromDBbyDir($dir)
    {
        return $this->getFromDBByCrit([$this->getTable() . '.directory' => $dir]);
    }

    /**
     * Boot active plugins.
     */
    public function bootPlugins(): void
    {
        /** @var DBmysql|null $DB */
        global $DB;

        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Booting plugins is forbidden when plugins execution is suspended.');
        }

        self::$booted_plugins = [];
        self::$activated_plugins = [];
        self::$autoloaded_plugins = [];
        self::$loaded_plugins = [];

        $plugins = $this->find(['state' => [self::ACTIVATED, self::TOBECONFIGURED]]);

        foreach ($plugins as $plugin) {
            $plugin_key = $plugin['directory'];

            if (!$this->isLoadable($plugin_key)) {
                continue;
            }

            foreach (GLPI_PLUGINS_DIRECTORIES as $base_dir) {
                $plugin_directory = "$base_dir/$plugin_key";

                if (!is_dir($plugin_directory)) {
                    continue; // try with next base dir
                }

                $this->registerPluginAutoloader($plugin_key, $plugin_directory);

                $boot_function = sprintf('plugin_%s_boot', $plugin_key);
                if (function_exists($boot_function)) {
                    try {
                        $boot_function();
                    } catch (Throwable $e) {
                        // Log error
                        /** @var LoggerInterface $PHPLOGGER */
                        global $PHPLOGGER;
                        $PHPLOGGER->error(
                            sprintf('An error occurred during the `%s` plugin boot: %s', $plugin_key, $e->getMessage()),
                            ['exception' => $e]
                        );
                        continue 2; // ignore this plugin
                    }
                }

                self::$booted_plugins[] = $plugin_key;

                if ((int) $plugin['state'] === self::ACTIVATED) {
                    self::$activated_plugins[] = $plugin_key;
                }

            }
        }
    }

    /**
     * Register the given plugin autoloader.
     */
    private function registerPluginAutoloader(string $plugin_key, string $plugin_directory): void
    {
        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Registering plugin autoloader is forbidden when plugins execution is suspended.');
        }

        if (in_array($plugin_key, self::$autoloaded_plugins)) {
            return;
        }

        $psr4_dir = $plugin_directory . '/src/';
        if (is_dir($psr4_dir)) {
            $psr4_autoloader = new ClassLoader();
            $psr4_autoloader->addPsr4(NS_PLUG . ucfirst($plugin_key) . '\\', $psr4_dir);
            $psr4_autoloader->register();

            self::$autoloaded_plugins[] = $plugin_key;
        }
    }

    /**
     * Initialize active plugins.
     */
    public function init()
    {
        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Initializing plugins is forbidden when plugins execution is suspended.');
        }

        self::$plugins_initialized = false;

        foreach (self::$booted_plugins as $plugin_key) {
            Profiler::getInstance()->start("{$plugin_key}:init", Profiler::CATEGORY_PLUGINS);
            Plugin::load($plugin_key);
            Profiler::getInstance()->stop("{$plugin_key}:init");
        }
        // For plugins which require action after all plugin init
        Plugin::doHook(Hooks::POST_INIT);

        self::$plugins_initialized = true;
    }


    /**
     * Init a plugin including setup.php file
     * launching plugin_init_NAME function  after checking compatibility
     *
     * @param string  $plugin_key        System name (Plugin directory)
     * @param boolean $withhook   Load hook functions (false by default)
     *
     * @return void
     **/
    public static function load($plugin_key, $withhook = false)
    {
        if ((new Plugin())->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Loading plugin files is forbidden when plugins execution is suspended.');
        }

        $loaded = false;
        foreach (GLPI_PLUGINS_DIRECTORIES as $base_dir) {
            if (!is_dir($base_dir)) {
                continue;
            }

            $plugin_directory = "$base_dir/$plugin_key";

            if (!file_exists($plugin_directory)) {
                continue;
            }

            if ((new self())->loadPluginSetupFile($plugin_key)) {
                $loaded = true;
                if (!in_array($plugin_key, self::$loaded_plugins)) {
                    (new self())->registerPluginAutoloader($plugin_key, $plugin_directory);

                    // Init plugin
                    self::$loaded_plugins[] = $plugin_key;
                    $init_function = "plugin_init_$plugin_key";
                    if (function_exists($init_function)) {
                        try {
                            $init_function();
                        } catch (Throwable $e) {
                            /** @var LoggerInterface $PHPLOGGER */
                            global $PHPLOGGER;
                            $PHPLOGGER->error(
                                sprintf(
                                    'Error while loading plugin %s: %s',
                                    $plugin_key,
                                    $e->getMessage()
                                ),
                                ['exception' => $e]
                            );

                            // Plugin has errored, so it should be disabled if it isn't already
                            $plugin = new self();
                            if ($plugin->isActivated($plugin_key)) {
                                // We don't want to override another status like TOBECONFIGURED or NOTUPDATED
                                $plugin->getFromDBbyDir($plugin_key);
                                $plugin->unactivate($plugin->getID());
                            }
                            continue;
                        }
                        self::loadLang($plugin_key);
                    }
                }
            }
            if ($withhook) {
                self::includeHook($plugin_key);
            }

            if ($loaded) {
                break;
            }
        }
    }

    /**
     * Unload a plugin.
     *
     * @param string  $plugin_key  System name (Plugin directory)
     *
     * @return void
     */
    private function unload($plugin_key)
    {
        if (($key = array_search($plugin_key, self::$activated_plugins)) !== false) {
            unset(self::$activated_plugins[$key]);
        }

        if (($key = array_search($plugin_key, self::$loaded_plugins)) !== false) {
            unset(self::$loaded_plugins[$key]);
        }

        // reset menu
        if (isset($_SESSION['glpimenu'])) {
            unset($_SESSION['glpimenu']);
        }

        $this->resetHookableCacheEntries($plugin_key);
    }


    /**
     * Load lang file for a plugin
     *
     * @param string $plugin_key    System name (Plugin directory)
     * @param string $forcelang     Force a specific lang (default '')
     * @param string $coretrytoload Lang trying to be loaded from core (default '')
     *
     * @return void
     **/
    public static function loadLang($plugin_key, $forcelang = '', $coretrytoload = '')
    {
        /**
         * @var array $CFG_GLPI
         * @var Translator $TRANSLATE
         */
        global $CFG_GLPI, $TRANSLATE;

        if ((new Plugin())->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Loading plugin locales is forbidden when plugins execution is suspended.');
        }

        $trytoload = 'en_GB';
        if (isset($_SESSION['glpilanguage'])) {
            $trytoload = $_SESSION["glpilanguage"];
        }
        // Force to load a specific lang
        if (!empty($forcelang)) {
            $trytoload = $forcelang;
        }

        // If not set try default lang file
        if (empty($trytoload)) {
            $trytoload = $CFG_GLPI["language"];
        }

        if (empty($coretrytoload)) {
            $coretrytoload = $trytoload;
        }

        // New localisation system
        $mofile = false;
        foreach (GLPI_PLUGINS_DIRECTORIES as $base_dir) {
            if (!is_dir($base_dir)) {
                continue;
            }
            $locales_dir = "$base_dir/$plugin_key/locales/";
            if (
                array_key_exists($trytoload, $CFG_GLPI["languages"])
                && file_exists($locales_dir . $CFG_GLPI["languages"][$trytoload][1])
            ) {
                $mofile = $locales_dir . $CFG_GLPI["languages"][$trytoload][1];
            } elseif (
                !empty($CFG_GLPI["language"])
                && array_key_exists($CFG_GLPI["language"], $CFG_GLPI["languages"])
                && file_exists($locales_dir . $CFG_GLPI["languages"][$CFG_GLPI["language"]][1])
            ) {
                $mofile = $locales_dir . $CFG_GLPI["languages"][$CFG_GLPI["language"]][1];
            } elseif (file_exists($locales_dir . "en_GB.mo")) {
                $mofile = $locales_dir . "en_GB.mo";
            }

            if ($mofile !== false) {
                break;
            }
        }

        if ($mofile !== false) {
            $TRANSLATE->addTranslationFile(
                'gettext',
                $mofile,
                $plugin_key,
                $coretrytoload
            );
        }

        $plugin_folders = is_dir(GLPI_LOCAL_I18N_DIR) ? scandir(GLPI_LOCAL_I18N_DIR) : [];
        $plugin_folders = array_filter($plugin_folders, function ($dir) use ($plugin_key) {
            if (!is_dir(GLPI_LOCAL_I18N_DIR . "/$dir")) {
                return false;
            }

            if ($dir == $plugin_key) {
                return true;
            }

            return str_starts_with($dir, $plugin_key . '_');
        });

        foreach ($plugin_folders as $plugin_folder) {
            $mofile = GLPI_LOCAL_I18N_DIR . "/$plugin_folder/$coretrytoload.mo";
            $phpfile = str_replace('.mo', '.php', $mofile);

            // Load local PHP file if it exists
            if (file_exists($phpfile)) {
                $TRANSLATE->addTranslationFile('phparray', $phpfile, $plugin_key, $coretrytoload);
            }

            // Load local MO file if it exists -- keep last so it gets precedence
            if (file_exists($mofile)) {
                $TRANSLATE->addTranslationFile('gettext', $mofile, $plugin_key, $coretrytoload);
            }
        }
    }


    /**
     * Check plugins states and detect new plugins.
     *
     * @param boolean $scan_inactive_and_new_plugins
     *
     * @return void
     */
    public function checkStates($scan_inactive_and_new_plugins = false)
    {
        /**
         * @var array $CFG_GLPI
         * @var DBmysql $DB
         */
        global $CFG_GLPI, $DB;

        if ($this->isPluginsExecutionSuspended()) {
            // Do not check plugins states when their execution is suspended.
            // Checking their state requires their `setup.php` file to be loaded, we do not want this to happen.
            return;
        }

        if (Update::isUpdateMandatory() && countElementsInTable(self::getTable()) > 0) {
            // Suspend all plugins once a new mandatory update is detected.
            // This prevents incompatible plugins to be loaded.
            // Use a direct DB query to prevent trigerring `CommonDBTM` hooks.
            $DB->updateOrInsert(
                Config::getTable(),
                [
                    'value'   => self::EXECUTION_MODE_SUSPENDED_BY_UPDATE,
                ],
                [
                    'context' => 'core',
                    'name'    => 'plugins_execution_mode',
                ],
            );

            Event::log(
                '',
                Plugin::class,
                3,
                "setup",
                __('Execution of all the plugins has been suspended since a database update is required.')
            );

            return; // Do not check individual plugins states.
        }

        $directories = [];

        // Add known plugins to the check list
        $condition = $scan_inactive_and_new_plugins ? [] : ['state' => self::ACTIVATED];
        $known_plugins = $this->find($condition);
        foreach ($known_plugins as $plugin) {
            $directories[] = $plugin['directory'];
        }

        if ($scan_inactive_and_new_plugins) {
            array_push($directories, ...$this->getFilesystemPluginKeys());
        }

        // Prevent duplicated checks
        $directories = array_unique($directories);

        // Check all directories from the checklist
        foreach ($directories as $directory) {
            $this->checkPluginState($directory, $scan_inactive_and_new_plugins);
        }
    }

    /**
     * Get information for a given plugin.
     */
    private function getPluginInformation(string $plugin_key): ?array
    {
        if (!array_key_exists($plugin_key, $this->plugins_information)) {
            $information = $this->getInformationsFromDirectory($plugin_key, with_lang: false);
            $this->plugins_information[$plugin_key] = !empty($information) ? $information : null;
        }

        return $this->plugins_information[$plugin_key];
    }

    /**
     * Return plugin keys corresponding to directories found in filesystem.
     */
    private function getFilesystemPluginKeys(): array
    {
        if ($this->filesystem_plugin_keys === null) {
            $this->filesystem_plugin_keys = [];

            $plugins_directories = new AppendIterator();
            foreach (GLPI_PLUGINS_DIRECTORIES as $base_dir) {
                if (!is_dir($base_dir)) {
                    continue;
                }
                $plugins_directories->append(new DirectoryIterator($base_dir));
            }

            foreach ($plugins_directories as $plugin_directory) {
                if (
                    str_starts_with($plugin_directory->getFilename(), '.') // ignore hidden files
                    || !is_dir($plugin_directory->getRealPath())
                ) {
                    continue;
                }

                $this->filesystem_plugin_keys[] = $plugin_directory->getFilename();
            }
        }

        return $this->filesystem_plugin_keys;
    }

    /**
     * Check plugin state.
     *
     * @param string $plugin_key System name (Plugin directory)
     *
     * return void
     */
    public function checkPluginState($plugin_key, bool $check_for_replacement = false)
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Checking a plugin state is forbidden when plugins execution is suspended.');
        }

        $plugin = new self();

        $information      = $this->getPluginInformation($plugin_key) ?? [];
        $is_loadable      = $information !== [];
        $is_already_known = $plugin->getFromDBByCrit(['directory' => $plugin_key]);
        $new_specs        = $check_for_replacement ? $this->getNewInfoAndDirBasedOnOldName($plugin_key) : null;
        $is_replaced      = $new_specs !== null;

        if (!$is_already_known && !$is_loadable) {
            // Plugin is not known and we are unable to load information, we ignore it.
            return;
        }

        // Filter information to keep only fields expected to be inserted/updated into the DB.
        $information = array_filter(
            $information,
            fn($key) => in_array(
                $key,
                ['name', 'version', 'author', 'homepage', 'license'],
                true
            ),
            ARRAY_FILTER_USE_KEY
        );

        if ($is_already_known && $is_replaced) {
            // Filesystem contains both the checked plugin and the plugin that is supposed to replace it.
            // Mark it as REPLACED as it should not be loaded anymore.
            if ((int) $plugin->fields['state'] !== self::REPLACED) {
                trigger_error(
                    sprintf(
                        'Plugin "%s" has been replaced by "%s" and therefore has been deactivated.',
                        $plugin_key,
                        $new_specs['directory']
                    ),
                    E_USER_WARNING
                );
                $DB->update(
                    self::getTable(),
                    [
                        'state' => self::REPLACED,
                    ] + $information,
                    [
                        'id' => $plugin->fields['id'],
                    ]
                );

                $this->unload($plugin_key);

                Event::log(
                    '',
                    Plugin::class,
                    3,
                    "setup",
                    sprintf(
                        __('Plugin %1$s has been replaced by %2$s and therefore has been deactivated.'),
                        $plugin_key,
                        $new_specs['directory']
                    )
                );
            }
            // Plugin has been replaced, we ignore it
            return;
        }

        if (!$is_loadable) {
            trigger_error(
                sprintf(
                    'Unable to load plugin "%s" information.',
                    $plugin_key
                ),
                E_USER_WARNING
            );
            // Plugin is known but we are unable to load information, we ignore it
            return;
        }

        if (!$is_already_known) {
            // Plugin not known, add it in DB
            $DB->insert(
                self::getTable(),
                [
                    'state'     => $is_replaced ? self::REPLACED : self::NOTINSTALLED,
                    'directory' => $plugin_key,
                ] + $information
            );
            return;
        }

        if (
            $information['version'] != $plugin->fields['version']
            || $plugin_key != $plugin->fields['directory']
        ) {
            // Plugin known version differs from information or plugin has been renamed,
            // update information in database
            $input              = $information;
            $input['directory'] = $plugin_key;
            if (!in_array($plugin->fields['state'], [self::ANEW, self::NOTINSTALLED, self::NOTUPDATED])) {
                // mark it as 'updatable' unless it was not installed
                trigger_error(
                    sprintf(
                        'Plugin "%s" version changed. It has been deactivated as its update process has to be launched.',
                        $plugin_key
                    ),
                    E_USER_WARNING
                );

                $input['state']     = self::NOTUPDATED;

                Event::log(
                    '',
                    Plugin::class,
                    3,
                    "setup",
                    sprintf(
                        __('Plugin %1$s version changed. It has been deactivated as its update process has to be launched.'),
                        $plugin_key
                    )
                );
            }

            $DB->update(
                self::getTable(),
                $input,
                [
                    'id' => $plugin->fields['id'],
                ]
            );

            $this->unload($plugin_key);

            return;
        }

        // Check if replacement state changed
        if ((int) $plugin->fields['state'] === self::REPLACED && !$is_replaced) {
            // Reset plugin state as replacement plugin is not present anymore on filesystem
            $DB->update(
                self::getTable(),
                [
                    'state' => self::NOTINSTALLED,
                ],
                [
                    'id' => $plugin->fields['id'],
                ]
            );
            return;
        }

        // Check if configuration state changed
        if (in_array((int) $plugin->fields['state'], [self::ACTIVATED, self::TOBECONFIGURED, self::NOTACTIVATED], true)) {
            $function = 'plugin_' . $plugin_key . '_check_config';
            $is_config_ok = !function_exists($function) || $function();

            if ((int) $plugin->fields['state'] === self::TOBECONFIGURED && $is_config_ok) {
                // Remove TOBECONFIGURED state if configuration is OK now
                $DB->update(
                    self::getTable(),
                    [
                        'state' => self::NOTACTIVATED,
                    ],
                    [
                        'id' => $plugin->fields['id'],
                    ]
                );
                return;
            } elseif ((int) $plugin->fields['state'] !== self::TOBECONFIGURED && !$is_config_ok) {
                // Add TOBECONFIGURED state if configuration is required
                trigger_error(
                    sprintf(
                        'Plugin "%s" must be configured.',
                        $plugin_key
                    ),
                    E_USER_WARNING
                );
                $DB->update(
                    self::getTable(),
                    [
                        'state' => self::TOBECONFIGURED,
                    ],
                    [
                        'id' => $plugin->fields['id'],
                    ]
                );
                return;
            }
        }

        if (self::ACTIVATED !== (int) $plugin->fields['state']) {
            // Plugin is not activated, nothing to do
            return;
        }

        // Check that active state of plugin can be kept
        $usage_ok = true;

        // Check compatibility
        ob_start();
        if (!$this->checkVersions($plugin_key)) {
            $usage_ok = false;
        }
        ob_end_clean();

        // Check prerequisites
        if ($usage_ok) {
            $function = 'plugin_' . $plugin_key . '_check_prerequisites';
            if (function_exists($function)) {
                ob_start();
                if (!$function()) {
                    $usage_ok = false;
                }
                ob_end_clean();
            }
        }

        if (!$usage_ok) {
            // Deactivate if not usable
            trigger_error(
                sprintf(
                    'Plugin "%s" prerequisites are not matched. It has been deactivated.',
                    $plugin_key
                ),
                E_USER_WARNING
            );

            $DB->update(
                self::getTable(),
                [
                    'state' => self::NOTACTIVATED,
                ],
                [
                    'id' => $plugin->fields['id'],
                ]
            );

            $this->unload($plugin_key);

            Event::log(
                '',
                Plugin::class,
                3,
                "setup",
                sprintf(
                    'Plugin "%s" prerequisites are not matched. It has been deactivated.',
                    $plugin_key
                )
            );
        }
    }


    /**
     * Get plugin information based on its old name.
     *
     * @param string $oldname
     *
     * @return null|array If a new directory is found, returns an array containing 'directory' and 'information' keys.
     */
    private function getNewInfoAndDirBasedOnOldName($oldname)
    {
        foreach ($this->getFilesystemPluginKeys() as $plugin_key) {
            $information = $this->getPluginInformation($plugin_key);

            if (($information['oldname'] ?? null) === $oldname) {
                // Return information if oldname specified in parsed directory matches passed value
                return [
                    'directory'   => $plugin_key,
                    'information' => $information,
                ];
            }
        }

        return null;
    }

    /**
     * Get list of all plugins
     *
     * @param array $fields Fields to retrieve
     * @param array $order  Query ORDER clause
     *
     * @return array
     */
    public function getList(array $fields = [], array $order = ['name', 'directory'])
    {
        /** @var DBmysql $DB */
        global $DB;

        $query = [
            'FROM'   => $this->getTable(),
        ];

        if (count($fields) > 0) {
            $query['FIELDS'] = $fields;
        }

        if (count($order) > 0) {
            $query['ORDER'] = $order;
        }

        $iterator = $DB->request($query);
        return iterator_to_array($iterator, false);
    }


    /**
     * Uninstall a plugin
     *
     * @param integer $ID ID of the plugin (The `id` field, not directory)
     **/
    public function uninstall($ID)
    {
        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Executing a plugin maintenance method is forbidden when plugins execution is suspended.');
        }

        $message = '';
        $type = ERROR;

        if ($this->getFromDB($ID)) {
            CronTask::Unregister($this->fields['directory']);
            self::load($this->fields['directory'], true); // Force load in case plugin is not active
            FieldUnicity::deleteForItemtype($this->fields['directory']);
            Link_Itemtype::deleteForItemtype($this->fields['directory']);

            // Run the Plugin's Uninstall Function first
            $function = 'plugin_' . $this->fields['directory'] . '_uninstall';
            if (function_exists($function)) {
                $function();
            } else {
                Session::addMessageAfterRedirect(
                    htmlescape(sprintf(__('Plugin %1$s has no uninstall function!'), $this->fields['name'])),
                    true,
                    WARNING
                );
            }

            $this->update([
                'id'      => $ID,
                'state'   => self::NOTINSTALLED,
            ]);
            $this->unload($this->fields['directory']);

            self::doHook(Hooks::POST_PLUGIN_UNINSTALL, $this->fields['directory']);

            $type = INFO;
            $message = sprintf(__('Plugin %1$s has been uninstalled!'), $this->fields['name']);

            Event::log(
                '',
                Plugin::class,
                3,
                "setup",
                sprintf(
                    __('Plugin %1$s has been uninstalled by %2$s.'),
                    $this->fields['name'],
                    User::getNameForLog(Session::getLoginUserID(true))
                )
            );
        } else {
            $message = sprintf(__('Plugin %1$s not found!'), $ID);
        }

        Session::addMessageAfterRedirect(
            htmlescape($message),
            true,
            $type
        );
    }


    /**
     * Install a plugin
     *
     * @param integer $ID      ID of the plugin (The `id` field, not directory)
     * @param array   $params  Additional params to pass to install hook.
     *
     * @return void
     *
     * @since 9.5.0 Added $param parameter
     **/
    public function install($ID, array $params = [])
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Executing a plugin maintenance method is forbidden when plugins execution is suspended.');
        }

        $message = '';
        $type = ERROR;

        if ($this->getFromDB($ID)) {
            // Clear locale cache to prevent errors while reloading plugin locales
            (new CacheManager())->getTranslationsCacheInstance()->clear();

            self::load($this->fields['directory'], true); // Load plugin hooks

            $install_function = 'plugin_' . $this->fields['directory'] . '_install';
            if (function_exists($install_function)) {
                $DB->disableTableCaching(); //prevents issues on table/fieldExists upgrading from old versions
                if ($install_function($params)) {
                    $type = INFO;
                    $check_function = 'plugin_' . $this->fields['directory'] . '_check_config';
                    $is_config_ok = !function_exists($check_function) || $check_function();
                    if ($is_config_ok) {
                        $this->update(['id'    => $ID,
                            'state' => self::NOTACTIVATED,
                        ]);
                        $message  = htmlescape(sprintf(__('Plugin %1$s has been installed!'), $this->fields['name']));
                        $message .= '<br/><br/>' . str_replace(
                            '%activate_link',
                            Html::getSimpleForm(
                                static::getFormURL(),
                                ['action' => 'activate'],
                                mb_strtolower(_x('button', 'Enable')),
                                ['id' => $ID],
                                '',
                                'class="pointer"'
                            ),
                            __('Do you want to %activate_link it?')
                        );
                    } else {
                        $this->update(['id'    => $ID,
                            'state' => self::TOBECONFIGURED,
                        ]);
                        $message = htmlescape(sprintf(__('Plugin %1$s has been installed and must be configured!'), $this->fields['name']));
                    }

                    $this->resetHookableCacheEntries($this->fields['directory']);

                    self::doHook(Hooks::POST_PLUGIN_INSTALL, $this->fields['directory']);

                    Event::log(
                        '',
                        Plugin::class,
                        3,
                        "setup",
                        sprintf(
                            __('Plugin %1$s has been installed by %2$s.'),
                            $this->fields['name'],
                            User::getNameForLog(Session::getLoginUserID(true))
                        )
                    );
                }
            } else {
                $type = WARNING;
                $message = htmlescape(sprintf(__('Plugin %1$s has no install function!'), $this->fields['name']));
            }
        } else {
            $message = htmlescape(sprintf(__('Plugin %1$s not found!'), $ID));
        }

        Session::addMessageAfterRedirect(
            $message,
            true,
            $type
        );
    }


    /**
     * activate a plugin
     *
     * @param integer $ID ID of the plugin (The `id` field, not directory)
     *
     * @return boolean about success
     **/
    public function activate($ID)
    {
        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;

        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Executing a plugin maintenance method is forbidden when plugins execution is suspended.');
        }

        if ($this->getFromDB($ID)) {
            // Enable autoloader and load plugin hooks
            self::load($this->fields['directory'], true);

            $function = 'plugin_' . $this->fields['directory'] . '_check_prerequisites';
            if (function_exists($function)) {
                ob_start();
                $do_activate = $function();
                $msg = '';
                if (!$do_activate) {
                    $msg = '<span class="error">' . ob_get_contents() . '</span>';
                }
                ob_end_clean();

                if (!$do_activate) {
                    $this->unload($this->fields['directory']);

                    Session::addMessageAfterRedirect(
                        htmlescape(sprintf(__('Plugin %1$s prerequisites are not matching, it cannot be activated.'), $this->fields['name'])) . ' ' . $msg,
                        true,
                        ERROR
                    );
                    return false;
                }
            }

            $function = 'plugin_' . $this->fields['directory'] . '_check_config';
            if (!function_exists($function) || $function()) {
                $activate_function = 'plugin_' . $this->fields['directory'] . '_activate';
                if (function_exists($activate_function)) {
                    $activate_function();
                }

                $this->update(['id'    => $ID,
                    'state' => self::ACTIVATED,
                ]);

                $this->resetHookableCacheEntries($this->fields['directory']);

                // Initialize session for the plugin
                if (
                    isset($PLUGIN_HOOKS[Hooks::INIT_SESSION][$this->fields['directory']])
                    && is_callable($PLUGIN_HOOKS[Hooks::INIT_SESSION][$this->fields['directory']])
                ) {
                    call_user_func($PLUGIN_HOOKS[Hooks::INIT_SESSION][$this->fields['directory']]);
                }

                // Initialize profile for the plugin
                if (
                    isset($PLUGIN_HOOKS[Hooks::CHANGE_PROFILE][$this->fields['directory']])
                    && is_callable($PLUGIN_HOOKS[Hooks::CHANGE_PROFILE][$this->fields['directory']])
                    && isset($_SESSION['glpiactiveprofile'])
                ) {
                    call_user_func($PLUGIN_HOOKS[Hooks::CHANGE_PROFILE][$this->fields['directory']]);
                }
                // reset menu
                if (isset($_SESSION['glpimenu'])) {
                    unset($_SESSION['glpimenu']);
                }
                self::doHook(Hooks::POST_PLUGIN_ENABLE, $this->fields['directory']);

                Session::addMessageAfterRedirect(
                    htmlescape(sprintf(__('Plugin %1$s has been activated!'), $this->fields['name'])),
                    true,
                    INFO
                );

                Event::log(
                    '',
                    Plugin::class,
                    3,
                    "setup",
                    sprintf(
                        __('Plugin %1$s has been activated by %2$s.'),
                        $this->fields['name'],
                        User::getNameForLog(Session::getLoginUserID(true))
                    )
                );

                return true;
            } else {
                $this->unload($this->fields['directory']);

                Session::addMessageAfterRedirect(
                    htmlescape(sprintf(__('Plugin %1$s configuration must be done, it cannot be activated.'), $this->fields['name'])),
                    true,
                    ERROR
                );
                return false;
            }
        }

        Session::addMessageAfterRedirect(
            htmlescape(sprintf(__('Plugin %1$s not found!'), $ID)),
            true,
            ERROR
        );
        return false;
    }

    /**
     * Suspend execution of all active plugins.
     */
    final public function suspendAllPluginsExecution(): bool
    {
        Config::setConfigurationValues('core', ['plugins_execution_mode' => Plugin::EXECUTION_MODE_SUSPENDED_MANUALLY]);

        return true; // TODO Make the Config::setConfigurationValues() return a success boolean
    }

    /**
     * Resume execution of all suspended plugins.
     */
    final public function resumeAllPluginsExecution(): bool
    {
        Config::setConfigurationValues('core', ['plugins_execution_mode' => Plugin::EXECUTION_MODE_ON]);

        return true; // TODO Make the Config::setConfigurationValues() return a success boolean
    }

    /**
     * Unactivate a plugin
     *
     * @param integer $ID ID of the plugin (The `id` field, not directory)
     *
     * @return boolean
     **/
    public function unactivate($ID)
    {
        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Executing a plugin maintenance method is forbidden when plugins execution is suspended.');
        }

        if ($this->getFromDB($ID)) {
            // Load plugin hooks
            self::load($this->fields['directory'], true);

            $deactivate_function = 'plugin_' . $this->fields['directory'] . '_deactivate';
            if (function_exists($deactivate_function)) {
                $deactivate_function();
            }

            $this->update([
                'id'    => $ID,
                'state' => self::NOTACTIVATED,
            ]);
            $this->unload($this->fields['directory']);

            self::doHook(Hooks::POST_PLUGIN_DISABLE, $this->fields['directory']);

            Session::addMessageAfterRedirect(
                sprintf(__('Plugin %1$s has been deactivated!'), $this->fields['name']),
                true,
                INFO
            );

            Event::log(
                '',
                Plugin::class,
                3,
                "setup",
                sprintf(
                    __('Plugin %1$s has been deactivated by %2$s.'),
                    $this->fields['name'],
                    User::getNameForLog(Session::getLoginUserID(true))
                )
            );

            return true;
        }

        Session::addMessageAfterRedirect(
            htmlescape(sprintf(__('Plugin %1$s not found!'), $ID)),
            true,
            ERROR
        );

        return false;
    }

    /**
     * clean a plugin
     *
     * @param int $ID ID of the plugin
     **/
    public function clean($ID)
    {
        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Executing a plugin maintenance method is forbidden when plugins execution is suspended.');
        }

        if ($this->getFromDB($ID)) {
            $this->unload($this->fields['directory']);
            $log_message = sprintf(__('Plugin %1$s cleaned!'), $this->fields['directory']);
            self::doHook(Hooks::POST_PLUGIN_CLEAN, $this->fields['directory']);
            $this->delete(['id' => $ID]);

            Event::log(
                '',
                Plugin::class,
                3,
                "setup",
                $log_message
            );
        }
    }


    /**
     * Is a plugin activated ?
     *
     * @phpstan-impure
     *
     * @param string $directory  Plugin directory
     *
     * @return boolean
     */
    public function isActivated($directory)
    {
        if (!self::$plugins_initialized) {
            // `$activated_plugins` content will not be reliable until plugins have been initialized.
            // In this case, plugins states have to be fetched from DB.
            $self = new self();
            return $self->getFromDBbyDir($directory)
                && $self->fields['state'] == self::ACTIVATED
                && $self->isLoadable($directory);
        }

        // Make a lowercase comparison, as sometime this function is called based on
        // extraction of plugin name from a classname, which does not use same naming rules than directories.
        $activated_plugins = array_map('strtolower', self::$activated_plugins);
        $directory = strtolower($directory);

        return in_array($directory, $activated_plugins);
    }


    /**
     * Is a plugin updatable ?
     *
     * @param string $directory  Plugin directory
     *
     * @return boolean
     */
    public function isUpdatable($directory)
    {
        // Make a lowercase comparison, as sometime this function is called based on
        // extraction of plugin name from a classname, which does not use same naming rules than directories.
        $activated_plugins = array_map('strtolower', self::$activated_plugins);
        if (in_array(strtolower($directory), $activated_plugins)) {
            // If plugin is marked as activated, no need to query DB on this case.
            return false;
        }

        // If plugin is not marked as activated, check on DB as it may have not been loaded yet.
        if ($this->getFromDBbyDir($directory)) {
            return ($this->fields['state'] == self::NOTUPDATED) && $this->isLoadable($directory);
        }

        return false;
    }


    /**
     * Is a plugin loadable ?
     *
     * @param string $directory  Plugin directory
     *
     * @return boolean
     */
    public function isLoadable($directory)
    {
        $plugin_dir = Plugin::getPhpDir($directory);

        if ($plugin_dir === false) {
            return false;
        }

        $setup_file_path = $plugin_dir . '/setup.php';

        return file_exists($setup_file_path) && is_readable($setup_file_path);
    }


    /**
     * Is a plugin installed ?
     *
     * @phpstan-impure
     *
     * @param string $directory  Plugin directory
     *
     * @return boolean
     */
    public function isInstalled($directory)
    {
        if ($this->isActivated($directory)) {
            // If plugin is activated, it is de facto installed.
            // No need to query DB on this case.
            return true;
        }

        if ($this->getFromDBbyDir($directory)) {
            return $this->isLoadable($directory)
                && in_array($this->fields['state'], [self::ACTIVATED, self::TOBECONFIGURED, self::NOTACTIVATED]);
        }

        return false;
    }


    /**
     * Get system information
     *
     * @return array
     * @phpstan-return array{label: string, content: string}
     */
    public function getSystemInformation()
    {
        // No need to translate, this part always display in english (for copy/paste to forum)
        $content = '';

        $plug     = new Plugin();
        $pluglist = $plug->find([], "name, directory");
        foreach ($pluglist as $plugin) {
            $name = Toolbox::stripTags($plugin['name']);
            $version = Toolbox::stripTags($plugin['version']);
            $state = $plug->isLoadable($plugin['directory']) ? $plugin['state'] : self::TOBECLEANED;
            $state = self::getState($state);
            $is_marketplace = file_exists(GLPI_MARKETPLACE_DIR . "/" . $plugin['directory']);
            $install_method = $is_marketplace ? "Marketplace" : "Manual";

            $msg  = substr(str_pad($plugin['directory'], 30), 0, 20) .
                 " Name: " . Toolbox::substr(str_pad($name, 40), 0, 30) .
                 " Version: " . str_pad($version, 10) .
                 " State: " . str_pad($state, 40) .
                 " Install Method: " . $install_method;
            $content .= "\n" . $msg;
        }
        return [
            'label' => 'Plugins list',
            'content' => $content,
        ];
    }


    /**
     * Define a new class managed by a plugin
     *
     * @param string $itemtype Class name
     * @param array  $attrib   Array of attributes, a hashtable with index in
     *                         (classname, typename, reservation_types)
     *
     * @return bool
     **/
    public static function registerClass($itemtype, $attrib = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $plug = isPluginItemType($itemtype);
        if (!$plug) {
            return false;
        }

        // Handle `linkuser_types`/`linkgroup_types`/`linkuser_tech_types`/`linkgroup_tech_types` deprecation
        $is_assignable = false;
        foreach (['linkuser_types', 'linkgroup_types', 'linkuser_tech_types', 'linkgroup_tech_types'] as $cfg_key) {
            if (isset($attrib[$cfg_key]) && $attrib[$cfg_key]) {
                Toolbox::deprecated(sprintf('`%s` configuration is deprecated, use `%s` instead.', $cfg_key, 'assignable_types'));
                $is_assignable = true;
                break;
            }
        }
        if ($is_assignable) {
            $attrib['assignable_types'] = true;
        }

        $all_types = preg_grep('/.+_types/', array_keys($CFG_GLPI));
        $all_types[] = 'networkport_instantiations';

        $blacklist = ['device_types'];
        foreach ($all_types as $att) {
            if (in_array($att, $blacklist) || !isset($attrib[$att])) {
                continue;
            }
            if ($attrib[$att]) {
                $CFG_GLPI[$att][] = $itemtype;
            }
            unset($attrib[$att]);
        }

        if (
            isset($attrib['device_types']) && $attrib['device_types']
            && method_exists($itemtype, 'getItem_DeviceType')
        ) {
            if (class_exists($itemtype::getItem_DeviceType())) {
                $CFG_GLPI['device_types'][] = $itemtype;
            }
            unset($attrib['device_types']);
        }

        if (isset($attrib['addtabon'])) {
            if (!is_array($attrib['addtabon'])) {
                $attrib['addtabon'] = [$attrib['addtabon']];
            }
            foreach ($attrib['addtabon'] as $form) {
                CommonGLPI::registerStandardTab($form, $itemtype);
            }
            unset($attrib['addtabon']);
        }

        //Manage entity forward from a source itemtype to this itemtype
        if (isset($attrib['forwardentityfrom'])) {
            CommonDBTM::addForwardEntity($attrib['forwardentityfrom'], $itemtype);
            unset($attrib['forwardentityfrom']);
        }

        // Handle plugins specific configurations
        foreach ($attrib as $key => $value) {
            if (preg_match('/^plugin[a-z]+_types$/', $key)) {
                if ($value) {
                    if (!array_key_exists($key, $CFG_GLPI)) {
                        $CFG_GLPI[$key] = [];
                    }
                    $CFG_GLPI[$key][] = $itemtype;
                }
                unset($attrib[$key]);
            }
        }

        // Warn for unmanaged keys
        if (!empty($attrib)) {
            trigger_error(
                sprintf(
                    'Unknown attributes "%s" used in "%s" class registration',
                    implode('", "', array_keys($attrib)),
                    $itemtype
                ),
                E_USER_WARNING
            );
        }

        return true;
    }


    /**
     * This function executes a hook.
     *
     * @param string  $name   Name of hook to fire
     * @param mixed   $param  Parameters if needed : if object limit to the itemtype (default NULL)
     *
     * @return mixed $data
     **/
    public static function doHook($name, $param = null)
    {
        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;

        if ($param == null) {
            $data = func_get_args();
        } else {
            $data = $param;
        }

        if ((new Plugin())->isPluginsExecutionSuspended()) {
            return $data;
        }

        // Apply hook only for the item
        if (($param != null) && is_object($param)) {
            $itemtype = get_class($param);
            if (isset($PLUGIN_HOOKS[$name]) && is_array($PLUGIN_HOOKS[$name])) {
                foreach ($PLUGIN_HOOKS[$name] as $plugin_key => $tab) {
                    if (!Plugin::isPluginActive($plugin_key)) {
                        continue;
                    }

                    if ($name === Hooks::SHOW_IN_TIMELINE) { // @phpstan-ignore classConstant.deprecated
                        Toolbox::deprecated('`show_in_timeline` hook is deprecated, use `timeline_items` instead.');
                    }

                    if (isset($tab[$itemtype])) {
                        Profiler::getInstance()->start("{$plugin_key}:{$name}", Profiler::CATEGORY_PLUGINS);
                        self::includeHook($plugin_key);
                        if (is_callable($tab[$itemtype])) {
                            call_user_func($tab[$itemtype], $data);
                        }
                        Profiler::getInstance()->stop("{$plugin_key}:{$name}");
                    }
                }
            }
        } else { // Standard hook call
            if (isset($PLUGIN_HOOKS[$name]) && is_array($PLUGIN_HOOKS[$name])) {
                foreach ($PLUGIN_HOOKS[$name] as $plugin_key => $function) {
                    if (!Plugin::isPluginActive($plugin_key)) {
                        continue;
                    }

                    Profiler::getInstance()->start("{$plugin_key}:{$name}", Profiler::CATEGORY_PLUGINS);
                    self::includeHook($plugin_key);
                    if (is_callable($function)) {
                        call_user_func($function, $data);
                    }
                    Profiler::getInstance()->stop("{$plugin_key}:{$name}");
                }
            }
        }
        /* Variable-length argument lists have a slight problem when */
        /* passing values by reference. Pity. This is a workaround.  */
        return $data;
    }


    /**
     * This function executes a hook.
     *
     * @param string $name   Name of hook to fire
     * @param mixed  $parm   Parameters (default NULL)
     *
     * @return mixed $data
     **/
    public static function doHookFunction($name, $parm = null)
    {
        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;

        if ((new Plugin())->isPluginsExecutionSuspended()) {
            return $parm;
        }

        $ret = $parm;
        if (isset($PLUGIN_HOOKS[$name]) && is_array($PLUGIN_HOOKS[$name])) {
            foreach ($PLUGIN_HOOKS[$name] as $plugin_key => $function) {
                if (!Plugin::isPluginActive($plugin_key)) {
                    continue;
                }

                self::includeHook($plugin_key);
                if (is_callable($function)) {
                    $ret = call_user_func($function, $ret);
                }
            }
        }
        /* Variable-length argument lists have a slight problem when */
        /* passing values by reference. Pity. This is a workaround.  */
        return $ret;
    }


    /**
     * This function executes a hook for 1 plugin.
     *
     * @param string          $plugin_key System name of the plugin
     * @param string|callable $hook     suffix used to build function to be called ("plugin_myplugin_{$hook}")
     *                                  or callable function
     * @param mixed           ...$args  [optional] One or more arguments passed to hook function
     *
     * @return mixed $data
     **/
    public static function doOneHook($plugin_key, $hook, ...$args)
    {
        if ((new Plugin())->isPluginsExecutionSuspended()) {
            return;
        }

        $plugin_key = strtolower($plugin_key);

        if (!Plugin::isPluginActive($plugin_key)) {
            return;
        }

        self::includeHook($plugin_key);

        if (is_string($hook) && !is_callable($hook)) {
            $hook = "plugin_" . $plugin_key . "_" . $hook;
        }

        if (is_callable($hook)) {
            return call_user_func_array($hook, $args);
        }
    }


    /**
     * Get dropdowns for plugins
     *
     * @return array Array containing plugin dropdowns
     **/
    public static function getDropdowns()
    {
        if ((new Plugin())->isPluginsExecutionSuspended()) {
            return [];
        }

        $dps = [];
        foreach (self::getPlugins() as $plug) {
            $tab = self::doOneHook($plug, Hooks::AUTO_GET_DROPDOWN);
            if (is_array($tab)) {
                $dps = array_merge($dps, [self::getInfo($plug, 'name') => $tab]);
            }
        }
        return $dps;
    }


    /**
     * Get information from a plugin
     *
     * @param string $plugin System name (Plugin directory)
     * @param string $info   Wanted info (name, version, ...), NULL for all
     *
     * @since 0.84
     *
     * @return string|array The specific information value requested or an array of all information if $info is null.
     **/
    public static function getInfo($plugin, $info = null)
    {

        $fct = 'plugin_version_' . strtolower($plugin);
        if (function_exists($fct)) {
            $res = $fct();
            if (!isset($res['requirements']) && isset($res['minGlpiVersion'])) {
                $res['requirements'] = ['glpi' => ['min' => $res['minGlpiVersion']]];
            }
        } else {
            trigger_error("$fct method must be defined!", E_USER_WARNING);
            $res = [];
        }
        if (isset($info)) {
            return ($res[$info] ?? '');
        }
        return $res;
    }

    /**
     * Get plugin files version.
     *
     * @param string $key
     *
     * @return string|null
     */
    public static function getPluginFilesVersion(string $key): ?string
    {
        return (new self())->getInformationsFromDirectory($key, false)['version'] ?? null;
    }

    /**
     * Returns plugin information from directory.
     *
     * @param string $directory
     * @param bool $with_lang
     *
     * @return array
     */
    public function getInformationsFromDirectory($directory, bool $with_lang = true)
    {
        if (!$this->loadPluginSetupFile($directory)) {
            return [];
        }

        if ($with_lang) {
            self::loadLang($directory);
        }
        return self::getInfo($directory);
    }

    /**
     * Returns plugin options.
     *
     * @param string $plugin_key
     *
     * @return array
     */
    public function getPluginOptions(string $plugin_key): array
    {
        if (!$this->loadPluginSetupFile($plugin_key)) {
            return [];
        }

        $options_callable = sprintf('plugin_%s_options', $plugin_key);
        if (!function_exists($options_callable)) {
            return [];
        }

        $options = $options_callable();
        if (!is_array($options)) {
            trigger_error(
                sprintf('Invalid "options" key provided by plugin `plugin_%s_options()` method.', $plugin_key),
                E_USER_WARNING
            );
            return [];
        }

        return $options;
    }

    /**
     * Returns plugin option.
     *
     * @param string $plugin_key
     * @param string $option_key
     * @param mixed  $default_value
     *
     * @return array
     */
    public function getPluginOption(string $plugin_key, string $option_key, $default_value = null)//: mixed
    {
        $options = $this->getPluginOptions($plugin_key);
        return array_key_exists($option_key, $options)
            ? $options[$option_key]
            : $default_value;
    }

    /**
     * Load plugin setup file.
     *
     * @param string $plugin_key
     *
     * @return bool
     */
    private function loadPluginSetupFile(string $plugin_key): bool
    {
        if ($this->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Fetching plugin information from its setup file is forbidden when plugins execution is suspended.');
        }

        if (preg_match(self::PLUGIN_KEY_PATTERN, $plugin_key) !== 1) {
            // Prevent issues with illegal chars
            return false;
        }

        foreach (GLPI_PLUGINS_DIRECTORIES as $base_dir) {
            if (!is_dir($base_dir)) {
                continue;
            }
            $file_path = sprintf('%s/%s/setup.php', $base_dir, $plugin_key);

            if (file_exists($file_path)) {
                // Includes are made inside a function to prevent included files to override
                // variables used in this function.
                // For example, if the included files contains a $key variable, it will
                // replace the $key variable used here.
                $include_fct = function () use ($file_path) {
                    include_once($file_path);
                };
                $include_fct();
                return true;
            }
        }
        return false;
    }

    /**
     * Get database relations for plugins
     *
     * @return array Array containing plugin database relations
     **/
    public static function getDatabaseRelations()
    {
        if ((new Plugin())->isPluginsExecutionSuspended()) {
            return [];
        }

        $dps = [];
        foreach (self::getPlugins() as $plugin_key) {
            self::includeHook($plugin_key);
            $function2 = "plugin_" . $plugin_key . "_getDatabaseRelations";
            if (function_exists($function2)) {
                $dps = array_merge_recursive($dps, $function2());
            }
        }
        return $dps;
    }


    /**
     * Get additional search options managed by plugins
     *
     * @param $itemtype
     *
     * @return array Array containing plugin search options for given type
     **/
    public static function getAddSearchOptions($itemtype)
    {
        if ((new Plugin())->isPluginsExecutionSuspended()) {
            return [];
        }

        $sopt = [];
        foreach (self::getPlugins() as $plugin_key) {
            self::includeHook($plugin_key);
            $function = "plugin_" . $plugin_key . "_getAddSearchOptions";
            if (function_exists($function)) {
                $tmp = $function($itemtype);
                if (is_array($tmp) && count($tmp)) {
                    $sopt += $tmp;
                }
            }
        }
        return $sopt;
    }


    /**
     * Include the hook file for a plugin
     *
     * @param string $plugin_key
     */
    public static function includeHook(string $plugin_key = "")
    {
        if ((new Plugin())->isPluginsExecutionSuspended()) {
            throw new RuntimeException('Including plugin hook files is forbidden when plugins execution is suspended.');
        }

        foreach (GLPI_PLUGINS_DIRECTORIES as $base_dir) {
            if (file_exists("$base_dir/$plugin_key/hook.php")) {
                include_once("$base_dir/$plugin_key/hook.php");
                break;
            }
        }
    }


    /**
     * Get additional search options managed by plugins
     *
     * @since 9.2
     *
     * @param string $itemtype Item type
     *
     * @return array An *indexed* array of search options
     *
     * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
     **/
    public static function getAddSearchOptionsNew($itemtype)
    {
        if ((new Plugin())->isPluginsExecutionSuspended()) {
            return [];
        }

        $options = [];

        foreach (self::getPlugins() as $plugin_key) {
            self::includeHook($plugin_key);
            $function = "plugin_" . $plugin_key . "_getAddSearchOptionsNew";
            if (function_exists($function)) {
                $tmp = $function($itemtype);
                foreach ($tmp as $opt) {
                    if (!isset($opt['id'])) {
                        throw new Exception($itemtype . ': invalid search option! ' . print_r($opt, true));
                    }
                    $optid = $opt['id'];
                    unset($opt['id']);

                    if (isset($options[$optid])) {
                        $message = "Duplicate key $optid ({$options[$optid]['name']}/{$opt['name']}) in " .
                        $itemtype . " searchOptions!";
                        trigger_error($message, E_USER_WARNING);
                    }

                    foreach ($opt as $k => $v) {
                        $options[$optid][$k] = $v;
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Get an internationalized message for incompatible plugins (either core or php version)
     *
     * @param string $type Either 'php' or 'core', defaults to 'core'
     * @param string $min  Minimal required version
     * @param string $max  Maximal required version
     *
     * @since 9.2
     *
     * @return string
     */
    public static function messageIncompatible($type = 'core', $min = null, $max = null)
    {
        $type = ($type === 'core' ? __('GLPI') : __('PHP'));
        if ($min === null && $max !== null) {
            return sprintf(
                __('This plugin requires %1$s < %2$s.'),
                $type,
                $max
            );
        } elseif ($min !== null && $max === null) {
            return sprintf(
                __('This plugin requires %1$s >= %2$s.'),
                $type,
                $min
            );
        } else {
            return sprintf(
                __('This plugin requires %1$s >= %2$s and < %3$s.'),
                $type,
                $min,
                $max
            );
        }
    }

    /**
     * Get an internationalized message for missing requirement (extension, other plugin, ...)
     *
     * @param string $type Type of what is missing, one of:
     *                     - ext (PHP module)
     *                     - plugin (other plugin)
     *                     - compil (compilation option)
     *                     - param (GLPI configuration parameter)
     * @param string $name Missing name
     *
     * @since 9.2
     *
     * @return string
     */
    public static function messageMissingRequirement($type, $name)
    {
        switch ($type) {
            case 'ext':
                return sprintf(
                    __('This plugin requires PHP extension %1$s'),
                    $name
                );
            case 'plugin':
                return sprintf(
                    __('This plugin requires %1$s plugin'),
                    $name
                );
            case 'compil':
                return sprintf(
                    __('This plugin requires PHP compiled along with "%1$s"'),
                    $name
                );
            case 'param':
                return sprintf(
                    __('This plugin requires PHP parameter %1$s'),
                    $name
                );
            case 'glpiparam':
                return sprintf(
                    __('This plugin requires GLPI parameter %1$s'),
                    $name
                );
            default:
                throw new RuntimeException("messageMissing type $type is unknown!");
        }
    }

    /**
     * Check declared versions (GLPI, PHP, ...)
     *
     * @since 9.2
     *
     * @param string $name System name (Plugin directory)
     *
     * @return boolean
     */
    public function checkVersions($name)
    {
        $infos = self::getInfo($name);
        $ret = true;
        if (isset($infos['requirements'])) {
            if (isset($infos['requirements']['glpi'])) {
                $glpi = $infos['requirements']['glpi'];
                if (isset($glpi['min']) || isset($glpi['max'])) {
                    $ret = $this->checkGlpiVersion($infos['requirements']['glpi']);
                }
                if (isset($glpi['params'])) {
                    $ret = $ret && $this->checkGlpiParameters($glpi['params']);
                }
                if (isset($glpi['plugins'])) {
                    $ret = $ret && $this->checkGlpiPlugins($glpi['plugins']);
                }
            }
            if (isset($infos['requirements']['php'])) {
                $php = $infos['requirements']['php'];
                if (isset($php['min']) || isset($php['max'])) {
                    $ret = $ret && $this->checkPhpVersion($php);
                }
                if (isset($php['exts'])) {
                    $ret = $ret && $this->checkPhpExtensions($php['exts']);
                }
                if (isset($php['params'])) {
                    $ret = $ret && $this->checkPhpParameters($php['params']);
                }
            }
        }
        return $ret;
    }

    /**
     * Check for GLPI version
     *
     * @since 9.2
     * @since 9.3 Removed the 'dev' key of $info parameter.
     *
     * @param array $infos Requirements infos:
     *                     - min: minimal supported version,
     *                     - max: maximal supported version
     *                     One of min or max is required.
     *
     * @return boolean
     */
    public function checkGlpiVersion($infos)
    {
        if (!isset($infos['min']) && !isset($infos['max'])) {
            throw new LogicException('Either "min" or "max" is required for GLPI requirements!');
        }

        $glpiVersion = $this->getGlpiVersion();

        $compat = true;
        if (isset($infos['min']) && !version_compare($glpiVersion, $infos['min'], '>=')) {
            $compat = false;
        }
        if (isset($infos['max']) && !version_compare($glpiVersion, $infos['max'], '<')) {
            $compat = false;
        }

        if (!$compat) {
            echo htmlescape(Plugin::messageIncompatible(
                'core',
                ($infos['min'] ?? null),
                ($infos['max'] ?? null)
            ));
        }

        return $compat;
    }

    /**
     * Check for PHP version
     *
     * @since 9.2
     *
     * @param array $infos Requirements infos:
     *                     - min: minimal supported version,
     *                     - max: maximal supported version.
     *                     One of min or max is required.
     *
     * @return boolean
     */
    public function checkPhpVersion($infos)
    {
        $compat = true;

        if (isset($infos['min']) && isset($infos['max'])) {
            $compat = !(version_compare($this->getPhpVersion(), $infos['min'], 'lt') || version_compare($this->getPhpVersion(), $infos['max'], 'ge'));
        } elseif (isset($infos['min'])) {
            $compat = !(version_compare($this->getPhpVersion(), $infos['min'], 'lt'));
        } elseif (isset($infos['max'])) {
            $compat = !(version_compare($this->getPhpVersion(), $infos['max'], 'ge'));
        } else {
            throw new LogicException('Either "min" or "max" is required for PHP requirements!');
        }

        if (!$compat) {
            echo htmlescape(Plugin::messageIncompatible(
                'php',
                ($infos['min'] ?? null),
                ($infos['max'] ?? null)
            ));
        }

        return $compat;
    }


    /**
     * Check fo required PHP extensions
     *
     * @since 9.2
     *
     * @param array $exts Extensions lists/config @see Config::checkExtensions()
     *
     * @return boolean
     */
    public function checkPhpExtensions($exts)
    {
        $report = Config::checkExtensions($exts);
        if (count($report['missing'])) {
            foreach (array_keys($report['missing']) as $ext) {
                echo htmlescape(self::messageMissingRequirement('ext', $ext)) . '<br/>';
            }
            return false;
        }
        return true;
    }


    /**
     * Check expected GLPI parameters
     *
     * @since 9.2
     *
     * @param array $params Expected parameters to be setup
     *
     * @return boolean
     */
    public function checkGlpiParameters($params)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $compat = true;
        foreach ($params as $param) {
            if (!isset($CFG_GLPI[$param]) || trim($CFG_GLPI[$param]) == '' || !$CFG_GLPI[$param]) {
                echo htmlescape(self::messageMissingRequirement('glpiparam', $param)) . '<br/>';
                $compat = false;
            }
        }

        return $compat;
    }


    /**
     * Check expected PHP parameters
     *
     * @since 9.2
     *
     * @param array $params Expected parameters to be setup
     *
     * @return boolean
     */
    public function checkPhpParameters($params)
    {
        $compat = true;
        foreach ($params as $param) {
            if (!ini_get($param) || trim(ini_get($param)) == '') {
                echo htmlescape(self::messageMissingRequirement('param', $param)) . '<br/>';
                $compat = false;
            }
        }

        return $compat;
    }


    /**
     * Check expected GLPI plugins
     *
     * @since 9.2
     *
     * @param array $plugins Expected plugins
     *
     * @return boolean
     */
    public function checkGlpiPlugins($plugins)
    {
        $compat = true;
        foreach ($plugins as $plugin) {
            if (!$this->isActivated($plugin)) {
                echo htmlescape(self::messageMissingRequirement('plugin', $plugin)) . '<br/>';
                $compat = false;
            }
        }

        return $compat;
    }


    /**
     * Get GLPI version
     * Used from unit tests to mock.
     *
     * @since 9.2
     *
     * @return string
     */
    public function getGlpiVersion()
    {
        return VersionParser::getNormalizedVersion(GLPI_VERSION, false);
    }

    /**
     * Get PHP version
     * Used from unit tests to mock.
     *
     * @since 9.2
     *
     * @return string
     */
    public function getPhpVersion()
    {
        return PHP_VERSION;
    }

    /**
     * Return label for an integer plugin state
     *
     * @since 9.3
     *
     * @param  integer $state see this class constants (ex self::ANEW, self::ACTIVATED)
     * @return string  the label
     */
    public static function getState($state = 0)
    {
        switch ($state) {
            case self::ANEW:
                return _x('status', 'New');

            case self::ACTIVATED:
                return _x('plugin', 'Enabled');

            case self::NOTINSTALLED:
                return _x('plugin', 'Not installed');

            case self::NOTUPDATED:
                return __('To update');

            case self::TOBECONFIGURED:
                return _x('plugin', 'Installed / not configured');

            case self::NOTACTIVATED:
                return _x('plugin', 'Installed / not activated');

            case self::REPLACED:
                return _x('plugin', 'Replaced');
        }

        return __('Error / to clean');
    }


    /**
     * Return key for an integer plugin state
     * purpose is to have a corresponding css class name
     *
     * @since 9.5
     *
     * @param  integer $state see this class constants (ex self::ANEW, self::ACTIVATED)
     * @return string  the key
     */
    public static function getStateKey(int $state = 0): string
    {
        switch ($state) {
            case self::ANEW:
                return "new";

            case self::ACTIVATED:
                return "activated";

            case self::NOTINSTALLED:
                return "notinstalled";

            case self::NOTUPDATED:
                return "notupdated";

            case self::TOBECONFIGURED:
                return "tobeconfigured";

            case self::NOTACTIVATED:
                return "notactived";
        }

        return "";
    }

    /**
     * Get plugins list
     *
     * @since 9.3.2
     *
     * @return array
     */
    public static function getPlugins()
    {
        return self::$activated_plugins;
    }

    /**
     * Check if a plugin is loaded
     *
     * @since 9.3.2
     *
     * @param string $plugin_key  System name (Plugin directory)
     *
     * @return boolean
     */
    public static function isPluginLoaded($plugin_key)
    {
        // Make a lowercase comparison, as sometime this function is called based on
        // extraction of plugin name from a classname, which does not use same naming rules than directories.
        $loadedPlugins = array_map('strtolower', self::$loaded_plugins);
        return in_array(strtolower($plugin_key), $loadedPlugins);
    }

    /**
     * Check if a plugin is active
     *
     * @since 9.5.0
     *
     * @param string $plugin_key  System name (Plugin directory)
     *
     * @return boolean
     */
    public static function isPluginActive($plugin_key)
    {
        $plugin = new self();
        return $plugin->isActivated($plugin_key);
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
            'table'              => $this->getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'specific',
            'massiveaction'      => false, // implicit key==1
            'additionalfields'   => ['state', 'directory'],
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'directory',
            'name'               => __('Directory'),
            'massiveaction'      => false,
            'nosearch'           => true,
            'noremove'           => true,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'version',
            'name'               => _n('Version', 'Versions', 1),
            'datatype'           => 'specific',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => $this->getTable(),
            'field'              => 'license',
            'name'               => SoftwareLicense::getTypeName(1),
            'datatype'           => 'specific',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => $this->getTable(),
            'field'              => 'state',
            'name'               => __('Status'),
            'searchtype'         => 'equals',
            'noremove'           => true,
            'additionalfields'   => ['directory'],
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'author',
            'name'               => __('Authors'),
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '7',
            'table'              => $this->getTable(),
            'field'              => 'homepage',
            'name'               => __('Website'),
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => $this->getTable(),
            'field'              => 'id',
            'name'               => _n('Action', 'Actions', Session::getPluralNumber()),
            'massiveaction'      => false,
            'nosearch'           => true,
            'datatype'           => 'specific',
            'noremove'           => true,
            'additionalfields'   => ['directory'],
        ];

        return $tab;
    }


    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        /**
         * @var array $CFG_GLPI
         * @var array $PLUGIN_HOOKS
         */
        global $CFG_GLPI, $PLUGIN_HOOKS;

        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'id':
                if ((new Plugin())->isPluginsExecutionSuspended()) {
                    // Do not show actions if the plugins execution is suspended.
                    // These actions would require to load the plugin, we do not want this to happen.
                    return \sprintf(
                        '<span class="text-info" data-bs-toggle="tooltip" title="%s"><i class="ti ti-info-circle-filled"></i></span>',
                        __s('The plugins maintenance actions are disabled when the plugins execution is suspended.')
                    );
                }

                //action...
                $ID = $values[$field];

                $plugin = new self();
                $plugin->getFromDB($ID);

                $directory = $plugin->fields['directory'];
                $state = (int) $plugin->fields['state'];

                if ($plugin->isLoadable($directory)) {
                    self::load($directory, true);
                } else {
                    $state = self::TOBECLEANED;
                }

                $output = '';

                if (
                    in_array($state, [self::ACTIVATED, self::TOBECONFIGURED], true)
                    && isset($PLUGIN_HOOKS[Hooks::CONFIG_PAGE][$directory])
                ) {
                    // Configuration button for activated or configurable plugins
                    $config_url = "{$CFG_GLPI['root_doc']}/plugins/{$directory}/{$PLUGIN_HOOKS[Hooks::CONFIG_PAGE][$directory]}";
                    $output .= '<a href="' . $config_url . '" title="' . __s('Configure') . '">'
                    . '<i class="ti ti-tool fs-2x"></i>'
                    . '<span class="sr-only">' . __s('Configure') . '</span>'
                    . '</a>'
                    . '&nbsp;';
                }

                if ($state === self::ACTIVATED) {
                    // Deactivate button for active plugins
                    $output .= Html::getSimpleForm(
                        static::getFormURL(),
                        ['action' => 'unactivate'],
                        _x('button', 'Disable'),
                        ['id' => $ID],
                        'ti-toggle-right-filled fs-2x enabled'
                    ) . '&nbsp;';
                } elseif ($state === self::NOTACTIVATED) {
                    // Activate button for configured and up to date plugins
                    ob_start();
                    $do_activate = $plugin->checkVersions($directory);
                    if (!$do_activate) {
                        $output .= "<span class='error'>" . ob_get_contents() . "</span>";
                    }
                    ob_end_clean();
                    $function = 'plugin_' . $directory . '_check_prerequisites';
                    if (function_exists($function) && $do_activate) {
                        ob_start();
                        $do_activate = $function();
                        if (!$do_activate) {
                            $output .= '<span class="error">' . ob_get_contents() . '</span>';
                        }
                        ob_end_clean();
                    }
                    if ($do_activate) {
                        $output .= Html::getSimpleForm(
                            static::getFormURL(),
                            ['action' => 'activate'],
                            _x('button', 'Enable'),
                            ['id' => $ID],
                            'ti-toggle-left-filled fs-2x disabled'
                        ) . '&nbsp;';
                    }
                }

                if (in_array($state, [self::ANEW, self::NOTINSTALLED, self::NOTUPDATED], true)) {
                    // Install button for new, not installed or not up to date plugins
                    if (function_exists("plugin_" . $directory . "_install")) {
                        $function   = 'plugin_' . $directory . '_check_prerequisites';

                        ob_start();
                        $do_install = $plugin->checkVersions($directory);
                        if (!$do_install) {
                            $output .= "<span class='error'>" . ob_get_contents() . "</span>";
                        }
                        ob_end_clean();

                        if ($do_install && function_exists($function)) {
                            ob_start();
                            $do_install = $function();
                            $msg = '';
                            if (!$do_install) {
                                $msg = '<span class="error">' . ob_get_contents() . '</span>';
                            }
                            ob_end_clean();
                            $output .= $msg;
                        }
                        if ($state == self::NOTUPDATED) {
                            $msg = _x('button', 'Upgrade');
                            $output .= TemplateRenderer::getInstance()->render('components/plugin_update_modal.html.twig', [
                                'plugin_name' => $plugin->getField('name'),
                                'to_version' => $plugin->getField('version'),
                                'modal_id' => 'updateModal' . $plugin->getField('directory'),
                                'open_btn' => '<a class="pointer"><span class="ti ti-caret-up fs-2x me-1"
                                                          data-bs-toggle="modal"
                                                          data-bs-target="#updateModal' . $plugin->getField('directory') . '"
                                                          title="' . __s("Update") . '">
                                                          <span class="sr-only">' . __s("Update") . '</span>
                                                      </span></a>',
                                'update_btn' => Html::getSimpleForm(
                                    static::getFormURL(),
                                    ['action' => 'install'],
                                    $msg,
                                    ['id' => $ID],
                                    '',
                                    'class="btn btn-warning w-100"'
                                ),
                            ]);
                        } else {
                            $msg = _x('button', 'Install');
                            if ($do_install) {
                                $output .= Html::getSimpleForm(
                                    static::getFormURL(),
                                    ['action' => 'install'],
                                    $msg,
                                    ['id' => $ID],
                                    'ti-folder-plus fs-2x me-1'
                                );
                            }
                        }
                    } else {
                        $missing = '';
                        $missing .= "plugin_" . $directory . "_install";

                        //TRANS: %s is the list of missing functions
                        $output .= sprintf(
                            __('%1$s: %2$s'),
                            __('Non-existent function'),
                            $missing
                        );
                    }
                }
                if (in_array($state, [self::ACTIVATED, self::NOTUPDATED, self::TOBECONFIGURED, self::NOTACTIVATED], true)) {
                    // Uninstall button for installed plugins
                    if (function_exists("plugin_" . $directory . "_uninstall")) {
                        $uninstall_label = __s("Uninstall");
                        $output .= <<<TWIG
                            <a class="pointer"><span class="ti ti-folder-minus fs-2x me-1"
                                data-bs-toggle="modal"
                                data-bs-target="#uninstallModal{$plugin->getField('directory')}"
                                title="{$uninstall_label}">
                                <span class="sr-only">{$uninstall_label}</span>
                            </span></a>
TWIG;

                        $output .= TemplateRenderer::getInstance()->render('components/danger_modal.html.twig', [
                            'modal_id' => 'uninstallModal' . $plugin->getField('directory'),
                            'confirm_btn' => Html::getSimpleForm(
                                static::getFormURL(),
                                ['action' => 'uninstall'],
                                _x('button', 'Uninstall'),
                                ['id' => $ID],
                                '',
                                'class="btn btn-danger w-100"'
                            ),
                            'content' => sprintf(
                                __('By uninstalling the "%s" plugin you will lose all the data of the plugin.'),
                                $plugin->getField('name')
                            ),
                        ]);
                    } else {
                        //TRANS: %s is the list of missing functions
                        $output .= sprintf(
                            __('%1$s: %2$s'),
                            __('Non-existent function'),
                            "plugin_" . $directory . "_uninstall"
                        );
                    }
                } elseif ($state === self::TOBECLEANED) {
                    $output .= Html::getSimpleForm(
                        static::getFormURL(),
                        ['action' => 'clean'],
                        _x('button', 'Clean'),
                        ['id' => $ID],
                        'fa-broom fs-2x'
                    );
                }

                return "<div style='text-align:right'>$output</div>";
            case 'state':
                $plugin = new self();
                $state = $plugin->isLoadable($values['directory']) ? $values[$field] : self::TOBECLEANED;
                return self::getState($state);
            case 'homepage':
                $value = Toolbox::formatOutputWebLink($values[$field]);
                if (!empty($value)) {
                    $value = htmlescape($value);
                    return "<a href=\"" . $value . "\" target='_blank'>
                     <i class='ti ti-external-link-alt fs-2x'></i><span class='sr-only'>$value</span>
                  </a>";
                }
                return "&nbsp;";
            case 'name':
                $value = Toolbox::stripTags($values[$field]);
                $state = $values['state'];
                $directory = $values['directory'];

                if (!(new Plugin())->isPluginsExecutionSuspended()) {
                    // Load plugin to give it ability to define its config_page hook
                    // unless plugins execution is suspended.
                    self::load($directory);
                }

                if (
                    in_array($state, [self::ACTIVATED, self::TOBECONFIGURED])
                    && isset($PLUGIN_HOOKS[Hooks::CONFIG_PAGE][$directory])
                ) {
                    $config_url = "{$CFG_GLPI['root_doc']}/plugins/{$directory}/{$PLUGIN_HOOKS[Hooks::CONFIG_PAGE][$directory]}";
                    return "<a href='$config_url'><span class='b'>$value</span></a>";
                } else {
                    return $value;
                }
                // no break
            case 'author':
            case 'license':
            case 'version':
                return $value = Toolbox::stripTags($values[$field]);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }


    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'state':
                $tab = [
                    self::ANEW           => _x('status', 'New'),
                    self::ACTIVATED      => _x('plugin', 'Enabled'),
                    self::NOTINSTALLED   => _x('plugin', 'Not installed'),
                    self::NOTUPDATED     => __('To update'),
                    self::TOBECONFIGURED => _x('plugin', 'Installed / not configured'),
                    self::NOTACTIVATED   => _x('plugin', 'Installed / not activated'),
                    self::TOBECLEANED    => __('Error / to clean'),
                ];
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, $tab, $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'clone';
        $forbidden[] = 'purge';
        return $forbidden;
    }


    /**
     * Return the system path for a given plugin key
     *
     * @since 9.5
     *
     * @param string $plugin_key plugin system key
     * @param bool $full true for absolute path
     *
     * @return false|string the path
     */
    public static function getPhpDir(string $plugin_key = "", $full = true)
    {
        $directory = false;
        foreach (GLPI_PLUGINS_DIRECTORIES as $plugins_directory) {
            if (is_dir("$plugins_directory/$plugin_key")) {
                $directory = "$plugins_directory/$plugin_key";
                break;
            }
        }

        if ($directory === false) {
            return false;
        }

        if (!$full) {
            $directory = str_replace(GLPI_ROOT, "", $directory);
        }

        return str_replace('\\', '/', $directory);
    }


    /**
     * Return the web path for a given plugin key
     *
     * @since 9.5
     *
     * @param string $plugin_key plugin system key
     * @param bool $full if true, append root_doc from config
     * @param bool $use_url_base if true, url_base instead root_doc
     *
     * @return false|string the web path
     *
     * @deprecated 11.0
     */
    public static function getWebDir(string $plugin_key = "", $full = true, $use_url_base = false)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        Toolbox::deprecated('All plugins resources should be accessed from the `/plugins/` path.');

        $directory = self::getPhpDir($plugin_key, false);
        if ($directory === false) {
            return false;
        }

        $directory = ltrim($directory, '/\\');

        if ($full) {
            $root = $use_url_base ? $CFG_GLPI['url_base'] : $CFG_GLPI["root_doc"];
            $directory = "$root/$directory";
        }

        return str_replace('\\', '/', $directory);
    }


    public static function getIcon()
    {
        return "ti ti-puzzle";
    }

    public function getSpecificMassiveActions($checkitem = null)
    {
        $actions = [];

        if (Session::getCurrentInterface() === 'central' && Config::canUpdate()) {
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'install']
            = "<i class='ti ti-folder-plus'></i>" .
            __s('Install');
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'uninstall']
            = "<i class='ti ti-folder-minus'></i>" .
            __s('Uninstall');
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'enable']
            = "<i class='ti ti-toggle-right-filled'></i>" .
            __s('Enable');
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'disable']
            = "<i class='ti ti-toggle-left-filled'></i>" .
            __s('Disable');
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'clean']
            = "<i class='ti ti-recycle'></i>" .
            __s('Clean');
        }

        $actions += parent::getSpecificMassiveActions($checkitem);

        return $actions;
    }

    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'install':
                echo "<table class='mx-auto'><tr>";
                echo "<td colspan='4'>";
                echo Html::submit(_x('button', 'Install'), [
                    'name'      => 'install',
                ]);
                echo "</td></tr></table>";
                return true;
            case 'uninstall':
                echo "<table class='mx-auto'><tr>";
                echo "<td>" . __s('This will only affect plugins already installed') . "</td><td colspan='3'>";
                echo Html::submit(_x('button', 'Uninstall'), [
                    'name'      => 'uninstall',
                ]);
                echo "</td></tr></table>";
                return true;
            case 'enable':
                echo "<table class='mx-auto'><tr>";
                echo "<td>" . __s('This will only affect plugins already installed') . "</td><td colspan='3'>";
                echo Html::submit(_x('button', 'Enable'), [
                    'name'      => 'enable',
                ]);
                echo "</td></tr></table>";
                return true;
            case 'disable':
                echo "<table class='mx-auto'><tr>";
                echo "<td>" . __s('This will only affect plugins already enabled') . "</td><td colspan='3'>";
                echo Html::submit(_x('button', 'Disable'), [
                    'name'      => 'disable',
                ]);
                echo "</td></tr></table>";
                return true;
            case 'clean':
                echo "<table class='mx-auto'><tr>";
                echo "<td>" . __s('This will only affect plugins ready to be cleaned') . "</td><td colspan='3'>";
                echo Html::submit(_x('button', 'Clean'), [
                    'name'      => 'clean',
                ]);
                echo "</td></tr></table>";
                return true;
        }
        return parent::showMassiveActionsSubForm($ma);
    }


    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        $plugin = new self();
        switch ($ma->getAction()) {
            case 'install':
                foreach ($ids as $id) {
                    $plugin->getFromDB($id);
                    if (!$plugin->isInstalled($plugin->fields['directory'])) {
                        $plugin->install($id);
                        if ($plugin->isInstalled($plugin->fields['directory'])) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::NO_ACTION);
                    }
                }
                return;
            case 'uninstall':
                foreach ($ids as $id) {
                    $plugin->getFromDB($id);
                    if ($plugin->isInstalled($plugin->fields['directory'])) {
                        $plugin->uninstall($id);
                        if (!$plugin->isInstalled($plugin->fields['directory'])) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::NO_ACTION);
                    }
                }
                return;
            case 'enable':
                foreach ($ids as $id) {
                    $plugin->getFromDB($id);
                    if ($plugin->isInstalled($plugin->fields['directory']) && !$plugin->isActivated($plugin->fields['directory'])) {
                        $plugin->activate($id);
                        if ($plugin->isActivated($plugin->fields['directory'])) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::NO_ACTION);
                    }
                }
                return;
            case 'disable':
                foreach ($ids as $id) {
                    $plugin->getFromDB($id);
                    if ($plugin->isActivated($plugin->fields['directory'])) {
                        $plugin->unactivate($id);
                        if (!$plugin->isActivated($plugin->fields['directory'])) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::NO_ACTION);
                    }
                }
                return;
            case 'clean':
                foreach ($ids as $id) {
                    $plugin->getFromDB($id);
                    if (!$plugin->isLoadable($plugin->fields['directory'])) {
                        $plugin->clean($id);
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::NO_ACTION);
                    }
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * Reset cache entries that may be indirectly altered by plugins.
     *
     * @param string $plugin_key
     *
     * @return bool
     */
    private function resetHookableCacheEntries(string $plugin_key): bool
    {
        /**
         * @var array $CFG_GLPI
         * @var CacheInterface $GLPI_CACHE
         */
        global $CFG_GLPI, $GLPI_CACHE;

        $to_clear = [
            // Plugin lowercase/case-sensitive class names mapping.
            // see `DbUtils::fixItemtypeCase()`
            sprintf('itemtype-case-mapping-%s', $plugin_key),

            // Will be stale as long as a plugin adds/remove a custom right.
            'all_possible_rights',
        ];

        foreach (array_keys($CFG_GLPI['languages']) as $language) {
            // Hookable using `$CFG_GLPI['device_types']`, `$CFG_GLPI['asset_types']`,
            // and `Hooks::DASHBOARD_FILTERS`.
            $to_clear[] = Grid::getAllDashboardCardsCacheKey($language);
        }

        return $GLPI_CACHE->deleteMultiple($to_clear);
    }

    final public function getPluginsListSuspendBanner(): string
    {
        return TemplateRenderer::getInstance()->render(
            'pages/admin/plugins/list_suspend_banner.html.twig',
            [
                'execution_suspended' => $this->isPluginsExecutionSuspended(),
            ]
        );
    }

    /**
     * Indicates whether the plugins execution is suspended.
     */
    public function isPluginsExecutionSuspended(): bool
    {
        /**
         * @var array $CFG_GLPI
         */
        global $CFG_GLPI;

        return in_array(
            $CFG_GLPI['plugins_execution_mode'] ?? null,
            [
                self::EXECUTION_MODE_SUSPENDED_BY_UPDATE,
                self::EXECUTION_MODE_SUSPENDED_MANUALLY,
            ]
        );
    }
}
