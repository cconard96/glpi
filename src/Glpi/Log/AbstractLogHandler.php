<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Log;

use Monolog\Handler\StreamHandler;
use Psr\Log\LogLevel;

abstract class AbstractLogHandler extends StreamHandler
{
    public function __construct(string $logfile)
    {
        if (\defined('GLPI_LOG_LVL')) {
            $log_level = GLPI_LOG_LVL;
        } else {
            $log_level = match (GLPI_ENVIRONMENT_TYPE) {
                \GLPI::ENV_DEVELOPMENT => LogLevel::DEBUG,
                \GLPI::ENV_TESTING => LogLevel::DEBUG,
                default => LogLevel::WARNING,
            };
        }

        parent::__construct($logfile, $log_level);
    }
}
