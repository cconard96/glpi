<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
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

use Glpi\Features\AssignableItemInterface;
use Glpi\Plugin\Hooks;
use Glpi\Search\SearchEngine;
use Glpi\Search\SearchOption;
use Glpi\Toolbox\URL;

use function Safe\json_encode;

/**
 * @since 9.5.0
 * @todo This should use standard GLPI right management. Currently blocking API access.
 */
class Impact extends CommonGLPI
{
    // Constants used to express the direction or "flow" of a graph
    // These constants can also be used to express if an edge is reachable
    // when exploring the graph forward, backward or both (0b11)
    public const DIRECTION_FORWARD    = 0b01;
    public const DIRECTION_BACKWARD   = 0b10;

    // Default colors used for the edges of the graph according to their flow
    /** @var string The default edge color. Used for edges which are not accessible from the starting point of the graph. */
    public const DEFAULT_COLOR            = 'black';
    /** @var string The color used for edges going forward from the starting point of the graph */
    public const IMPACT_COLOR             = '#ff3418';
    /** @var string The color used for edges going backward from the starting point of the graph */
    public const DEPENDS_COLOR            = '#1c76ff';
    /** @var string The color used for edges going both forward and backward from the starting point of the graph */
    public const IMPACT_AND_DEPENDS_COLOR = '#ca29ff';

    public const NODE_ID_DELIMITER = "::";
    public const EDGE_ID_DELIMITER = "->";

    // Consts for depth values
    public const DEFAULT_DEPTH = 5;
    public const MAX_DEPTH = 10;
    public const NO_DEPTH_LIMIT = 10000;

    // Config values
    public const CONF_ENABLED = 'impact_enabled_itemtypes';

    public static function getTypeName($nb = 0)
    {
        return __('Impact analysis');
    }

    /**
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-affiliate';
    }

    /**
     * Build the data used to represent the impact graph as a semi-flat list
     *
     * @param array      $graph        array containing the graph nodes and egdes
     * @param int        $direction    should the list be build for item that are
     *                                 impacted by $item or that impact $item ?
     * @param CommonDBTM $item         starting point of the graph
     * @param int        $max_depth    max depth from context
     *
     * @return array
     */
    public static function buildListData(array $graph, int $direction, CommonDBTM $item, int $max_depth): array
    {
        $data = [];

        // Filter tree
        $sub_graph = self::filterGraph($graph, $direction);

        // Empty graph, no need to go further
        if (!count($sub_graph['nodes'])) {
            return $data;
        }

        // Evaluate path to each assets from the starting node
        $start_node_id = self::getNodeID($item);
        $start_node = $sub_graph['nodes'][$start_node_id];

        foreach ($sub_graph['nodes'] as $key => $vertex) {
            if ($key !== $start_node_id) {
                // Set path for target node using BFS
                $path = self::bfs(
                    $sub_graph,
                    $start_node,
                    $vertex,
                    $direction
                );

                // Add if path is not longer than the allowed value
                if (count($path) - 1 <= $max_depth) {
                    $sub_graph['nodes'][$key]['path'] = $path;
                }
            }
        }

        // Split the items by type
        foreach ($sub_graph['nodes'] as $node) {
            $details = explode(self::NODE_ID_DELIMITER, $node['id']);
            [$itemtype, $items_id] = $details;

            // Skip start node or empty path
            if ($node['id'] === $start_node_id || !isset($node['path'])) {
                continue;
            }

            // Init itemtype if empty
            if (!isset($data[$itemtype])) {
                $data[$itemtype] = [];
            }

            // Add to itemtype
            $itemtype_item = getItemForItemtype($itemtype);
            $itemtype_item->getFromDB($items_id);
            $data[$itemtype][] = [
                'stored' => $itemtype_item,
                'node'   => $node,
            ];
        }

        return $data;
    }

    /**
     * Return a subgraph matching the given direction
     *
     * @param array $graph      array containing the graph nodes and egdes
     * @param int   $direction  direction to match
     *
     * @return array
     */
    public static function filterGraph(array $graph, int $direction)
    {
        $new_graph = [
            'edges' => [],
            'nodes' => [],
        ];

        // For each edge in the graph
        foreach ($graph['edges'] as $edge) {
            // Filter on direction
            if ($edge['flag'] & $direction) {
                // Add the edge and its two connected nodes
                $source = $edge['source'];
                $target = $edge['target'];

                $new_graph['edges'][] = $edge;
                $new_graph['nodes'][$source] = $graph['nodes'][$source];
                $new_graph['nodes'][$target] = $graph['nodes'][$target];
            }
        }

        return $new_graph;
    }

