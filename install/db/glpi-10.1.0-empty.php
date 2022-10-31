<?php

/** @noinspection PhpUnhandledExceptionInspection */

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

/** @global \Glpi\DB\DB $DB_PDO */
global $DB_PDO;

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
        'notnull' => true,
    ]),
    'date_mod' => new Column('date_mod', Type::getType('datetime'), [
        'notnull' => true,
    ]),
    'comment' => new Column('comment', Type::getType('text')),
    'entities_id' => new Column('entities_id', Type::getType('integer'), [
        'unsigned' => true,
        'notnull' => true,
    ]),
    'is_recursive' => new Column('is_recursive', Type::getType('boolean'), [
        'notnull' => true,
        'default' => false,
    ]),
];

$fk_column = static function (string $name) {
    return new Column($name, Type::getType('integer'), [
        'unsigned' => true,
        'notnull' => true,
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

if ($schema->tablesExist('glpi_authldaps')) {
    $schema->dropTable('glpi_authldaps');
}

if ($schema->tablesExist('glpi_authmails')) {
    $schema->dropTable('glpi_authmails');
}

if ($schema->tablesExist('glpi_apiclients')) {
    $schema->dropTable('glpi_apiclients');
}

if ($schema->tablesExist('glpi_autoupdatesystems')) {
    $schema->dropTable('glpi_autoupdatesystems');
}

if ($schema->tablesExist('glpi_blacklistedmailcontents')) {
    $schema->dropTable('glpi_blacklistedmailcontents');
}

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

if ($schema->tablesExist('glpi_savedsearches_users')) {
    $schema->dropTable('glpi_savedsearches_users');
}

if ($schema->tablesExist('glpi_savedsearches_alerts')) {
    $schema->dropTable('glpi_savedsearches_alerts');
}

if ($schema->tablesExist('glpi_budgets')) {
    $schema->dropTable('glpi_budgets');
}

if ($schema->tablesExist('glpi_budgettypes')) {
    $schema->dropTable('glpi_budgettypes');
}

if ($schema->tablesExist('glpi_businesscriticities')) {
    $schema->dropTable('glpi_businesscriticities');
}

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
    new Column('cache_duration', Type::getType('text')),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_calendars_holidays')) {
    $schema->dropTable('glpi_calendars_holidays');
}

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
    new Column('cache_duration', Type::getType('text')),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_cartridgeitems')) {
    $schema->dropTable('glpi_cartridgeitems');
}

if ($schema->tablesExist('glpi_printers_cartridgeinfos')) {
    $schema->dropTable('glpi_printers_cartridgeinfos');
}

if ($schema->tablesExist('glpi_cartridgeitems_printermodels')) {
    $schema->dropTable('glpi_cartridgeitems_printermodels');
}

if ($schema->tablesExist('glpi_cartridgeitemtypes')) {
    $schema->dropTable('glpi_cartridgeitemtypes');
}

if ($schema->tablesExist('glpi_cartridges')) {
    $schema->dropTable('glpi_cartridges');
}

if ($schema->tablesExist('glpi_certificates')) {
    $schema->dropTable('glpi_certificates');
}

if ($schema->tablesExist('glpi_certificates_items')) {
    $schema->dropTable('glpi_certificates_items');
}

if ($schema->tablesExist('glpi_certificatetypes')) {
    $schema->dropTable('glpi_certificatetypes');
}

if ($schema->tablesExist('glpi_changecosts')) {
    $schema->dropTable('glpi_changecosts');
}

if ($schema->tablesExist('glpi_changes')) {
    $schema->dropTable('glpi_changes');
}

if ($schema->tablesExist('glpi_changes_groups')) {
    $schema->dropTable('glpi_changes_groups');
}

if ($schema->tablesExist('glpi_changes_items')) {
    $schema->dropTable('glpi_changes_items');
}

if ($schema->tablesExist('glpi_changes_problems')) {
    $schema->dropTable('glpi_changes_problems');
}

if ($schema->tablesExist('glpi_changes_suppliers')) {
    $schema->dropTable('glpi_changes_suppliers');
}

if ($schema->tablesExist('glpi_changes_tickets')) {
    $schema->dropTable('glpi_changes_tickets');
}

if ($schema->tablesExist('glpi_changes_users')) {
    $schema->dropTable('glpi_changes_users');
}

if ($schema->tablesExist('glpi_changetasks')) {
    $schema->dropTable('glpi_changetasks');
}

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

if ($schema->tablesExist('glpi_computervirtualmachines')) {
    $schema->dropTable('glpi_computervirtualmachines');
}

if ($schema->tablesExist('glpi_items_operatingsystems')) {
    $schema->dropTable('glpi_items_operatingsystems');
}

if ($schema->tablesExist('glpi_operatingsystemkernels')) {
    $schema->dropTable('glpi_operatingsystemkernels');
}

if ($schema->tablesExist('glpi_operatingsystemkernelversions')) {
    $schema->dropTable('glpi_operatingsystemkernelversions');
}

if ($schema->tablesExist('glpi_operatingsystemeditions')) {
    $schema->dropTable('glpi_operatingsystemeditions');
}

if ($schema->tablesExist('glpi_configs')) {
    $schema->dropTable('glpi_configs');
}
$schema->createTable(new Table('glpi_configs', [
    $column_templates['id'],
    new Column('context', Type::getType('string'), [
        'length' => 150,
        'default' => null,
    ]),
    $column_templates['name'],
    new Column('value', Type::getType('text')),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_impactrelations')) {
    $schema->dropTable('glpi_impactrelations');
}

if ($schema->tablesExist('glpi_impactcompounds')) {
    $schema->dropTable('glpi_impactcompounds');
}

if ($schema->tablesExist('glpi_impactitems')) {
    $schema->dropTable('glpi_impactitems');
}

if ($schema->tablesExist('glpi_impactcontexts')) {
    $schema->dropTable('glpi_impactcontexts');
}

if ($schema->tablesExist('glpi_consumableitems')) {
    $schema->dropTable('glpi_consumableitems');
}

if ($schema->tablesExist('glpi_consumableitemtypes')) {
    $schema->dropTable('glpi_consumableitemtypes');
}

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

if ($schema->tablesExist('glpi_crontasklogs')) {
    $schema->dropTable('glpi_crontasklogs');
}

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
        'default' => null,
        'comment' => 'last run date',
    ]),
    new Column('lastcode', Type::getType('integer'), [
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
        'default' => null,
    ]),
    new Column('y', Type::getType('integer'), [
        'default' => null,
    ]),
    new Column('width', Type::getType('integer'), [
        'default' => null,
    ]),
    new Column('height', Type::getType('integer'), [
        'default' => null,
    ]),
    new Column('card_options', Type::getType('text')),
], [
    $id_index
]));

