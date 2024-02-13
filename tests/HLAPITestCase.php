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

use Glpi\Api\HL\Controller\CoreController;
use Glpi\Api\HL\Doc as Doc;
use Glpi\Api\HL\Middleware\ResultFormatterMiddleware;
use Glpi\Api\HL\Route;
use Glpi\Api\HL\RoutePath;
use Glpi\Api\HL\Router;
use Glpi\Http\Request;
use Glpi\Http\Response;

/**
 * @property HLAPIHelper $api
 */
class HLAPITestCase extends \DbTestCase
{
    private $bearer_token = null;

    public function afterTestMethod($method)
    {
        HLAPIHelper::$routes_cache = [];
        Session::destroy();
        parent::afterTestMethod($method);
    }

    public function loginWeb(string $user_name = TU_USER, string $user_pass = TU_PASS, bool $noauto = true, bool $expected = true): \Auth
    {
        return parent::login($user_name, $user_pass, $noauto, $expected);
    }

    public function login(string $user_name = TU_USER, string $user_pass = TU_PASS, bool $noauto = true, bool $expected = true): \Auth
    {
        $request = new Request('POST', '/token', [
            'Content-Type' => 'application/json'
        ], json_encode([
            'grant_type' => 'password',
            'client_id' => TU_OAUTH_CLIENT_ID,
            'client_secret' => TU_OAUTH_CLIENT_SECRET,
            'username' => $user_name,
            'password' => $user_pass,
            'scope' => ''
        ]));
        $this->api->call($request, function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) {
                    $this->array($content)->hasKeys(['token_type', 'expires_in', 'access_token', 'refresh_token']);
                    $this->string($content['token_type'])->isEqualTo('Bearer');
                    $this->string($content['access_token'])->isNotEmpty();
                    $this->bearer_token = $content['access_token'];
                });
        });
        return new \Auth();
    }

    public function getTestFunctionName(): string
    {
        // Look through the call stack to find the first call to a HLAPITestCase method, then get the class::method for the previous frame
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $test_function_name = null;
        foreach ($stack as $frame) {
            if (isset($frame['class']) && $frame['class'] === self::class) {
                $test_function_name = $frame['function'];
            }
        }
        if ($test_function_name === null) {
            throw new \Exception('Unable to find the test function name');
        }
        return $test_function_name;
    }

    public function getCurrentBearerToken(): ?string
    {
        return $this->bearer_token;
    }

    protected function checkSimpleContentExpect(array $content, array $expected): void
    {
        if (isset($expected['count'])) {
            $operator = $expected['count'][0];
            switch ($operator) {
                case '>':
                    $this->integer(count($content))->isGreaterThan($expected['count'][1]);
                    break;
                case '>=':
                    $this->integer(count($content))->isGreaterThanOrEqualTo($expected['count'][1]);
                    break;
                case '<':
                    $this->integer(count($content))->isLessThan($expected['count'][1]);
                    break;
                case '<=':
                    $this->integer(count($content))->isLessThanOrEqualTo($expected['count'][1]);
                    break;
                case '=':
                    $this->integer(count($content))->isEqualTo($expected['count'][1]);
                    break;
            }
        }

        if (isset($expected['fields'])) {
            foreach ($expected['fields'] as $field => $value) {
                // $field could be an array path. We need to check each part of the path
                $parts = explode('.', $field);
                $current = $content;
                foreach ($parts as $part) {
                    $this->array($current)->hasKey($part);
                    $current = $current[$part];
                }
                $this->variable($current)->isEqualTo($value);
            }
        }
    }

    public function __get($name)
    {
        if ($name === 'api') {
            return new HLAPIHelper(Router::getInstance(), $this);
        }
    }
}

// @codingStandardsIgnoreStart
final class HLAPIHelper
{
    /** @var RoutePath[] */
    public static array $routes_cache = [];

    // @codingStandardsIgnoreEnd
    public function __construct(
        private Router $router,
        private HLAPITestCase $test
    ) {
    }

    /**
     * @return RoutePath[]
     */
    private function getRoutes(): array
    {
        if (count(self::$routes_cache) === 0) {
            self::$routes_cache = $this->router->getAllRoutes();
        }
        return self::$routes_cache;
    }

