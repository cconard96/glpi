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

/**
 *  NetworkAlias Class
 *
 * @since 0.84
 **
 */
class NetworkAlias extends FQDNLabel
{
    // From CommonDBChild
    public static $itemtype = NetworkName::class;
    public static $items_id           = 'networknames_id';
    public $dohistory                 = true;

    public static $checkParentRights = CommonDBConnexity::HAVE_SAME_RIGHT_ON_ITEM;


    public static function getTypeName($nb = 0)
    {
        return _n('Network alias', 'Network aliases', $nb);
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '12',
            'table'              => 'glpi_fqdns',
            'field'              => 'fqdn',
            'name'               => FQDN::getTypeName(1),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '20',
            'table'              => 'glpi_networknames',
            'field'              => 'name',
            'name'               => NetworkName::getTypeName(1),
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
        ];

        return $tab;
    }
}
