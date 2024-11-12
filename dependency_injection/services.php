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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Glpi\DependencyInjection\PublicService;
use Glpi\Log\LegacyGlobalLogger;

return static function (ContainerConfigurator $container): void {
    $projectDir = dirname(__DIR__);
    $parameters = $container->parameters();
    $services = $container->services();

    // Default secret, just in case
    $parameters->set('glpi.default_secret', bin2hex(random_bytes(32)));
    $parameters->set('env(APP_SECRET_FILE)', $projectDir . '/config/glpicrypt.key');
    $parameters->set('kernel.secret', env('default:glpi.default_secret:file:APP_SECRET_FILE'));

    $services = $services
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->instanceof(PublicService::class)->public()
    ;

    $services->load('Glpi\Config\\', $projectDir . '/src/Glpi/Config');
    $services->load('Glpi\Component\\', $projectDir . '/src/Glpi/Component');
    $services->load('Glpi\Controller\\', $projectDir . '/src/Glpi/Controller');
    $services->load('Glpi\Http\\', $projectDir . '/src/Glpi/Http');
    $services->load('Glpi\DependencyInjection\\', $projectDir . '/src/Glpi/DependencyInjection');

    /**
     * Override Symfony's logger.
     * @see \Symfony\Component\HttpKernel\DependencyInjection\LoggerPass
     */
    $services->set('logger', LegacyGlobalLogger::class);
};
