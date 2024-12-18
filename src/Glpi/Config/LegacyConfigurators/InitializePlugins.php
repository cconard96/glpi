<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
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

namespace Glpi\Config\LegacyConfigurators;

use Glpi\Config\LegacyConfigProviderInterface;
use Glpi\Debug\Profiler;
use Glpi\DependencyInjection\PluginContainer;
use Plugin;
use Update;

final readonly class InitializePlugins implements LegacyConfigProviderInterface
{
    public function __construct(private PluginContainer $pluginContainer)
    {
    }

    public function execute(): void
    {
        /*
         * On startup, register all plugins configured for use,
         * except during the database install/update process.
         */
        if (isset($_SESSION['is_installing']) || (!defined('SKIP_UPDATES') && !Update::isDbUpToDate())) {
            return;
        }

        Profiler::getInstance()->start('InitializePlugins::execute', Profiler::CATEGORY_BOOT);
        $plugin = new Plugin();
        $plugin->init(true);

        $this->pluginContainer->initializeContainer();
        Profiler::getInstance()->stop('InitializePlugins::execute');
    }
}
