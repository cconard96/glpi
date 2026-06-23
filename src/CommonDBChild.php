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
use function Safe\preg_match;

/**
 * Common DataBase Relation Table Manager Class
 */
abstract class CommonDBChild extends CommonDBConnexity
{
    // Mapping between DB fields
    // * definition
    /** @var class-string<CommonDBTM>|string $itemtype Class name or field name (start with itemtype) for link to Parent */
    public static $itemtype;
    /** @var string $items_id */
    public static $items_id; // Field name
    // * rights
    /** @var CommonDBConnexity::DONT_CHECK_ITEM_RIGHTS|CommonDBConnexity::HAVE_VIEW_RIGHT_ON_ITEM|CommonDBConnexity::HAVE_SAME_RIGHT_ON_ITEM */
    public static $checkParentRights  = self::HAVE_SAME_RIGHT_ON_ITEM;
    /** @var bool */
    public static $mustBeAttached     = true;
    // * log
    /** @var bool */
    public static $logs_for_parent    = true;
    /** @var Log::HISTORY_* */
    public static $log_history_add    = Log::HISTORY_ADD_SUBITEM;
    /** @var Log::HISTORY_* */
    public static $log_history_update = Log::HISTORY_UPDATE_SUBITEM;
    /** @var Log::HISTORY_* */
    public static $log_history_delete = Log::HISTORY_DELETE_SUBITEM;
    /** @var Log::HISTORY_* */
    public static $log_history_lock   = Log::HISTORY_LOCK_SUBITEM;
    /** @var Log::HISTORY_* */
    public static $log_history_unlock = Log::HISTORY_UNLOCK_SUBITEM;


    /**
     * Get request criteria to search for an item
     *
     * @since 9.4
     *
     * @param string  $itemtype Item type
     * @param int $items_id Item ID
     *
     * @return array|null
     **/
    public static function getSQLCriteriaToSearchForItem($itemtype, $items_id)
    {
        $table = static::getTable();

        $criteria = [
            'SELECT' => [
                static::getIndexName(),
                static::$items_id . ' AS items_id',
            ],
            'FROM'   => $table,
            'WHERE'  => [
                $table . '.' . static::$items_id  => $items_id,
            ],
        ];

        // Check item 1 type
        $request = false;
        if (preg_match('/^itemtype/', static::$itemtype)) {
            $criteria['SELECT'][] = static::$itemtype . ' AS itemtype';
            $criteria['WHERE'][$table . '.' . static::$itemtype] = $itemtype;
            $request = true;
        } else {
            $criteria['SELECT'][] = new QueryExpression("'" . static::$itemtype . "' AS itemtype");
            if (
                ($itemtype ==  static::$itemtype)
                || is_subclass_of($itemtype, static::$itemtype)
            ) {
                $request = true;
            }
        }
        if ($request === true) {
            return $criteria;
        }
        return null;
    }

    public static function canCreate(): bool
    {

        if ((static::$rightname) && (!Session::haveRight(static::$rightname, CREATE))) {
            return false;
        }
        return static::canChild('canUpdate');
    }

    public static function canView(): bool
    {
        if ((static::$rightname) && (!Session::haveRight(static::$rightname, READ))) {
            return false;
        }
        return static::canChild('canView');
    }

    public static function canUpdate(): bool
    {
        if ((static::$rightname) && (!Session::haveRight(static::$rightname, UPDATE))) {
            return false;
        }
        return static::canChild('canUpdate');
    }

    public static function canDelete(): bool
    {
        if ((static::$rightname) && (!Session::haveRight(static::$rightname, DELETE))) {
            return false;
        }
        return static::canChild('canUpdate');
    }

    public static function canPurge(): bool
    {
        if ((static::$rightname) && (!Session::haveRight(static::$rightname, PURGE))) {
            return false;
        }
        return static::canChild('canUpdate');
    }

    public function canCreateItem(): bool
    {
        return $this->canChildItem('canUpdateItem', 'canUpdate');
    }

    public function canViewItem(): bool
    {
        return $this->canChildItem('canViewItem', 'canView');
    }

    public function canUpdateItem(): bool
    {
        return $this->canChildItem('canUpdateItem', 'canUpdate');
    }

    public function canDeleteItem(): bool
    {
        return $this->canChildItem('canUpdateItem', 'canUpdate');
    }

    public function canPurgeItem(): bool
    {
        return $this->canChildItem('canUpdateItem', 'canUpdate');
    }


    /**
     * @since 0.84
     *
     * @param string $method
     * @return bool
     **/
    public static function canChild($method)
    {

        return static::canConnexity(
            $method,
            static::$checkParentRights,
            static::$itemtype,
            static::$items_id
        );
    }


