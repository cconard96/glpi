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

namespace tests\units\Glpi\Api\HL\Controller;

use Glpi\Http\Request;

class AssetController extends \HLAPITestCase
{
//    public function testIndex()
//    {
//        global $CFG_GLPI;
//        $types = $CFG_GLPI['asset_types'];
//
//        $this->login();
//        $this->api->call(new Request('GET', '/Assets'), function ($call) use ($types) {
//            /** @var \HLAPICallAsserter $call */
//            $call->response
//                ->isOK()
//                ->jsonContent(function ($content) use ($types) {
//                    $this->array($content)->size->isGreaterThanOrEqualTo(count($types));
//                    foreach ($content as $asset) {
//                        $this->array($asset)->hasKeys(['itemtype', 'name', 'href']);
//                        $this->string($asset['name'])->isNotEmpty();
//                        $this->string($asset['href'])->isEqualTo('/Assets/' . $asset['itemtype']);
//                    }
//                });
//        });
//    }
//
//    public function testSearch()
//    {
//        /** @var array $CFG_GLPI */
//        global $CFG_GLPI;
//        $this->login();
//
//        $asset_types = $CFG_GLPI['asset_types'];
//
//        foreach ($asset_types as $asset_type) {
//            $schema_name = str_replace('\\', '_', $asset_type);
//            $this->api->autoTestSearch('/Assets/' . $schema_name, [
//                ['name' => __FUNCTION__ . '_1'],
//                ['name' => __FUNCTION__ . '_2'],
//                ['name' => __FUNCTION__ . '_3'],
//            ]);
//        }
//    }
//
//    protected function getItemProvider()
//    {
//        return [
//            ['schema' => 'Computer', 'id' => getItemByTypeName('Computer', '_test_pc01', true), 'expected' => ['fields' => ['name' => '_test_pc01']]],
//            ['schema' => 'Monitor', 'id' => getItemByTypeName('Monitor', '_test_monitor_1', true), 'expected' => ['fields' => ['name' => '_test_monitor_1']]],
//            ['schema' => 'NetworkEquipment', 'id' => getItemByTypeName('NetworkEquipment', '_test_networkequipment_1', true), 'expected' => ['fields' => ['name' => '_test_networkequipment_1']]],
//            ['schema' => 'Peripheral', 'id' => getItemByTypeName('Peripheral', '_test_peripheral_1', true), 'expected' => ['fields' => ['name' => '_test_peripheral_1']]],
//            ['schema' => 'Phone', 'id' => getItemByTypeName('Phone', '_test_phone_1', true), 'expected' => ['fields' => ['name' => '_test_phone_1']]],
//            ['schema' => 'Printer', 'id' => getItemByTypeName('Printer', '_test_printer_all', true), 'expected' => ['fields' => ['name' => '_test_printer_all']]],
//            ['schema' => 'SoftwareLicense', 'id' => getItemByTypeName('SoftwareLicense', '_test_softlic_1', true), 'expected' => ['fields' => ['name' => '_test_softlic_1']]],
//        ];
//    }
//
//    /**
//     * @dataProvider getItemProvider
//     */
//    public function testGetItem(string $schema, int $id, array $expected)
//    {
//        $this->login();
//        $request = new Request('GET', '/Assets/' . $schema . '/' . $id);
//        $this->api->call($request, function ($call) use ($schema, $expected) {
//            /** @var \HLAPICallAsserter $call */
//            $call->response
//                ->isOK()
//                ->jsonContent(function ($content) use ($expected) {
//                    $this->checkSimpleContentExpect($content, $expected);
//                })
//                ->matchesSchema($schema);
//        });
//    }

    public function testCreateUpdateDeleteItem()
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;
        $this->login();

        $asset_types = $CFG_GLPI['asset_types'];

        foreach ($asset_types as $asset_type) {
            $schema_name = str_replace('\\', '_', $asset_type);
            $this->api->autoTestCRUD('/Assets/' . $schema_name, [
                ['name' => __FUNCTION__ . '_1'],
                ['name' => __FUNCTION__ . '_2'],
                ['name' => __FUNCTION__ . '_3'],
            ]);
        }
    }

