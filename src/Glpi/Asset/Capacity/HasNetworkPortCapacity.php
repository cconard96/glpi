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

namespace Glpi\Asset\Capacity;

use CommonGLPI;
use Glpi\Asset\CapacityConfig;
use NetworkPort;
use Override;
use Session;

class HasNetworkPortCapacity extends AbstractCapacity
{
    public function getLabel(): string
    {
        return NetworkPort::getTypeName(Session::getPluralNumber());
    }

    public function getIcon(): string
    {
        return NetworkPort::getIcon();
    }

    public function getCloneRelations(): array
    {
        return [
            NetworkPort::class,
        ];
    }

    #[Override]
    public function getDescription(): string
    {
        return __("Has network ports (like ethernet and wlan)");
    }

    public function isUsed(string $classname): bool
    {
        return parent::isUsed($classname)
            && $this->countAssetsLinkedToPeerItem($classname, NetworkPort::class) > 0;
    }

    public function getCapacityUsageDescription(string $classname): string
    {
        return sprintf(
            __('%1$s network ports attached to %2$s assets'),
            $this->countPeerItemsUsage($classname, NetworkPort::class),
            $this->countAssetsLinkedToPeerItem($classname, NetworkPort::class)
        );
    }

    public function onClassBootstrap(string $classname, CapacityConfig $config): void
    {
        $this->registerToTypeConfig('networkport_types', $classname);

        CommonGLPI::registerStandardTab(
            $classname,
            NetworkPort::class,
            50
        );
    }

    public function onCapacityDisabled(string $classname, CapacityConfig $config): void
    {
        // Unregister from document types
        $this->unregisterFromTypeConfig('networkport_types', $classname);

        // Delete related networkport data
        $networkport = new NetworkPort();
        $networkport->deleteByCriteria(
            [
                'itemtype' => $classname,
            ],
            force: true,
            history: false
        );

        // Clean history related to networkports
        $this->deleteRelationLogs($classname, NetworkPort::class);

        // Clean display preferences
        $this->deleteDisplayPreferences($classname, NetworkPort::rawSearchOptionsToAdd($classname));
    }
}