    /**
     * @param string $endpoint
     * @return RoutePath[]
     */
    private function getRoutesForEndpoint(string $endpoint): array
    {
        $methods = ['GET', 'POST', 'PATCH', 'DELETE'];
        $all_routes = [];
        foreach ($methods as $method) {
            $all_routes = [...$all_routes, ...$this->router->matchAll(new Request($method, $endpoint))];
            $all_routes = [...$all_routes, ...$this->router->matchAll(new Request($method, $endpoint . '/1'))];
        }
        // Remove default routes
        $all_routes = array_filter($all_routes, static fn ($route) => !($route->getController() === CoreController::class && $route->getMethod()->getShortName() === 'defaultRoute'));
        return $all_routes;
    }

    private function getRoutePathDoc(RoutePath $route_path, string $method, bool $required = true): Doc\Route
    {
        $doc = $route_path->getRouteDoc($method);
        if ($required) {
            $this->test->variable($doc)->isNotNull('No documentation found for route ' . $route_path->getRoutePath() . ' with method ' . $method);
        }
        return $doc;
    }

    private function getRoutePathBodySchema(RoutePath $route_path, string $method): array|null
    {
        $doc = $route_path->getRouteDoc($method);
        $this->test->variable($doc)->isNotNull('No documentation found for route ' . $route_path->getRoutePath() . ' with method ' . $method);

        $params = $doc->getParameters();
        $body_params = array_filter($params, static fn ($param) => $param->getName() === '_' && $param->getLocation() === 'body');
        if (empty($body_params)) {
            return null;
        }
        $this->test->array($body_params)->hasSize(1, 'Multiple body parameters found for route ' . $route_path->getRoutePath() . ' with method ' . $method);
        $body_param = array_values($body_params)[0];
        $schema = $body_param->getSchema();
        if ($schema instanceof Doc\SchemaReference) {
            $is_array = str_ends_with($schema->getRef(), '[]');
            $schema = Doc\SchemaReference::resolveRef($schema->getRef(), $route_path->getController());
            if ($is_array) {
                $schema = [
                    'type' => Doc\Schema::TYPE_ARRAY,
                    'items' => $schema
                ];
            }
        }
        return $schema;
    }

    private function getRoutePathResponseSchema(RoutePath $route_path, string $method, $status = 200): array|null
    {
        $doc = $route_path->getRouteDoc($method);
        $this->test->variable($doc)->isNotNull('No documentation found for route ' . $route_path->getRoutePath() . ' with method ' . $method);

        $responses = $doc->getResponses();
        $response = array_filter($responses, static fn ($response) => $response->getStatusCode() === $status)[0] ?? null;
        if ($response === null) {
            return null;
        }
        $schema = $response->getSchema();
        if ($schema instanceof Doc\SchemaReference) {
            $schema = Doc\SchemaReference::resolveRef($schema->getRef(), $route_path->getController());
        }
        return $schema;
    }

    private function routePathHasParameter(RoutePath $route_path, string $parameter, string $location): bool
    {
        $doc = $route_path->getRouteDoc('GET');
        $this->test->variable($doc)->isNotNull('No documentation found for route ' . $route_path->getRoutePath() . ' with method GET');

        $params = $doc->getParameters();
        $matches = array_filter($params, static fn ($param) => $param->getName() === $parameter && $param->getLocation() === $location);
        return !empty($matches);
    }

    private function routePathHasMiddleware(RoutePath $route_path, string $middleware_class): bool
    {
        $middlewares = $route_path->getMiddlewares();
        $matches = array_filter($middlewares, static fn ($middleware) => $middleware === $middleware_class);
        return !empty($matches);
    }