    /**
     * Evaluate the path from one node to another using BFS algorithm
     *
     * @param array  $graph          array containing the graph nodes and egdes
     * @param array  $a              a node of the graph
     * @param array  $b              a node of the graph
     * @param int    $direction      direction used to travel the graph
     * @return array                 the path from $a to $b
     */
    public static function bfs(array $graph, array $a, array $b, int $direction): array
    {
        switch ($direction) {
            case self::DIRECTION_FORWARD:
                $start = $a;
                $target = $b;
                break;

            case self::DIRECTION_BACKWARD:
                $start = $b;
                $target = $a;
                break;

            default:
                throw new InvalidArgumentException("Invalid direction : $direction");
        }

        // Insert start node in the queue
        $queue = [];
        $queue[] = $start;
        // Label start as discovered
        $discovered = [$start['id'] => true];

        // For each other nodes
        while (count($queue) > 0) {
            $node = array_shift($queue);

            if ($node['id'] === $target['id']) {
                // target found, build path to node
                $path = [$target];

                while (isset($node['dfs_parent'])) {
                    $node = $node['dfs_parent'];
                    array_unshift($path, $node);
                }

                return $path;
            }

            foreach ($graph['edges'] as $edge) {
                // Skip edge if not connected to the current node
                if ($edge['source'] !== $node['id']) {
                    continue;
                }

                $nextNode = $graph['nodes'][$edge['target']];

                // Skip already discovered node
                if (isset($discovered[$nextNode['id']])) {
                    continue;
                }

                $nextNode['dfs_parent'] = $node;
                $discovered[$nextNode['id']] = true;

                $queue[] = $nextNode;
            }
        }

        // No path found
        //TODO Ask if this should throw an exception instead
        return [];
    }

    /**
     * Search asset by itemtype and name
     *
     * @param string  $itemtype   type
     * @param array   $used       ids to exlude from the search
     * @param string  $filter     filter on name
     * @param int     $page       page offset
     * @return array Result of the search
     */
    public static function searchAsset(string $itemtype, array $used, string $filter, int $page = 0): array
    {
        global $DB;

        // Check if this type is enabled in config
        if (!self::isEnabled($itemtype)) {
            throw new InvalidArgumentException(
                "itemtype ($itemtype) must be enabled in config"
            );
        }

        // Check class exist and is a child of CommonDBTM
        if (!is_subclass_of($itemtype, "CommonDBTM", true)) {
            throw new InvalidArgumentException(
                "itemtype ($itemtype) must be a valid child of CommonDBTM"
            );
        }

        // Return empty result if the user doesn't have READ rights
        if (!Session::haveRightsOr($itemtype::$rightname, [READ, READ_ASSIGNED, READ_OWNED])) {
            return [
                "items" => [],
                "total" => 0,
            ];
        }

        // This array can't be empty since we will use it in the NOT IN part of the reqeust
        if (!count($used)) {
            $used[] = -1;
        }

        // Search for items
        $table = $itemtype::getTable();
        $base_request = [
            'FROM'   => $table,
            'WHERE'  => [
                'NOT' => [
                    "$table.id" => $used,
                ],
            ] + $itemtype::getSystemSQLCriteria(),
        ];

        // Add friendly name search criteria
        $base_request['WHERE'] = array_merge(
            $base_request['WHERE'],
            $itemtype::getFriendlyNameSearchCriteria($filter)
        );

        if (is_subclass_of($itemtype, "ExtraVisibilityCriteria", true)) {
            $base_request = array_merge_recursive(
                $base_request,
                $itemtype::getVisibilityCriteria()
            );
        }

        $item = new $itemtype();

        // Add assignable criteria if the item is assignable
        if ($item instanceof AssignableItemInterface) {
            $visibility_criteria = $item->getAssignableVisiblityCriteria();
            if (count($visibility_criteria)) {
                $base_request['WHERE'][] = $visibility_criteria;
            }
        }

        if ($item->isEntityAssign()) {
            $base_request['WHERE'] = array_merge_recursive(
                $base_request['WHERE'],
                getEntitiesRestrictCriteria($itemtype::getTable())
            );
        }

        if ($item->mayBeDeleted()) {
            $base_request['WHERE']["$table.is_deleted"] = 0;
        }

        if ($item->mayBeTemplate()) {
            $base_request['WHERE']["$table.is_template"] = 0;
        }

        $select = [
            'SELECT' => ["$table.id", $itemtype::getFriendlyNameFields()],
        ];
        $limit = [
            'START' => $page * 20,
            'LIMIT' => "20",
        ];
        $count = [
            'COUNT' => "total",
        ];

        // Get items
        $rows = $DB->request($base_request + $select + $limit);

        // Get total
        $total = $DB->request($base_request + $count);

        return [
            "items" => iterator_to_array($rows, false),
            "total" => iterator_to_array($total, false)[0]['total'],
        ];
    }

