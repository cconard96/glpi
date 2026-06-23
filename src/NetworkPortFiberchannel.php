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

use function Safe\preg_replace;

/**
 * NetworkPortFiberchannel class: Fiberchannel instantiation of NetworkPort
 *
 * @since 9.1
 */
class NetworkPortFiberchannel extends NetworkPortInstantiation
{
    public static function getTypeName($nb = 0)
    {
        return __('Fiber channel port');
    }

    public function getNetworkCardInterestingFields()
    {
        return ['link.mac' => 'mac'];
    }

    /**
     * @param array $input
     *
     * @return array
     */
    public function prepareInput($input)
    {
        if (isset($input['speed']) && ($input['speed'] === 'speed_other_value')) {
            if (!isset($input['speed_other_value'])) {
                unset($input['speed']);
            } else {
                $speed = self::transformPortSpeed($input['speed_other_value'], false);
                if ($speed === false) {
                    unset($input['speed']);
                } else {
                    $input['speed'] = $speed;
                }
            }
        }
        return $input;
    }

    public function prepareInputForAdd($input)
    {
        return parent::prepareInputForAdd($this->prepareInput($input));
    }

    public function prepareInputForUpdate($input)
    {
        return parent::prepareInputForUpdate($this->prepareInput($input));
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics'),
        ];

        $tab[] = [
            'id'                 => '10',
            'table'              => NetworkPort::getTable(),
            'field'              => 'mac',
            'datatype'           => 'mac',
            'name'               => __('MAC'),
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'empty',
            ],
        ];

        $tab[] = [
            'id'                 => '11',
            'table'              => static::getTable(),
            'field'              => 'wwn',
            'name'               => __('World Wide Name'),
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '12',
            'table'              => static::getTable(),
            'field'              => 'speed',
            'name'               => __('Fiber channel port speed'),
            'massiveaction'      => false,
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '13',
            'table'              => 'glpi_networkportfiberchanneltypes',
            'field'              => 'name',
            'name'               => __('Fiber port type'),
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }

    /**
     * Transform a port speed from string to integerer and vice-versa
     *
     * @param int|string $val        port speed
     * @param bool        $to_string  true if we must transform the speed to string
     *
     * @return false|int|string (regarding what is requested)
     **/
    public static function transformPortSpeed($val, $to_string)
    {
        if ($to_string) {
            if (($val % 1000) === 0) {
                //TRANS: %d is the speed
                return sprintf(__('%d Gbit/s'), $val / 1000);
            }

            if ((($val % 100) === 0) && ($val > 1000)) {
                $val /= 100;
                //TRANS: %f is the speed
                return sprintf(__('%.1f Gbit/s'), $val / 10);
            }

            //TRANS: %d is the speed
            return sprintf(__('%d Mbit/s'), $val);
        }

        $val = preg_replace('/\s+/', '', strtolower($val));

        $number = sscanf($val, "%f%s", $speed, $unit);
        if ($number !== 2) {
            return false;
        }

        return match ($unit) {
            'mbit/s', 'mb/s' => (int) $speed,
            'gbit/s', 'gb/s' => ($speed * 1000),
            default => false,
        };
    }

    /**
     * Get the possible value for Ethernet port speed
     *
     * @param int|null $val  if not set, ask for all values, else for 1 value (default NULL)
     *
     * @return array|string
     **/
    public static function getPortSpeed($val = null)
    {
        $tmp = [
            0     => '',
            //TRANS: %d is the speed
            10    => sprintf(__('%d Mbit/s'), 10),
            100   => sprintf(__('%d Mbit/s'), 100),
            //TRANS: %d is the speed
            1000  => sprintf(__('%d Gbit/s'), 1),
            10000 => sprintf(__('%d Gbit/s'), 10),
        ];

        if (is_null($val)) {
            return $tmp;
        }
        return $tmp[$val] ?? self::transformPortSpeed($val, true);
    }

    /**
     * @param array $tab
     * @param array $joinparams
     *
     * @return void
     */
    public static function getSearchOptionsToAddForInstantiation(array &$tab, array $joinparams)
    {
        $tab[] = [
            'id'                 => '62',
            'table'              => 'glpi_sockets',
            'field'              => 'name',
            'datatype'           => 'dropdown',
            'name'               => __('Network fiber socket'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'child',
                'linkfield'           => 'networkports_id',
                'beforejoin'         => [
                    'table'              => 'glpi_networkportfiberchannels',
                    'joinparams'         => $joinparams,
                ],
            ],
        ];
    }
}