if ($schema->tablesExist('glpi_dashboards_rights')) {
    $schema->dropTable('glpi_dashboards_rights');
}

if ($schema->tablesExist('glpi_devicecasemodels')) {
    $schema->dropTable('glpi_devicecasemodels');
}

if ($schema->tablesExist('glpi_devicecases')) {
    $schema->dropTable('glpi_devicecases');
}

if ($schema->tablesExist('glpi_devicecasetypes')) {
    $schema->dropTable('glpi_devicecasetypes');
}

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

if ($schema->tablesExist('glpi_devicesoundcardmodels')) {
    $schema->dropTable('glpi_devicesoundcardmodels');
}

if ($schema->tablesExist('glpi_devicesoundcards')) {
    $schema->dropTable('glpi_devicesoundcards');
}

if ($schema->tablesExist('glpi_displaypreferences')) {
    $schema->dropTable('glpi_displaypreferences');
}

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

if ($schema->tablesExist('glpi_domains')) {
    $schema->dropTable('glpi_domains');
}

if ($schema->tablesExist('glpi_dropdowntranslations')) {
    $schema->dropTable('glpi_dropdowntranslations');
}

if ($schema->tablesExist('glpi_entities')) {
    $schema->dropTable('glpi_entities');
}

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

if ($schema->tablesExist('glpi_fieldblacklists')) {
    $schema->dropTable('glpi_fieldblacklists');
}

if ($schema->tablesExist('glpi_fieldunicities')) {
    $schema->dropTable('glpi_fieldunicities');
}

if ($schema->tablesExist('glpi_filesystems')) {
    $schema->dropTable('glpi_filesystems');
}

if ($schema->tablesExist('glpi_fqdns')) {
    $schema->dropTable('glpi_fqdns');
}

if ($schema->tablesExist('glpi_groups')) {
    $schema->dropTable('glpi_groups');
}

if ($schema->tablesExist('glpi_groups_knowbaseitems')) {
    $schema->dropTable('glpi_groups_knowbaseitems');
}

if ($schema->tablesExist('glpi_groups_problems')) {
    $schema->dropTable('glpi_groups_problems');
}

if ($schema->tablesExist('glpi_groups_reminders')) {
    $schema->dropTable('glpi_groups_reminders');
}

if ($schema->tablesExist('glpi_groups_rssfeeds')) {
    $schema->dropTable('glpi_groups_rssfeeds');
}

if ($schema->tablesExist('glpi_groups_tickets')) {
    $schema->dropTable('glpi_groups_tickets');
}

if ($schema->tablesExist('glpi_groups_users')) {
    $schema->dropTable('glpi_groups_users');
}

if ($schema->tablesExist('glpi_holidays')) {
    $schema->dropTable('glpi_holidays');
}

