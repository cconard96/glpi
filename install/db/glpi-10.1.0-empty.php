<?php

/** @noinspection PhpUnhandledExceptionInspection */

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

/** @global \Glpi\DB\DB $DB_PDO */
global $DB_PDO;

$TEXT_LENGTH = 65535;
$MEDIUMTEXT_LENGTH = 16777215;
$LONGTEXT_LENGTH = 4294967295;

$schema = $DB_PDO->getSchemaManager();
$column_templates = [
    'id' => new Column('id', Type::getType('integer'), [
        'autoincrement' => true,
        'unsigned' => true,
        'notnull' => true,
    ]),
    'itemtype' => new Column('itemtype', Type::getType('string'), [
        'length' => 100,
        'notnull' => true,
    ]),
    'items_id' => new Column('items_id', Type::getType('integer'), [
        'unsigned' => true,
        'notnull' => true,
    ]),
    'name' => new Column('name', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
    ]),
    'date_creation' => new Column('date_creation', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    'date_mod' => new Column('date_mod', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    'comment' => new Column('comment', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    'entities_id' => new Column('entities_id', Type::getType('integer'), [
        'unsigned' => true,
        'notnull' => true,
        'default' => 0,
    ]),
    'is_recursive' => new Column('is_recursive', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    'is_active' => new Column('is_active', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
];

$fk_column = static function (string $name) {
    return new Column($name, Type::getType('integer'), [
        'unsigned' => true,
        'notnull' => true,
        'default' => 0,
    ]);
};

$id_index = new Index('id', ['id'], false, true);

$DB_PDO->disableForeignKeyChecks();

if ($schema->tablesExist('glpi_alerts')) {
    $schema->dropTable('glpi_alerts');
}
$schema->createTable(new Table('glpi_alerts', [
    $column_templates['id'],
    $column_templates['itemtype'],
    $column_templates['items_id'],
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
        'comment' => 'see define.php ALERT_* constant',
    ]),
    new Column('data', Type::getType('datetime'), [
        'notnull' => true,
        'default' => 'CURRENT_TIMESTAMP',
    ]),
], [
    $id_index,
    new Index('unicity', ['itemtype', 'items_id', 'type'], true),
]));

if ($schema->tablesExist('glpi_authldapreplicates')) {
    $schema->dropTable('glpi_authldapreplicates');
}
$schema->createTable(new Table('glpi_authldapreplicates', [
    $column_templates['id'],
    $fk_column('authldaps_id'),
    new Column('host', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('port', Type::getType('integer'), [
        'notnull' => true,
        'default' => 389,
    ]),
    $column_templates['name'],
    new Column('timeout', Type::getType('integer'), [
        'notnull' => true,
        'default' => 10,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_authldaps')) {
    $schema->dropTable('glpi_authldaps');
}
$schema->createTable(new Table('glpi_authldaps', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('host', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('basedn', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('root_dn', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('port', Type::getType('integer'), [
        'notnull' => true,
        'default' => 389,
    ]),
    new Column('condition', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('login_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('sync_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('use_tls', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('group_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('group_condition', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('group_search_type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('group_member_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('email1_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('realname_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('firstname_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('phone_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('phone2_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('mobile_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('comment_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('use_dn', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 1,
    ]),
    new Column('time_offset', Type::getType('integer'), [
            'notnull' => true,
            'default' => 0,
            'comment' => 'in seconds',
    ]),
    new Column('deref_option', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('title_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('category_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('language_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('entity_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('entity_condition', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['date_mod'],
    $column_templates['comment'],
    new Column('is_default', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_active', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('rootdn_passwd', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('registration_number_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('email2_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('email3_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('email4_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('location_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('responsible_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('pagesize', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('ldap_maxlimit', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('can_support_pagesize', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('picture_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $column_templates['date_creation'],
    new Column('inventory_domain', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('tls_certfile', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('tls_keyfile', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('use_bind', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 1,
    ]),
    new Column('timeout', Type::getType('integer'), [
        'notnull' => true,
        'default' => 10,
    ]),
    new Column('tls_version', Type::getType('string'), [
        'length' => 10,
        'notnull' => false,
        'default' => null,
    ])
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_authmails')) {
    $schema->dropTable('glpi_authmails');
}
$schema->createTable(new Table('glpi_authmails', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('connect_string', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('host', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $column_templates['comment'],
    new Column('is_active', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_apiclients')) {
    $schema->dropTable('glpi_apiclients');
}
$schema->createTable(new Table('glpi_apiclients', [
    $column_templates['id'],
    $fk_column('entities_id'),
    $column_templates['is_recursive'],
    $column_templates['name'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    new Column('is_active', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('ipv4_range_start', Type::getType('bigint'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('ipv4_range_end', Type::getType('bigint'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('ipv6', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('app_token', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('app_token_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('dolog_method', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['comment'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_autoupdatesystems')) {
    $schema->dropTable('glpi_autoupdatesystems');
}
$schema->createTable(new Table('glpi_autoupdatesystems', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_blacklistedmailcontents')) {
    $schema->dropTable('glpi_blacklistedmailcontents');
}
$schema->createTable(new Table('glpi_blacklistedmailcontents', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('content', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_blacklists')) {
    $schema->dropTable('glpi_blacklists');
}
$schema->createTable(new Table('glpi_blacklists', [
    $column_templates['id'],
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['name'],
    new Column('value', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
    ]),
    $column_templates['comment'],
    $column_templates['date_creation'],
    $column_templates['date_mod'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_savedsearches')) {
    $schema->dropTable('glpi_savedsearches');
}
$schema->createTable(new Table('glpi_savedsearches', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
        'comment' => 'see SavedSearch:: constants'
    ]),
    $column_templates['itemtype'],
    $fk_column('users_id'),
    new Column('is_private', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('query', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('last_execution_time', Type::getType('integer'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('do_count', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 2,
        'comment' => 'Do or do not count results on list display see SavedSearch::COUNT_* constants'
    ]),
    new Column('last_execution_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('counter', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_savedsearches_users')) {
    $schema->dropTable('glpi_savedsearches_users');
}
$schema->createTable(new Table('glpi_savedsearches_users', [
    $column_templates['id'],
    $fk_column('users_id'),
    $column_templates['itemtype'],
    $fk_column('savedsearches_id'),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_savedsearches_alerts')) {
    $schema->dropTable('glpi_savedsearches_alerts');
}
$schema->createTable(new Table('glpi_savedsearches_alerts', [
    $column_templates['id'],
    $fk_column('savedsearches_id'),
    $column_templates['name'],
    new Column('is_active', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('operator', Type::getType('tinyint'), [
        'notnull' => true,
    ]),
    new Column('value', Type::getType('integer'), [
        'notnull' => true,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    new Column('frequency', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_budgets')) {
    $schema->dropTable('glpi_budgets');
}
$schema->createTable(new Table('glpi_budgets', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    new Column('is_deleted', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('begin_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('value', Type::getType('decimal'), [
        'notnull' => true,
        'default' => 0,
        'precision' => 20,
        'scale' => 4,
    ]),
    new Column('is_template', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('template_name', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $fk_column('locations_id'),
    $fk_column('budgettypes_id'),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_budgettypes')) {
    $schema->dropTable('glpi_budgettypes');
}
$schema->createTable(new Table('glpi_budgettypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_businesscriticities')) {
    $schema->dropTable('glpi_businesscriticities');
}
$schema->createTable(new Table('glpi_businesscriticities', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $fk_column('businesscriticities_id'),
    new Column('completename', Type::getType('text'), ['length' => $TEXT_LENGTH, 'notnull' => false]),
    new Column('level', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('ancestors_cache', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('sons_cache', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_calendars')) {
    $schema->dropTable('glpi_calendars');
}
$schema->createTable(new Table('glpi_calendars', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    $column_templates['date_creation'],
    $column_templates['date_mod'],
    new Column('cache_duration', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_calendars_holidays')) {
    $schema->dropTable('glpi_calendars_holidays');
}
$schema->createTable(new Table('glpi_calendars_holidays', [
    $column_templates['id'],
    $fk_column('calendars_id'),
    $fk_column('holidays_id'),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_calendarsegments')) {
    $schema->dropTable('glpi_calendarsegments');
}
$schema->createTable(new Table('glpi_calendarsegments', [
    $column_templates['id'],
    $fk_column('calendars_id'),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('day', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('begin', Type::getType('time'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end', Type::getType('time'), [
        'notnull' => false,
        'default' => null,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_cartridgeitems')) {
    $schema->dropTable('glpi_cartridgeitems');
}
$schema->createTable(new Table('glpi_cartridgeitems', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['name'],
    new Column('ref', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('locations_id'),
    $fk_column('cartridgeitemtypes_id'),
    $fk_column('manufacturers_id'),
    $fk_column('users_id_tech'),
    $fk_column('groups_id_tech'),
    new Column('is_deleted', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    $column_templates['comment'],
    new Column('alarm_threshold', Type::getType('integer'), [
        'notnull' => true,
        'default' => 10,
    ]),
    new Column('stock_target', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    new Column('pictures', Type::getType('text'), ['length' => $TEXT_LENGTH, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_printers_cartridgeinfos')) {
    $schema->dropTable('glpi_printers_cartridgeinfos');
}
$schema->createTable(new Table('glpi_printers_cartridgeinfos', [
    $column_templates['id'],
    $fk_column('printers_id'),
    new Column('property', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('value', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_cartridgeitems_printermodels')) {
    $schema->dropTable('glpi_cartridgeitems_printermodels');
}
$schema->createTable(new Table('glpi_cartridgeitems_printermodels', [
    $column_templates['id'],
    $fk_column('cartridgeitems_id'),
    $fk_column('printermodels_id'),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_cartridgeitemtypes')) {
    $schema->dropTable('glpi_cartridgeitemtypes');
}
$schema->createTable(new Table('glpi_cartridgeitemtypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_cartridges')) {
    $schema->dropTable('glpi_cartridges');
}
$schema->createTable(new Table('glpi_cartridges', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $fk_column('cartridgeitems_id'),
    $fk_column('printers_id'),
    new Column('date_in', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('date_use', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('date_out', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('pages', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_certificates')) {
    $schema->dropTable('glpi_certificates');
}
$schema->createTable(new Table('glpi_certificates', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('serial', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('otherserial', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    new Column('is_deleted', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('is_template', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('template_name', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('certificatetypes_id'),
    new Column('dns_name', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('dns_suffix', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('users_id_tech'),
    $fk_column('groups_id_tech'),
    $fk_column('locations_id'),
    $fk_column('manufacturers_id'),
    new Column('contact', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('contact_num', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('users_id'),
    $fk_column('groups_id'),
    new Column('is_autosign', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('date_expiration', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('states_id'),
    new Column('command', Type::getType('text'), ['length' => $TEXT_LENGTH, 'notnull' => false]),
    new Column('certificate_request', Type::getType('text'), ['length' => $TEXT_LENGTH, 'notnull' => false]),
    new Column('certificate_item', Type::getType('text'), ['length' => $TEXT_LENGTH, 'notnull' => false]),
    $column_templates['date_creation'],
    $column_templates['date_mod'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_certificates_items')) {
    $schema->dropTable('glpi_certificates_items');
}
$schema->createTable(new Table('glpi_certificates_items', [
    $column_templates['id'],
    $fk_column('certificates_id'),
    $column_templates['items_id'],
    $column_templates['itemtype'],
    $column_templates['date_creation'],
    $column_templates['date_mod'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_certificatetypes')) {
    $schema->dropTable('glpi_certificatetypes');
}
$schema->createTable(new Table('glpi_certificatetypes', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_creation'],
    $column_templates['date_mod'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changecosts')) {
    $schema->dropTable('glpi_changecosts');
}
$schema->createTable(new Table('glpi_changecosts', [
    $column_templates['id'],
    $fk_column('changes_id'),
    $column_templates['name'],
    $column_templates['comment'],
    new Column('begin_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('actiontime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_time', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_fixed', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_material', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    $fk_column('budgets_id'),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changes')) {
    $schema->dropTable('glpi_changes');
}
$schema->createTable(new Table('glpi_changes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('is_deleted', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('status', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
    new Column('content', Type::getType('text'), ['length' => $TEXT_LENGTH, 'notnull' => false]),
    $column_templates['date_mod'],
    new Column('date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('solvedate', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('closedate', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('time_to_resolve', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('users_id_recipient'),
    $fk_column('users_id_lastupdater'),
    new Column('urgency', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
    new Column('impact', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
    new Column('priority', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
    $fk_column('itilcategories_id'),
    new Column('impactcontent', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('controlistcontent', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('rolloutplancontent', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('backoutplancontent', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('checklistcontent', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('global_validation', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
    new Column('validation_percent', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('actiontime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('begin_waiting_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('waiting_duration', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('close_delay_stat', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('solve_delay_stat', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_creation'],
    $fk_column('changetemplates_id'),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changes_groups')) {
    $schema->dropTable('glpi_changes_groups');
}
$schema->createTable(new Table('glpi_changes_groups', [
    $column_templates['id'],
    $fk_column('changes_id'),
    $fk_column('groups_id'),
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changes_items')) {
    $schema->dropTable('glpi_changes_items');
}
$schema->createTable(new Table('glpi_changes_items', [
    $column_templates['id'],
    $fk_column('changes_id'),
    $column_templates['itemtype'],
    $column_templates['items_id'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changes_problems')) {
    $schema->dropTable('glpi_changes_problems');
}
$schema->createTable(new Table('glpi_changes_problems', [
    $column_templates['id'],
    $fk_column('changes_id'),
    $fk_column('problems_id'),
    new Column('link', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changes_suppliers')) {
    $schema->dropTable('glpi_changes_suppliers');
}
$schema->createTable(new Table('glpi_changes_suppliers', [
    $column_templates['id'],
    $fk_column('changes_id'),
    $fk_column('suppliers_id'),
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('use_notification', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('alternative_email', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changes_tickets')) {
    $schema->dropTable('glpi_changes_tickets');
}
$schema->createTable(new Table('glpi_changes_tickets', [
    $column_templates['id'],
    $fk_column('changes_id'),
    $fk_column('tickets_id'),
    new Column('link', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changes_users')) {
    $schema->dropTable('glpi_changes_users');
}
$schema->createTable(new Table('glpi_changes_users', [
    $column_templates['id'],
    $fk_column('changes_id'),
    $fk_column('users_id'),
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('use_notification', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('alternative_email', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changetasks')) {
    $schema->dropTable('glpi_changetasks');
}
$schema->createTable(new Table('glpi_changetasks', [
    $column_templates['id'],
    new Column('uuid', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('changes_id'),
    $fk_column('taskcategories_id'),
    new Column('state', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('begin', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('users_id'),
    $fk_column('users_id_editor'),
    $fk_column('users_id_tech'),
    $fk_column('groups_id_tech'),
    new Column('content', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('actiontime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $fk_column('tasktemplates_id'),
    new Column('timeline_position', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_private', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_changevalidations')) {
    $schema->dropTable('glpi_changevalidations');
}

if ($schema->tablesExist('glpi_computerantiviruses')) {
    $schema->dropTable('glpi_computerantiviruses');
}

if ($schema->tablesExist('glpi_items_disks')) {
    $schema->dropTable('glpi_items_disks');
}

if ($schema->tablesExist('glpi_computermodels')) {
    $schema->dropTable('glpi_computermodels');
}

if ($schema->tablesExist('glpi_computers')) {
    $schema->dropTable('glpi_computers');
}

if ($schema->tablesExist('glpi_computers_items')) {
    $schema->dropTable('glpi_computers_items');
}

if ($schema->tablesExist('glpi_items_softwarelicenses')) {
    $schema->dropTable('glpi_items_softwarelicenses');
}

if ($schema->tablesExist('glpi_items_softwareversions')) {
    $schema->dropTable('glpi_items_softwareversions');
}

if ($schema->tablesExist('glpi_computertypes')) {
    $schema->dropTable('glpi_computertypes');
}
$schema->createTable(new Table('glpi_computertypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_computervirtualmachines')) {
    $schema->dropTable('glpi_computervirtualmachines');
}

if ($schema->tablesExist('glpi_items_operatingsystems')) {
    $schema->dropTable('glpi_items_operatingsystems');
}

if ($schema->tablesExist('glpi_operatingsystemkernels')) {
    $schema->dropTable('glpi_operatingsystemkernels');
}
$schema->createTable(new Table('glpi_operatingsystemkernels', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_operatingsystemkernelversions')) {
    $schema->dropTable('glpi_operatingsystemkernelversions');
}
$schema->createTable(new Table('glpi_operatingsystemkernelversions', [
    $column_templates['id'],
    $fk_column('operatingsystemkernels_id'),
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_operatingsystemeditions')) {
    $schema->dropTable('glpi_operatingsystemeditions');
}
$schema->createTable(new Table('glpi_operatingsystemeditions', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_configs')) {
    $schema->dropTable('glpi_configs');
}
$schema->createTable(new Table('glpi_configs', [
    $column_templates['id'],
    new Column('context', Type::getType('string'), [
        'length' => 150,
        'notnull' => false,
        'default' => null,
    ]),
    $column_templates['name'],
    new Column('value', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_impactrelations')) {
    $schema->dropTable('glpi_impactrelations');
}
$schema->createTable(new Table('glpi_impactrelations', [
    $column_templates['id'],
    $fk_column('itemtype_source'),
    $fk_column('items_id_source'),
    $fk_column('itemtype_impacted'),
    $fk_column('items_id_impacted'),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_impactcompounds')) {
    $schema->dropTable('glpi_impactcompounds');
}
$schema->createTable(new Table('glpi_impactcompounds', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('color', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => '',
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_impactitems')) {
    $schema->dropTable('glpi_impactitems');
}
$schema->createTable(new Table('glpi_impactitems', [
    $column_templates['id'],
    $column_templates['itemtype'],
    $column_templates['items_id'],
    $fk_column('parents_id'),
    $fk_column('impactcontexts_id'),
    new Column('is_slave', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_impactcontexts')) {
    $schema->dropTable('glpi_impactcontexts');
}
$schema->createTable(new Table('glpi_impactcontexts', [
    $column_templates['id'],
    new Column('positions', Type::getType('text'), ['length' => $MEDIUMTEXT_LENGTH, 'notnull' => true]),
    new Column('zoom', Type::getType('float'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('pan_x', Type::getType('float'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('pan_y', Type::getType('float'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('impact_color', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => '',
    ]),
    new Column('depends_color', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => '',
    ]),
    new Column('impact_and_depends_color', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => '',
    ]),
    new Column('show_depends', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true,
    ]),
    new Column('show_impact', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true,
    ]),
    new Column('max_depth', Type::getType('integer'), [
        'notnull' => true,
        'default' => 5,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_consumableitems')) {
    $schema->dropTable('glpi_consumableitems');
}

if ($schema->tablesExist('glpi_consumableitemtypes')) {
    $schema->dropTable('glpi_consumableitemtypes');
}
$schema->createTable(new Table('glpi_consumableitemtypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_consumables')) {
    $schema->dropTable('glpi_consumables');
}

if ($schema->tablesExist('glpi_contacts')) {
    $schema->dropTable('glpi_contacts');
}

if ($schema->tablesExist('glpi_contacts_suppliers')) {
    $schema->dropTable('glpi_contacts_suppliers');
}

if ($schema->tablesExist('glpi_contacttypes')) {
    $schema->dropTable('glpi_contacttypes');
}
$schema->createTable(new Table('glpi_contacttypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_contractcosts')) {
    $schema->dropTable('glpi_contractcosts');
}

if ($schema->tablesExist('glpi_contracts')) {
    $schema->dropTable('glpi_contracts');
}

if ($schema->tablesExist('glpi_contracts_items')) {
    $schema->dropTable('glpi_contracts_items');
}

if ($schema->tablesExist('glpi_contracts_suppliers')) {
    $schema->dropTable('glpi_contracts_suppliers');
}

if ($schema->tablesExist('glpi_contracttypes')) {
    $schema->dropTable('glpi_contracttypes');
}
$schema->createTable(new Table('glpi_contracttypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_crontasklogs')) {
    $schema->dropTable('glpi_crontasklogs');
}
$schema->createTable(new Table('glpi_crontasklogs', [
    $column_templates['id'],
    $fk_column('crontasks_id'),
    $fk_column('crontasklogs_id'),
    new Column('date', Type::getType('datetime'), [
        'notnull' => true,
        'default' => 'CURRENT_TIMESTAMP',
    ]),
    new Column('state', Type::getType('integer'), [
        'notnull' => true,
        'comment' => '0:start, 1:run, 2:stop'
    ]),
    new Column('elapsed', Type::getType('float'), [
        'notnull' => true,
        'comment' => 'time elapsed since start'
    ]),
    new Column('volume', Type::getType('integer'), [
        'notnull' => true,
        'comment' => 'for statistics'
    ]),
    new Column('content', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
        'comment' => 'message'
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_crontasks')) {
    $schema->dropTable('glpi_crontasks');
}
$schema->createTable(new Table('glpi_crontasks', [
    $column_templates['id'],
    $column_templates['itemtype'],
    $column_templates['name'],
    new Column('frequency', Type::getType('integer'), [
        'notnull' => true,
        'comment' => 'second between launch',
    ]),
    new Column('param', Type::getType('integer'), [
        'notnull' => false,
        'default' => null,
        'comment' => 'task specify parameter',
    ]),
    new Column('state', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
        'comment' => '0:disabled, 1:waiting, 2:running',
    ]),
    new Column('mode', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
        'comment' => '1:internal, 2:external',
    ]),
    new Column('allowmode', Type::getType('integer'), [
        'notnull' => true,
        'default' => 3,
        'comment' => '1:internal, 2:external, 3:both',
    ]),
    new Column('hourmin', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('hourmax', Type::getType('integer'), [
        'notnull' => true,
        'default' => 24,
    ]),
    new Column('logs_lifetime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 30,
        'comment' => 'number of days',
    ]),
    new Column('lastrun', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
        'comment' => 'last run date',
    ]),
    new Column('lastcode', Type::getType('integer'), [
        'notnull' => false,
        'default' => null,
        'comment' => 'last run return code',
    ]),
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_dashboards_dashboards')) {
    $schema->dropTable('glpi_dashboards_dashboards');
}
$schema->createTable(new Table('glpi_dashboards_dashboards', [
    $column_templates['id'],
    new Column('key', Type::getType('string'), [
        'length' => 100,
        'notnull' => true,
    ]),
    $column_templates['name'],
    new Column('context', Type::getType('string'), [
        'length' => 100,
        'notnull' => true,
    ]),
    $fk_column('users_id')
], [
    $id_index
]));

if ($schema->tablesExist('glpi_dashboards_filters')) {
    $schema->dropTable('glpi_dashboards_filters');
}
$schema->createTable(new Table('glpi_dashboards_filters', [
    $column_templates['id'],
    $fk_column('dashboards_dashboards_id'),
    $fk_column('users_id'),
    new Column('filter', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_dashboards_items')) {
    $schema->dropTable('glpi_dashboards_items');
}
$schema->createTable(new Table('glpi_dashboards_items', [
    $column_templates['id'],
    $fk_column('dashboards_dashboards_id'),
    new Column('gridstack_id', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
    ]),
    new Column('card_id', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
    ]),
    new Column('x', Type::getType('integer'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('y', Type::getType('integer'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('width', Type::getType('integer'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('height', Type::getType('integer'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('card_options', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_dashboards_rights')) {
    $schema->dropTable('glpi_dashboards_rights');
}
$schema->createTable(new Table('glpi_dashboards_rights', [
    $column_templates['id'],
    $fk_column('dashboards_dashboards_id'),
    $column_templates['itemtype'],
    $column_templates['items_id'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_devicecasemodels')) {
    $schema->dropTable('glpi_devicecasemodels');
}

if ($schema->tablesExist('glpi_devicecases')) {
    $schema->dropTable('glpi_devicecases');
}

if ($schema->tablesExist('glpi_devicecasetypes')) {
    $schema->dropTable('glpi_devicecasetypes');
}
$schema->createTable(new Table('glpi_devicecasetypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_devicecontrolmodels')) {
    $schema->dropTable('glpi_devicecontrolmodels');
}

if ($schema->tablesExist('glpi_devicecontrols')) {
    $schema->dropTable('glpi_devicecontrols');
}

if ($schema->tablesExist('glpi_devicedrivemodels')) {
    $schema->dropTable('glpi_devicedrivemodels');
}

if ($schema->tablesExist('glpi_devicedrives')) {
    $schema->dropTable('glpi_devicedrives');
}

if ($schema->tablesExist('glpi_devicegenericmodels')) {
    $schema->dropTable('glpi_devicegenericmodels');
}

if ($schema->tablesExist('glpi_devicegenerics')) {
    $schema->dropTable('glpi_devicegenerics');
}

if ($schema->tablesExist('glpi_devicegenerictypes')) {
    $schema->dropTable('glpi_devicegenerictypes');
}

if ($schema->tablesExist('glpi_devicegraphiccardmodels')) {
    $schema->dropTable('glpi_devicegraphiccardmodels');
}

if ($schema->tablesExist('glpi_devicegraphiccards')) {
    $schema->dropTable('glpi_devicegraphiccards');
}

if ($schema->tablesExist('glpi_deviceharddrivemodels')) {
    $schema->dropTable('glpi_deviceharddrivemodels');
}

if ($schema->tablesExist('glpi_deviceharddrives')) {
    $schema->dropTable('glpi_deviceharddrives');
}

if ($schema->tablesExist('glpi_devicecameras')) {
    $schema->dropTable('glpi_devicecameras');
}

if ($schema->tablesExist('glpi_items_devicecameras')) {
    $schema->dropTable('glpi_items_devicecameras');
}

if ($schema->tablesExist('glpi_devicecameramodels')) {
    $schema->dropTable('glpi_devicecameramodels');
}

if ($schema->tablesExist('glpi_imageformats')) {
    $schema->dropTable('glpi_imageformats');
}

if ($schema->tablesExist('glpi_imageresolutions')) {
    $schema->dropTable('glpi_imageresolutions');
}

if ($schema->tablesExist('glpi_items_devicecameras_imageformats')) {
    $schema->dropTable('glpi_items_devicecameras_imageformats');
}

if ($schema->tablesExist('glpi_items_devicecameras_imageresolutions')) {
    $schema->dropTable('glpi_items_devicecameras_imageresolutions');
}

if ($schema->tablesExist('glpi_devicememorymodels')) {
    $schema->dropTable('glpi_devicememorymodels');
}

if ($schema->tablesExist('glpi_devicememories')) {
    $schema->dropTable('glpi_devicememories');
}

if ($schema->tablesExist('glpi_devicememorytypes')) {
    $schema->dropTable('glpi_devicememorytypes');
}
$schema->createTable(new Table('glpi_devicememorytypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_devicemotherboardmodels')) {
    $schema->dropTable('glpi_devicemotherboardmodels');
}

if ($schema->tablesExist('glpi_devicemotherboards')) {
    $schema->dropTable('glpi_devicemotherboards');
}

if ($schema->tablesExist('glpi_devicenetworkcardmodels')) {
    $schema->dropTable('glpi_devicenetworkcardmodels');
}

if ($schema->tablesExist('glpi_devicenetworkcards')) {
    $schema->dropTable('glpi_devicenetworkcards');
}

if ($schema->tablesExist('glpi_devicepcimodels')) {
    $schema->dropTable('glpi_devicepcimodels');
}

if ($schema->tablesExist('glpi_devicepcis')) {
    $schema->dropTable('glpi_devicepcis');
}

if ($schema->tablesExist('glpi_devicepowersupplymodels')) {
    $schema->dropTable('glpi_devicepowersupplymodels');
}

if ($schema->tablesExist('glpi_devicepowersupplies')) {
    $schema->dropTable('glpi_devicepowersupplies');
}

if ($schema->tablesExist('glpi_deviceprocessormodels')) {
    $schema->dropTable('glpi_deviceprocessormodels');
}

if ($schema->tablesExist('glpi_deviceprocessors')) {
    $schema->dropTable('glpi_deviceprocessors');
}

if ($schema->tablesExist('glpi_devicesensors')) {
    $schema->dropTable('glpi_devicesensors');
}

if ($schema->tablesExist('glpi_devicesensormodels')) {
    $schema->dropTable('glpi_devicesensormodels');
}

if ($schema->tablesExist('glpi_devicesensortypes')) {
    $schema->dropTable('glpi_devicesensortypes');
}

if ($schema->tablesExist('glpi_devicesimcards')) {
    $schema->dropTable('glpi_devicesimcards');
}

if ($schema->tablesExist('glpi_items_devicesimcards')) {
    $schema->dropTable('glpi_items_devicesimcards');
}

if ($schema->tablesExist('glpi_devicesimcardtypes')) {
    $schema->dropTable('glpi_devicesimcardtypes');
}
$schema->createTable(new Table('glpi_devicesimcardtypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_devicesoundcardmodels')) {
    $schema->dropTable('glpi_devicesoundcardmodels');
}

if ($schema->tablesExist('glpi_devicesoundcards')) {
    $schema->dropTable('glpi_devicesoundcards');
}

if ($schema->tablesExist('glpi_displaypreferences')) {
    $schema->dropTable('glpi_displaypreferences');
}
$schema->createTable(new Table('glpi_displaypreferences', [
    $column_templates['id'],
    $column_templates['itemtype'],
    new Column('num', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('rank', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $fk_column('users_id')
], [
    $id_index
]));

if ($schema->tablesExist('glpi_documentcategories')) {
    $schema->dropTable('glpi_documentcategories');
}

if ($schema->tablesExist('glpi_documents')) {
    $schema->dropTable('glpi_documents');
}

if ($schema->tablesExist('glpi_documents_items')) {
    $schema->dropTable('glpi_documents_items');
}

if ($schema->tablesExist('glpi_documenttypes')) {
    $schema->dropTable('glpi_documenttypes');
}
$schema->createTable(new Table('glpi_documenttypes', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('ext', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('icon', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('mime', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('is_uploadable', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true,
    ]),
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_domains')) {
    $schema->dropTable('glpi_domains');
}

if ($schema->tablesExist('glpi_dropdowntranslations')) {
    $schema->dropTable('glpi_dropdowntranslations');
}
$schema->createTable(new Table('glpi_dropdowntranslations', [
    $column_templates['id'],
    $column_templates['items_id'],
    $column_templates['itemtype'],
    new Column('language', Type::getType('string'), [
        'length' => 10,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('value', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_entities')) {
    $schema->dropTable('glpi_entities');
}
$schema->createTable(new Table('glpi_entities', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('entities_id', Type::getType('integer'), [
        'unsigned' => true,
        'notnull' => false,
        'default' => 0,
    ]),
    new Column('completename', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['comment'],
    new Column('level', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('sons_cache', Type::getType('text'), ['length' => 4294967295, 'notnull' => false]),
    new Column('ancestors_cache', Type::getType('text'), ['length' => 4294967295, 'notnull' => false]),
    new Column('registration_number', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('address', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('postcode', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('town', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('state', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('country', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('website', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('phonenumber', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('fax', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('email', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('admin_email', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('admin_email_name', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('from_email', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('from_email_name', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('noreply_email', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('noreply_email_name', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('replyto_email', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('replyto_email_name', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('notification_subject_tag', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('ldap_dn', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('tag', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('authldaps_id'),
    new Column('mail_domain', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('entity_ldapfilter', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('mailing_signature', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('cartridges_alert_repeat', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('consumables_alert_repeat', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('use_licenses_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('send_licenses_alert_before_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('use_certificates_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('send_certificates_alert_before_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('certificates_alert_repeat_interval', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('use_contracts_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('send_contracts_alert_before_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('use_infocoms_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('send_infocoms_alert_before_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('use_reservations_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('use_domains_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('send_domains_alert_close_expiries_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('send_domains_alert_expired_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('autoclose_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('autopurge_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -10,
    ]),
    new Column('notclosed_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('calendars_strategy', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    $fk_column('calendars_id'),
    new Column('auto_assign_mode', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('tickettype', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('max_closedate', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('inquest_config', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('inquest_rate', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('inquest_delay', Type::getType('integer'), [
        'notnull' => true,
        'default' => -10,
    ]),
    new Column('inquest_URL', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('inquest_max_rate', Type::getType('integer'), [
        'notnull' => true,
        'default' => 5,
    ]),
    new Column('inquest_default_rate', Type::getType('integer'), [
        'notnull' => true,
        'default' => 3,
    ]),
    new Column('inquest_mandatory_comment', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('max_closedate_change', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('inquest_config_change', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('inquest_rate_change', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('inquest_delay_change', Type::getType('integer'), [
        'notnull' => true,
        'default' => -10,
    ]),
    new Column('inquest_URL_change', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('inquest_max_rate_change', Type::getType('integer'), [
        'notnull' => true,
        'default' => 5,
    ]),
    new Column('inquest_default_rate_change', Type::getType('integer'), [
        'notnull' => true,
        'default' => 3,
    ]),
    new Column('inquest_mandatory_comment_change', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('autofill_warranty_date', Type::getType('string'), [
        'length' => 255,
        'default' => '-2',
    ]),
    new Column('autofill_use_date', Type::getType('string'), [
        'length' => 255,
        'default' => '-2',
    ]),
    new Column('autofill_buy_date', Type::getType('string'), [
        'length' => 255,
        'default' => '-2',
    ]),
    new Column('autofill_delivery_date', Type::getType('string'), [
        'length' => 255,
        'default' => '-2',
    ]),
    new Column('autofill_order_date', Type::getType('string'), [
        'length' => 255,
        'default' => '-2',
    ]),
    new Column('tickettemplates_strategy', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => -2,
    ]),
    $fk_column('tickettemplates_id'),
    new Column('changetemplates_strategy', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => -2,
    ]),
    $fk_column('changetemplates_id'),
    new Column('problemtemplates_strategy', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => -2,
    ]),
    $fk_column('problemtemplates_id'),
    new Column('entities_strategy_software', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => -2,
    ]),
    $fk_column('entities_id_software'),
    new Column('default_contract_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('default_infocom_alert', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('default_cartridges_alarm_threshold', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('default_consumables_alarm_threshold', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('delay_send_emails', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('is_notif_enable_default', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('inquest_duration', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('inquest_duration_change', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    new Column('autofill_decommission_date', Type::getType('string'), [
        'length' => 255,
        'default' => '-2',
    ]),
    new Column('suppliers_as_private', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('anonymize_support_agents', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('display_users_initials', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('contracts_strategy_default', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => -2,
    ]),
    $fk_column('contracts_id_default'),
    new Column('enable_custom_css', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
    new Column('custom_css_code', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('latitude', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('longitude', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('altitude', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('transfers_strategy', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => -2,
    ]),
    $fk_column('transfers_id'),
    new Column('agent_base_url', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('approval_reminder_repeat_interval', Type::getType('integer'), [
        'notnull' => true,
        'default' => -2,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_entities_knowbaseitems')) {
    $schema->dropTable('glpi_entities_knowbaseitems');
}

if ($schema->tablesExist('glpi_entities_reminders')) {
    $schema->dropTable('glpi_entities_reminders');
}

if ($schema->tablesExist('glpi_entities_rssfeeds')) {
    $schema->dropTable('glpi_entities_rssfeeds');
}

if ($schema->tablesExist('glpi_events')) {
    $schema->dropTable('glpi_events');
}
$schema->createTable(new Table('glpi_events', [
    $column_templates['id'],
    $column_templates['items_id'],
    new Column('type', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('service', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('level', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('message', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_fieldblacklists')) {
    $schema->dropTable('glpi_fieldblacklists');
}
$schema->createTable(new Table('glpi_fieldblacklists', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('value', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $column_templates['itemtype'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_fieldunicities')) {
    $schema->dropTable('glpi_fieldunicities');
}
$schema->createTable(new Table('glpi_fieldunicities', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['is_recursive'],
    $column_templates['itemtype'],
    $column_templates['entities_id'],
    new Column('fields', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('is_active', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('action_refuse', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('action_notify', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_filesystems')) {
    $schema->dropTable('glpi_filesystems');
}
$schema->createTable(new Table('glpi_filesystems', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_fqdns')) {
    $schema->dropTable('glpi_fqdns');
}

if ($schema->tablesExist('glpi_groups')) {
    $schema->dropTable('glpi_groups');
}
$schema->createTable(new Table('glpi_groups', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['name'],
    $column_templates['comment'],
    new Column('ldap_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('ldap_value', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('ldap_group_dn', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['date_mod'],
    $fk_column('groups_id'),
    new Column('completename', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('level', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('ancestors_cache', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('sons_cache', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('is_requester', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_watcher', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_assign', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_task', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_notify', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_itemgroup', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_usergroup', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_manager', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_groups_knowbaseitems')) {
    $schema->dropTable('glpi_groups_knowbaseitems');
}
$schema->createTable(new Table('glpi_groups_knowbaseitems', [
    $column_templates['id'],
    $fk_column('knowbaseitems_id'),
    $fk_column('groups_id'),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('no_entity_restriction', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_groups_problems')) {
    $schema->dropTable('glpi_groups_problems');
}
$schema->createTable(new Table('glpi_groups_problems', [
    $column_templates['id'],
    $fk_column('problems_id'),
    $fk_column('groups_id'),
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_groups_reminders')) {
    $schema->dropTable('glpi_groups_reminders');
}
$schema->createTable(new Table('glpi_groups_reminders', [
    $column_templates['id'],
    $fk_column('reminders_id'),
    $fk_column('groups_id'),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('no_entity_restriction', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_groups_rssfeeds')) {
    $schema->dropTable('glpi_groups_rssfeeds');
}
$schema->createTable(new Table('glpi_groups_rssfeeds', [
    $column_templates['id'],
    $fk_column('rssfeeds_id'),
    $fk_column('groups_id'),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('no_entity_restriction', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_groups_tickets')) {
    $schema->dropTable('glpi_groups_tickets');
}
$schema->createTable(new Table('glpi_groups_tickets', [
    $column_templates['id'],
    $fk_column('tickets_id'),
    $fk_column('groups_id'),
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 1,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_groups_users')) {
    $schema->dropTable('glpi_groups_users');
}
$schema->createTable(new Table('glpi_groups_users', [
    $column_templates['id'],
    $fk_column('users_id'),
    $fk_column('groups_id'),
    new Column('is_dynamic', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_manager', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_userdelegate', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_holidays')) {
    $schema->dropTable('glpi_holidays');
}

if ($schema->tablesExist('glpi_infocoms')) {
    $schema->dropTable('glpi_infocoms');
}

if ($schema->tablesExist('glpi_interfacetypes')) {
    $schema->dropTable('glpi_interfacetypes');
}
$schema->createTable(new Table('glpi_interfacetypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_ipaddresses')) {
    $schema->dropTable('glpi_ipaddresses');
}

if ($schema->tablesExist('glpi_ipaddresses_ipnetworks')) {
    $schema->dropTable('glpi_ipaddresses_ipnetworks');
}

if ($schema->tablesExist('glpi_ipnetworks')) {
    $schema->dropTable('glpi_ipnetworks');
}

if ($schema->tablesExist('glpi_ipnetworks_vlans')) {
    $schema->dropTable('glpi_ipnetworks_vlans');
}

if ($schema->tablesExist('glpi_items_devicecases')) {
    $schema->dropTable('glpi_items_devicecases');
}

if ($schema->tablesExist('glpi_items_devicecontrols')) {
    $schema->dropTable('glpi_items_devicecontrols');
}

if ($schema->tablesExist('glpi_items_devicedrives')) {
    $schema->dropTable('glpi_items_devicedrives');
}

if ($schema->tablesExist('glpi_items_devicegenerics')) {
    $schema->dropTable('glpi_items_devicegenerics');
}

if ($schema->tablesExist('glpi_items_devicegraphiccards')) {
    $schema->dropTable('glpi_items_devicegraphiccards');
}

if ($schema->tablesExist('glpi_items_deviceharddrives')) {
    $schema->dropTable('glpi_items_deviceharddrives');
}

if ($schema->tablesExist('glpi_items_devicememories')) {
    $schema->dropTable('glpi_items_devicememories');
}

if ($schema->tablesExist('glpi_items_devicemotherboards')) {
    $schema->dropTable('glpi_items_devicemotherboards');
}

if ($schema->tablesExist('glpi_items_devicenetworkcards')) {
    $schema->dropTable('glpi_items_devicenetworkcards');
}

if ($schema->tablesExist('glpi_items_devicepcis')) {
    $schema->dropTable('glpi_items_devicepcis');
}

if ($schema->tablesExist('glpi_items_devicepowersupplies')) {
    $schema->dropTable('glpi_items_devicepowersupplies');
}

if ($schema->tablesExist('glpi_items_deviceprocessors')) {
    $schema->dropTable('glpi_items_deviceprocessors');
}

if ($schema->tablesExist('glpi_items_devicesensors')) {
    $schema->dropTable('glpi_items_devicesensors');
}

if ($schema->tablesExist('glpi_items_devicesoundcards')) {
    $schema->dropTable('glpi_items_devicesoundcards');
}

if ($schema->tablesExist('glpi_items_problems')) {
    $schema->dropTable('glpi_items_problems');
}

if ($schema->tablesExist('glpi_items_processes')) {
    $schema->dropTable('glpi_items_processes');
}

if ($schema->tablesExist('glpi_items_environments')) {
    $schema->dropTable('glpi_items_environments');
}

if ($schema->tablesExist('glpi_items_projects')) {
    $schema->dropTable('glpi_items_projects');
}

if ($schema->tablesExist('glpi_items_tickets')) {
    $schema->dropTable('glpi_items_tickets');
}

if ($schema->tablesExist('glpi_itilcategories')) {
    $schema->dropTable('glpi_itilcategories');
}

if ($schema->tablesExist('glpi_itils_projects')) {
    $schema->dropTable('glpi_itils_projects');
}

if ($schema->tablesExist('glpi_knowbaseitemcategories')) {
    $schema->dropTable('glpi_knowbaseitemcategories');
}

if ($schema->tablesExist('glpi_knowbaseitems')) {
    $schema->dropTable('glpi_knowbaseitems');
}

if ($schema->tablesExist('glpi_knowbaseitems_knowbaseitemcategories')) {
    $schema->dropTable('glpi_knowbaseitems_knowbaseitemcategories');
}

if ($schema->tablesExist('glpi_knowbaseitems_profiles')) {
    $schema->dropTable('glpi_knowbaseitems_profiles');
}

if ($schema->tablesExist('glpi_knowbaseitems_users')) {
    $schema->dropTable('glpi_knowbaseitems_users');
}

if ($schema->tablesExist('glpi_knowbaseitemtranslations')) {
    $schema->dropTable('glpi_knowbaseitemtranslations');
}

if ($schema->tablesExist('glpi_lines')) {
    $schema->dropTable('glpi_lines');
}

if ($schema->tablesExist('glpi_lineoperators')) {
    $schema->dropTable('glpi_lineoperators');
}

if ($schema->tablesExist('glpi_linetypes')) {
    $schema->dropTable('glpi_linetypes');
}
$schema->createTable(new Table('glpi_linetypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_links')) {
    $schema->dropTable('glpi_links');
}

if ($schema->tablesExist('glpi_links_itemtypes')) {
    $schema->dropTable('glpi_links_itemtypes');
}

if ($schema->tablesExist('glpi_locations')) {
    $schema->dropTable('glpi_locations');
}

if ($schema->tablesExist('glpi_logs')) {
    $schema->dropTable('glpi_logs');
}
$schema->createTable(new Table('glpi_logs', [
    $column_templates['id'],
    $column_templates['itemtype'],
    $column_templates['items_id'],
    new Column('itemtype_link', Type::getType('string'), ['length' => 100, 'notnull' => true, 'default' => '']),
    new Column('linked_action', Type::getType('integer'), ['notnull' => true, 'default' => 0, 'comment' => 'see define.php HISTORY_* constant']),
    new Column('user_name', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    $column_templates['date_mod'],
    new Column('id_search_option', Type::getType('integer'), ['notnull' => true, 'default' => 0, 'comment' => 'see search.constant.php for value']),
    new Column('old_value', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    new Column('new_value', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_mailcollectors')) {
    $schema->dropTable('glpi_mailcollectors');
}

if ($schema->tablesExist('glpi_manufacturers')) {
    $schema->dropTable('glpi_manufacturers');
}
$schema->createTable(new Table('glpi_manufacturers', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_monitormodels')) {
    $schema->dropTable('glpi_monitormodels');
}

if ($schema->tablesExist('glpi_monitors')) {
    $schema->dropTable('glpi_monitors');
}

if ($schema->tablesExist('glpi_monitortypes')) {
    $schema->dropTable('glpi_monitortypes');
}
$schema->createTable(new Table('glpi_monitortypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_sockets')) {
    $schema->dropTable('glpi_sockets');
}

if ($schema->tablesExist('glpi_cables')) {
    $schema->dropTable('glpi_cables');
}

if ($schema->tablesExist('glpi_cabletypes')) {
    $schema->dropTable('glpi_cabletypes');
}
$schema->createTable(new Table('glpi_cabletypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_cablestrands')) {
    $schema->dropTable('glpi_cablestrands');
}
$schema->createTable(new Table('glpi_cablestrands', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_socketmodels')) {
    $schema->dropTable('glpi_socketmodels');
}
$schema->createTable(new Table('glpi_socketmodels', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_networkaliases')) {
    $schema->dropTable('glpi_networkaliases');
}

if ($schema->tablesExist('glpi_networkequipmentmodels')) {
    $schema->dropTable('glpi_networkequipmentmodels');
}

if ($schema->tablesExist('glpi_networkequipments')) {
    $schema->dropTable('glpi_networkequipments');
}

if ($schema->tablesExist('glpi_networkequipmenttypes')) {
    $schema->dropTable('glpi_networkequipmenttypes');
}
$schema->createTable(new Table('glpi_networkequipmenttypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_networkinterfaces')) {
    $schema->dropTable('glpi_networkinterfaces');
}
$schema->createTable(new Table('glpi_networkinterfaces', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_networknames')) {
    $schema->dropTable('glpi_networknames');
}

if ($schema->tablesExist('glpi_networkportaggregates')) {
    $schema->dropTable('glpi_networkportaggregates');
}

if ($schema->tablesExist('glpi_networkportaliases')) {
    $schema->dropTable('glpi_networkportaliases');
}

if ($schema->tablesExist('glpi_networkportdialups')) {
    $schema->dropTable('glpi_networkportdialups');
}

if ($schema->tablesExist('glpi_networkportethernets')) {
    $schema->dropTable('glpi_networkportethernets');
}

if ($schema->tablesExist('glpi_networkportfiberchanneltypes')) {
    $schema->dropTable('glpi_networkportfiberchanneltypes');
}
$schema->createTable(new Table('glpi_networkportfiberchanneltypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_networkportfiberchannels')) {
    $schema->dropTable('glpi_networkportfiberchannels');
}

if ($schema->tablesExist('glpi_networkportlocals')) {
    $schema->dropTable('glpi_networkportlocals');
}

if ($schema->tablesExist('glpi_networkports')) {
    $schema->dropTable('glpi_networkports');
}

if ($schema->tablesExist('glpi_networkports_networkports')) {
    $schema->dropTable('glpi_networkports_networkports');
}

if ($schema->tablesExist('glpi_networkports_vlans')) {
    $schema->dropTable('glpi_networkports_vlans');
}

if ($schema->tablesExist('glpi_networkportwifis')) {
    $schema->dropTable('glpi_networkportwifis');
}

if ($schema->tablesExist('glpi_networks')) {
    $schema->dropTable('glpi_networks');
}
$schema->createTable(new Table('glpi_networks', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_notepads')) {
    $schema->dropTable('glpi_notepads');
}

if ($schema->tablesExist('glpi_notifications')) {
    $schema->dropTable('glpi_notifications');
}
$schema->createTable(new Table('glpi_notifications', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['itemtype'],
    new Column('event', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
    ]),
    $column_templates['comment'],
    $column_templates['is_recursive'],
    $column_templates['is_active'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    new Column('allow_response', Type::getType('boolean'), [
        'notnull' => true,
        'default' => 1,
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_notifications_notificationtemplates')) {
    $schema->dropTable('glpi_notifications_notificationtemplates');
}
$schema->createTable(new Table('glpi_notifications_notificationtemplates', [
    $column_templates['id'],
    $fk_column('notifications_id'),
    new Column('mode', Type::getType('string'), [
        'length' => 20,
        'notnull' => true,
        'comment' => 'See Notification_NotificationTemplate::MODE_* constants'
    ]),
    $fk_column('notificationtemplates_id'),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_notificationtargets')) {
    $schema->dropTable('glpi_notificationtargets');
}
$schema->createTable(new Table('glpi_notificationtargets', [
    $column_templates['id'],
    $column_templates['items_id'],
    new Column('type', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    $fk_column('notifications_id'),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_notificationtemplates')) {
    $schema->dropTable('glpi_notificationtemplates');
}
$schema->createTable(new Table('glpi_notificationtemplates', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['itemtype'],
    $column_templates['date_mod'],
    $column_templates['comment'],
    new Column('css', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['date_creation']
], [
    $id_index
]));

if ($schema->tablesExist('glpi_notificationtemplatetranslations')) {
    $schema->dropTable('glpi_notificationtemplatetranslations');
}
$schema->createTable(new Table('glpi_notificationtemplatetranslations', [
    $column_templates['id'],
    $fk_column('notificationtemplates_id'),
    new Column('language', Type::getType('string'), [
        'length' => 10,
        'notnull' => true,
        'default' => ''
    ]),
    new Column('subject', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
    ]),
    new Column('content_text', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('content_html', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_notimportedemails')) {
    $schema->dropTable('glpi_notimportedemails');
}

if ($schema->tablesExist('glpi_objectlocks')) {
    $schema->dropTable('glpi_objectlocks');
}

if ($schema->tablesExist('glpi_operatingsystemarchitectures')) {
    $schema->dropTable('glpi_operatingsystemarchitectures');
}
$schema->createTable(new Table('glpi_operatingsystemarchitectures', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_operatingsystems')) {
    $schema->dropTable('glpi_operatingsystems');
}
$schema->createTable(new Table('glpi_operatingsystems', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_operatingsystemservicepacks')) {
    $schema->dropTable('glpi_operatingsystemservicepacks');
}
$schema->createTable(new Table('glpi_operatingsystemservicepacks', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_operatingsystemversions')) {
    $schema->dropTable('glpi_operatingsystemversions');
}
$schema->createTable(new Table('glpi_operatingsystemversions', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_passivedcequipments')) {
    $schema->dropTable('glpi_passivedcequipments');
}

if ($schema->tablesExist('glpi_passivedcequipmentmodels')) {
    $schema->dropTable('glpi_passivedcequipmentmodels');
}

if ($schema->tablesExist('glpi_passivedcequipmenttypes')) {
    $schema->dropTable('glpi_passivedcequipmenttypes');
}
$schema->createTable(new Table('glpi_passivedcequipmenttypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_peripheralmodels')) {
    $schema->dropTable('glpi_peripheralmodels');
}

if ($schema->tablesExist('glpi_peripherals')) {
    $schema->dropTable('glpi_peripherals');
}

if ($schema->tablesExist('glpi_peripheraltypes')) {
    $schema->dropTable('glpi_peripheraltypes');
}
$schema->createTable(new Table('glpi_peripheraltypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_phonemodels')) {
    $schema->dropTable('glpi_phonemodels');
}

if ($schema->tablesExist('glpi_phonepowersupplies')) {
    $schema->dropTable('glpi_phonepowersupplies');
}
$schema->createTable(new Table('glpi_phonepowersupplies', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_phones')) {
    $schema->dropTable('glpi_phones');
}

if ($schema->tablesExist('glpi_phonetypes')) {
    $schema->dropTable('glpi_phonetypes');
}
$schema->createTable(new Table('glpi_phonetypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_planningrecalls')) {
    $schema->dropTable('glpi_planningrecalls');
}

if ($schema->tablesExist('glpi_plugins')) {
    $schema->dropTable('glpi_plugins');
}

if ($schema->tablesExist('glpi_printermodels')) {
    $schema->dropTable('glpi_printermodels');
}

if ($schema->tablesExist('glpi_printers')) {
    $schema->dropTable('glpi_printers');
}

if ($schema->tablesExist('glpi_printertypes')) {
    $schema->dropTable('glpi_printertypes');
}
$schema->createTable(new Table('glpi_printertypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_problemcosts')) {
    $schema->dropTable('glpi_problemcosts');
}
$schema->createTable(new Table('glpi_problemcosts', [
    $column_templates['id'],
    $fk_column('problems_id'),
    $column_templates['name'],
    $column_templates['comment'],
    new Column('begin_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('actiontime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_time', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_fixed', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_material', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    $fk_column('budgets_id'),
    $column_templates['entities_id'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_problems')) {
    $schema->dropTable('glpi_problems');
}

if ($schema->tablesExist('glpi_problems_suppliers')) {
    $schema->dropTable('glpi_problems_suppliers');
}

if ($schema->tablesExist('glpi_problems_tickets')) {
    $schema->dropTable('glpi_problems_tickets');
}

if ($schema->tablesExist('glpi_problems_users')) {
    $schema->dropTable('glpi_problems_users');
}

if ($schema->tablesExist('glpi_problemtasks')) {
    $schema->dropTable('glpi_problemtasks');
}
$schema->createTable(new Table('glpi_problemtasks', [
    $column_templates['id'],
    new Column('uuid', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('problems_id'),
    $fk_column('taskcategories_id'),
    new Column('date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('begin', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('users_id'),
    $fk_column('users_id_editor'),
    $fk_column('users_id_tech'),
    $fk_column('groups_id_tech'),
    new Column('content', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('actiontime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('state', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $fk_column('tasktemplates_id'),
    new Column('timeline_position', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('is_private', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_profilerights')) {
    $schema->dropTable('glpi_profilerights');
}
$schema->createTable(new Table('glpi_profilerights', [
    $column_templates['id'],
    $fk_column('profiles_id'),
    $column_templates['name'],
    new Column('rights', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ])
], [
    $id_index
]));

if ($schema->tablesExist('glpi_profiles')) {
    $schema->dropTable('glpi_profiles');
}
$schema->createTable(new Table('glpi_profiles', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('interface', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => 'helpdesk'
    ]),
    new Column('is_default', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    new Column('helpdesk_hardware', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('helpdesk_item_type', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('ticket_status', Type::getType('text'), [
        'length' => 65535,
        'notnull' => false,
        'comment' => 'json encoded array of from/dest allowed status change'
    ]),
    $column_templates['date_mod'],
    $column_templates['comment'],
    new Column('problem_status', Type::getType('text'), [
        'length' => 65535,
        'notnull' => false,
        'comment' => 'json encoded array of from/dest allowed status change'
    ]),
    new Column('create_ticket_on_login', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    $fk_column('tickettemplates_id'),
    $fk_column('changetemplates_id'),
    $fk_column('problemtemplates_id'),
    new Column('change_status', Type::getType('text'), [
        'length' => 65535,
        'notnull' => false,
        'comment' => 'json encoded array of from/dest allowed status change'
    ]),
    new Column('managed_domainrecordtypes', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['date_creation']
], [
    $id_index
]));

if ($schema->tablesExist('glpi_profiles_reminders')) {
    $schema->dropTable('glpi_profiles_reminders');
}

if ($schema->tablesExist('glpi_profiles_rssfeeds')) {
    $schema->dropTable('glpi_profiles_rssfeeds');
}

if ($schema->tablesExist('glpi_profiles_users')) {
    $schema->dropTable('glpi_profiles_users');
}
$schema->createTable(new Table('glpi_profiles_users', [
    $column_templates['id'],
    $fk_column('users_id'),
    $fk_column('profiles_id'),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('is_dynamic', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    new Column('is_default_profile', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_projectcosts')) {
    $schema->dropTable('glpi_projectcosts');
}

if ($schema->tablesExist('glpi_projects')) {
    $schema->dropTable('glpi_projects');
}

if ($schema->tablesExist('glpi_projectstates')) {
    $schema->dropTable('glpi_projectstates');
}
$schema->createTable(new Table('glpi_projectstates', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    new Column('color', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('is_finished', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation']
], [
    $id_index
]));

if ($schema->tablesExist('glpi_projecttasks')) {
    $schema->dropTable('glpi_projecttasks');
}

if ($schema->tablesExist('glpi_projecttasklinks')) {
    $schema->dropTable('glpi_projecttasklinks');
}

if ($schema->tablesExist('glpi_projecttasktemplates')) {
    $schema->dropTable('glpi_projecttasktemplates');
}

if ($schema->tablesExist('glpi_projecttasks_tickets')) {
    $schema->dropTable('glpi_projecttasks_tickets');
}

if ($schema->tablesExist('glpi_projecttaskteams')) {
    $schema->dropTable('glpi_projecttaskteams');
}

if ($schema->tablesExist('glpi_projecttasktypes')) {
    $schema->dropTable('glpi_projecttasktypes');
}

if ($schema->tablesExist('glpi_projectteams')) {
    $schema->dropTable('glpi_projectteams');
}

if ($schema->tablesExist('glpi_projecttypes')) {
    $schema->dropTable('glpi_projecttypes');
}

if ($schema->tablesExist('glpi_queuednotifications')) {
    $schema->dropTable('glpi_queuednotifications');
}

if ($schema->tablesExist('glpi_registeredids')) {
    $schema->dropTable('glpi_registeredids');
}

if ($schema->tablesExist('glpi_reminders')) {
    $schema->dropTable('glpi_reminders');
}

if ($schema->tablesExist('glpi_remindertranslations')) {
    $schema->dropTable('glpi_remindertranslations');
}

if ($schema->tablesExist('glpi_reminders_users')) {
    $schema->dropTable('glpi_reminders_users');
}

if ($schema->tablesExist('glpi_requesttypes')) {
    $schema->dropTable('glpi_requesttypes');
}
$schema->createTable(new Table('glpi_requesttypes', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('is_helpdesk_default', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    new Column('is_followup_default', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    new Column('is_mail_default', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    new Column('is_mailfollowup_default', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    new Column('is_active', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true
    ]),
    new Column('is_ticketheader', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true
    ]),
    new Column('is_itilfollowup', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true
    ]),
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation']
], [
    $id_index
]));

if ($schema->tablesExist('glpi_reservationitems')) {
    $schema->dropTable('glpi_reservationitems');
}

if ($schema->tablesExist('glpi_reservations')) {
    $schema->dropTable('glpi_reservations');
}

if ($schema->tablesExist('glpi_rssfeeds')) {
    $schema->dropTable('glpi_rssfeeds');
}

if ($schema->tablesExist('glpi_rssfeeds_users')) {
    $schema->dropTable('glpi_rssfeeds_users');
}

if ($schema->tablesExist('glpi_ruleactions')) {
    $schema->dropTable('glpi_ruleactions');
}
$schema->createTable(new Table('glpi_ruleactions', [
    $column_templates['id'],
    $fk_column('rules_id'),
    new Column('action_type', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
        'comment' => 'VALUE IN (assign, regex_result, append_regex_result, affectbyip, affectbyfqdn, affectbymac)'
    ]),
    new Column('field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('value', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_rulecriterias')) {
    $schema->dropTable('glpi_rulecriterias');
}
$schema->createTable(new Table('glpi_rulecriterias', [
    $column_templates['id'],
    $fk_column('rules_id'),
    new Column('criteria', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    new Column('condition', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
        'comment' => 'see define.php PATTERN_* and REGEX_* constant'
    ]),
    new Column('pattern', Type::getType('text'), ['length' => 65535, 'notnull' => false])
], [
    $id_index
]));

if ($schema->tablesExist('glpi_rulerightparameters')) {
    $schema->dropTable('glpi_rulerightparameters');
}
$schema->createTable(new Table('glpi_rulerightparameters', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('value', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation']
], [
    $id_index
]));

if ($schema->tablesExist('glpi_rules')) {
    $schema->dropTable('glpi_rules');
}
$schema->createTable(new Table('glpi_rules', [
    $column_templates['id'],
    $column_templates['entities_id'],
    new Column('sub_type', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => ''
    ]),
    new Column('ranking', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    $column_templates['name'],
    new Column('description', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('match', Type::getType('string'), [
        'length' => 10,
        'notnull' => false,
        'default' => null,
        'comment' => 'see define.php *_MATCHING constant'
    ]),
    new Column('is_active', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true
    ]),
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['is_recursive'],
    new Column('uuid', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('condition', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $column_templates['date_creation']
], [
    $id_index
]));

if ($schema->tablesExist('glpi_slalevelactions')) {
    $schema->dropTable('glpi_slalevelactions');
}

if ($schema->tablesExist('glpi_slalevelcriterias')) {
    $schema->dropTable('glpi_slalevelcriterias');
}

if ($schema->tablesExist('glpi_slalevels')) {
    $schema->dropTable('glpi_slalevels');
}

if ($schema->tablesExist('glpi_slalevels_tickets')) {
    $schema->dropTable('glpi_slalevels_tickets');
}

if ($schema->tablesExist('glpi_olalevelactions')) {
    $schema->dropTable('glpi_olalevelactions');
}

if ($schema->tablesExist('glpi_olalevelcriterias')) {
    $schema->dropTable('glpi_olalevelcriterias');
}

if ($schema->tablesExist('glpi_olalevels')) {
    $schema->dropTable('glpi_olalevels');
}

if ($schema->tablesExist('glpi_olalevels_tickets')) {
    $schema->dropTable('glpi_olalevels_tickets');
}

if ($schema->tablesExist('glpi_slms')) {
    $schema->dropTable('glpi_slms');
}

if ($schema->tablesExist('glpi_slas')) {
    $schema->dropTable('glpi_slas');
}

if ($schema->tablesExist('glpi_olas')) {
    $schema->dropTable('glpi_olas');
}

if ($schema->tablesExist('glpi_softwarecategories')) {
    $schema->dropTable('glpi_softwarecategories');
}
$schema->createTable(new Table('glpi_softwarecategories', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $fk_column('softwarecategories_id'),
    new Column('completename', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('level', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('sons_cache', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('ancestors_cache', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_softwarelicenses')) {
    $schema->dropTable('glpi_softwarelicenses');
}

if ($schema->tablesExist('glpi_softwarelicensetypes')) {
    $schema->dropTable('glpi_softwarelicensetypes');
}
$schema->createTable(new Table('glpi_softwarelicensetypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $fk_column('softwarelicensetypes_id'),
    new Column('level', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('sons_cache', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('ancestors_cache', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('completename', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_softwares')) {
    $schema->dropTable('glpi_softwares');
}

if ($schema->tablesExist('glpi_softwareversions')) {
    $schema->dropTable('glpi_softwareversions');
}

if ($schema->tablesExist('glpi_solutiontemplates')) {
    $schema->dropTable('glpi_solutiontemplates');
}

if ($schema->tablesExist('glpi_solutiontypes')) {
    $schema->dropTable('glpi_solutiontypes');
}
$schema->createTable(new Table('glpi_solutiontypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_itilsolutions')) {
    $schema->dropTable('glpi_itilsolutions');
}

if ($schema->tablesExist('glpi_ssovariables')) {
    $schema->dropTable('glpi_ssovariables');
}
$schema->createTable(new Table('glpi_ssovariables', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_states')) {
    $schema->dropTable('glpi_states');
}

if ($schema->tablesExist('glpi_suppliers')) {
    $schema->dropTable('glpi_suppliers');
}

if ($schema->tablesExist('glpi_suppliers_tickets')) {
    $schema->dropTable('glpi_suppliers_tickets');
}

if ($schema->tablesExist('glpi_suppliertypes')) {
    $schema->dropTable('glpi_suppliertypes');
}
$schema->createTable(new Table('glpi_suppliertypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_taskcategories')) {
    $schema->dropTable('glpi_taskcategories');
}

if ($schema->tablesExist('glpi_tasktemplates')) {
    $schema->dropTable('glpi_tasktemplates');
}

if ($schema->tablesExist('glpi_ticketcosts')) {
    $schema->dropTable('glpi_ticketcosts');
}
$schema->createTable(new Table('glpi_ticketcosts', [
    $column_templates['id'],
    $fk_column('tickets_id'),
    $column_templates['name'],
    $column_templates['comment'],
    new Column('begin_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end_date', Type::getType('date'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('actiontime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_time', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_fixed', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('cost_material', Type::getType('decimal'), [
        'precision' => 20,
        'scale' => 4,
        'notnull' => true,
        'default' => 0,
    ]),
    $fk_column('budgets_id'),
    $column_templates['entities_id'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_ticketrecurrents')) {
    $schema->dropTable('glpi_ticketrecurrents');
}

if ($schema->tablesExist('glpi_recurrentchanges')) {
    $schema->dropTable('glpi_recurrentchanges');
}

if ($schema->tablesExist('glpi_tickets')) {
    $schema->dropTable('glpi_tickets');
}

if ($schema->tablesExist('glpi_tickets_tickets')) {
    $schema->dropTable('glpi_tickets_tickets');
}

if ($schema->tablesExist('glpi_tickets_users')) {
    $schema->dropTable('glpi_tickets_users');
}

if ($schema->tablesExist('glpi_ticketsatisfactions')) {
    $schema->dropTable('glpi_ticketsatisfactions');
}

if ($schema->tablesExist('glpi_tickettasks')) {
    $schema->dropTable('glpi_tickettasks');
}
$schema->createTable(new Table('glpi_tickettasks', [
    $column_templates['id'],
    new Column('uuid', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('tickets_id'),
    $fk_column('taskcategories_id'),
    new Column('date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    $fk_column('users_id'),
    $fk_column('users_id_editor'),
    new Column('content', Type::getType('text'), ['length' => $LONGTEXT_LENGTH, 'notnull' => false]),
    new Column('is_private', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
    new Column('actiontime', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    new Column('begin', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('end', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null,
    ]),
    new Column('state', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $fk_column('users_id_tech'),
    $fk_column('groups_id_tech'),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $fk_column('tasktemplates_id'),
    new Column('timeline_position', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0,
    ]),
    $fk_column('sourceitems_id'),
    $fk_column('sourceof_items_id'),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_tickettemplatehiddenfields')) {
    $schema->dropTable('glpi_tickettemplatehiddenfields');
}

if ($schema->tablesExist('glpi_changetemplatehiddenfields')) {
    $schema->dropTable('glpi_changetemplatehiddenfields');
}

if ($schema->tablesExist('glpi_problemtemplatehiddenfields')) {
    $schema->dropTable('glpi_problemtemplatehiddenfields');
}

if ($schema->tablesExist('glpi_tickettemplatemandatoryfields')) {
    $schema->dropTable('glpi_tickettemplatemandatoryfields');
}
$schema->createTable(new Table('glpi_tickettemplatemandatoryfields', [
    $column_templates['id'],
    $fk_column('tickettemplates_id'),
    new Column('num', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_changetemplatemandatoryfields')) {
    $schema->dropTable('glpi_changetemplatemandatoryfields');
}
$schema->createTable(new Table('glpi_changetemplatemandatoryfields', [
    $column_templates['id'],
    $fk_column('changetemplates_id'),
    new Column('num', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_problemtemplatemandatoryfields')) {
    $schema->dropTable('glpi_problemtemplatemandatoryfields');
}
$schema->createTable(new Table('glpi_problemtemplatemandatoryfields', [
    $column_templates['id'],
    $fk_column('problemtemplates_id'),
    new Column('num', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_tickettemplatepredefinedfields')) {
    $schema->dropTable('glpi_tickettemplatepredefinedfields');
}

if ($schema->tablesExist('glpi_changetemplatepredefinedfields')) {
    $schema->dropTable('glpi_changetemplatepredefinedfields');
}

if ($schema->tablesExist('glpi_problemtemplatepredefinedfields')) {
    $schema->dropTable('glpi_problemtemplatepredefinedfields');
}

if ($schema->tablesExist('glpi_tickettemplates')) {
    $schema->dropTable('glpi_tickettemplates');
}
$schema->createTable(new Table('glpi_tickettemplates', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    new Column('allowed_statuses', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => '[1,2,3,4,5,6]',
    ])
], [
    $id_index
]));

if ($schema->tablesExist('glpi_changetemplates')) {
    $schema->dropTable('glpi_changetemplates');
}
$schema->createTable(new Table('glpi_changetemplates', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    new Column('allowed_statuses', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => '[1,9,10,7,4,11,12,5,8,6,14,13]',
    ])
], [
    $id_index
]));

if ($schema->tablesExist('glpi_problemtemplates')) {
    $schema->dropTable('glpi_problemtemplates');
}
$schema->createTable(new Table('glpi_problemtemplates', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
    new Column('allowed_statuses', Type::getType('string'), [
        'length' => 255,
        'notnull' => true,
        'default' => '[1,7,2,3,4,5,8,6]',
    ])
], [
    $id_index
]));

if ($schema->tablesExist('glpi_ticketvalidations')) {
    $schema->dropTable('glpi_ticketvalidations');
}

if ($schema->tablesExist('glpi_transfers')) {
    $schema->dropTable('glpi_transfers');
}
$schema->createTable(new Table('glpi_transfers', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('keep_ticket', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_networklink', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_reservation', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_history', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_device', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_infocom', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_dc_monitor', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_dc_monitor', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_dc_phone', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_dc_phone', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_dc_peripheral', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_dc_peripheral', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_dc_printer', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_dc_printer', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_supplier', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_supplier', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_contact', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_contact', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_contract', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_contract', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_software', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_software', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_document', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_document', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_cartridgeitem', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_cartridgeitem', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_cartridge', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_consumable', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    $column_templates['date_mod'],
    $column_templates['date_creation'],
    $column_templates['comment'],
    new Column('keep_disk', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('keep_certificate', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('clean_certificate', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_usercategories')) {
    $schema->dropTable('glpi_usercategories');
}
$schema->createTable(new Table('glpi_usercategories', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_useremails')) {
    $schema->dropTable('glpi_useremails');
}
$schema->createTable(new Table('glpi_useremails', [
    $column_templates['id'],
    $fk_column('users_id'),
    new Column('is_default', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('is_dynamic', Type::getType('tinyint'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('email', Type::getType('string'), [
        'notnull' => true,
        'length' => 255
    ]),
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_users')) {
    $schema->dropTable('glpi_users');
}
$schema->createTable(new Table('glpi_users', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('password', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('password_last_update', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('phone', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('phone2', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('mobile', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('realname', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('firstname', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    $fk_column('locations_id'),
    new Column('language', Type::getType('string'), [
        'length' => 10,
        'notnull' => false,
        'default' => null
    ]),
    new Column('use_mode', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('list_limit', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('is_active', Type::getType('boolean'), [
        'notnull' => true,
        'default' => true
    ]),
    $column_templates['comment'],
    $fk_column('auths_id'),
    new Column('authtype', Type::getType('integer'), [
        'notnull' => true,
        'default' => 0
    ]),
    new Column('last_login', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    $column_templates['date_mod'],
    new Column('date_sync', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('is_deleted', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false
    ]),
    $fk_column('profiles_id'),
    $fk_column('entities_id'),
    $fk_column('usertitles_id'),
    $fk_column('usercategories_id'),
    new Column('date_format', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('number_format', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('names_format', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('csv_delimiter', Type::getType('char'), [
        'length' => 1,
        'notnull' => false,
        'default' => null
    ]),
    new Column('is_ids_visible', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('use_flat_dropdowntree', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('show_jobs_at_login', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('priority_1', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('priority_2', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('priority_3', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('priority_4', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('priority_5', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('priority_6', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('followup_private', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('task_private', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('default_requesttypes_id', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('password_forget_token', Type::getType('char'), [
        'length' => 40,
        'notnull' => false,
        'default' => null
    ]),
    new Column('password_forget_token_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('user_dn', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('registration_number', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('show_count_on_tabs', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('refresh_views', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('set_default_tech', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('personal_token', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('personal_token_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('api_token', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('api_token_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('cookie_token', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('cookie_token_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('display_count_on_home', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('notification_to_myself', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('duedateok_color', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('duedatewarning_color', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('duedatecritical_color', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('duedatewarning_less', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('duedatecritical_less', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('duedatewarning_unit', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('duedatecritical_unit', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('display_options', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('is_deleted_ldap', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('pdffont', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('picture', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('begin_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('end_date', Type::getType('datetime'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('keep_devices_when_purging_item', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('privatebookmarkorder', Type::getType('text'), ['length' => 4294967295, 'notnull' => false]),
    new Column('backcreated', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('task_state', Type::getType('integer'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('palette', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('page_layout', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('fold_menu', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('fold_search', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('savedsearches_pinned', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('timeline_order', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('itil_layout', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('richtext_layout', Type::getType('char'), [
        'length' => 20,
        'notnull' => false,
        'default' => null
    ]),
    new Column('set_default_requester', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('lock_autolock_mode', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('lock_directunlock_notification', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    $column_templates['date_creation'],
    new Column('highcontrast_css', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('plannings', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    new Column('sync_field', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    $fk_column('groups_id'),
    $fk_column('users_id_supervisor'),
    new Column('timezone', Type::getType('string'), [
        'length' => 50,
        'notnull' => false,
        'default' => null
    ]),
    new Column('default_dashboard_central', Type::getType('string'), [
        'length' => 100,
        'notnull' => false,
        'default' => null
    ]),
    new Column('default_dashboard_assets', Type::getType('string'), [
        'length' => 100,
        'notnull' => false,
        'default' => null
    ]),
    new Column('default_dashboard_helpdesk', Type::getType('string'), [
        'length' => 100,
        'notnull' => false,
        'default' => null
    ]),
    new Column('default_dashboard_mini_ticket', Type::getType('string'), [
        'length' => 100,
        'notnull' => false,
        'default' => null
    ]),
    new Column('default_central_tab', Type::getType('tinyint'), [
        'notnull' => false,
        'default' => null
    ]),
    new Column('nickname', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
    new Column('toast_location', Type::getType('string'), [
        'length' => 255,
        'notnull' => false,
        'default' => null
    ]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_usertitles')) {
    $schema->dropTable('glpi_usertitles');
}
$schema->createTable(new Table('glpi_usertitles', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_virtualmachinestates')) {
    $schema->dropTable('glpi_virtualmachinestates');
}
$schema->createTable(new Table('glpi_virtualmachinestates', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_virtualmachinesystems')) {
    $schema->dropTable('glpi_virtualmachinesystems');
}
$schema->createTable(new Table('glpi_virtualmachinesystems', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_virtualmachinetypes')) {
    $schema->dropTable('glpi_virtualmachinetypes');
}
$schema->createTable(new Table('glpi_virtualmachinetypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_vlans')) {
    $schema->dropTable('glpi_vlans');
}

if ($schema->tablesExist('glpi_wifinetworks')) {
    $schema->dropTable('glpi_wifinetworks');
}

if ($schema->tablesExist('glpi_knowbaseitems_items')) {
    $schema->dropTable('glpi_knowbaseitems_items');
}

if ($schema->tablesExist('glpi_knowbaseitems_revisions')) {
    $schema->dropTable('glpi_knowbaseitems_revisions');
}

if ($schema->tablesExist('glpi_knowbaseitems_comments')) {
    $schema->dropTable('glpi_knowbaseitems_comments');
}

if ($schema->tablesExist('glpi_devicebatterymodels')) {
    $schema->dropTable('glpi_devicebatterymodels');
}

if ($schema->tablesExist('glpi_devicebatteries')) {
    $schema->dropTable('glpi_devicebatteries');
}

if ($schema->tablesExist('glpi_items_devicebatteries')) {
    $schema->dropTable('glpi_items_devicebatteries');
}

if ($schema->tablesExist('glpi_devicebatterytypes')) {
    $schema->dropTable('glpi_devicebatterytypes');
}
$schema->createTable(new Table('glpi_devicebatterytypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_devicefirmwaremodels')) {
    $schema->dropTable('glpi_devicefirmwaremodels');
}

if ($schema->tablesExist('glpi_devicefirmwares')) {
    $schema->dropTable('glpi_devicefirmwares');
}

if ($schema->tablesExist('glpi_items_devicefirmwares')) {
    $schema->dropTable('glpi_items_devicefirmwares');
}

if ($schema->tablesExist('glpi_devicefirmwaretypes')) {
    $schema->dropTable('glpi_devicefirmwaretypes');
}
$schema->createTable(new Table('glpi_devicefirmwaretypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_datacenters')) {
    $schema->dropTable('glpi_datacenters');
}

if ($schema->tablesExist('glpi_dcrooms')) {
    $schema->dropTable('glpi_dcrooms');
}

if ($schema->tablesExist('glpi_rackmodels')) {
    $schema->dropTable('glpi_rackmodels');
}

if ($schema->tablesExist('glpi_racktypes')) {
    $schema->dropTable('glpi_racktypes');
}
$schema->createTable(new Table('glpi_racktypes', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_racks')) {
    $schema->dropTable('glpi_racks');
}

if ($schema->tablesExist('glpi_items_racks')) {
    $schema->dropTable('glpi_items_racks');
}

if ($schema->tablesExist('glpi_enclosuremodels')) {
    $schema->dropTable('glpi_enclosuremodels');
}

if ($schema->tablesExist('glpi_enclosures')) {
    $schema->dropTable('glpi_enclosures');
}

if ($schema->tablesExist('glpi_items_enclosures')) {
    $schema->dropTable('glpi_items_enclosures');
}

if ($schema->tablesExist('glpi_pdumodels')) {
    $schema->dropTable('glpi_pdumodels');
}

if ($schema->tablesExist('glpi_pdutypes')) {
    $schema->dropTable('glpi_pdutypes');
}
$schema->createTable(new Table('glpi_pdutypes', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_pdus')) {
    $schema->dropTable('glpi_pdus');
}

if ($schema->tablesExist('glpi_plugs')) {
    $schema->dropTable('glpi_plugs');
}
$schema->createTable(new Table('glpi_plugs', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_pdus_plugs')) {
    $schema->dropTable('glpi_pdus_plugs');
}

if ($schema->tablesExist('glpi_pdus_racks')) {
    $schema->dropTable('glpi_pdus_racks');
}

if ($schema->tablesExist('glpi_itilfollowuptemplates')) {
    $schema->dropTable('glpi_itilfollowuptemplates');
}

if ($schema->tablesExist('glpi_itilfollowups')) {
    $schema->dropTable('glpi_itilfollowups');
}

if ($schema->tablesExist('glpi_clustertypes')) {
    $schema->dropTable('glpi_clustertypes');
}
$schema->createTable(new Table('glpi_clustertypes', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_clusters')) {
    $schema->dropTable('glpi_clusters');
}

if ($schema->tablesExist('glpi_items_clusters')) {
    $schema->dropTable('glpi_items_clusters');
}

if ($schema->tablesExist('glpi_planningexternalevents')) {
    $schema->dropTable('glpi_planningexternalevents');
}

if ($schema->tablesExist('glpi_planningexternaleventtemplates')) {
    $schema->dropTable('glpi_planningexternaleventtemplates');
}

if ($schema->tablesExist('glpi_planningeventcategories')) {
    $schema->dropTable('glpi_planningeventcategories');
}

if ($schema->tablesExist('glpi_items_kanbans')) {
    $schema->dropTable('glpi_items_kanbans');
}

if ($schema->tablesExist('glpi_vobjects')) {
    $schema->dropTable('glpi_vobjects');
}

if ($schema->tablesExist('glpi_domaintypes')) {
    $schema->dropTable('glpi_domaintypes');
}

if ($schema->tablesExist('glpi_domainrelations')) {
    $schema->dropTable('glpi_domainrelations');
}
$schema->createTable(new Table('glpi_domainrelations', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_domains_items')) {
    $schema->dropTable('glpi_domains_items');
}

if ($schema->tablesExist('glpi_domainrecordtypes')) {
    $schema->dropTable('glpi_domainrecordtypes');
}
$schema->createTable(new Table('glpi_domainrecordtypes', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('fields', Type::getType('text'), ['length' => 65535, 'notnull' => false]),
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    $column_templates['comment'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_domainrecords')) {
    $schema->dropTable('glpi_domainrecords');
}

if ($schema->tablesExist('glpi_appliances')) {
    $schema->dropTable('glpi_appliances');
}

if ($schema->tablesExist('glpi_appliances_items')) {
    $schema->dropTable('glpi_appliances_items');
}

if ($schema->tablesExist('glpi_appliancetypes')) {
    $schema->dropTable('glpi_appliancetypes');
}

if ($schema->tablesExist('glpi_applianceenvironments')) {
    $schema->dropTable('glpi_applianceenvironments');
}

if ($schema->tablesExist('glpi_appliances_items_relations')) {
    $schema->dropTable('glpi_appliances_items_relations');
}

if ($schema->tablesExist('glpi_agenttypes')) {
    $schema->dropTable('glpi_agenttypes');
}
$schema->createTable(new Table('glpi_agenttypes', [
    $column_templates['id'],
    $column_templates['name'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_agents')) {
    $schema->dropTable('glpi_agents');
}

if ($schema->tablesExist('glpi_rulematchedlogs')) {
    $schema->dropTable('glpi_rulematchedlogs');
}

if ($schema->tablesExist('glpi_lockedfields')) {
    $schema->dropTable('glpi_lockedfields');
}

if ($schema->tablesExist('glpi_unmanageds')) {
    $schema->dropTable('glpi_unmanageds');
}

if ($schema->tablesExist('glpi_networkporttypes')) {
    $schema->dropTable('glpi_networkporttypes');
}
$schema->createTable(new Table('glpi_networkporttypes', [
    $column_templates['id'],
    $column_templates['entities_id'],
    $column_templates['is_recursive'],
    new Column('value_decimal', Type::getType('integer'), ['notnull' => true]),
    $column_templates['name'],
    $column_templates['comment'],
    new Column('is_importable', Type::getType('boolean'), ['notnull' => true, 'default' => 0]),
    new Column('instantiation_type', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    $column_templates['date_creation'],
    $column_templates['date_mod'],
], [
    $id_index
]));

if ($schema->tablesExist('glpi_printerlogs')) {
    $schema->dropTable('glpi_printerlogs');
}

if ($schema->tablesExist('glpi_networkportconnectionlogs')) {
    $schema->dropTable('glpi_networkportconnectionlogs');
}

if ($schema->tablesExist('glpi_networkportmetrics')) {
    $schema->dropTable('glpi_networkportmetrics');
}

if ($schema->tablesExist('glpi_refusedequipments')) {
    $schema->dropTable('glpi_refusedequipments');
}

if ($schema->tablesExist('glpi_usbvendors')) {
    $schema->dropTable('glpi_usbvendors');
}

if ($schema->tablesExist('glpi_pcivendors')) {
    $schema->dropTable('glpi_pcivendors');
}

if ($schema->tablesExist('glpi_items_remotemanagements')) {
    $schema->dropTable('glpi_items_remotemanagements');
}

if ($schema->tablesExist('glpi_pendingreasons')) {
    $schema->dropTable('glpi_pendingreasons');
}

if ($schema->tablesExist('glpi_pendingreasons_items')) {
    $schema->dropTable('glpi_pendingreasons_items');
}

if ($schema->tablesExist('glpi_manuallinks')) {
    $schema->dropTable('glpi_manuallinks');
}

if ($schema->tablesExist('glpi_tickets_contracts')) {
    $schema->dropTable('glpi_tickets_contracts');
}

if ($schema->tablesExist('glpi_databaseinstancetypes')) {
    $schema->dropTable('glpi_databaseinstancetypes');
}
$schema->createTable(new Table('glpi_databaseinstancetypes', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_databaseinstancecategories')) {
    $schema->dropTable('glpi_databaseinstancecategories');
}
$schema->createTable(new Table('glpi_databaseinstancecategories', [
    $column_templates['id'],
    $column_templates['name'],
    $column_templates['comment'],
    $column_templates['date_mod'],
    $column_templates['date_creation'],
], [
    $id_index,
]));

if ($schema->tablesExist('glpi_databaseinstances')) {
    $schema->dropTable('glpi_databaseinstances');
}

if ($schema->tablesExist('glpi_databases')) {
    $schema->dropTable('glpi_databases');
}

if ($schema->tablesExist('glpi_snmpcredentials')) {
    $schema->dropTable('glpi_snmpcredentials');
}
$schema->createTable(new Table('glpi_snmpcredentials', [
    $column_templates['id'],
    $column_templates['name'],
    new Column('snmpversion', Type::getType('string'), ['length' => 8, 'notnull' => true, 'default' => '1']),
    new Column('community', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    new Column('username', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    new Column('authentication', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    new Column('auth_passphrase', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    new Column('encryption', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    new Column('priv_passphrase', Type::getType('string'), ['length' => 255, 'notnull' => false, 'default' => null]),
    new Column('is_deleted', Type::getType('boolean'), ['notnull' => true, 'default' => 0]),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_items_ticketrecurrents')) {
    $schema->dropTable('glpi_items_ticketrecurrents');
}

if ($schema->tablesExist('glpi_items_lines')) {
    $schema->dropTable('glpi_items_lines');
}

if ($schema->tablesExist('glpi_changes_changes')) {
    $schema->dropTable('glpi_changes_changes');
}

if ($schema->tablesExist('glpi_problems_problems')) {
    $schema->dropTable('glpi_problems_problems');
}

if ($schema->tablesExist('glpi_changesatisfactions')) {
    $schema->dropTable('glpi_changesatisfactions');
}

$DB_PDO->enableForeignKeyChecks();
