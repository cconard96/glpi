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

namespace Glpi\Component\Debug;

use CommonGLPI;
use Glpi\Application\ErrorHandler;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Component\Component;
use Glpi\Component\ComponentInterface;
use Glpi\Debug\Profile;
use Glpi\Search\SearchOption;
use Glpi\UI\ThemeManager;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[Component(component_name: 'Debug/Toolbar')]
class Toolbar implements ComponentInterface
{
    public function ajaxRequestData(Request $request): Response
    {
        if ($_SESSION['glpi_use_mode'] !== \Session::DEBUG_MODE) {
            return new JsonResponse([]);
        }
        $ajax_id = $request->get('ajax_id');
        $profile = \Glpi\Debug\Profile::pull($ajax_id);
        // Close session ASAP to not block other requests.
        // DO NOT do it before call to `\Glpi\Debug\Profile::pull()`,
        // as we have to delete profile from `$_SESSION` during the pull operation.
        session_write_close();
        return new JsonResponse($profile ? $profile->getDebugInfo() : []);
    }

    public function getItemtypes(Request $request): Response
    {
        if ($_SESSION['glpi_use_mode'] !== \Session::DEBUG_MODE) {
            return new JsonResponse([]);
        }
        $loaded = get_declared_classes();
        $glpi_classes = array_filter($loaded, static function ($class) {
            if (!is_subclass_of($class, 'CommonDBTM')) {
                return false;
            }

            $reflection_class = new ReflectionClass($class);
            if ($reflection_class->isAbstract()) {
                return false;
            }

            return true;
        });
        sort($glpi_classes);
        return new JsonResponse($glpi_classes);
    }

    public function getSearchOptions(Request $request): Response
    {
        if ($_SESSION['glpi_use_mode'] !== \Session::DEBUG_MODE) {
            return new JsonResponse([]);
        }
        $class = $request->get('itemtype');
        if (!class_exists($class) || !is_subclass_of($class, 'CommonDBTM')) {
            return new JsonResponse([]);
        }
        $reflection_class = new ReflectionClass($class);
        if ($reflection_class->isAbstract()) {
            return new JsonResponse([]);
        }
        // In some cases, a class that isn't a proper itemtype may show in the selection box and this would trigger a SQL error that cannot be caught.
        ErrorHandler::getInstance()->disableOutput();
        $options = [];
        try {
            /** @var CommonGLPI $item */
            if ($item = getItemForItemtype($class)) {
                $options = SearchOption::getOptionsForItemtype($item::class);
            }
        } catch (Throwable) {
        }
        $options = array_filter($options, static function ($k) {
            return is_numeric($k);
        }, ARRAY_FILTER_USE_KEY);
        return new JsonResponse($options);
    }

    public function getThemes(Request $request): Response
    {
        if ($_SESSION['glpi_use_mode'] !== \Session::DEBUG_MODE) {
            return new JsonResponse([]);
        }
        return new JsonResponse(ThemeManager::getInstance()->getAllThemes());
    }

    public function show(): void
    {
        $info = Profile::getCurrent()->getDebugInfo();
        // language=Twig
        echo TemplateRenderer::getInstance()->renderFromStringTemplate(<<<TWIG
            <div id="debug-toolbar-applet" {{ mount_vue_component('Debug/Toolbar', _context, '^Debug\/Widget\/') }}></div>
TWIG, ['initial_request' => $info]);
    }
}
