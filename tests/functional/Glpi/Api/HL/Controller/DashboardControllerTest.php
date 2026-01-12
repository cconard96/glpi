<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
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
use Glpi\Tests\HLAPICallAsserter;
use Glpi\Tests\HLAPITestCase;

class DashboardControllerTest extends HLAPITestCase
{
    public function testCRUDDashboard()
    {
        $this->login();
        $this->loginWeb();

        $request = new Request('POST', '/Dashboards/Dashboard');
        $request->setParameter('name', 'Test mini dashboard');
        $request->setParameter('context', 'mini_core');

        $new_location = null;
        $this->api->call($request, function ($call) use (&$new_location) {
            /** @var HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->content(function ($content) {
                    var_dump($content);ob_flush();
                })
                ->headers(function ($headers) use (&$new_location) {
                    $this->assertStringStartsWith('/Dashboards/Dashboard/', $headers['Location']);
                    $new_location = $headers['Location'];
                });
        });

        // Search
        $this->api->call(new Request('GET', '/Dashboards/Dashboard'), function ($call) {
            /** @var HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) {
                    $found = false;
                    foreach ($content as $dashboard) {
                        if ($dashboard['name'] === 'Test mini dashboard') {
                            $found = true;
                            break;
                        }
                    }
                    $this->assertTrue($found);
                });
        });

        // Get
        $this->api->call(new Request('GET', $new_location), function ($call) {
            /** @var HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) {
                    $this->assertEquals('Test mini dashboard', $content['name']);
                    $this->assertEquals('mini_core', $content['context']);
                    $this->assertEquals('test-mini-dashboard', $content['key']);
                });
        });

        // Update
        $request = new Request('PATCH', $new_location);
        $request->setParameter('name', 'Test mini dashboard2');
        $this->api->call($request, function ($call) {
            /** @var HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) {
                    $this->assertEquals('Test mini dashboard2', $content['name']);
                    $this->assertEquals('mini_core', $content['context']);
                    // Key only gets set at creation
                    $this->assertEquals('test-mini-dashboard', $content['key']);
                });
        });

        // Delete
        $this->api->call(new Request('DELETE', $new_location), function ($call) {
            /** @var HLAPICallAsserter $call */
            $call->response
                ->isOK();
        });

        $this->api->call(new Request('GET', $new_location), function ($call) {
            /** @var HLAPICallAsserter $call */
            $call->response->isNotFoundError();
        });
    }
}