    public function hasMatch(Request $request)
    {
        if ($this->test->getCurrentBearerToken() !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->test->getCurrentBearerToken());
        }
        $match = $this->router->match($request);
        $is_default_route = false;
        if (
            $match !== null &&
            ($match->getController() === CoreController::class && $match->getMethod()->getShortName() === 'defaultRoute')
        ) {
            $is_default_route = true;
        }
        return $this->test->boolean($match !== null && !$is_default_route);
    }

    /**
     * @param Request $request
     * @param callable(HLAPICallAsserter): void $fn
     * @return self
     */
    public function call(Request $request, callable $fn, bool $auto_auth_header = true): self
    {
        if ($auto_auth_header && $this->test->getCurrentBearerToken() !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->test->getCurrentBearerToken());
        }
        $response = $this->router->handleRequest($request);
        $fn(new HLAPICallAsserter($this->test, $this->router, $response));
        return $this;
    }

    public function autoTestCRUD(string $endpoint, array $create_params = [], array $update_params = []): self
    {
        $unique_id = $this->test->getTestFunctionName();

        $routes = [...$this->getRoutesForEndpoint($endpoint), ...$this->getRoutesForEndpoint($endpoint . '/{id}')];
        $all_methods = [];
        foreach ($routes as $route) {
            $all_methods = [...$all_methods, ...$route->getRouteMethods()];
        }
        $required_methods = ['POST', 'GET', 'PATCH', 'DELETE'];
        $missing_methods = array_diff($required_methods, $all_methods);
        $this->test->array($missing_methods)->isEmpty('The endpoint "' . $endpoint . '" does not support the following CRUD methods: ' . implode(', ', $missing_methods));

        $schema = null;
        foreach ($routes as $route) {
            if (in_array('POST', $route->getRouteMethods(), true)) {
                $schema = $this->getRoutePathBodySchema($route, 'POST');
                break;
            }
        }
        $this->test->variable($schema)->isNotNull('The POST route for endpoint "' . $endpoint . '" does not have a body schema');
        $this->test->string($schema['type'])->isEqualTo('object', 'The POST route for endpoint "' . $endpoint . '" body schema is not for an object');
        $schema_json = json_encode($schema);
        $flattened_props = Doc\Schema::flattenProperties($schema['properties'] ?? []);

        foreach ($routes as $route) {
            if (in_array('GET', $route->getRouteMethods(), true) && str_ends_with($route->getRoutePath(), '/{id}')) {
                $get_schema = $this->getRoutePathResponseSchema($route, 'GET');
                $this->test
                    ->variable($get_schema)->isNotNull('The GET route for endpoint "' . $endpoint . '" does not have a response schema');
                $this->test
                    ->string(json_encode($get_schema))->isEqualTo($schema_json, 'The POST route for endpoint "' . $endpoint . '" body schema does not match the GET route response schema');
            } else if (in_array('PATCH', $route->getRouteMethods(), true)) {
                $patch_schema = $this->getRoutePathBodySchema($route, 'PATCH');
                $this->test
                    ->variable($patch_schema)->isNotNull('The PATCH route for endpoint "' . $endpoint . '" does not have a body schema');
                $this->test
                    ->string(json_encode($patch_schema))->isEqualTo($schema_json, 'The input body for the POST route path and the body for the PATCH route path of endpoint "' . $endpoint . '" do not match');
            } else if (in_array('DELETE', $route->getRouteMethods(), true)) {
                $delete_schema = $this->getRoutePathBodySchema($route, 'DELETE');
                $this->test->variable($delete_schema)->isNull('The DELETE route for endpoint "' . $endpoint . '" has a body schema');
            }
        }

        // CREATE
        $request = new Request('POST', $endpoint);
        if (isset($schema['properties']['name']) && !isset($create_params['name'])) {
            $create_params['name'] = $unique_id;
            if (empty($update_params)) {
                $update_params['name'] = $unique_id . '2';
            }
        }
        if (isset($schema['properties']['entity']) && !isset($create_params['entity'])) {
            $create_params['entity'] = getItemByTypeName('Entity', '_test_root_entity', true);
        }
        foreach ($create_params as $key => $value) {
            $request->setParameter($key, $value);
        }
        // remove writeonly properties from $create_params so checks below don't fail
        foreach ($flattened_props as $key => $value) {
            if (isset($value['x-writeonly']) && $value['x-writeonly'] === true) {
                unset($create_params[$key]);
            }
        }

        $this->call(new Request('POST', $endpoint), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response->isUnauthorizedError();
        }, false);

        $new_item_location = null;
        $this->call($request, function ($call) use (&$new_item_location, $endpoint) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($endpoint) {
                    $this->test->array($content)
                        ->hasKey('id', 'The response for the POST route path of endpoint "' . $endpoint . '" does not have an "id" field');
                    $this->test->integer($content['id'])
                        ->isGreaterThan(0, 'The response for the POST route path of endpoint "' . $endpoint . '" has an "id" field that is not valid');
                    $this->test->array($content)
                        ->hasKey('href', 'The response for the POST route path of endpoint "' . $endpoint . '" does not have an "href" field');
                    $this->test->integer(strcasecmp($content['href'], $endpoint . '/' . $content['id']))
                        ->isEqualTo(0, 'The response for the POST route path of endpoint "' . $endpoint . '" has an "href" field that is not valid');
                })
                ->headers(function ($headers) use (&$new_item_location) {
                    $this->test->array($headers)->hasKey('Location');
                    $this->test->string($headers['Location'])->isNotEmpty();
                    $new_item_location = $headers['Location'];
                });
        });

        $this->call(new Request('PATCH', $new_item_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response->isUnauthorizedError();
        }, false);
        $this->call(new Request('GET', $new_item_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response->isUnauthorizedError();
        }, false);
        $this->call(new Request('DELETE', $new_item_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response->isUnauthorizedError();
        }, false);

        var_dump('Test1');
        // Get the new item
        $this->call(new Request('GET', $new_item_location), function ($call) use ($schema, $endpoint, $create_params) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->matchesSchema($schema, 'The response for the GET route path of endpoint "' . $endpoint . '" does not match the schema', 'read')
                ->jsonContent(function ($content) use ($create_params) {
                    foreach ($create_params as $key => $value) {
                        if (is_array($content[$key]) && isset($content[$key]['id'])) {
                            $this->test->variable($content[$key]['id'])->isEqualTo($value);
                        } else {
                            $this->test->variable($content[$key])->isEqualTo($value);
                        }
                    }
                });
            });
        var_dump('Test2');

        // Update the new item
        $request = new Request('PATCH', $new_item_location);
        foreach ($update_params as $key => $value) {
            $request->setParameter($key, $value);
        }
        $this->call($request, function ($call) use ($schema, $endpoint) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->matchesSchema($schema, 'The response for the PATCH route path of endpoint "' . $endpoint . '" does not match the schema', 'read');
        });

        // Get the new item again and verify that the name has been updated
        $this->call(new Request('GET', $new_item_location), function ($call) use ($update_params) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($update_params) {
                    foreach ($update_params as $key => $value) {
                        if (is_array($content[$key]) && isset($content[$key]['id'])) {
                            $this->test->variable($content[$key]['id'])->isEqualTo($value);
                        } else {
                            $this->test->variable($content[$key])->isEqualTo($value);
                        }
                    }
                });
        });

        // Delete the new item
        $this->call(new Request('DELETE', $new_item_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(fn ($content) => $this->test->variable($content)->isNull());
        });

        $itemtype = $schema['x-itemtype'];
        if (is_subclass_of($itemtype, \CommonDBTM::class)) {
            $item = new $itemtype();
            if ($item->maybeDeleted()) {
                // Try getting the new item again. It should still exist.
                $this->call(new Request('GET', $new_item_location), function ($call) use ($update_params) {
                    /** @var \HLAPICallAsserter $call */
                    $call->response
                        ->isOK()
                        ->jsonContent(function ($content) use ($update_params) {
                            foreach ($update_params as $key => $value) {
                                if (is_array($content[$key]) && isset($content[$key]['id'])) {
                                    $this->test->variable($content[$key]['id'])->isEqualTo($value);
                                } else {
                                    $this->test->variable($content[$key])->isEqualTo($value);
                                }
                            }
                        });
                });

                // Force delete the new item
                $request = new Request('DELETE', $new_item_location);
                $request->setParameter('force', 1);
                $this->call($request, function ($call) {
                    /** @var \HLAPICallAsserter $call */
                    $call->response
                        ->isOK()
                        ->jsonContent(fn ($content) => $this->test->variable($content)->isNull());
                });
            }
        }

        // Try getting the new item again (should be a 404)
        $this->call(new Request('GET', $new_item_location), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isNotFoundError();
        });

        return $this;
    }

    public function autoTestSearch(string $endpoint, array $dataset, string $unique_field = 'name'): self
    {
        $this->test->array($dataset)->size->isGreaterThan(2, 'Dataset for endpoint "' . $endpoint . '" must have at least 3 entries');

        // Search without authorization should return an error
        $this->call(new Request('GET', $endpoint), function ($call) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isUnauthorizedError();
        }, false);

        /** @var RoutePath[] $routes */
        $routes = [...$this->getRoutesForEndpoint($endpoint)];
        $search_route = array_filter($routes, static fn ($rp) => in_array('GET', $rp->getRouteMethods()))[0] ?? null;

        $this->test->variable($search_route)->isNotNull('No GET route found for endpoint "' . $endpoint . '"');
        $response_schema = $this->getRoutePathResponseSchema($search_route, 'GET');
        $this->test->variable($response_schema)->isNotNull('No response schema found for GET route for endpoint "' . $endpoint . '"');
        $this->test->string($response_schema['type'])->isEqualTo('array', 'Response schema for GET route for endpoint "' . $endpoint . '" is not for an array');

        // Search routes should allow filtering and pagination
        $this->test->boolean($this->routePathHasParameter($search_route, 'filter', 'query'))->isTrue('No "filter" query parameter found for GET route for endpoint "' . $endpoint . '"');
        $this->test->boolean($this->routePathHasParameter($search_route, 'start', 'query'))->isTrue('No "start" query parameter found for GET route for endpoint "' . $endpoint . '"');
        $this->test->boolean($this->routePathHasParameter($search_route, 'limit', 'query'))->isTrue('No "limit" query parameter found for GET route for endpoint "' . $endpoint . '"');

        // Search routes should specify the ResultFormatterMiddleware to allow optionally returning results as CSV or XML
        $this->test->boolean($this->routePathHasMiddleware($search_route, ResultFormatterMiddleware::class))->isTrue('ResultFormatterMiddleware not found on GET route for endpoint "' . $endpoint . '"');

        foreach ($dataset as $i => &$entry) {
            if (!isset($entry[$unique_field])) {
                $entry[$unique_field] = $this->test->getTestFunctionName() . '_' . $i;
            }
            var_dump($response_schema);
            if (isset($response_schema['items']['properties']['entity']) && !isset($entry['entity'])) {
                $entry['entity'] = getItemByTypeName('Entity', '_test_root_entity', true);
            }
        }
        unset($entry);

        foreach ($dataset as $entry) {
            $request = new Request('POST', $endpoint);
            foreach ($entry as $key => $value) {
                $request->setParameter($key, $value);
            }
            $this->call($request, function ($call) {
                /** @var \HLAPICallAsserter $call */
                $call->response->isOK();
            });
        }

        // Test default pagination
        $this->call(new Request('GET', $endpoint), function ($call) use ($endpoint, $dataset) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->headers(function ($headers) use ($endpoint, $dataset) {
                    $this->test->array($headers)->hasKey('Content-Range');
                    $content_range = $headers['Content-Range'];
                    [$result_range, $total_count] = explode('/', $content_range);
                    $result_range = explode('-', $result_range);
                    $this->test->integer((int) $result_range[0])->isEqualTo(0, 'The Content-Range header for endpoint "' . $endpoint . '" does not start at 0 when no pagination parameters are specified (' . $content_range . ')');
                    $this->test->integer((int) $result_range[1])->isLessThanOrEqualTo($total_count, 'The Content-Range header for endpoint "' . $endpoint . '" does not have a valid range when no pagination parameters are specified (' . $content_range . ')');
                    $this->test->integer((int) $total_count)->isGreaterThanOrEqualTo(count($dataset), 'The Content-Range header for endpoint "' . $endpoint . '" does not have a valid total count when no pagination parameters are specified (' . $content_range . ')');
                });
        });

        // Test pagination
        $request = new Request('GET', $endpoint);
        $request->setParameter('start', 1);
        $request->setParameter('limit', 2);
        $this->call($request, function ($call) use ($endpoint, $dataset) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->headers(function ($headers) use ($endpoint, $dataset) {
                    $this->test->array($headers)->hasKey('Content-Range');
                    $content_range = $headers['Content-Range'];
                    [$result_range, $total_count] = explode('/', $content_range);
                    $result_range = explode('-', $result_range);
                    $this->test->integer((int) $result_range[0])->isEqualTo(1, 'The Content-Range header for endpoint "' . $endpoint . '" does not start at the correct position (' . $content_range . ')');
                    $this->test->integer((int) $result_range[1])->isEqualTo(2, 'The Content-Range header for endpoint "' . $endpoint . '" does not have a valid range (' . $content_range . ')');
                    $this->test->integer((int) $total_count)->isGreaterThanOrEqualTo(count($dataset), 'The Content-Range header for endpoint "' . $endpoint . '" does not have a valid total count (' . $content_range . ')');
                });
        });

        // Test filtering
        $request = new Request('GET', $endpoint);
        $unique_prefix = explode('_', $dataset[0][$unique_field])[0];
        $request->setParameter('filter', $unique_field . '=like=' . $unique_prefix . '*');

        $this->call($request, function ($call) use ($unique_field, $endpoint, $dataset) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($unique_field, $endpoint, $dataset) {
                    if (count($content) !== count($dataset)) {
                        var_dump($content);ob_flush();
                    }
                    $this->test->array($content)->hasSize(count($dataset), 'The response for the GET route path of endpoint "' . $endpoint . '" does not have the correct number of results when filtering by ' . $unique_field);
                });
        });

        $request = new Request('GET', $endpoint);
        $request->setParameter('filter', $unique_field . '==' . $dataset[0][$unique_field]);
        $this->call($request, function ($call) use ($unique_field, $endpoint) {
            /** @var \HLAPICallAsserter $call */
            $call->response
                ->isOK()
                ->jsonContent(function ($content) use ($unique_field, $endpoint) {
                    $this->test->array($content)->hasSize(1, 'The response for the GET route path of endpoint "' . $endpoint . '" does not have the correct number of results when filtering by a specific ' . $unique_field);
                });
        });

        return $this;
    }
}