    /**
     * @since 0.84
     *
     * @param string $methodItem
     * @param string $methodNotItem
     *
     * @return bool
     **/
    public function canChildItem($methodItem, $methodNotItem)
    {

        try {
            return $this->canConnexityItem(
                $methodItem,
                $methodNotItem,
                static::$checkParentRights,
                static::$itemtype,
                static::$items_id
            );
        } catch (CommonDBConnexityItemNotFound $e) {
            return !static::$mustBeAttached;
        }
    }


    /**
     * Get the item associated with the current object. Rely on CommonDBConnexity::getItemFromArray()
     *
     * @since 0.84
     *
     * @param bool $getFromDB   (true by default)
     * @param bool $getEmpty    (true by default)
     *
     * @return CommonDBTM|false object of the concerned item or false on error
     **/
    public function getItem($getFromDB = true, $getEmpty = true)
    {
        return $this->getConnexityItem(
            static::$itemtype,
            static::$items_id,
            $getFromDB,
            $getEmpty
        );
    }


    /**
     * Recursively display the items of this
     *
     * @param array  $recursiveItems    items of the current elements (see recursivelyGetItems())
     * @param string $elementToDisplay  what to display : 'Type', 'Name', 'Link'
     * @param bool $display  display html or return html
     * @return bool|string
     **/
    public static function displayRecursiveItems(array $recursiveItems, $elementToDisplay, bool $display = true)
    {

        if ($recursiveItems === []) {
            echo __s('Item not linked to an object');
            return false;
        }

        switch ($elementToDisplay) {
            case 'Type':
                $masterItem = $recursiveItems[count($recursiveItems) - 1];
                $out = htmlescape($masterItem->getTypeName(1));
                if ($display) {
                    echo $out;
                } else {
                    return $out;
                }
                break;

            case 'Name':
            case 'Link':
                $items_elements  = [];
                foreach ($recursiveItems as $item) {
                    if ($elementToDisplay == 'Name') {
                        $items_elements[] = htmlescape($item->getName());
                    } else {
                        $items_elements[] = $item->getLink();
                    }
                }

                $out = implode(' &lt; ', $items_elements);

                if ($display) {
                    echo $out;
                } else {
                    return $out;
                }
                break;
        }

        return true;
    }


    /**
     * Get all the items associated with the current object by recursive requests
     *
     * @since 0.84
     *
     * @return array
     **/
    public function recursivelyGetItems()
    {

        $item = $this->getItem();
        if ($item !== false) {
            if ($item instanceof CommonDBChild) {
                return array_merge([$item], $item->recursivelyGetItems());
            }
            return [$item];
        }
        return [];
    }


    /**
     * Get the ID of entity assigned to the object
     *
     * @return int ID of the entity
     **/
    public function getEntityID()
    {

        // Case of Duplicate Entity info to child
        if (parent::isEntityAssign()) {
            return parent::getEntityID();
        }

        $item = $this->getItem();
        if (($item !== false) && ($item->isEntityAssign())) {
            return $item->getEntityID();
        }
        return -1;
    }


    public function isEntityAssign()
    {

        // Case of Duplicate Entity info to child
        if (parent::isEntityAssign()) {
            return true;
        }

        $item = $this->getItem(false);

        if ($item !== false) {
            return $item->isEntityAssign();
        }

        return false;
    }


    /**
     * Is the object may be recursive
     *
     * @return bool
     **/
    public function maybeRecursive()
    {

        // Case of Duplicate Entity info to child
        if (parent::maybeRecursive()) {
            return true;
        }

        $item = $this->getItem(false);

        if ($item !== false) {
            return $item->maybeRecursive();
        }

        return false;
    }


    /**
     * Is the object recursive
     *
     * @return bool
     **/
    public function isRecursive()
    {

        // Case of Duplicate Entity info to child
        if (parent::maybeRecursive()) {
            return parent::isRecursive();
        }

        $item = $this->getItem();

        if ($item !== false) {
            return $item->isRecursive();
        }

        return false;
    }


    public function addNeededInfoToInput($input)
    {

        // is entity missing and forwarding on ?
        if ($this->tryEntityForwarding() && !isset($input['entities_id'])) {
            // Merge both arrays to ensure all the fields are defined for the following checks
            $completeinput = array_merge($this->fields, $input);
            // Set the item to allow parent::prepareinputforadd to get the right item ...
            if (
                $itemToGetEntity = static::getItemFromArray(
                    static::$itemtype,
                    static::$items_id,
                    $completeinput
                )
            ) {
                if (
                    ($itemToGetEntity instanceof CommonDBTM)
                    && $itemToGetEntity->isEntityForwardTo(static::class)
                ) {
                    $input['entities_id']  = $itemToGetEntity->getEntityID();
                    $input['is_recursive'] = intval($itemToGetEntity->isRecursive());
                } else {
                    // No entity link : set default values
                    $input['entities_id']  = 0;
                    $input['is_recursive'] = 0;
                }
            }
        }
        return $input;
    }


