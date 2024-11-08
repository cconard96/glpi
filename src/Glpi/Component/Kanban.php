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

namespace Glpi\Component;

use Glpi\Features\Teamwork;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Component(component_name: 'Kanban/Kanban')]
class Kanban implements ComponentInterface
{
    public function update(Request $request): Response
    {
        return new Response();
    }

    public function addItem(Request $request): Response
    {
        return new Response();
    }

    public function bulkAddItem(Request $request): Response
    {
        return new Response();
    }

    public function moveItem(Request $request): Response
    {
        return new Response();
    }

    public function showColumn(Request $request): Response
    {
        return new Response();
    }

    public function hideColumn(Request $request): Response
    {
        return new Response();
    }

    public function collapseColumn(Request $request): Response
    {
        return new Response();
    }

    public function expandColumn(Request $request): Response
    {
        return new Response();
    }

    public function moveColumn(Request $request): Response
    {
        return new Response();
    }

    public function refresh(Request $request): Response
    {
        return new Response();
    }

    public function getSwitcherDropdown(Request $request): Response
    {
        return new Response();
    }

    public function getKanbans(Request $request): Response
    {
        return new Response();
    }

    public function createColumn(Request $request): Response
    {
        return new Response();
    }

    public function saveColumnState(Request $request): Response
    {
        return new Response();
    }

    public function loadColumnState(Request $request): Response
    {
        return new Response();
    }

    public function listColumns(Request $request): Response
    {
        return new Response();
    }

    public function getColumn(Request $request): Response
    {
        return new Response();
    }

    public function deleteItem(Request $request): Response
    {
        return new Response();
    }

    public function restoreItem(Request $request): Response
    {
        return new Response();
    }

    public function addTeammember(Request $request): Response
    {
        /** @var class-string $itemtype */
        $itemtype = $request->get('itemtype');
        $items_id = (int) $request->get('items_id', 0);
        if (
            !($item = getItemForItemtype($itemtype))
            || !$item->getFromDB($items_id)
            || !(method_exists($item, 'canAssign') ? $item->canAssign() : $item->can($_REQUEST['items_id'], UPDATE))
            || !\Toolbox::hasTrait($itemtype, Teamwork::class)
        ) {
            return new Response('', 403);
        }

        $itemtype_teammember = $request->get('itemtype_teammember');
        $items_id_teammember = (int) $request->get('items_id_teammember');
        $role = (int) $request->get('role');

        if (empty($itemtype_teammember) || empty($items_id_teammember) || empty($role)) {
            return new Response('', 400);
        }
        $item->addTeamMember($itemtype_teammember, $items_id_teammember, [
            'role' => $role,
        ]);
        return new Response();
    }

    public function deleteTeammember(Request $request): Response
    {
        /** @var class-string $itemtype */
        $itemtype = $request->get('itemtype');
        $items_id = (int) $request->get('items_id', 0);
        if (
            !($item = getItemForItemtype($itemtype))
            || !$item->getFromDB($items_id)
            || !$item->can($_REQUEST['items_id'], UPDATE)
        ) {
            return new Response('', 403);
        }

        $itemtype_teammember = $request->get('itemtype_teammember');
        $items_id_teammember = (int) $request->get('items_id_teammember');
        $role = (int) $request->get('role');

        if (empty($itemtype_teammember) || empty($items_id_teammember) || empty($role)) {
            return new Response('', 400);
        }
        $item->deleteTeamMember($itemtype_teammember, $items_id_teammember, [
            'role' => $role,
        ]);
        return new Response();
    }

    public function loadItemPanel(Request $request): Response
    {
        return new Response();
    }

    public function loadTeammemberForm(Request $request): Response
    {
        /** @var class-string $itemtype */
        $itemtype = $request->get('itemtype');
        $items_id = (int) $request->get('items_id', 0);
        if (
            !($item = getItemForItemtype($itemtype))
            || !$item->getFromDB($items_id)
            || !(method_exists($item, 'canAssign') ? $item->canAssign() : $item->can($_REQUEST['items_id'], UPDATE))
            || !\Toolbox::hasTrait($itemtype, Teamwork::class)
        ) {
            return new Response('', 403);
        }
        return new Response($item::getTeammemberForm($item));
    }
}
