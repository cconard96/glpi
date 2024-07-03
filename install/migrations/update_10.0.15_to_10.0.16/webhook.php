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

/**
 * @var \Migration $migration
 * @var array $ADDTODISPLAYPREF
 * @var \DBmysql $DB
 */

$default_charset = DBConnection::getDefaultCharset();
$default_collation = DBConnection::getDefaultCollation();
$default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

if (!$DB->fieldExists('glpi_webhooks', 'webhookcategories_id')) {
    // Dev migration
    $migration->addField('glpi_webhooks', 'webhookcategories_id', 'fkey', [
        'after' => 'comment'
    ]);
    $migration->addKey('glpi_webhooks', 'webhookcategories_id', 'webhookcategories_id');
}

if (!$DB->tableExists('glpi_webhookcategories')) {
    $query = "CREATE TABLE `glpi_webhookcategories` (
      `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
      `name` varchar(255) DEFAULT NULL,
      `comment` text,
      `webhookcategories_id` int {$default_key_sign} NOT NULL DEFAULT '0',
      `completename` text,
      `level` int NOT NULL DEFAULT '0',
      `ancestors_cache` longtext,
      `sons_cache` longtext,
      `date_mod` timestamp NULL DEFAULT NULL,
      `date_creation` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unicity` (`webhookcategories_id`,`name`),
      KEY `name` (`name`),
      KEY `date_mod` (`date_mod`),
      KEY `date_creation` (`date_creation`),
      KEY `level` (`level`)
    ) ENGINE = InnoDB ROW_FORMAT = DYNAMIC DEFAULT CHARSET = {$default_charset} COLLATE = {$default_collation};";
    $DB->doQueryOrDie($query, "add table glpi_webhookcategories");
}
