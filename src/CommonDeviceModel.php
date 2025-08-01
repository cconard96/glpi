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

use Glpi\Features\Clonable;

/// Class DeviceBatteryModel
abstract class CommonDeviceModel extends CommonDropdown
{
    use Clonable;

    public static function getTypeName($nb = 0)
    {
        return _n('Device model', 'Device models', $nb);
    }

    public static function getFormURL($full = true)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $dir = ($full ? $CFG_GLPI['root_doc'] : '');
        $itemtype = static::class;
        $link = "$dir/front/devicemodel.form.php?itemtype=$itemtype";

        return $link;
    }

    public static function getSearchURL($full = true)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $dir = ($full ? $CFG_GLPI['root_doc'] : '');
        $itemtype = static::class;
        $link = "$dir/front/devicemodel.php?itemtype=$itemtype";

        return $link;
    }

    public static function getIcon()
    {
        $model_class  = static::class;
        $device_class = str_replace('Model', '', $model_class);
        return $device_class::getIcon();
    }

    public function getCloneRelations(): array
    {
        return [];
    }
}