if ($schema->tablesExist('glpi_infocoms')) {
    $schema->dropTable('glpi_infocoms');
}

if ($schema->tablesExist('glpi_interfacetypes')) {
    $schema->dropTable('glpi_interfacetypes');
}

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

if ($schema->tablesExist('glpi_mailcollectors')) {
    $schema->dropTable('glpi_mailcollectors');
}

if ($schema->tablesExist('glpi_manufacturers')) {
    $schema->dropTable('glpi_manufacturers');
}

if ($schema->tablesExist('glpi_monitormodels')) {
    $schema->dropTable('glpi_monitormodels');
}

if ($schema->tablesExist('glpi_monitors')) {
    $schema->dropTable('glpi_monitors');
}

if ($schema->tablesExist('glpi_monitortypes')) {
    $schema->dropTable('glpi_monitortypes');
}

if ($schema->tablesExist('glpi_sockets')) {
    $schema->dropTable('glpi_sockets');
}

if ($schema->tablesExist('glpi_cables')) {
    $schema->dropTable('glpi_cables');
}

if ($schema->tablesExist('glpi_cabletypes')) {
    $schema->dropTable('glpi_cabletypes');
}

if ($schema->tablesExist('glpi_cablestrands')) {
    $schema->dropTable('glpi_cablestrands');
}

if ($schema->tablesExist('glpi_socketmodels')) {
    $schema->dropTable('glpi_socketmodels');
}

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

if ($schema->tablesExist('glpi_networkinterfaces')) {
    $schema->dropTable('glpi_networkinterfaces');
}

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

if ($schema->tablesExist('glpi_notepads')) {
    $schema->dropTable('glpi_notepads');
}

if ($schema->tablesExist('glpi_notifications')) {
    $schema->dropTable('glpi_notifications');
}

if ($schema->tablesExist('glpi_notifications_notificationtemplates')) {
    $schema->dropTable('glpi_notifications_notificationtemplates');
}

if ($schema->tablesExist('glpi_notificationtargets')) {
    $schema->dropTable('glpi_notificationtargets');
}

if ($schema->tablesExist('glpi_notificationtemplates')) {
    $schema->dropTable('glpi_notificationtemplates');
}

if ($schema->tablesExist('glpi_notificationtemplatetranslations')) {
    $schema->dropTable('glpi_notificationtemplatetranslations');
}

if ($schema->tablesExist('glpi_notimportedemails')) {
    $schema->dropTable('glpi_notimportedemails');
}

if ($schema->tablesExist('glpi_objectlocks')) {
    $schema->dropTable('glpi_objectlocks');
}

if ($schema->tablesExist('glpi_operatingsystemarchitectures')) {
    $schema->dropTable('glpi_operatingsystemarchitectures');
}

if ($schema->tablesExist('glpi_operatingsystems')) {
    $schema->dropTable('glpi_operatingsystems');
}

if ($schema->tablesExist('glpi_operatingsystemservicepacks')) {
    $schema->dropTable('glpi_operatingsystemservicepacks');
}

if ($schema->tablesExist('glpi_operatingsystemversions')) {
    $schema->dropTable('glpi_operatingsystemversions');
}

if ($schema->tablesExist('glpi_passivedcequipments')) {
    $schema->dropTable('glpi_passivedcequipments');
}

if ($schema->tablesExist('glpi_passivedcequipmentmodels')) {
    $schema->dropTable('glpi_passivedcequipmentmodels');
}

if ($schema->tablesExist('glpi_passivedcequipmenttypes')) {
    $schema->dropTable('glpi_passivedcequipmenttypes');
}

if ($schema->tablesExist('glpi_peripheralmodels')) {
    $schema->dropTable('glpi_peripheralmodels');
}

if ($schema->tablesExist('glpi_peripherals')) {
    $schema->dropTable('glpi_peripherals');
}

if ($schema->tablesExist('glpi_peripheraltypes')) {
    $schema->dropTable('glpi_peripheraltypes');
}

if ($schema->tablesExist('glpi_phonemodels')) {
    $schema->dropTable('glpi_phonemodels');
}

if ($schema->tablesExist('glpi_phonepowersupplies')) {
    $schema->dropTable('glpi_phonepowersupplies');
}

if ($schema->tablesExist('glpi_phones')) {
    $schema->dropTable('glpi_phones');
}

if ($schema->tablesExist('glpi_phonetypes')) {
    $schema->dropTable('glpi_phonetypes');
}

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

if ($schema->tablesExist('glpi_problemcosts')) {
    $schema->dropTable('glpi_problemcosts');
}

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

if ($schema->tablesExist('glpi_profilerights')) {
    $schema->dropTable('glpi_profilerights');
}

