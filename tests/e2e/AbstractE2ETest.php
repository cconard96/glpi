<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
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

namespace Glpi\Tests\E2E;

use Facebook\WebDriver\Exception\TimeoutException;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

abstract class AbstractE2ETest extends PantherTestCase
{
    /** @var Client */
    private $client;

    private const DEFAULT_TIMEOUT = 1;

    protected function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = static::createPantherClient();
        }
        return $this->client;
    }

    protected function visit(string $url, array $options = [])
    {
        $method = $options['method'] ?? 'GET';
        $this->getClient()->request($method, $url);
    }

    protected function title(): string
    {
        return $this->getClient()->getTitle();
    }

    protected function url(): string
    {
        return $this->getClient()->getCurrentURL();
    }

    protected function get(string $selector, array $options = [])
    {
        $this->expectToExist($selector, $options);
        return $this->getClient()->getCrawler()->filter($selector);
    }

    protected function setValue(string $selector, mixed $value, array $options = [])
    {
        $this->expectToExist($selector, $options);
        $val = is_string($value) ? "'$value'" : $value;
        $this->client->executeScript(
            "$('$selector').val($val);"
        );
    }

    protected function click(string $selector, array $options = [])
    {
        $this->expectToExist($selector, $options);
        $this->getClient()->getCrawler()->filter($selector)->click();
    }

    protected function expectToExist(string $selector, array $options = [])
    {
        $timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        try {
            $this->getClient()->waitFor($selector, $timeout);
        } catch (TimeoutException) {
            $this->fail("Expected to find '$selector' within $timeout seconds");
        }
    }

    protected function screenshot(?string $name = null)
    {
        $this->getClient()->takeScreenshot($name);
    }

    protected function login(string $username = TU_USER, string $password = TU_PASS)
    {
        //$this->getClient()->request('GET', '/');
        $this->visit('/');
        self::assertStringContainsString('Authentication', $this->title());

        //$this->getClient()->waitFor('#login_name', 5);
        //$login_name = $this->getClient()->getCrawler()->filter('#login_name')->attr('name');
        //$password_name = $this->getClient()->getCrawler()->filter('input[type="password"]')->attr('name');
        //$this->getClient()->getCrawler()->selectButton('submit')->form()->get($login_name)->setValue($username);
        //$this->getClient()->getCrawler()->selectButton('submit')->form()->get($password_name)->setValue($password);
        //$this->getClient()->getCrawler()->selectButton('submit')->form()->get('auth')->setValue('local');
        $this->setValue('#login_name', $username);
        $this->setValue('input[type="password"]', $password);
        $this->setValue('select[name="auth"]', 'local');

        //$this->getClient()->takeScreenshot('prelogin.png');
        //$this->getClient()->getCrawler()->filter('button[name="submit"]')->click();
        $this->screenshot('prelogin.png');
        $this->click('button[name="submit"]');

        //$this->getClient()->takeScreenshot('postlogin.png');
        $this->screenshot('postlogin.png');

        // Expect to be taken to front/central.php
        //$this->getClient()->waitFor('div.page', 5);
        $this->expectToExist('div.page');
        //$this->assertStringContainsString('central.php', $this->getClient()->getCurrentURL());
        $this->assertStringContainsString('Standard', $this->title());
        $this->assertStringContainsString('central.php', $this->url());
    }
}