// @codingStandardsIgnoreStart
/**
 * @property HLAPIRequestAsserter $originalRequest
 * @property HLAPIRequestAsserter $finalRequest
 * @property HLAPIResponseAsserter $response
 * @property HLAPIRouteAsserter $route
 */
final class HLAPICallAsserter
{
    // @codingStandardsIgnoreEnd
    public function __construct(
        public HLAPITestCase $test,
        private Router $router,
        private Response $response
    ) {
    }

    public function __get(string $name)
    {
        return match ($name) {
            'originalRequest' => new HLAPIRequestAsserter($this, $this->router->getOriginalRequest()),
            'finalRequest' => new HLAPIRequestAsserter($this, $this->router->getFinalRequest()),
            'response' => new HLAPIResponseAsserter($this, $this->response),
            'route' => new HLAPIRouteAsserter($this, $this->router->getLastInvokedRoute()),
            default => null,
        };
    }
}

// @codingStandardsIgnoreStart
final class HLAPIRequestAsserter
{
    // @codingStandardsIgnoreEnd
    public function __construct(
        private HLAPICallAsserter $call_asserter,
        private Request $request
    ) {
    }

    public function method(callable $fn): self
    {
        $fn($this->request->getMethod());
        return $this;
    }

    public function uri(callable $fn): self
    {
        $fn($this->request->getUri());
        return $this;
    }