if ($schema->tablesExist('glpi_profiles')) {
    $schema->dropTable('glpi_profiles');
}

if ($schema->tablesExist('glpi_profiles_reminders')) {
    $schema->dropTable('glpi_profiles_reminders');
}

if ($schema->tablesExist('glpi_profiles_rssfeeds')) {
    $schema->dropTable('glpi_profiles_rssfeeds');
}

if ($schema->tablesExist('glpi_profiles_users')) {
    $schema->dropTable('glpi_profiles_users');
}

if ($schema->tablesExist('glpi_projectcosts')) {
    $schema->dropTable('glpi_projectcosts');
}

if ($schema->tablesExist('glpi_projects')) {
    $schema->dropTable('glpi_projects');
}

if ($schema->tablesExist('glpi_projectstates')) {
    $schema->dropTable('glpi_projectstates');
}

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

if ($schema->tablesExist('glpi_rulecriterias')) {
    $schema->dropTable('glpi_rulecriterias');
}

if ($schema->tablesExist('glpi_rulerightparameters')) {
    $schema->dropTable('glpi_rulerightparameters');
}

if ($schema->tablesExist('glpi_rules')) {
    $schema->dropTable('glpi_rules');
}

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

if ($schema->tablesExist('glpi_softwarelicenses')) {
    $schema->dropTable('glpi_softwarelicenses');
}

if ($schema->tablesExist('glpi_softwarelicensetypes')) {
    $schema->dropTable('glpi_softwarelicensetypes');
}

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

if ($schema->tablesExist('glpi_itilsolutions')) {
    $schema->dropTable('glpi_itilsolutions');
}

if ($schema->tablesExist('glpi_ssovariables')) {
    $schema->dropTable('glpi_ssovariables');
}

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

if ($schema->tablesExist('glpi_taskcategories')) {
    $schema->dropTable('glpi_taskcategories');
}

if ($schema->tablesExist('glpi_tasktemplates')) {
    $schema->dropTable('glpi_tasktemplates');
}

if ($schema->tablesExist('glpi_ticketcosts')) {
    $schema->dropTable('glpi_ticketcosts');
}

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

if ($schema->tablesExist('glpi_changetemplatemandatoryfields')) {
    $schema->dropTable('glpi_changetemplatemandatoryfields');
}

if ($schema->tablesExist('glpi_problemtemplatemandatoryfields')) {
    $schema->dropTable('glpi_problemtemplatemandatoryfields');
}

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

if ($schema->tablesExist('glpi_changetemplates')) {
    $schema->dropTable('glpi_changetemplates');
}

if ($schema->tablesExist('glpi_problemtemplates')) {
    $schema->dropTable('glpi_problemtemplates');
}

if ($schema->tablesExist('glpi_ticketvalidations')) {
    $schema->dropTable('glpi_ticketvalidations');
}

if ($schema->tablesExist('glpi_transfers')) {
    $schema->dropTable('glpi_transfers');
}

if ($schema->tablesExist('glpi_usercategories')) {
    $schema->dropTable('glpi_usercategories');
}

if ($schema->tablesExist('glpi_useremails')) {
    $schema->dropTable('glpi_useremails');
}

if ($schema->tablesExist('glpi_users')) {
    $schema->dropTable('glpi_users');
}

if ($schema->tablesExist('glpi_usertitles')) {
    $schema->dropTable('glpi_usertitles');
}

if ($schema->tablesExist('glpi_virtualmachinestates')) {
    $schema->dropTable('glpi_virtualmachinestates');
}

if ($schema->tablesExist('glpi_virtualmachinesystems')) {
    $schema->dropTable('glpi_virtualmachinesystems');
}

if ($schema->tablesExist('glpi_virtualmachinetypes')) {
    $schema->dropTable('glpi_virtualmachinetypes');
}

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

if ($schema->tablesExist('glpi_pdus')) {
    $schema->dropTable('glpi_pdus');
}

if ($schema->tablesExist('glpi_plugs')) {
    $schema->dropTable('glpi_plugs');
}

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

if ($schema->tablesExist('glpi_domains_items')) {
    $schema->dropTable('glpi_domains_items');
}

if ($schema->tablesExist('glpi_domainrecordtypes')) {
    $schema->dropTable('glpi_domainrecordtypes');
}

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

if ($schema->tablesExist('glpi_databaseinstancecategories')) {
    $schema->dropTable('glpi_databaseinstancecategories');
}

if ($schema->tablesExist('glpi_databaseinstances')) {
    $schema->dropTable('glpi_databaseinstances');
}

if ($schema->tablesExist('glpi_databases')) {
    $schema->dropTable('glpi_databases');
}

if ($schema->tablesExist('glpi_snmpcredentials')) {
    $schema->dropTable('glpi_snmpcredentials');
}

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
