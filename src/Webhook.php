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

use Glpi\Api\HL\Controller\AbstractController;
use Glpi\Api\HL\Controller\AssetController;
use Glpi\Api\HL\Controller\ITILController;
use Glpi\Api\HL\Controller\ManagementController;
use Glpi\Api\HL\Doc\Schema;
use Glpi\Api\HL\Router;
use Glpi\Http\Request;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Search\FilterableInterface;
use Glpi\Search\FilterableTrait;
use GuzzleHttp\Client as Guzzle_Client;

class Webhook extends CommonDBTM implements FilterableInterface
{
    use Glpi\Features\Clonable;
    use FilterableTrait;

    public static $rightname         = 'config';

    // From CommonDBTM
    public $dohistory                = true;

    public static $undisclosedFields = [
        'secret'
    ];

    public function getCloneRelations(): array
    {
        return [
            Notepad::class,
        ];
    }

    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb([
            QueuedWebhook::class
        ]);
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Webhook', 'Webhooks', $nb);
    }

    public static function canCreate()
    {
        return static::canUpdate();
    }

    public static function canPurge()
    {
        return static::canUpdate();
    }

    public static function canDelete()
    {
        return static::canUpdate();
    }

    public function defineTabs($options = [])
    {
        $parent_tabs = parent::defineTabs();
        $tabs = [
            // Main tab retrieved from parents
            array_keys($parent_tabs)[0] => array_shift($parent_tabs),
            array_keys($parent_tabs)[0] => array_shift($parent_tabs),
        ];

        $this->addStandardTab(__CLASS__, $tabs, $options);
        $this->addStandardTab(WebhookTest::class, $tabs, $options);
        // Add common tabs
        $tabs = array_merge($tabs, $parent_tabs);
        $this->addStandardTab('Log', $tabs, $options);

        // Final order of tabs: main, filter, payload editor, queries, test, historical
        return $tabs;
    }

    public function rawSearchOptions()
    {

        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '2',
            'table'              => self::getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false, // implicit field is id
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => self::getTable(),
            'field'              => 'is_active',
            'name'               => __('Active'),
            'datatype'           => 'bool'
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => self::getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Type', 'Types', 1),
            'massiveaction'      => false,
            'datatype'           => 'specific',
            'searchtype'         => ['equals', 'notequals']
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => self::getTable(),
            'field'              => 'event',
            'name'               => _n('Event', 'Events', 1),
            'massiveaction'      => false,
            'datatype'           => 'specific',
            'additionalfields'   => [
                'itemtype'
            ],
            'searchtype'         => ['equals', 'notequals']
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'itemtype':
                if (isset($values[$field]) && class_exists($values[$field])) {
                    return $values[$field]::getTypeName(0);
                }
                break;
            case 'event':
                if (!empty($values['itemtype'])) {
                    $label = NotificationEvent::getEventName($values['itemtype'], $values[$field]);
                    if ($label === NOT_AVAILABLE) {
                        return self::getDefaultEventsListLabel($values[$field]);
                    }
                    return $label;
                }
                break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        switch ($field) {
            case 'itemtype':
                return Dropdown::showFromArray(
                    $name,
                    self::getItemtypesDropdownValues(),
                    [
                        'display'             => false,
                        'display_emptychoice' => true,
                        'value'               => $values[$field],
                    ]
                );
            case 'event':
                /**
                 * @var array $list_itemtype
                 * @phpstan-var array<class-string<CommonDBTM>, string> $list_itemtype
                 */
                $recursive_search = static function ($list_itemtype) use (&$recursive_search) {
                    $events = [];
                    foreach ($list_itemtype as $itemtype => $itemtype_label) {
                        if (is_array($itemtype_label)) {
                            $events += $recursive_search($itemtype_label);
                        } else {
                            if (isset($itemtype) && class_exists($itemtype)) {
                                $target = NotificationTarget::getInstanceByType($itemtype);
                                if ($target) {
                                    $events[$itemtype::getTypeName(0)] = $target->getAllEvents();
                                } else {
                                    //return standard CRUD
                                    $events[$itemtype::getTypeName(0)] = self::getDefaultEventsList();
                                }
                            }
                        }
                    }
                    return $events;
                };

                $events = $recursive_search(self::getItemtypesDropdownValues());
                return Dropdown::showFromArray(
                    $name,
                    $events,
                    [
                        'display'             => false,
                        'display_emptychoice' => true,
                        'value'               => $values[$field],
                    ]
                );
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Return a list of GLPI events that are valid for an itemtype.
     *
     * @param class-string<CommonDBTM>|null $itemtype
     * @return array
     */
    public static function getGlpiEventsList(?string $itemtype): array
    {
        if ($itemtype !== null && class_exists($itemtype)) {
            return self::getDefaultEventsList();
        } else {
            return [];
        }
    }

    /**
     * Return a list of default events.
     *
     * @return array
     */
    public static function getDefaultEventsList(): array
    {
        return [
            'new' => __("New"),
            'update' => __('Update'),
            'delete' => __("Delete"),
        ];
    }

    /**
     * Return default event name.
     *
     * @param string $event_name
     * @return string
     */
    public static function getDefaultEventsListLabel($event_name): string
    {
        $events = [
            'new' => __("New"),
            'update' => __('Update'),
            'delete' => __("Delete"),
        ];

        if (isset($events[$event_name])) {
            return $events[$event_name];
        }
        return NOT_AVAILABLE;
    }

    /**
     * Return a list of default events.
     *
     * @return array
     */
    public static function getHttpMethod(): array
    {
        return [
            'post'      => 'POST',
            'get'       => 'GET',
            'update'    => 'UPDATE',
            'patch'     => 'PATCH',
        ];
    }

    /**
     * Return status icon
     *
     * @return string
     */
    public static function getStatusIcon($status): string
    {
        if ($status) {
            return '<i class="fa-solid fa-triangle-exclamation fa-beat fa-lg" style="color: #ff0000;"></i>';
        } else {
            return '<i class="fa-solid fa-circle-check fa-beat fa-lg" style="color: #36d601;"></i>';
        }
    }

    private static function getAPIItemtypeData(): array
    {
        global $CFG_GLPI;

        static $supported = null;

        if ($supported === null) {
            $supported = [
                AssetController::class => [
                    'main' => $CFG_GLPI['asset_types']
                ],
                ITILController::class => [
                    'main' => [Ticket::class, Change::class, Problem::class],
                    'subtypes' => [
                        TicketTask::class => ['parent' => Ticket::class],
                        ChangeTask::class => ['parent' => Change::class],
                        ProblemTask::class => ['parent' => Problem::class],
                        ITILFollowup::class => [], // All main types can be the parent
                        Document_Item::class => [],
                        ITILSolution::class => [],
                        TicketValidation::class => [],
                    ]
                ],
                ManagementController::class => [
                    'main' => [
                        Appliance::class, Budget::class, Certificate::class, Cluster::class, Contact::class,
                        Contract::class, Database::class, Datacenter::class, Document::class, Domain::class,
                        SoftwareLicense::class, Line::class, Supplier::class
                    ]
                ]
            ];

            /**
             * @param class-string<CommonDBTM> $itemtype
             * @param array $schemas
             * @return array|null
             * @phpstan-return array{name: string, schema: array}|null
             */
            $fn_get_schema_by_itemtype = static function (string $itemtype, array $schemas) {
                $match = null;
                foreach ($schemas as $schema_name => $schema) {
                    if (isset($schema['x-itemtype']) && $schema['x-itemtype'] === $itemtype) {
                        $match = [
                            'name' => $schema_name,
                            'schema' => $schema
                        ];
                        break;
                    }
                }
                return $match;
            };

            /**
             * @var AbstractController $controller
             * @phpstan-var class-string<AbstractController> $controller
             * @var array $categories
             */
            foreach ($supported as $controller => $categories) {
                $schemas = $controller::getKnownSchemas();
                foreach ($categories as $category => $itemtypes) {
                    if ($category === 'main') {
                        foreach ($itemtypes as $i => $supported_itemtype) {
                            $schema = $fn_get_schema_by_itemtype($supported_itemtype, $schemas);
                            if ($schema) {
                                $supported[$controller][$category][$supported_itemtype] = [
                                    'name' => $schema['name'],
                                ];
                                unset($supported[$controller][$category][$i]);
                            }
                        }
                    } else if ($category === 'subtypes' && $controller === ITILController::class) {
                        /** @var ITILController $controller */
                        foreach ($itemtypes as $supported_itemtype => $type_data) {
                            $supported[$controller][$category][$supported_itemtype]['name'] = $controller::getFriendlyNameForSubtype($supported_itemtype);
                        }
                    }
                }
            }
        }

        return $supported;
    }

    /**
     * Return a list of GLPI itemtypes availabel through HL API.
     *
     * @return array
     */
    public static function getItemtypesDropdownValues(): array
    {
        $values = [];
        $supported = self::getAPIItemtypeData();

        $values[__('Assets')] = array_keys($supported[AssetController::class]['main']);
        $values[__('Assistance')] = array_merge(
            array_keys($supported[ITILController::class]['main']),
            array_keys($supported[ITILController::class]['subtypes'])
        );
        $values[__('Management')] = array_keys($supported[ManagementController::class]['main']);

        // Move leaf values to the keys and make the value the ::getTypeName
        foreach ($values as $category => $itemtypes) {
            foreach ($itemtypes as $i => $itemtype) {
                $values[$category][$itemtype] = $itemtype::getTypeName(1);
                unset($values[$category][$i]);
            }
        }

        return $values;
    }

    public static function getSubItemForAssistance(): array
    {
        $sub_item = [
            'ITILFollowup' => ITILFollowup::getTypeName(0),
            'Document_Item' => Document_Item::getTypeName(0),
            'ITILSolution' => ITILSolution::getTypeName(0),
        ];

        $itil_types = [Ticket::class, Change::class, Problem::class];
        foreach ($itil_types as $itil_type) {
            $validation = $itil_type::getValidationClassInstance();
            if ($validation !== null) {
                $sub_item[$validation::class] = $validation::getTypeName(0);
            }

            $task_class = $itil_type::getTaskClass();
            if ($task_class !== null) {
                $sub_item[$task_class] = $task_class::getTypeName(0);
            }
        }
        return $sub_item;
    }

    public function getResultForPath(string $path, string $event, bool $raw_output = false): ?string
    {
        $router = Router::getInstance();
        $path = rtrim($path, '/');
        $request = new Request('GET', $path);
        $request = $request->withHeader('Glpi-Session-Token', $_SESSION['valid_id']);
        $response = $router->handleRequest($request);
        if ($response->getStatusCode() === 200) {
            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if ($raw_output) {
                $data['event'] = $event;
                return json_encode($data, JSON_PRETTY_PRINT);
            } else {
                $payload_template = isset($this->fields['payload']) ? $this->fields['payload'] : null;
                if ($this->fields['use_default_payload'] === 1) {
                    $payload_template = null;
                }
                if (!empty($payload_template)) {
                    try {
                        $data = [
                            'item' => $data
                        ];
                        $data['event'] = $event;
                        $env = new \Twig\Environment(
                            new \Twig\Loader\ArrayLoader([
                                'payload' => $payload_template
                            ])
                        );
                        return $env->render('payload', $data);
                    } catch (Throwable $e) {
                        return null;
                    }
                } else {
                    $data['event'] = $event;
                    return json_encode($data, JSON_PRETTY_PRINT);
                }
            }
        }
        // An error occurred, so return nothing.
        return null;
    }

    public function getApiPath(CommonDBTM $item): string
    {
        $itemtype = $item->getType();
        $id = $item->getID();
        $itemtypes = self::getAPIItemtypeData();

        $controller = null;
        $api_name = null;
        $parent_itemtype = null;
        $parent_name = null;
        foreach ($itemtypes as $controller_class => $categories) {
            if (array_key_exists($itemtype, $categories['main'])) {
                $api_name = $categories['main'][$itemtype]['name'];
                $controller = $controller_class;
                break;
            }

            if (isset($categories['subtypes']) && array_key_exists($itemtype, $categories['subtypes'])) {
                $api_name = $categories['subtypes'][$itemtype]['name'];
                $controller = $controller_class;
                // Use the specified parent itemtype or the first main one if none is specified (all work)
                $parent_itemtype = $categories['subtypes'][$itemtype]['parent'] ?? array_key_first($categories['main']);
                break;
            }
        }

        if ($parent_itemtype !== null) {
            $parent_name = $itemtypes[$controller]['main'][$parent_itemtype]['name'];
        }

        $path = match ($controller) {
            AssetController::class => '/Assets/',
            ITILController::class => '/Assistance/',
            ManagementController::class => '/Management/',
            default => '/_404/' // Nonsense path to trigger a 404
        };

        if ($parent_name !== null) {
            if ($item instanceof CommonDBChild) {
                $itemtype_field = $item::$itemtype;
                if ($itemtype_field === 'itemtype') {
                    $itemtype_value = $item->fields[$itemtype_field];
                } else {
                    $itemtype_value = $itemtype_field;
                }
                $parent_name = $itemtypes[$controller]['main'][$itemtype_value]['name'];
                $parent_id = $item->fields[$item::$items_id];
            } else if ($item instanceof CommonDBRelation) {
                $itemtype_field = $item::$itemtype_2;
                if (str_starts_with($itemtype_field, "itemtype")) {
                    $itemtype_value = $item->fields[$itemtype_field];
                } else {
                    $itemtype_value = $itemtype_field;
                }
                $items_id_value = $item->fields[$item::$items_id_2];
                $parent_name = $itemtypes[$controller]['main'][$itemtype_value]['name'];
                $parent_id = $items_id_value;
            } else if ($item instanceof CommonITILTask) {
                $parent_itemtype = $item->getItilObjectItemType();
                $parent_name = $itemtypes[$controller]['main'][$parent_itemtype]['name'];
                $parent_id = $item->fields[$parent_itemtype::getForeignKeyField()];
            }

            $path .= $parent_name . '/' . ($parent_id ?? 0) . '/';

            if ($controller === ITILController::class) {
                $path .= 'Timeline/';
            }
        }

        $path .= $api_name . '/' . $id;

        return $path;
    }

    public function showForm($id, array $options = [])
    {
        if (!empty($id)) {
            $this->getFromDB($id);

            //validate CRA if needed
            if (isset($this->fields['use_cra_challenge']) && $this->fields['use_cra_challenge']) {
                $response = self::validateCRAChallenge($this->fields['url'], 'validate_cra_challenge', $this->fields['secret']);
                if (!$response['status']) {
                    $this->fields['is_cra_challenge_valid'] = false;
                    $this->update($this->fields);
                }
            }
        } else {
            $this->getEmpty();
        }
        $this->initForm($id, $options);

        TemplateRenderer::getInstance()->display('pages/setup/webhook/webhook.html.twig', [
            'item' => $this
        ]);

        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return [
            1 => self::createTabEntry(__('Security'), 0, $item::getType(), 'ti ti-shield-lock'),
            2 => self::createTabEntry(__('Payload editor'), 0, $item::getType(), 'ti ti-code-dots'),
            3 => self::createTabEntry(_n('Query log', 'Queries log', Session::getPluralNumber()), 0, $item::getType(), 'ti ti-mail-forward')
        ];
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ((int) $tabnum === 1) {
            $item->showSecurityForm();
            return true;
        }

        if ((int) $tabnum === 2) {
            $item->showPayloadEditor();
            return true;
        }

        if ((int) $tabnum === 3) {
            $item->showSentQueries();
            return true;
        }
        return false;
    }

    private function showSecurityForm(): void
    {
        TemplateRenderer::getInstance()->display('pages/setup/webhook/webhook_security.html.twig', [
            'item' => $this,
            'secret_already_used' => $this->getWebhookWithSameSecret()
        ]);
    }

    /**
     * @param array $schema The API schema used to generate the payload
     * @return string The default payload as a twig template
     */
    private function getDefaultPayloadAsTwigTemplate(array $schema): string
    {
        $default_payload = [
            'event' => '{{ event }}',
            'item' => []
        ];

        // default payload should follow the same nested structure as the original $schema['properties'] but the values should be replaced with a twig tag of the key
        $fn_append_properties = function ($schema_arr, $prefix_keys = []) use (&$fn_append_properties) {
            $result = [];
            foreach ($schema_arr as $key => $value) {
                $new_prefix_keys = array_merge($prefix_keys, [$key]);
                if ($value['type'] === Schema::TYPE_OBJECT) {
                    $result = array_merge($result, $fn_append_properties($value['properties'], $new_prefix_keys));
                } else {
                    // walk through the result array for each prefix key (creating if needed) and set the value to the twig tag
                    $current = &$result;
                    foreach ($prefix_keys as $prefix_key) {
                        if (!isset($current[$prefix_key])) {
                            $current[$prefix_key] = [];
                        }
                        $current = &$current[$prefix_key];
                    }
                    $current[$key] = "{{ item." . implode('.', $new_prefix_keys) . " }}";
                }
            }
            return $result;
        };
        $default_payload['item'] = $fn_append_properties($schema['properties']);

        $default_payload_str = json_encode($default_payload, JSON_PRETTY_PRINT);

        return $default_payload_str;
    }

    private function showPayloadEditor(): void
    {
        $itemtype = $this->fields['itemtype'];
        /** @var class-string<AbstractController> $controller_class */
        $controller_class = null;
        $schema_name = null;
        $supported = self::getAPIItemtypeData();

        foreach ($supported as $controller => $categories) {
            if (array_key_exists($itemtype, $categories['main'])) {
                $schema_name = $categories['main'][$itemtype]['name'];
                $controller_class = $controller;
                break;
            }
            if (isset($categories['subtypes']) && array_key_exists($itemtype, $categories['subtypes'])) {
                $schema_name = $categories['subtypes'][$itemtype]['name'];
                $controller_class = $controller;
                break;
            }
        }

        if ($controller_class === null || $schema_name === null) {
            echo __('This itemtype is not supported by the API. Maybe a plugin is missing/disabled?');
            return;
        }
        $schema = $controller_class::getKnownSchemas()[$schema_name] ?? null;
        $props = Schema::flattenProperties($schema['properties'], 'item.');

        $response_schema = [
            [
                'name' => 'event',
                'type' => 'Variable'
            ]
        ];

        foreach ($props as $prop_name => $prop_data) {
            $response_schema[] = [
                'name' => $prop_name,
                'type' => 'Variable'
            ];
        }

        TemplateRenderer::getInstance()->display('pages/setup/webhook/payload_editor.html.twig', [
            'item' => $this,
            'params' => [
                'canedit' => $this->canUpdateItem(),
                'candel' => false
            ],
            'response_schema' => $response_schema,
            'default_payload' => $this->getDefaultPayloadAsTwigTemplate($schema)
        ]);
    }

    private function showSentQueries(): void
    {
        // Show embeded search engine for QueuedWebhook with the criteria for the current webhook ID
        $params = [
            'criteria' => [
                [
                    'link' => 'AND',
                    'field' => 22,
                    'searchtype' => 'equals',
                    'value' => $this->fields['id']
                ]
            ],
            // Sort by creation date descending by default
            'sort' => [16],
            'order' => ['DESC'],
            'forcetoview' => [80, 2, 20, 21, 7, 30, 16],
            'is_deleted' => 0,
            'as_map' => 0,
            'browse' => 0,
            'push_history' => 0,
            'hide_controls' => 1,
            'showmassiveactions' => 0,
            'usesession' => 0 // Don't save the search criteria in session or use any criteria currently saved
        ];
        Search::showList(QueuedWebhook::class, $params);
    }

    /**
     * Check if secret is already use dby another webhook
     * @return array of webhook using same secret
     */
    private function getWebhookWithSameSecret(): array
    {

        if (self::isNewID($this->fields['id'])) {
            return [];
        }

        //check if secret is already use by another webhook
        $webhook = new self();
        $data = $webhook->find([
            'secret' => $this->fields['secret'],
            'NOT' => [
                'id' => $this->fields['id']
            ],
        ]);

        $already_use = [];
        foreach ($data as $webhook_value) {
            $webhook->getFromDB($webhook_value['id']);
            $already_use[$webhook_value['id']] = [
                'link' => $webhook->getLink()
            ];
        }
        return $already_use;
    }

    public static function getSignature($data, $secret): string
    {
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Validate Challenge Response Answer
     *
     * @param string $url
     * @param string $body
     * @param string $secret
     *
     * @return boolean
     */
    public static function validateCRAChallenge($url, $body, $secret): array
    {
        global $CFG_GLPI;

        $challenge_response = [];
        $options = [
            'base_uri'        => $url,
            'connect_timeout' => 1,
        ];

        // add proxy string if configured in glpi
        if (!empty($CFG_GLPI["proxy_name"])) {
            $proxy_creds      = !empty($CFG_GLPI["proxy_user"])
                ? $CFG_GLPI["proxy_user"] . ":" . (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"]) . "@"
                : "";
            $proxy_string     = "http://{$proxy_creds}" . $CFG_GLPI['proxy_name'] . ":" . $CFG_GLPI['proxy_port'];
            $options['proxy'] = $proxy_string;
        }

        // init guzzle client with base options
        $httpClient = new Guzzle_Client($options);
        try {
            //prepare query / body
            $response = $httpClient->request('GET', '', [
                'query' => ['crc_token' => self::getSignature($body, $secret)],
            ]);

            if ($response->getStatusCode() == 200 && $response->getBody()) {
                $response_challenge = $response->getBody()->getContents();
                //check response
                if ($response_challenge == hash_hmac('sha256', self::getSignature($body, $secret), $secret)) {
                    $challenge_response = [
                        'status' => true,
                        'message' => __('Challenge–response authentication validated'),
                    ];
                } else {
                    $challenge_response = [
                        'status' => false,
                        'message' => __('Challenge–response authentication failed, the answer returned by target is different')
                    ];
                }
            } else {
                $challenge_response = [
                    'status' => false,
                    'message' => $response->getReasonPhrase()
                ];
            }
        } catch (\GuzzleHttp\Exception\ClientException | \GuzzleHttp\Exception\RequestException $e) {
            $challenge_response['status'] = false;
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $challenge_response['message'] = $response->getReasonPhrase();
                $challenge_response['status_code'] = $response->getStatusCode();
            } else {
                $challenge_response['message'] = $e->getMessage();
                $challenge_response['status_code'] = 503;
            }
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $challenge_response['status'] = false;
            $challenge_response['message'] = $e->getMessage();
        }

        return $challenge_response;
    }

    /**
     * Raise an event for an item to trigger related outgoing webhooks
     * @param string $event The event being raised
     * @param CommonDBTM $item The item the event is being raised for
     * @return void
     */
    public static function raise(string $event, CommonDBTM $item): void
    {
        global $DB;

        // Ignore raising if the table doesn't exist (happens during install/update)
        if (!$DB->tableExists(self::getTable())) {
            return;
        }

        $supported = self::getAPIItemtypeData();
        $supported_types = [];
        foreach ($supported as $categories) {
            foreach ($categories as $types) {
                $supported_types = array_merge($supported_types, array_keys($types));
            }
        }

        // Ignore raising if the item type is not supported
        if (!in_array($item->getType(), $supported_types, true)) {
            return;
        }

        $it = $DB->request([
            'SELECT' => ['id'],
            'FROM' => self::getTable(),
            'WHERE' => [
                'event' => $event,
                'itemtype' => $item->getType(),
                'is_active' => 1
            ]
        ]);
        if ($it->count() === 0) {
            return;
        }

        $webhook = new self();
        $path = $webhook->getApiPath($item);

        foreach ($it as $webhook_data) {
            $webhook->getFromDB($webhook_data['id']);
            $body = $webhook->getResultForPath($path, $event);
            // Check if the item matches the webhook filters
            if (!$webhook->itemMatchFilter($item)) {
                continue;
            }
            $timestamp = time();
            $headers = [
                'X-GLPI-signature' => self::getSignature($body . $timestamp, $webhook->fields['secret']),
                'X-GLPI-timestamp' => $timestamp
            ];
            $data = $webhook->fields;
            $data['items_id'] = $item->getID();
            $data['body'] = $body;
            $data['headers'] = json_encode($headers);
            self::send($data);
        }
    }

    /**
     * Send a webhook to the queue
     * @param array $data The data for the webhook
     * @return void
     */
    public static function send(array $data): void
    {
        $queued_webhook = new QueuedWebhook();
        $queued_webhook->add([
            'itemtype' => $data['itemtype'],
            'items_id' => $data['items_id'],
            'entities_id' => $data['entities_id'],
            'webhooks_id' => $data['id'],
            'url' => $data['url'],
            'body' => $data['body'],
            'event' => $data['event'],
            'headers' => $data['headers'],
        ]);
    }

    public function prepareInputForAdd($input)
    {
        return $this->handleInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->handleInput($input);
    }

    public function post_getFromDB()
    {
        if (!empty($this->fields['secret'])) {
            $this->fields['secret'] = (new GLPIKey())->decrypt($this->fields['secret']);
        }
    }

    public static function generateRandomSecret()
    {
        return Toolbox::getRandomString(40);
    }

    public function handleInput($input)
    {
        $valid_input = true;

        if (isset($input["itemtype"]) && !$input["itemtype"]) {
            Session::addMessageAfterRedirect(__('An item type is required'), false, ERROR);
            $valid_input = false;
        }

        if (isset($input["event"]) && !$input["event"]) {
            Session::addMessageAfterRedirect(__('An event is required'), false, ERROR);
            $valid_input = false;
        }

        if (!$valid_input) {
            return false;
        }

        if ((empty($input['secret']) && empty($this->fields['secret'])) || isset($input['_regenerate_secret'])) {
            //generate random secret if needed or if empty
            $input['secret'] = self::generateRandomSecret();
        }

        if (!empty($input['secret'])) {
            $input['secret'] = (new GLPIKey())->encrypt($input['secret']);
        }

        if (isset($input['use_cra_challenge'])) {
            $input['use_cra_challenge'] = (int)$input['use_cra_challenge'];
        }

        return $input;
    }

    public function post_getEmpty()
    {
        $this->fields['is_cra_challenge_valid']                        = 0;
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

            $mp_icon     = QueuedWebhook::getIcon();
            $mp_title    = QueuedWebhook::getTypeName();
            $queuedwebhook = "<i class='$mp_icon pointer' title='$mp_title'></i><span class='d-none d-xxl-block'>$mp_title</span>";
            $menu['links'][$queuedwebhook] = '/front/queuedwebhook.php';
        }
        if (count($menu)) {
            return $menu;
        }
        return false;
    }

    public static function getIcon()
    {
        return "ti ti-webhook";
    }

    public function getItemtypeToFilter(): string
    {
        return $this->fields['itemtype'];
    }

    public function getItemtypeField(): ?string
    {
        return 'itemtype';
    }

    public function getInfoTitle(): string
    {
        return __('Webhook target filter');
    }

    public function getInfoDescription(): string
    {
        return __("Webhooks will only be sent for items that match the defined filter.");
    }
}