    public function getUri(): string
    {
        return $this->request->getUri();
    }

    public function headers(callable $fn): self
    {
        $fn($this->request->getHeaders());
        return $this;
    }

    public function body(callable $fn): self
    {
        $fn($this->request->getBody());
        return $this;
    }
}

// @codingStandardsIgnoreStart
final class HLAPIResponseAsserter
{
    // @codingStandardsIgnoreEnd
    public function __construct(
        private HLAPICallAsserter $call_asserter,
        private Response $response
    ) {
    }

    public function status(callable $fn): self
    {
        $fn($this->response->getStatusCode());
        return $this;
    }

    public function headers(callable $fn): self
    {
        $headers = $this->response->getHeaders();
        $headers = array_map(static function ($header) {
            if (is_array($header) && count($header) === 1) {
                return $header[0];
            }
            return $header;
        }, $headers);
        $fn($headers);
        return $this;
    }

    /**
     * @param callable $fn
     * @phpstan-param callable(string): void $fn
     * @return $this
     */
    public function content(callable $fn): self
    {
        $fn((string) $this->response->getBody());
        return $this;
    }

    /**
     * @param callable $fn
     * @phpstan-param callable(array): void $fn
     * @return $this
     */
    public function jsonContent(callable $fn): self
    {
        $fn(json_decode((string) $this->response->getBody(), true));
        return $this;
    }