    public function prepareInputForAdd($input)
    {

        if (!is_array($input)) {
            return false;
        }

        if (!$this->getItemFromArray(static::$itemtype, static::$items_id, $input)) {
            // The parent item is invalid.

            if (static::$mustBeAttached) {
                // A valid parent item is mandatory, so creation is blocked with an error message.
                $linked_itemtype = preg_match('/^itemtype/', static::$itemtype)
                    ? ($input[static::$itemtype] ?? null)
                    : static::$itemtype
                ;
                $linked_items_id = $input[static::$items_id] ?? null;

                Session::addMessageAfterRedirect(
                    htmlescape(sprintf(
                        __('Parent item %s #%s is invalid.'),
                        is_a($linked_itemtype, CommonDBTM::class, true) ? $linked_itemtype::getTypeName(1) : ($linked_itemtype ?? 'null'),
                        $linked_items_id ?? 'null'
                    )),
                    false,
                    ERROR
                );
                return false;
            } else {
                // A valid parent is not mandatory, so invalid input is cleaned.
                if (array_key_exists(static::$itemtype, $input) && preg_match('/^itemtype/', static::$itemtype)) {
                    $input[static::$itemtype] = ''; // `itemtype` fields are usually not nullable, a default value must be set
                }
                if (array_key_exists(static::$items_id, $input)) {
                    $input[static::$items_id] = 0; // foreign key fields may be not nullable, a default value must be set
                }
            }
        }

        return $this->addNeededInfoToInput($input);
    }


    public function prepareInputForUpdate($input)
    {

        if (!is_array($input)) {
            return false;
        }

        // True if item changed
        if (
            !$this->checkAttachedItemChangesAllowed($input, [static::$itemtype,
                static::$items_id,
            ])
        ) {
            // A message is already added by `self::checkAttachedItemChangesAllowed()`
            return false;
        }

        return parent::addNeededInfoToInput($input);
    }


    /**
     * Get the history name of item
     *
     * @param CommonDBTM $item the other item
     * @param string     $case : can be overwrite by object
     *    - 'add' when this CommonDBChild is added (to and item)
     *    - 'update item previous' transfert : this is removed from the old item
     *    - 'update item next' transfert : this is added to the new item
     *    - 'delete' when this CommonDBChild is remove (from an item)
     *
     * @return string the name of the entry for the database (ie. : correctly slashed)
     **/
    public function getHistoryNameForItem(CommonDBTM $item, $case)
    {

        return $this->getNameID(['forceid'    => true,
            'additional' => true,
        ]);
    }


    /**
     * Actions done after the ADD of the item in the database
     *
     * @return void
     **/
    public function post_addItem()
    {

        $item = $this->getItem();
        if ($item === false) {
            return;
        }

        if (
            $item->dohistory
            && !(isset($this->input['_no_history']) && $this->input['_no_history'])
            && static::$logs_for_parent
        ) {
            $changes = [
                '0',
                '',
                $this->getHistoryNameForItem($item, 'add'),
            ];
            Log::history(
                $item->getID(),
                $item->getType(),
                $changes,
                $this->getType(),
                static::$log_history_add
            );
        }

        parent::post_addItem();
    }


    /**
     * Actions done after the UPDATE of the item in the database
     *
     * @since 0.84
     *
     * @param int|bool $history store changes history ?
     *
     * @return void
     **/
    public function post_updateItem($history = true)
    {

        if (
            !((isset($this->input['_no_history']) && $this->input['_no_history']))
            && static::$logs_for_parent
        ) {
            $items_for_log = $this->getItemsForLog(static::$itemtype, static::$items_id);

            // Whatever case : we log the changes
            $oldvalues = $this->oldvalues;
            unset($oldvalues[static::$itemtype]);
            unset($oldvalues[static::$items_id]);
            $item      = $items_for_log['new'];
            if (
                ($item !== false)
                && $item->dohistory
            ) {
                foreach (array_keys($oldvalues) as $field) {
                    if (in_array($field, $this->getNonLoggedFields())) {
                        continue;
                    }
                    $changes = $this->getHistoryChangeWhenUpdateField($field);
                    if ((!is_array($changes)) || (count($changes) != 3)) {
                        continue;
                    }
                    Log::history(
                        $item->getID(),
                        $item->getType(),
                        $changes,
                        $this->getType(),
                        static::$log_history_update
                    );
                }
            }

            if (isset($items_for_log['previous'])) {
                // Have updated the connexity relation

                $prevItem = $items_for_log['previous'];
                $newItem  = $items_for_log['new'];

                if (
                    ($prevItem !== false)
                    && $prevItem->dohistory
                ) {
                    $changes[0] = '0';
                    $changes[1] = $this->getHistoryNameForItem($prevItem, 'update item previous');
                    $changes[2] = '';
                    Log::history(
                        $prevItem->getID(),
                        $prevItem->getType(),
                        $changes,
                        $this->getType(),
                        static::$log_history_delete
                    );
                }

                if (
                    ($newItem !== false)
                    && $newItem->dohistory
                ) {
                    $changes[0] = '0';
                    $changes[1] = '';
                    $changes[2] = $this->getHistoryNameForItem($newItem, 'update item next');
                    Log::history(
                        $newItem->getID(),
                        $newItem->getType(),
                        $changes,
                        $this->getType(),
                        static::$log_history_add
                    );
                }
            }
        }

        parent::post_updateItem($history);
    }

