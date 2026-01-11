<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Api\HL\Controller;

use Glpi\Api\HL\Doc as Doc;
use Glpi\Api\HL\ResourceAccessor;
use Glpi\Api\HL\Route;
use Glpi\Api\HL\RouteVersion;
use Glpi\Dashboard as Dashboard;
use Glpi\Http\Request;
use Glpi\Http\Response;

#[Route(path: '/Dashboards', priority: 1, tags: ['Dashboards'])]
#[Doc\Route(
    parameters: [
        new Doc\Parameter(
            name: 'dashboard_id',
            schema: new Doc\Schema(Doc\Schema::TYPE_INTEGER),
            location: Doc\Parameter::LOCATION_PATH,
        ),
        new Doc\Parameter(
            name: 'id',
            schema: new Doc\Schema(Doc\Schema::TYPE_INTEGER),
            location: Doc\Parameter::LOCATION_PATH,
        ),
    ]
)]
final class DashboardController extends AbstractController
{
    protected static function getRawKnownSchemas(): array
    {
        global $DB;

        // always include core and mini_core contexts
        $known_contexts = ['core', 'mini_core'];
        $iterator = $DB->request([
            'SELECT'          => 'context',
            'DISTINCT'        => true,
            'FROM'            => Dashboard\Dashboard::getTable(),
        ]);
        foreach ($iterator as $data) {
            $context = $data['context'];
            if (!in_array($context, $known_contexts, true)) {
                $known_contexts[] = $context;
            }
        }

        $all_cards = (new Dashboard\Grid())->getAllDasboardCards();
        $all_widgets = [];
        foreach ($all_cards as $card) {
            if (isset($card['widgettype']) && is_array($card['widgettype'])) {
                $all_widgets = array_merge($all_widgets, $card['widgettype']);
            }
        }
        $all_widgets = array_unique(array_filter($all_widgets));

        $known_filters = array_map(static fn($f) => $f::getId(), Dashboard\Filter::getRegisteredFilterClasses());

        return [
            'Dashboard' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => Dashboard\Dashboard::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'readOnly' => true,
                    ],
                    'key' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'readOnly' => true,
                    ],
                    'name' => [
                        'type' => Doc\Schema::TYPE_STRING,
                    ],
                    'context' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'Dashboard context which controls where it may be used',
                        'enum' => $known_contexts,
                    ],
                    'user' => self::getDropdownTypeSchema(class: \User::class, full_schema: 'User'),
                ],
            ],
            'DashboardFilter' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => Dashboard\Filter::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'readOnly' => true,
                    ],
                    'dashboard' => self::getDropdownTypeSchema(class: Dashboard\Dashboard::class, full_schema: 'Dashboard'),
                    'user' => self::getDropdownTypeSchema(class: \User::class, full_schema: 'User'),
                    'filter' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'JSON encoded filters',
                    ],
                ],
            ],
            'DashboardItem' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => Dashboard\Item::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'readOnly' => true,
                    ],
                    'dashboard' => self::getDropdownTypeSchema(class: Dashboard\Dashboard::class, full_schema: 'Dashboard'),
                    'unique_key' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'x-field' => 'gridstack_id',
                        'description' => 'Unique key of the item in the dashboard',
                    ],
                    'card' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'x-field' => 'card_id',
                        'description' => 'The card type of the dashboard item. Usually corresponds to a widget key and some extra information. For example, "bn_count_Computer" is big number widget showing the count of computers.',
                    ],
                    'x' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'description' => 'X position of the item in the dashboard grid',
                    ],
                    'y' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'description' => 'Y position of the item in the dashboard grid',
                    ],
                    'width' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'description' => 'Width of the item in the dashboard grid',
                    ],
                    'height' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'description' => 'Height of the item in the dashboard grid',
                    ],
                    'card_options' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'JSON encoded options specific to the card type',
                    ],
                ],
            ],
            'DashboardRight' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => Dashboard\Right::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'readOnly' => true,
                    ],
                    'dashboard' => self::getDropdownTypeSchema(class: Dashboard\Dashboard::class, full_schema: 'Dashboard'),
                    'itemtype' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'Type of the item the right is granted to',
                        'enum' => [
                            \User::class,
                            \Group::class,
                            \Entity::class,
                            \Profile::class,
                        ],
                    ],
                    'items_id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'description' => 'ID of the item the right is granted to',
                    ],
                ],
            ],
            'DashboardCard' => [
                'x-version-introduced' => '2.2.0',
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'card' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'The card type key',
                    ],
                    'widget' => [
                        'type' => Doc\Schema::TYPE_ARRAY,
                        'description' => 'List of widget types that can be used to display this card',
                        'items' => [
                            'type' => Doc\Schema::TYPE_STRING,
                            'enum' => $all_widgets,
                        ],
                    ],
                    'group' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'The localized group name this card belongs to',
                    ],
                    'itemtype' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'The itemtype this card is related to, if any',
                    ],
                    'label' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'The localized label of the card',
                    ],
                    'filters' => [
                        'type' => Doc\Schema::TYPE_ARRAY,
                        'description' => 'List of filters applicable to this card',
                        'items' => [
                            'type' => Doc\Schema::TYPE_STRING,
                            'enum' => $known_filters,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[Route(path: '/Dashboard', methods: ['POST'])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\CreateRoute(schema_name: 'Dashboard')]
    public function createDashboard(Request $request): Response
    {
        return ResourceAccessor::createBySchema($this->getKnownSchema('Dashboard', $this->getAPIVersion($request)), $request->getParameters(), [self::class, 'getDashboard']);
    }

    #[Route(path: '/Dashboard', methods: ['GET'])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\SearchRoute(schema_name: 'Dashboard')]
    public function searchDashboards(Request $request): Response
    {
        return ResourceAccessor::searchBySchema($this->getKnownSchema('Dashboard', $this->getAPIVersion($request)), $request->getParameters());
    }

    #[Route(path: '/Dashboard/{dashboard_id}', methods: ['GET'], requirements: ['dashboard_id' => '\d+'])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\SearchRoute(schema_name: 'Dashboard')]
    public function getDashboard(Request $request): Response
    {
        $request->setAttribute('id', $request->getAttribute('dashboard_id'));
        return ResourceAccessor::getOneBySchema($this->getKnownSchema('Dashboard', $this->getAPIVersion($request)), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Dashboard/{dashboard_id}', methods: ['PATCH'], requirements: ['dashboard_id' => '\d+'])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\UpdateRoute(schema_name: 'Dashboard')]
    public function updateDashboard(Request $request): Response
    {
        $request->setAttribute('id', $request->getAttribute('dashboard_id'));
        return ResourceAccessor::updateBySchema($this->getKnownSchema('Dashboard', $this->getAPIVersion($request)), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Dashboard/{dashboard_id}', methods: ['DELETE'], requirements: ['dashboard_id' => '\d+'])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\DeleteRoute(schema_name: 'Dashboard')]
    public function deleteDashboard(Request $request): Response
    {
        $request->setAttribute('id', $request->getAttribute('dashboard_id'));
        return ResourceAccessor::deleteBySchema($this->getKnownSchema('Dashboard', $this->getAPIVersion($request)), $request->getAttributes(), $request->getParameters());
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Item', methods: ['POST'], requirements: [
        'dashboard_id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\CreateRoute(schema_name: 'DashboardItem')]
    public function createDashboardItem(Request $request): Response
    {
        //TODO Test
        $request->setAttribute('dashboard', $request->getAttribute('dashboard_id'));
        return ResourceAccessor::createBySchema(
            $this->getKnownSchema('DashboardItem', $this->getAPIVersion($request)),
            $request->getParameters(),
            [self::class, 'getDashboardItem'],
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Item', methods: ['GET'], requirements: [
        'dashboard_id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\SearchRoute(schema_name: 'DashboardItem')]
    public function searchDashboardItems(Request $request): Response
    {
        //TODO Test
        $filters = $request->hasParameter('filter') ? $request->getParameter('filter') : '';
        $filters .= ';dashboard.id==' . $request->getAttribute('dashboard_id');
        $request->setParameter('filter', $filters);
        return ResourceAccessor::searchBySchema(
            $this->getKnownSchema('DashboardItem', $this->getAPIVersion($request)),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Item/{id}', methods: ['GET'], requirements: [
        'dashboard_id' => '\d+',
        'id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\GetRoute(schema_name: 'DashboardItem')]
    public function getDashboardItem(Request $request): Response
    {
        //TODO Test
        $filters = $request->hasParameter('filter') ? $request->getParameter('filter') : '';
        $filters .= ';dashboard.id==' . $request->getAttribute('id');
        $request->setParameter('filter', $filters);
        return ResourceAccessor::getOneBySchema(
            $this->getKnownSchema('DashboardItem', $this->getAPIVersion($request)),
            $request->getAttributes(),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Item/{id}', methods: ['PATCH'], requirements: [
        'dashboard_id' => '\d+',
        'id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\UpdateRoute(schema_name: 'DashboardItem')]
    public function updateDashboardItem(Request $request): Response
    {
        //TODO Test
        return ResourceAccessor::updateBySchema(
            $this->getKnownSchema('DashboardItem', $this->getAPIVersion($request)),
            $request->getAttributes(),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Item/{id}', methods: ['DELETE'], requirements: [
        'dashboard_id' => '\d+',
        'id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\DeleteRoute(schema_name: 'DashboardItem')]
    public function deleteDashboardItem(Request $request): Response
    {
        //TODO Test
        return ResourceAccessor::deleteBySchema(
            $this->getKnownSchema('DashboardItem', $this->getAPIVersion($request)),
            $request->getAttributes(),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Filter', methods: ['POST'], requirements: [
        'dashboard_id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\CreateRoute(schema_name: 'DashboardFilter')]
    public function createDashboardFilter(Request $request): Response
    {
        //TODO Test
        $request->setAttribute('dashboard', $request->getAttribute('dashboard_id'));
        return ResourceAccessor::createBySchema(
            $this->getKnownSchema('DashboardFilter', $this->getAPIVersion($request)),
            $request->getParameters(),
            [self::class, 'getDashboardFilter'],
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Filter', methods: ['GET'], requirements: [
        'dashboard_id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\SearchRoute(schema_name: 'DashboardFilter')]
    public function searchDashboardFilters(Request $request): Response
    {
        //TODO Test
        $filters = $request->hasParameter('filter') ? $request->getParameter('filter') : '';
        $filters .= ';dashboard.id==' . $request->getAttribute('dashboard_id');
        $request->setParameter('filter', $filters);
        return ResourceAccessor::searchBySchema(
            $this->getKnownSchema('DashboardFilter', $this->getAPIVersion($request)),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Filter/{id}', methods: ['GET'], requirements: [
        'dashboard_id' => '\d+',
        'id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\GetRoute(schema_name: 'DashboardFilter')]
    public function getDashboardFilter(Request $request): Response
    {
        //TODO Test
        $filters = $request->hasParameter('filter') ? $request->getParameter('filter') : '';
        $filters .= ';dashboard.id==' . $request->getAttribute('dashboard_id');
        $request->setParameter('filter', $filters);
        return ResourceAccessor::getOneBySchema(
            $this->getKnownSchema('DashboardFilter', $this->getAPIVersion($request)),
            $request->getAttributes(),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Filter/{id}', methods: ['PATCH'], requirements: [
        'dashboard_id' => '\d+',
        'id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\UpdateRoute(schema_name: 'DashboardFilter')]
    public function updateDashboardFilter(Request $request): Response
    {
        //TODO Test
        return ResourceAccessor::updateBySchema(
            $this->getKnownSchema('DashboardFilter', $this->getAPIVersion($request)),
            $request->getAttributes(),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Filter/{id}', methods: ['DELETE'], requirements: [
        'dashboard_id' => '\d+',
        'id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\DeleteRoute(schema_name: 'DashboardFilter')]
    public function deleteDashboardFilter(Request $request): Response
    {
        //TODO Test
        return ResourceAccessor::deleteBySchema(
            $this->getKnownSchema('DashboardFilter', $this->getAPIVersion($request)),
            $request->getAttributes(),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Right', methods: ['POST'], requirements: [
        'dashboard_id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\CreateRoute(schema_name: 'DashboardRight')]
    public function createDashboardRight(Request $request): Response
    {
        //TODO Test
        $request->setAttribute('dashboard', $request->getAttribute('dashboard_id'));
        return ResourceAccessor::createBySchema(
            $this->getKnownSchema('DashboardRight', $this->getAPIVersion($request)),
            $request->getParameters(),
            [self::class, 'getDashboardRight'],
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Right', methods: ['GET'], requirements: [
        'dashboard_id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\SearchRoute(schema_name: 'DashboardRight')]
    public function searchDashboardRights(Request $request): Response
    {
        //TODO Test
        $filters = $request->hasParameter('filter') ? $request->getParameter('filter') : '';
        $filters .= ';dashboard.id==' . $request->getAttribute('dashboard_id');
        $request->setParameter('filter', $filters);
        return ResourceAccessor::searchBySchema(
            $this->getKnownSchema('DashboardRight', $this->getAPIVersion($request)),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Dashboard/{dashboard_id}/Right/{id}', methods: ['DELETE'], requirements: [
        'dashboard_id' => '\d+',
        'id' => '\d+',
    ])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\DeleteRoute(schema_name: 'DashboardRight')]
    public function deleteDashboardRight(Request $request): Response
    {
        //TODO Test
        return ResourceAccessor::deleteBySchema(
            $this->getKnownSchema('DashboardRight', $this->getAPIVersion($request)),
            $request->getAttributes(),
            $request->getParameters(),
        );
    }

    #[Route(path: '/Card', methods: ['GET'])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\SearchRoute(schema_name: 'DashboardCard')]
    public function searchDashboardCards(Request $request): Response
    {
        //TODO (Not stored in DB)
        return new Response();
    }

    #[Route(path: '/Card/{card}', methods: ['GET'], requirements: ['card' => '\w+'])]
    #[RouteVersion(introduced: '2.2')]
    #[Doc\GetRoute(schema_name: 'DashboardCard')]
    public function getDashboardCard(Request $request): Response
    {
        //TODO (Not stored in DB)
        return new Response();
    }
}
