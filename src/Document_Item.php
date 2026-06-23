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

use Glpi\DBAL\QueryExpression;

/**
 * Document_Item Class
 *
 *  Relation between Documents and Items
 **/
class Document_Item extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = Document::class;
    public static $items_id_1    = 'documents_id';
    public static $take_entity_1 = true;

    public static $itemtype_2    = 'itemtype';
    public static $items_id_2    = 'items_id';
    public static $take_entity_2 = false;

    public static function getTypeName($nb = 0)
    {
        return _n('Document item', 'Document items', $nb);
    }

    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    public function canCreateItem(): bool
    {
        if ($this->fields['itemtype'] === Ticket::class) {
            $ticket = new Ticket();
            // Not item linked for closed tickets
            if (
                $ticket->getFromDB($this->fields['items_id'])
                && in_array($ticket->fields['status'], $ticket->getClosedStatusArray(), true)
            ) {
                return false;
            }
        }

        return parent::canCreateItem();
    }

    public function prepareInputForAdd($input)
    {
        if (empty($input['itemtype'])) {
            trigger_error('Item type is mandatory', E_USER_WARNING);
            return false;
        }

        if (!class_exists($input['itemtype'])) {
            trigger_error(sprintf('No class found for type %s', $input['itemtype']), E_USER_WARNING);
            return false;
        }

        if (
            (empty($input['items_id']))
            && ($input['itemtype'] !== 'Entity')
        ) {
            trigger_error('Item ID is mandatory', E_USER_WARNING);
            return false;
        }

        if (empty($input['documents_id'])) {
            trigger_error('Document ID is mandatory', E_USER_WARNING);
            return false;
        }

        // Do not insert circular link for document
        if (
            ($input['itemtype'] === Document::class)
            && ((int) $input['items_id'] === (int) $input['documents_id'])
        ) {
            trigger_error('Cannot link document to itself', E_USER_WARNING);
            return false;
        }

        // #1476 - Inject ID of the actual user to known who attach an already existing document
        // to another item
        if (!isset($input['users_id'])) {
            $input['users_id'] = Session::getLoginUserID();
        }

        /** FIXME: should not this be handled on CommonITILObject side? */
        if (is_subclass_of($input['itemtype'], 'CommonITILObject') && !isset($input['timeline_position'])) {
            $input['timeline_position'] = CommonITILObject::TIMELINE_LEFT;
            if (isset($input["users_id"])) {
                $input['timeline_position'] = $input['itemtype']::getTimelinePosition($input['items_id'], static::class, $input["users_id"]);
            }
        }

        // Avoid duplicate entry
        if ($this->alreadyExists($input)) {
            trigger_error('Duplicated document item relation', E_USER_WARNING);
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    /**
     * Check if relation already exists.
     *
     * @param array $input
     *
     * @return bool
     *
     * @since 9.5.0
     */
    public function alreadyExists(array $input): bool
    {
        $criteria = [
            'documents_id'      => $input['documents_id'],
            'itemtype'          => $input['itemtype'],
            'items_id'          => $input['items_id'],
            'timeline_position' => $input['timeline_position'] ?? 0,
        ];
        if (array_key_exists('timeline_position', $input) && !empty($input['timeline_position'])) {
            $criteria['timeline_position'] = $input['timeline_position'];
        }
        return countElementsInTable(static::getTable(), $criteria) > 0;
    }

    public function pre_deleteItem()
    {
        // fordocument mandatory
        if ($this->fields['itemtype'] === Ticket::class) {
            $ticket = new Ticket();
            $ticket->getFromDB($this->fields['items_id']);

            $tt = $ticket->getITILTemplateToUse(
                0,
                $ticket->fields['type'],
                $ticket->fields['itilcategories_id'],
                $ticket->fields['entities_id']
            );

            if (isset($tt->mandatory['_documents_id'])) {
                // refuse delete if only one document
                if (
                    countElementsInTable(
                        static::getTable(),
                        ['items_id' => $this->fields['items_id'],
                            'itemtype' => Ticket::class,
                        ]
                    ) === 1
                ) {
                    $message = sprintf(
                        __('Mandatory fields are not filled. Please correct: %s'),
                        Document::getTypeName(Session::getPluralNumber())
                    );
                    Session::addMessageAfterRedirect(htmlescape($message), false, ERROR);
                    return false;
                }
            }
        }
        return true;
    }

    public function post_addItem()
    {
        if ($this->fields['itemtype'] === Ticket::class) {
            $ticket = new Ticket();
            $input  = [
                'id'              => $this->fields['items_id'],
                'date_mod'        => $_SESSION["glpi_currenttime"],
                '_do_update_date_mod' => true,
            ];

            if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
                $input['_forcenotif'] = true;
            }
            if (isset($this->input['_disablenotif']) && $this->input['_disablenotif']) {
                $input['_disablenotif'] = true;
            }

            $ticket->update($input);
        }

        self::addToMergedTickets();

        parent::post_addItem();
    }

    private function addToMergedTickets(): void
    {
        $merged = Ticket::getMergedTickets($this->fields['items_id']);
        foreach ($merged as $ticket_id) {
            $input = $this->input;
            $input['items_id'] = $ticket_id;

            $document = new self();
            $document->add($input);
        }
    }

    public function post_purgeItem()
    {
        if ($this->fields['itemtype'] === Ticket::class) {
            $ticket = new Ticket();
            $input = [
                'id'              => $this->fields['items_id'],
                'date_mod'        => $_SESSION["glpi_currenttime"],
            ];

            if (!isset($this->input['_do_notif']) || $this->input['_do_notif']) {
                $input['_forcenotif'] = true;
            }

            //Clean ticket description if an image is in it
            $doc = new Document();
            $doc->getFromDB($this->fields['documents_id']);
            if (!empty($doc->fields['tag'])) {
                $ticket->getFromDB($this->fields['items_id']);
                $input['content'] = Toolbox::cleanTagOrImage(
                    $ticket->fields['content'],
                    [$doc->fields['tag']]
                );
            }

            $ticket->update($input);
        }
        parent::post_purgeItem();
    }

    public static function getRelationMassiveActionsPeerForSubForm(MassiveAction $ma)
    {
        return match ($ma->getAction()) {
            'add', 'remove' => 1,
            'add_item', 'remove_item' => 2,
            default => 0,
        };
    }

    public static function getRelationMassiveActionsSpecificities()
    {
        $specificities              = parent::getRelationMassiveActionsSpecificities();
        $specificities['itemtypes'] = Document::getItemtypesThatCanHave();

        // Define normalized action for add_item and remove_item
        $specificities['normalized']['add'][]          = 'add_item';
        $specificities['normalized']['remove'][]       = 'remove_item';

        // Set the labels for add_item and remove_item
        $specificities['button_labels']['add_item']    = $specificities['button_labels']['add'];
        $specificities['button_labels']['remove_item'] = $specificities['button_labels']['remove'];

        return $specificities;
    }

    /**
     * @param CommonDBTM|null $checkitem
     * @return array<string, string>
     */
    public function getSpecificMassiveActions($checkitem = null): array
    {
        $actions = parent::getSpecificMassiveActions($checkitem);

        // Replace the generic MassiveAction:add_transfer_list with our own processor so that
        // we can redirect the transfer to the underlying Document instead of the relation
        $generic_key = MassiveAction::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_transfer_list';
        if (isset($actions[$generic_key])) {
            $label = $actions[$generic_key];
            unset($actions[$generic_key]);
            $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'add_transfer_list'] = $label;
        }

        return $actions;
    }

    /**
     * @param MassiveAction $ma
     * @param CommonDBTM $item
     * @param array<int> $ids
     */
    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ): void {
        global $CFG_GLPI;

        if ($ma->getAction() !== 'add_transfer_list') {
            parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
            return;
        }

        if (!isset($_SESSION['glpitransfer_list'])) {
            $_SESSION['glpitransfer_list'] = [];
        }

        // Remove any residual Document_Item entries to avoid errors in transfer list.
        unset($_SESSION['glpitransfer_list'][Document_Item::class]);
        if (!isset($_SESSION['glpitransfer_list'][Document::class])) {
            $_SESSION['glpitransfer_list'][Document::class] = [];
        }

        foreach ($ids as $id) {
            if (!$item->getFromDB($id)) {
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                continue;
            }
            $doc_id = (int) $item->fields['documents_id'];
            $_SESSION['glpitransfer_list'][Document::class][$doc_id] = $doc_id;
            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
        }

        $ma->setRedirect($CFG_GLPI['root_doc'] . '/front/transfer.action.php');
    }

    /**
     * Get items for an itemtype
     *
     * @since 9.3.1
     *
     * @param int $items_id Object id to restrict on
     * @param class-string<CommonDBTM> $itemtype Type for items to retrieve
     * @param bool $noent    Flag to not compute enitty information (see Document_Item::getTypeItemsQueryParams)
     * @param array   $where    Inital WHERE clause. Defaults to []
     *
     * @return array Criteria to use in a request
     */
    protected static function getTypeItemsQueryParams($items_id, $itemtype, $noent = false, $where = [])
    {
        $commonwhere = ['OR'  => [
            static::getTable() . '.' . static::$items_id_1  => $items_id,
            [
                static::getTable() . '.itemtype'                => static::$itemtype_1,
                static::getTable() . '.' . static::$items_id_2  => $items_id,
            ],
        ],
        ];

        if ($itemtype !== KnowbaseItem::class) {
            $params = parent::getTypeItemsQueryParams($items_id, $itemtype, $noent, $commonwhere);
        } else {
            //KnowbaseItem case: no entity restriction, we'll manage it here
            $params = parent::getTypeItemsQueryParams($items_id, $itemtype, true, $commonwhere);
            $params['SELECT'][] = new QueryExpression('-1 AS entity');
            $kb_params = KnowbaseItem::getVisibilityCriteria();

            if (!Session::getLoginUserID()) {
                // Anonymous access
                $kb_params['WHERE'] = [
                    'glpi_entities_knowbaseitems.entities_id'    => 0,
                    'glpi_entities_knowbaseitems.is_recursive'   => 1,
                ];
            }

            $params = array_merge_recursive($params, $kb_params);
        }

        return $params;
    }

    /**
     * Get linked items list for specified item
     *
     * @since 9.3.1
     *
     * @param CommonDBTM $item  Item instance
     * @param bool    $noent Flag to not compute entity information (see Document_Item::getTypeItemsQueryParams)
     *
     * @return array
     */
    protected static function getListForItemParams(CommonDBTM $item, $noent = false)
    {
        if (Session::getLoginUserID()) {
            $params = parent::getListForItemParams($item);
        } else {
            $params = parent::getListForItemParams($item, true);
            // Anonymous access from FAQ
            $params['WHERE'][static::getTable() . '.entities_id'] = 0;
        }

        return $params;
    }

    /**
     * Get distinct item types query parameters
     *
     * @since 9.3.1
     *
     * @param int $items_id    Object id to restrict on
     * @param array   $extra_where Extra where clause
     *
     * @return array
     */
    public static function getDistinctTypesParams($items_id, $extra_where = [])
    {
        $commonwhere = ['OR'  => [
            static::getTable() . '.' . static::$items_id_1  => $items_id,
            [
                static::getTable() . '.itemtype'                => static::$itemtype_1,
                static::getTable() . '.' . static::$items_id_2  => $items_id,
            ],
        ],
        ];

        $params = parent::getDistinctTypesParams($items_id, $extra_where);
        $params['WHERE'] = $commonwhere;
        if (count($extra_where)) {
            $params['WHERE'][] = $extra_where;
        }

        return $params;
    }

    /**
     * Check if this item author is a support agent
     *
     * @return bool
     */
    public function isFromSupportAgent()
    {
        // If not a CommonITILObject
        if (!is_a($this->fields['itemtype'], CommonITILObject::class, true)) {
            return true;
        }

        // Get parent item
        $commonITILObject = new $this->fields['itemtype']();
        $commonITILObject->getFromDB($this->fields['items_id']);

        $actors = $commonITILObject->getITILActors();
        $user_id = $this->fields['users_id'];
        $roles = $actors[$user_id] ?? [];

        if (in_array(CommonITILActor::ASSIGN, $roles)) {
            // The author is assigned -> support agent
            return true;
        } elseif (
            in_array(CommonITILActor::OBSERVER, $roles)
            || in_array(CommonITILActor::REQUESTER, $roles)
        ) {
            // The author is an observer or a requester -> not a support agent
            return false;
        } else {
            // The author is not an actor of the ticket -> he was most likely a
            // support agent that is no longer assigned to the ticket
            return true;
        }
    }

    public static function getDocumentForItemRequest(CommonDBTM $item, array $order = []): array
    {
        $criteria = [
            'SELECT'    => [
                'glpi_documents_items.id AS assocID',
                'glpi_documents_items.date_creation AS assocdate',
                'glpi_entities.id AS entityID',
                'glpi_entities.completename AS entity',
                'glpi_documentcategories.completename AS headings',
                'glpi_documents.*',
            ],
            'FROM'      => 'glpi_documents_items',
            'LEFT JOIN' => [
                'glpi_documents'  => [
                    'ON' => [
                        'glpi_documents_items'  => 'documents_id',
                        'glpi_documents'        => 'id',
                    ],
                ],
                'glpi_entities'   => [
                    'ON' => [
                        'glpi_documents'  => 'entities_id',
                        'glpi_entities'   => 'id',
                    ],
                ],
                'glpi_documentcategories'  => [
                    'ON' => [
                        'glpi_documentcategories'  => 'id',
                        'glpi_documents'           => 'documentcategories_id',
                    ],
                ],
            ],
            'WHERE'     => [
                'glpi_documents_items.items_id'  => $item->getID(),
                'glpi_documents_items.itemtype'  => $item::class,
            ],
            'ORDERBY'   => $order,
        ];

        if (Session::getLoginUserID()) {
            $criteria['WHERE'] += getEntitiesRestrictCriteria('glpi_documents', '', '', true);
        } else {
            // Anonymous access from FAQ
            $criteria['WHERE']['glpi_documents.entities_id'] = 0;
        }

        return $criteria;
    }
}
