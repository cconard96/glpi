<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
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
 * @var array $ADDTODISPLAYPREF
 * @var DB $DB
 * @var Migration $migration
 */

$default_charset = DBConnection::getDefaultCharset();
$default_collation = DBConnection::getDefaultCollation();
$default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

$assignable_itemtypes = [
    'Computer' => [
        'table' => 'glpi_computers',
        'rightname' => 'computer'
    ],
    'Monitor' => [
        'table' => 'glpi_monitors',
        'rightname' => 'monitor'
    ],
    'Software' => [
        'table' => 'glpi_softwares',
        'rightname' => 'software'
    ],
    'NetworkEquipment' => [
        'table' => 'glpi_networkequipments',
        'rightname' => 'networking'
    ],
    'Printer' => [
        'table' => 'glpi_printers',
        'rightname' => 'printer'
    ],
    'CartridgeItem' => [
        'table' => 'glpi_cartridgeitems',
        'rightname' => 'cartridge'
    ],
    'ConsumableItem' => [
        'table' => 'glpi_consumableitems',
        'rightname' => 'consumable'
    ],
    'Phone' => [
        'table' => 'glpi_phones',
        'rightname' => 'phone'
    ],
    'Peripheral' => [
        'table' => 'glpi_peripherals',
        'rightname' => 'peripheral'
    ],
    'SoftwareLicense' => [
        'table' => 'glpi_softwarelicenses',
        'rightname' => 'license'
    ],
    'Certificate' => [
        'table' => 'glpi_certificates',
        'rightname' => 'certificate'
    ],
    'Appliance' => [
        'table' => 'glpi_appliances',
        'rightname' => 'appliance'
    ],
    'Item_DeviceSimcard' => [
        'table' => 'glpi_items_devicesimcards',
        'rightname' => 'device'
    ],
    'Line' => [
        'table' => 'glpi_lines',
        'rightname' => 'line'
    ],
    'DatabaseInstance' => [
        'table' => 'glpi_databaseinstances',
        'rightname' => 'databaseinstance'
    ],
    'Cluster' => [
        'table' => 'glpi_clusters',
        'rightname' => 'cluster'
    ],
    'Enclosure' => [
        'table' => 'glpi_enclosures',
        'rightname' => 'datacenter'
    ],
    'Domain' => [
        'table' => 'glpi_domains',
        'rightname' => 'domain'
    ],
    'DomainRecord' => [
        'table' => 'glpi_domainrecords',
        'rightname' => 'domain'
    ],
    'Rack' => [
        'table' => 'glpi_racks',
        'rightname' => 'datacenter'
    ],
    'Unmanaged' => [
        'table' => 'glpi_unmanageds',
        'rightname' => 'unmanaged'
    ],
];

if (!$DB->tableExists('glpi_groups_items')) {
    $query = <<<SQL
        CREATE TABLE `glpi_groups_items` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `groups_id` int {$default_key_sign} NOT NULL DEFAULT '0',
          `itemtype` varchar(255) NOT NULL DEFAULT '',
          `items_id` int {$default_key_sign} NOT NULL DEFAULT '0',
          `type` tinyint NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unicity` (`groups_id`,`itemtype`,`items_id`, `type`),
          KEY `item` (`itemtype`, `items_id`),
          KEY `type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;
SQL;
    $DB->doQueryOrDie($query);
}

foreach ($assignable_itemtypes as $itemtype => $specs) {
    $itemtype_table     = $specs['table'];
    $itemtype_rightname = $specs['rightname'];

    $migration->addRight($itemtype_rightname, READ_ASSIGNED, [$itemtype_rightname => READ]);
    $migration->addRight($itemtype_rightname, UPDATE_ASSIGNED, [$itemtype_rightname => UPDATE]);

    // move groups to the new link table
    if ($DB->fieldExists($itemtype_table, 'groups_id')) {
        $DB->insert('glpi_groups_items', new QuerySubQuery([
            'SELECT' => [
                new QueryExpression('NULL', 'id'),
                new QueryExpression('1', 'type'),
                'id AS items_id',
                'groups_id',
                new QueryExpression($DB::quoteValue($itemtype), 'itemtype')
            ],
            'FROM'   => $itemtype_table,
            'WHERE'  => [
                'groups_id' => ['>', 0]
            ]
        ]));
    }
    if ($DB->fieldExists($itemtype_table, 'groups_id_tech')) {
        $DB->insert('glpi_groups_items', new QuerySubQuery([
            'SELECT' => [
                new QueryExpression('NULL', 'id'),
                new QueryExpression('2', 'type'),
                'id AS items_id',
                'groups_id_tech AS groups_id',
                new QueryExpression($DB::quoteValue($itemtype), 'itemtype')
            ],
            'FROM'   => $itemtype_table,
            'WHERE'  => [
                'groups_id_tech' => ['>', 0]
            ]
        ]));
    }

    $migration->dropKey($itemtype_table, 'groups_id');
    $migration->dropKey($itemtype_table, 'groups_id_tech');
    $migration->dropField($itemtype_table, 'groups_id');
    $migration->dropField($itemtype_table, 'groups_id_tech');
}
