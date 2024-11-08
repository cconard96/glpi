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

namespace Glpi\Component\Form;

use Glpi\Component\Component;
use Glpi\Component\ComponentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Component(component_name: 'Form/SubitemForm')]
class SubitemForm implements ComponentInterface
{
    public function form(Request $request): Response
    {
        $type = $request->get('type');
        $parent_type = $request->request->get('parent_type');
        $parent_id = (int) $request->request->get('parent_id', -1);
        $id = (int) $request->request->get('id', -1);

        if (!isset($type, $parent_type)) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        if (!($item = getItemForItemtype($type)) || !($parent = getItemForItemtype($parent_type))) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        if (!$parent->getFromDB($parent_id)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        return new StreamedResponse(function () use ($item, $id, $parent) {
            $item->showForm($id, ['parent' => $parent]);
        });
    }
}