//    public function testSearchAllAssets()
//    {
//        $this->login();
//
//        $request = new Request('GET', '/Assets/Global');
//        $request->setParameter('filter', ['name=ilike=*_test*']);
//        $request->setParameter('limit', 10000);
//        $this->api->call($request, function ($call) {
//            /** @var \HLAPICallAsserter $call */
//            $call->response
//                ->isOK()
//                ->jsonContent(function ($content) {
//                    $this->array($content)->size->isGreaterThanOrEqualTo(3);
//                    $count_by_type = [];
//                    // Count by the _itemtype field in each element
//                    foreach ($content as $item) {
//                        $count_by_type[$item['_itemtype']] = ($count_by_type[$item['_itemtype']] ?? 0) + 1;
//                    }
//                    $this->integer($count_by_type['Computer'])->isGreaterThanOrEqualTo(1);
//                    $this->integer($count_by_type['Monitor'])->isGreaterThanOrEqualTo(1);
//                    $this->integer($count_by_type['Printer'])->isGreaterThanOrEqualTo(1);
//                });
//        });
//    }
//
//    public function testCRUDSoftwareVersion()
//    {
//        $this->login();
//
//        // Create a software
//        $software = new \Software();
//        $this->integer($software_id = $software->add([
//            'name' => __FUNCTION__,
//            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
//        ]));
//
//        $this->api->autoTestCRUD('/Assets/Software/' . $software_id . '/Version', [
//            ['name' => '1.0'],
//            ['name' => '1.1'],
//            ['name' => '1.2'],
//        ]);
//    }
//
//    public function testDropdownTranslations()
//    {
//        global $CFG_GLPI;
//
//        $this->login();
//        $state = new \State();
//        $this->integer($state_id = $state->add([
//            'name' => 'Test',
//            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
//        ]))->isGreaterThan(0);
//        $computer = new \Computer();
//        $this->integer($computer_id = $computer->add([
//            'name' => 'Test',
//            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
//            'states_id' => $state_id,
//        ]))->isGreaterThan(0);
//        $dropdown_translation = new \DropdownTranslation();
//        $this->integer($dropdown_translation->add([
//            'items_id'  => $state_id,
//            'itemtype'  => 'State',
//            'language'  => 'fr_FR',
//            'field'     => 'name',
//            'value'     => 'Essai',
//        ]))->isGreaterThan(0);
//
//        $CFG_GLPI['translate_dropdowns'] = true;
//        // Get and verify
//        $this->api->call(new Request('GET', '/Assets/Computer/' . $computer_id), function ($call) use ($state_id) {
//            /** @var \HLAPICallAsserter $call */
//            $call->response
//                ->isOK()
//                ->jsonContent(function ($content) use ($state_id) {
//                    $this->array($content)->hasKey('status');
//                    $this->array($content['status'])->hasKey('name');
//                    $this->string($content['status']['name'])->isEqualTo('Test');
//                });
//        });
//        // Change language and verify
//        $request = new Request('GET', '/Assets/Computer/' . $computer_id, [
//            'Accept-Language' => 'fr_FR',
//        ]);
//        $this->api->call($request, function ($call) use ($state_id) {
//            /** @var \HLAPICallAsserter $call */
//            $call->response
//                ->isOK()
//                ->jsonContent(function ($content) use ($state_id) {
//                    $this->array($content)->hasKey('status');
//                    $this->array($content['status'])->hasKey('name');
//                    $this->string($content['status']['name'])->isEqualTo('Essai');
//                });
//        });
//    }
//
//    public function testMissingDropdownTranslation()
//    {
//        global $CFG_GLPI;
//
//        $this->login();
//        $state = new \State();
//        $this->integer($state_id = $state->add([
//            'name' => 'Test',
//            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
//        ]))->isGreaterThan(0);
//        $computer = new \Computer();
//        $this->integer($computer_id = $computer->add([
//            'name' => 'Test',
//            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true),
//            'states_id' => $state_id,
//        ]))->isGreaterThan(0);
//
//        $CFG_GLPI['translate_dropdowns'] = true;
//        // Get and verify
//        $this->api->call(new Request('GET', '/Assets/Computer/' . $computer_id), function ($call) use ($state_id) {
//            /** @var \HLAPICallAsserter $call */
//            $call->response
//                ->isOK()
//                ->jsonContent(function ($content) use ($state_id) {
//                    $this->array($content)->hasKey('status');
//                    $this->array($content['status'])->hasKey('name');
//                    $this->string($content['status']['name'])->isEqualTo('Test');
//                });
//        });
//        // Change language and verify the default name is returned instead of null
//        $_SESSION['glpilanguage'] = 'fr_FR';
//        $this->api->call(new Request('GET', '/Assets/Computer/' . $computer_id), function ($call) use ($state_id) {
//            /** @var \HLAPICallAsserter $call */
//            $call->response
//                ->isOK()
//                ->jsonContent(function ($content) use ($state_id) {
//                    $this->array($content)->hasKey('status');
//                    $this->array($content['status'])->hasKey('name');
//                    $this->string($content['status']['name'])->isEqualTo('Test');
//                });
//        });
//    }
}