    public function isOK(): HLAPIResponseAsserter
    {
        $fail_msg = 'Status code for call to ' . $this->call_asserter->originalRequest->getUri() . ' is not OK';
        $status_code = $this->response->getStatusCode();
        if ($status_code < 200 || $status_code >= 300) {
            $response_content = json_decode((string) $this->response->getBody(), true);
            $fail_msg .= " ($status_code):\n" . var_export($response_content, true);
            // Status is 200 - 299
            $this->call_asserter->test
                ->integer($this->response->getStatusCode())->isGreaterThanOrEqualTo(200, $fail_msg);
            $this->call_asserter->test
                ->integer($this->response->getStatusCode())->isLessThan(300, $fail_msg);
        }
        return $this;
    }

    public function isUnauthorizedError(): HLAPIResponseAsserter
    {
        // Status is 401
        $this->call_asserter->test
            ->integer($this->response->getStatusCode())->isEqualTo(401, 'Status code for call to ' . $this->call_asserter->originalRequest->getUri() . ' is not 401');
        $decoded_content = json_decode((string) $this->response->getBody(), true);
        $this->call_asserter->test
            ->array($decoded_content)->hasKeys(['title', 'detail', 'status']);
        $this->call_asserter->test
            ->string($decoded_content['status'])->isEqualTo('ERROR_UNAUTHENTICATED');
        return $this;
    }

