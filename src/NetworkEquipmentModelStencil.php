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

class NetworkEquipmentModelStencil extends Stencil
{
    public static function getTypeName($nb = 0): string
    {
        return __('Graphical slot definition');
    }

    public function getPicturesFields(): array
    {
        return ['picture_front', 'picture_rear'];
    }

    public function getParams(bool $editor): array
    {
        if ($editor) {
            return [
                'nb_zones_label' => __('Set number of ports'),
                'define_zones_label' => __('Define port data in image'),
                'zone_label' => __('Port Label'),
                'zone_number_label' => __('Port Number'),
                'save_zone_data_label' => __('Save port data'),
                'add_zone_label' => __('Add a new port'),
                'remove_zone_label' => __('Remove last port'),
            ];
        } else {
            return [
                'anchor_id' => 'port_number_',
            ];
        }
    }

    public function getMaxZoneNumber(): int
    {
        return 256;
    }

    private function getPortInformation(array $port): array
    {
        $networkPort = new NetworkPort();

        $port['ifstatus'] = -1;
        if (
            $networkPort->getFromDBByCrit([
                'logical_number' => $port['number'],
                'items_id'  => $this->getStencilItem()->getID(),
                'itemtype'  => $this->getStencilItem()->getType(),
                'is_deleted' => 0,
            ]) && $networkPort->fields['ifstatus']
        ) {
            $port['ifstatus'] = $networkPort->fields['ifstatus'];
        }

        return $port;
    }
}
