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

use Glpi\Application\View\TemplateRenderer;

class WebhookTest extends CommonGLPI
{
    public static $rightname         = 'config';


    public static function getTypeName($nb = 0)
    {
        return _n('Test', 'Test', $nb);
    }


    public static function canCreate()
    {
        return true;
    }


    public function showForm($id, array $options = [])
    {
        $webhook = new Webhook();
        $webhook->getFromDB($options['webhook_id']);
        TemplateRenderer::getInstance()->display('pages/setup/webhook/webhooktest.html.twig', [
            'webhook' => $webhook,
            'itemtype' => $options['itemtype'] ?? null,
            'event' => $options['event'] ?? null,
        ]);
        return true;
    }

    public static function getMenuContent()
    {
        $menu = [];
        if (Webhook::canView()) {
            $menu = [
                'title'    => _n('Webhook', 'Webhooks', Session::getPluralNumber()),
                'page'     => '/front/webhook.php',
                'icon'     => static::getIcon(),
            ];
            $menu['links']['search'] = '/front/webhook.php';
            $menu['links']['add'] = '/front/webhook.form.php';

            $mp_icon     = WebhookTest::getIcon();
            $mp_title    = WebhookTest::getTypeName();
            $webhook_test = "<i class='$mp_icon pointer' title='$mp_title'></i><span class='d-none d-xxl-block'>$mp_title</span>";
            $menu['links'][$webhook_test] = '/front/webhooktest.php';
        }
        if (count($menu)) {
            return $menu;
        }
        return [];
    }

    public static function getIcon()
    {
        return "ti ti-eye-exclamation";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(self::getTypeName(1));
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof Webhook)) {
            return false;
        }
        return (new self())->showForm(0, [
            'itemtype' => $item->fields['itemtype'],
            'event' => $item->fields['event'],
            'webhook_id' => $item->getID(),
        ]);
    }
}