    public function isNotFoundError(): HLAPIResponseAsserter
    {
        // Status is 404
        $this->call_asserter->test
            ->integer($this->response->getStatusCode())->isEqualTo(404, 'Status code for call to ' . $this->call_asserter->originalRequest->getUri() . ' is not 404');
        $decoded_content = json_decode((string) $this->response->getBody(), true);
        $this->call_asserter->test
            ->array($decoded_content)->hasKeys(['title', 'detail', 'status']);
        $this->call_asserter->test
            ->string($decoded_content['status'])->isEqualTo('ERROR_ITEM_NOT_FOUND');
        return $this;
    }

    public function matchesSchema(string|array $schema, string|null $fail_msg = null, string|null $operation = null): HLAPIResponseAsserter
    {
        if (is_string($schema)) {
            $matched_route = $this->call_asserter->route->get();
            /** @var class-string<\Glpi\Api\HL\Controller\AbstractController> $controller */
            $controller = $matched_route->getController();
            $schema = $controller::getKnownSchemas()[$schema];
        }
        $content = json_decode((string) $this->response->getBody(), true);

        // Verify the JSON content matches the OpenAPI schema
        $matches = Doc\Schema::fromArray($schema)->isValid($content, $operation);
        if (!$matches) {
            $fail_msg = $fail_msg ?? 'Response content does not match the schema';
            $fail_msg .= ":\n" . var_export($content, true);
            $fail_msg .= "\n\nSchema:\n" . var_export($schema, true);
            $this->call_asserter->test
                ->boolean($matches)->isTrue($fail_msg);
        }
        return $this;
    }
}

// @codingStandardsIgnoreStart
final class HLAPIRouteAsserter
{
    // @codingStandardsIgnoreEnd
    public function __construct(
        private HLAPICallAsserter $call_asserter,
        private RoutePath $routePath
    ) {
    }

    public function path(callable $fn): self
    {
        $fn($this->routePath->getRoutePath());
        return $this;
    }

    public function compiledPath(callable $fn): self
    {
        $fn($this->routePath->getCompiledPath());
        return $this;
    }

    public function isAuthRequired(): self
    {
        $this->call_asserter->test
            ->boolean($this->routePath->getRouteSecurityLevel() !== Route::SECURITY_NONE)->isTrue();
        return $this;
    }

    public function isAnonymousAllowed(): self
    {
        $this->call_asserter->test
            ->boolean($this->routePath->getRouteSecurityLevel() === Route::SECURITY_NONE)->isTrue();
        return $this;
    }

    public function get(): RoutePath
    {
        return $this->routePath;
    }
}