    /**
     * Build the impact graph starting from a node
     *
     * @since 9.5
     *
     * @param CommonDBTM $item Current item
     *
     * @return array{nodes: array, edges: array} Array containing edges and nodes
     */
    public static function buildGraph(CommonDBTM $item): array
    {
        $nodes = [];
        $edges = [];

        // Explore the graph forward
        self::buildGraphFromNode($nodes, $edges, $item, self::DIRECTION_FORWARD);

        // Explore the graph backward
        self::buildGraphFromNode($nodes, $edges, $item, self::DIRECTION_BACKWARD);

        // Add current node to the graph if no impact relations were found
        if (count($nodes) === 0) {
            self::addNode($nodes, $item);
        }

        // Add special flag to start node
        $nodes[self::getNodeID($item)]['start'] = 1;

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Explore dependencies of the current item, subfunction of buildGraph()
     *
     * @since 9.5
     *
     * @param array      $edges          Edges of the graph
     * @param array      $nodes          Nodes of the graph
     * @param CommonDBTM $node           Current node
     * @param int        $direction      The direction in which the graph
     *                                   is being explored : DIRECTION_FORWARD
     *                                   or DIRECTION_BACKWARD
     * @param array      $explored_nodes List of nodes that have already been
     *                                   explored
     *
     * @throws InvalidArgumentException
     */
    private static function buildGraphFromNode(
        array &$nodes,
        array &$edges,
        CommonDBTM $node,
        int $direction,
        array $explored_nodes = []
    ): void {
        global $DB;

        // Source and target are determined by the direction in which we are
        // exploring the graph
        switch ($direction) {
            case self::DIRECTION_BACKWARD:
                $source = "source";
                $target = "impacted";
                break;
            case self::DIRECTION_FORWARD:
                $source = "impacted";
                $target = "source";
                break;
            default:
                throw new InvalidArgumentException(
                    "Invalid value for argument \$direction ($direction)."
                );
        }

        // Get relations of the current node
        $relations = $DB->request([
            'FROM'   => ImpactRelation::getTable(),
            'WHERE'  => [
                'itemtype_' . $target => $node::class,
                'items_id_' . $target => $node->fields['id'],
            ],
        ]);

        // Add current code to the graph if we found at least one impact relation
        if (count($relations)) {
            self::addNode($nodes, $node);
        }
        // Iterate on each relation found
        foreach ($relations as $related_item) {
            // Do not explore disabled itemtypes
            if (!self::isEnabled($related_item['itemtype_' . $source])) {
                continue;
            }

            // Add the related node
            if (!($related_node = getItemForItemtype($related_item['itemtype_' . $source]))) {
                continue;
            }
            $related_node->getFromDB($related_item['items_id_' . $source]);
            $label = $related_item['name'];
            self::addNode($nodes, $related_node);

            // Add or update the relation on the graph
            $edgeID = self::getEdgeID($node, $related_node, $direction);
            self::addEdge($edges, $edgeID, $node, $related_node, $direction, $label);

            // Keep exploring from this node unless we already went through it
            $related_node_id = self::getNodeID($related_node);
            if (!isset($explored_nodes[$related_node_id])) {
                $explored_nodes[$related_node_id] = true;
                self::buildGraphFromNode(
                    $nodes,
                    $edges,
                    $related_node,
                    $direction,
                    $explored_nodes
                );
            }
        }
    }

    /**
     * Get the icon to be displayed for the given item.
     *
     * @param string $itemtype
     * @param int|null $id
     *
     * @return string
     */
    public static function getImpactIcon(string $itemtype, ?int $id = null): string
    {
        global $CFG_GLPI;

        // First, try to get the icon from plugins
        $plugin_icon = Plugin::doHookFunction(
            Hooks::SET_ITEM_IMPACT_ICON,
            [
                'itemtype' => $itemtype,
                'items_id' => $id,
            ]
        );
        if (is_string($plugin_icon) && $plugin_icon !== '' && URL::isGLPIRelativeUrl($plugin_icon)) {
            if (!str_starts_with($plugin_icon, '/')) {
                // Fix paths declared without a leading `/`, as it was done before GLPI 11.0.
                Toolbox::deprecated(
                    sprintf('Impact icon path `%s` must now be prefixed by a `/`.', $plugin_icon)
                );
                $plugin_icon = '/' . $plugin_icon;
            }

            return $CFG_GLPI['root_doc'] . $plugin_icon;
        }

        // Second, try to get the icon from the configuration entry
        $icon = $CFG_GLPI['impact_asset_types'][$itemtype] ?? '';
        if (is_string($icon) && $icon !== '' && URL::isGLPIRelativeUrl($icon)) {
            if (!str_starts_with($icon, '/')) {
                // Fix paths declared without a leading `/`, as it was done before GLPI 11.0.
                Toolbox::deprecated(
                    sprintf('Impact icon path `%s` must now be prefixed by a `/`.', $icon)
                );
                $icon = '/' . $icon;
            }

            return $CFG_GLPI['root_doc'] . $icon;
        }

        // Fallback to the default icon
        return $CFG_GLPI['root_doc'] . '/pics/impact/default.png';
    }

    /**
     * Add a node to the node list if missing
     *
     * @param array      $nodes  Nodes of the graph
     * @param CommonDBTM $item   Node to add
     *
     * @since 9.5
     *
     * @return bool true if the node was missing, else false
     */
    private static function addNode(array &$nodes, CommonDBTM $item): bool
    {
        global $CFG_GLPI;

        // Check if the node already exist
        $key = self::getNodeID($item);
        if (isset($nodes[$key])) {
            return false;
        }

        // Define basic data of the new node
        $id_field = [];
        if (in_array($item::class, SearchEngine::getMetaItemtypeAvailable(Ticket::class), true)) {
            $search_options = SearchOption::getOptionsForItemtype($item::class);
            $id_field = array_filter(
                $search_options,
                static fn($option, $id) => is_numeric($id)
                    && $option['field'] === $item::getIndexName()
                    && $option['table'] === $item::getTable(),
                ARRAY_FILTER_USE_BOTH
            );
        }
        $new_node = [
            'id'          => $key,
            'label'       => $item->getFriendlyName(),
            'image'       => self::getImpactIcon($item::class, $item->getID()),
            'ITILObjects' => $item->getITILTickets(true),
            'id_option'   => $id_field !== [] ? array_keys($id_field)[0] : null,
        ];

        // Only set GOTO link if the user have READ rights
        if ($item::canView()) {
            $new_node['link'] = $item->getLinkURL();
        }

        // Set incident badge if needed
        $nb_incidents = count($new_node['ITILObjects']['incidents']);
        $nb_problems = count($new_node['ITILObjects']['problems']);
        if ($nb_incidents + $nb_problems > 0) {
            $priority = 0;
            foreach ($new_node['ITILObjects']['incidents'] as $incident) {
                if ($priority < $incident['priority']) {
                    $priority = $incident['priority'];
                }
            }
            foreach ($new_node['ITILObjects']['problems'] as $problem) {
                if ($priority < $problem['priority']) {
                    $priority = $problem['priority'];
                }
            }

            if ($nb_problems && !$nb_incidents) {
                // If at least one problems and zero incidents, link to problems search
                $target = Problem::getSearchURL() . "?is_deleted=0&as_map=0&search=Search&itemtype=Problem";
            } else {
                // Link to tickets search
                $target = Ticket::getSearchURL() . "?is_deleted=0&as_map=0&search=Search&itemtype=Ticket";
            }

            $user = new User();
            $user->getFromDB(Session::getLoginUserID());
            $user->computePreferences();
            $new_node['badge'] = [
                'color'  => $user->fields["priority_$priority"],
                'count'  => $nb_incidents + $nb_problems,
                'target' => $target,
            ];
        }

        // Alter the label if we found some linked ITILObjects
        $itil_tickets_count = $new_node['ITILObjects']['count'];
        if ($itil_tickets_count > 0) {
            $new_node['label'] .= " ($itil_tickets_count)";
            $new_node['hasITILObjects'] = 1;
        }

        // Load or create a new ImpactItem object
        $impact_item = ImpactItem::findForItem($item);

        // Load node position and parent
        $new_node['impactitem_id'] = $impact_item->fields['id'];
        $new_node['parent']        = $impact_item->fields['parent_id'];

        // If the node has a parent, add it to the node list aswell
        if (!empty($new_node['parent'])) {
            $compound = new ImpactCompound();
            if (!isset($nodes[$new_node['parent']]) && $compound->getFromDB($new_node['parent'])) {
                $nodes[$new_node['parent']] = [
                    'id'    => $compound->fields['id'],
                    'label' => $compound->fields['name'],
                    'color' => $compound->fields['color'],
                ];
            }
        }

        // Insert the node
        $nodes[$key] = $new_node;
        return true;
    }

    /**
     * Add an edge to the edge list if missing, else update it's direction
     *
     * @param array      $edges      Edges of the graph
     * @param string     $key        ID of the new edge
     * @param CommonDBTM $itemA      One of the node connected to this edge
     * @param CommonDBTM $itemB      The other node connected to this edge
     * @param int        $direction  Direction of the edge : A to B or B to A ?
     *
     * @since 9.5
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function addEdge(array &$edges, string $key, CommonDBTM $itemA, CommonDBTM $itemB, int $direction, string $label): void
    {
        // Just update the flag if the edge already exist
        if (isset($edges[$key])) {
            $edges[$key]['flag'] |= $direction;
            return;
        }

        // Assign 'from' and 'to' according to the direction
        switch ($direction) {
            case self::DIRECTION_FORWARD:
                $from = self::getNodeID($itemA);
                $to = self::getNodeID($itemB);
                break;
            case self::DIRECTION_BACKWARD:
                $from = self::getNodeID($itemB);
                $to = self::getNodeID($itemA);
                break;
            default:
                throw new InvalidArgumentException(
                    "Invalid value for argument \$direction ($direction)."
                );
        }

        // Add the new edge
        $edges[$key] = [
            'id'     => $key,
            'source' => $from,
            'target' => $to,
            'flag'   => $direction,
            'label' => $label,
        ];
    }

    /**
     * Get saved graph params for the current item
     *
     * @param CommonDBTM $item
     *
     * @return string $item
     */
    public static function prepareParams(CommonDBTM $item): string
    {
        $impact_item = ImpactItem::findForItem($item);

        $params = array_intersect_key($impact_item->fields, [
            'parent_id'         => 1,
            'impactcontexts_id' => 1,
            'is_slave'          => 1,
        ]);

        // Load context if exist
        if ($params['impactcontexts_id']) {
            $impact_context = ImpactContext::findForImpactItem($impact_item);

            if ($impact_context) {
                $params += array_intersect_key(
                    $impact_context->fields,
                    [
                        'positions'                => 1,
                        'zoom'                     => 1,
                        'pan_x'                    => 1,
                        'pan_y'                    => 1,
                        'impact_color'             => 1,
                        'depends_color'            => 1,
                        'impact_and_depends_color' => 1,
                        'show_depends'             => 1,
                        'show_impact'              => 1,
                        'max_depth'                => 1,
                    ]
                );
            }
        }

        return json_encode($params);
    }

    /**
     * Convert the php array reprensenting the graph into the format required by
     * the Cytoscape library
     *
     * @param array{nodes: array, edges: array} $graph
     *
     * @return string json data
     */
    public static function makeDataForCytoscape(array $graph): string
    {
        $data = [];

        foreach ($graph['nodes'] as $node) {
            $data[] = [
                'group'    => 'nodes',
                'data'     => $node,
            ];
        }

        foreach ($graph['edges'] as $edge) {
            $data[] = [
                'group' => 'edges',
                'data'  => $edge,
                'classes'  => 'top-center',
            ];
        }

        return json_encode($data);
    }

    /**
     * Check that a given asset exist in the DB
     *
     * @param string $itemtype Class of the asset
     * @param string $items_id id of the asset
     * @return bool
     */
    public static function assetExist(string $itemtype, string $items_id): bool
    {
        try {
            // Check this asset type is enabled
            if (!self::isEnabled($itemtype)) {
                return false;
            }

            // Try to create an object matching the given item type
            $reflection_class = new ReflectionClass($itemtype);
            if (!$reflection_class->isInstantiable()) {
                return false;
            }

            // Look for a matching asset in the DB
            $asset = getItemForItemtype($itemtype);
            return $asset->getFromDB($items_id) !== false;
        } catch (ReflectionException $e) {
            // Class does not exist
            return false;
        }
    }

    /**
     * Create an ID for a node (itemtype::items_id)
     *
     * @param CommonDBTM  $item Name of the node
     *
     * @return string
     */
    public static function getNodeID(CommonDBTM $item): string
    {
        return $item::class . self::NODE_ID_DELIMITER . ((int) $item->fields['id']);
    }

    /**
     * Create an ID for an edge (NodeID->NodeID)
     *
     * @param CommonDBTM  $itemA     First node of the edge
     * @param CommonDBTM  $itemB     Second node of the edge
     * @param int         $direction Direction of the edge : A to B or B to A ?
     *
     * @return string|null
     *
     * @throws InvalidArgumentException
     */
    public static function getEdgeID(CommonDBTM $itemA, CommonDBTM $itemB, int $direction): ?string
    {
        return match ($direction) {
            self::DIRECTION_FORWARD => self::getNodeID($itemA) . self::EDGE_ID_DELIMITER . self::getNodeID($itemB),
            self::DIRECTION_BACKWARD => self::getNodeID($itemB) . self::EDGE_ID_DELIMITER . self::getNodeID($itemA),
            default => throw new InvalidArgumentException(
                "Invalid value for argument \$direction ($direction)."
            ),
        };
    }

    /**
     * Clean impact records for a given item that has been purged form the db
     *
     * @param CommonDBTM $item The item being purged
     */
    public static function clean(CommonDBTM $item): void
    {
        global $DB;

        // Skip if not a valid impact type
        if (!self::isEnabled($item::getType())) {
            return;
        }

        // Remove each relation
        $DB->delete(ImpactRelation::getTable(), [
            'OR' => [
                [
                    'itemtype_source' => get_class($item),
                    'items_id_source' => $item->fields['id'],
                ],
                [
                    'itemtype_impacted' => get_class($item),
                    'items_id_impacted' => $item->fields['id'],
                ],
            ],
        ]);

        // Remove associated ImpactItem
        $impact_item = ImpactItem::findForItem($item, false);
        if (!$impact_item) {
            // Stop here if no impactitem, nothing more to delete
            return;
        }

        $impact_item->delete($impact_item->fields);

        // Remove impact context if defined and not a slave, update others
        // contexts if they are slave to us
        if (
            $impact_item->fields['impactcontexts_id'] !== 0
            && $impact_item->fields['is_slave'] !== 0
        ) {
            $DB->update(ImpactItem::getTable(), [
                'impactcontexts_id' => 0,
            ], [
                'impactcontexts_id' => $impact_item->fields['impactcontexts_id'],
            ]);

            $DB->delete(ImpactContext::getTable(), [
                'id' => $impact_item->fields['impactcontexts_id'],
            ]);
        }

        // Delete group if less than two children remaining
        if ($impact_item->fields['parent_id'] !== 0) {
            $count = countElementsInTable(ImpactItem::getTable(), [
                'parent_id' => $impact_item->fields['parent_id'],
            ]);

            if ($count < 2) {
                $DB->update(ImpactItem::getTable(), [
                    'parent_id' => 0,
                ], [
                    'parent_id' => $impact_item->fields['parent_id'],
                ]);

                $DB->delete(ImpactCompound::getTable(), [
                    'id' => $impact_item->fields['parent_id'],
                ]);
            }
        }
    }

    /**
     * Check if the given itemtype is enabled in impact config
     *
     * @param string $itemtype
     * @return bool
     */
    public static function isEnabled(string $itemtype): bool
    {
        return in_array($itemtype, self::getEnabledItemtypes(), true);
    }

    /**
     * Return enabled itemtypes
     *
     * @return array
     */
    public static function getEnabledItemtypes(): array
    {
        global $CFG_GLPI;

        // Get configured values
        $enabled_itemtypes = $CFG_GLPI[Impact::CONF_ENABLED] ?? [];

        if (!count($enabled_itemtypes)) {
            return [];
        }

        // Remove any forbidden values
        return array_filter($enabled_itemtypes, static function ($itemtype) {
            global $CFG_GLPI;

            return array_key_exists($itemtype, $CFG_GLPI['impact_asset_types']);
        });
    }

    /**
     * Return default itemtypes
     *
     * @return array
     */
    public static function getDefaultItemtypes(): array
    {
        global $CFG_GLPI;

        $values = $CFG_GLPI["default_impact_asset_types"];
        return array_keys($values);
    }
}
