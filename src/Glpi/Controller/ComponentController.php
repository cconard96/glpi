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

namespace Glpi\Controller;

use Glpi\Exception\Http\NotFoundHttpException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ComponentController extends AbstractController
{
    private array $components;

    public function __construct(
        #[AutowireIterator(tag: 'glpi.component', indexAttribute: 'component_name')]
        iterable $components
    ) {
        $this->components = iterator_to_array($components);
    }

    #[Route("/ajax/Component/{component}", name: "glpi_component", requirements: ['component' => '.*'])]
    public function __invoke(Request $request): Response
    {
        $component = $request->attributes->getString('component');
        $default_response = new Response('', 400);

        if (array_key_exists($component, $this->components)) {
            $action = $request->query->getString('action');

            //convert snake_case to camelCase
            $action = lcfirst(str_replace('_', '', ucwords($action, '_')));

            if (!empty($action) && is_callable([$this->components[$component], $action])) {
                return $this->components[$component]->$action($request);
            }
        }
        return $default_response;
    }
}