    /**
     * Actions done after the DELETE of the item in the database
     *
     * @return void
     **/
    public function post_deleteFromDB()
    {

        if (
            (isset($this->input['_no_history']) && $this->input['_no_history'])
            || !static::$logs_for_parent
        ) {
            return;
        }

        $item = $this->getItem();

        if (
            ($item !== false)
            && $item->dohistory
        ) {
            $changes = [
                '0',
            ];

            if (static::$log_history_delete == Log::HISTORY_LOG_SIMPLE_MESSAGE) {
                $changes[1] = '';
                $changes[2] = $this->getHistoryNameForItem($item, 'delete');
            } else {
                $changes[1] = $this->getHistoryNameForItem($item, 'delete');
                $changes[2] = '';
            }
            Log::history(
                $item->getID(),
                $item->getType(),
                $changes,
                $this->getType(),
                static::$log_history_delete
            );
        }
    }


    /**
     *  Actions done when item flag deleted is set to an item
     *
     * @since 0.84
     *
     * @return void
     **/
    public function cleanDBonMarkDeleted()
    {

        if (
            (isset($this->input['_no_history']) && $this->input['_no_history'])
            || !static::$logs_for_parent
        ) {
            return;
        }

        if (
            $this->useDeletedToLockIfDynamic()
            && $this->isDynamic()
        ) {
            $item = $this->getItem();

            if (
                ($item !== false)
                && $item->dohistory
            ) {
                $changes = [
                    '0',
                    $this->getHistoryNameForItem($item, 'lock'),
                    '',
                ];
                Log::history(
                    $item->getID(),
                    $item->getType(),
                    $changes,
                    $this->getType(),
                    static::$log_history_lock
                );
            }
        }
    }


    /**
     * Actions done after the restore of the item
     *
     * @since 0.84
     *
     * @return void
     **/

    public function post_restoreItem()
    {
        if (
            (isset($this->input['_no_history']) && $this->input['_no_history'])
            || !static::$logs_for_parent
        ) {
            return;
        }

        if (
            $this->useDeletedToLockIfDynamic()
            && $this->isDynamic()
        ) {
            $item = $this->getItem();

            if (
                ($item !== false)
                && $item->dohistory
            ) {
                $changes = [
                    '0',
                    '',
                    $this->getHistoryNameForItem($item, 'unlock'),
                ];
                Log::history(
                    $item->getID(),
                    $item->getType(),
                    $changes,
                    $this->getType(),
                    static::$log_history_unlock
                );
            }
        }
    }

    /**
     * Affect a CommonDBChild to a given item. By default, unaffect it
     *
     * @param int    $id        the id of the CommonDBChild to affect
     * @param int    $items_id  the id of the new item (default 0)
     * @param class-string<CommonDBTM>|'' $itemtype  the type of the new item (default '')
     *
     * @return bool : true on success
     **/
    public function affectChild($id, $items_id = 0, $itemtype = '')
    {

        $input = [static::getIndexName() => $id,
            static::$items_id      => $items_id,
        ];

        if (preg_match('/^itemtype/', static::$itemtype)) {
            $input[static::$itemtype] = $itemtype;
        }

        return $this->update($input);
    }

    final public static function getItemField($itemtype): string
    {
        if (is_subclass_of($itemtype, 'Rule') && !is_subclass_of($itemtype, 'LevelAgreementLevel')) {
            $itemtype = Rule::class;
        }

        if (getItemtypeForForeignKeyField(static::$items_id) == $itemtype) {
            return static::$items_id;
        }

        if (preg_match('/^itemtype/', static::$itemtype)) {
            return static::$items_id;
        }

        throw new RuntimeException('Cannot guess field for itemtype ' . $itemtype . ' on ' . static::class);
    }
}
