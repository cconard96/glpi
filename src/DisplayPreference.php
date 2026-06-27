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

use Glpi\Plugin\Hooks;
use Glpi\Search\SearchOption;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DisplayPreference extends CommonDBTM
{

    // From CommonDBTM
    public $auto_message_on_action  = false;

    public static $rightname = 'search_config';

    public const PERSONAL = 1024;
    public const GENERAL  = 2048;

    public static function canCrud(): bool
    {
        return Session::haveRightsOr(
            static::$rightname,
            [DisplayPreference::PERSONAL | DisplayPreference::GENERAL],
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function checkCrudItem(array $input): void
    {
        $item = new self();
        $item->fields['users_id'] = isset($input['users_id']) ? (int) $input['users_id'] : (int) Session::getLoginUserID();
        if (!$item->canCrudItem()) {
            throw new AccessDeniedHttpException();
        }
    }

    public function canCrudItem(): bool
    {
        if (Session::haveRight(static::$rightname, DisplayPreference::GENERAL)) {
            return true;
        }

        if (
            Session::haveRight(static::$rightname, DisplayPreference::PERSONAL)
            && $this->fields['users_id'] == Session::getLoginUserID()
        ) {
            return true;
        }

        return false;
    }

    
    public static function canCreate(): bool
    {
        return static::canCrud();
    }

    
    public static function canView(): bool
    {
        return static::canCrud();
    }

    
    public static function canUpdate(): bool
    {
        return static::canCrud();
    }

    
    public static function canPurge(): bool
    {
        return static::canCrud();
    }

    
    public function canCreateItem(): bool
    {
        return $this->canCrudItem();
    }

    
    public function canViewItem(): bool
    {
        return $this->canCrudItem();
    }

    
    public function canUpdateItem(): bool
    {
        return $this->canCrudItem();
    }

    
    public function canPurgeItem(): bool
    {
        return $this->canCrudItem();
    }

    public static function getTypeName($nb = 0)
    {
        return __('Search result display');
    }

    public function prepareInputForAdd($input)
    {
        global $DB;

        $result = $DB->request([
            'SELECT' => ['MAX' => 'rank AS maxrank'],
            'FROM'   => $this->getTable(),
            'WHERE'  => [
                'itemtype'  => $input['itemtype'],
                'users_id'  => $input['users_id'],
            ],
        ])->current();
        $input['rank'] = $result['maxrank'] + 1;
        return $input;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {

        switch ($ma->getAction()) {
            case 'delete_for_user':
                $input = $ma->getInput();
                if (isset($input['users_id'])) {
                    $user = new User();
                    $user->getFromDB($input['users_id']);
                    foreach ($ids as $id) {
                        if ($input['users_id'] == Session::getLoginUserID()) {
                            if (
                                $item->deleteByCriteria(['users_id' => $input['users_id'],
                                    'itemtype' => $id,
                                ])
                            ) {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                                $ma->addMessage($user->getErrorMessage(ERROR_ON_ACTION));
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                            $ma->addMessage($user->getErrorMessage(ERROR_RIGHT));
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }
                return;
            case 'reset_to_default':
                $input = $ma->getInput();
                if (!isset($input['users_id']) || $input['users_id'] <= 0) {
                    foreach ($ids as $itemtype) {
                        if (self::resetToDefaultOptions($itemtype)) {
                            $ma->itemDone($item->getType(), $itemtype, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $itemtype, MassiveAction::ACTION_KO);
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }
                return;
        }
        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }

    /**
     * Get display preference for a user for an itemtype
     *
     * @param string  $itemtype  itemtype
     * @param int $user_id   user ID
     *
     * @return array
     **/
    public static function getForTypeUser($itemtype, $user_id, string $interface = 'central')
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'itemtype'  => $itemtype,
                'interface' => $interface,
                'OR'        => [
                    ['users_id' => $user_id],
                    ['users_id' => 0],
                ],
            ],
            'ORDER'  => ['users_id', 'rank'],
        ]);

        $default_prefs = [];
        $user_prefs = [];

        foreach ($iterator as $data) {
            if ($data["users_id"] != 0) {
                $user_prefs[] = $data["num"];
            } else {
                $default_prefs[] = $data["num"];
            }
        }

        return count($user_prefs) ? $user_prefs : $default_prefs;
    }

    /**
     * Active personal config based on global one
     *
     * @param $input  array parameter (itemtype,users_id)
     *
     * @return void|false
     */
    public function activatePerso(array $input)
    {
        global $DB;

        if (!Session::haveRight(self::$rightname, self::PERSONAL)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'itemtype'  => $input['itemtype'],
                'users_id'  => 0,
            ],
        ]);

        if (count($iterator)) {
            foreach ($iterator as $data) {
                unset($data["id"]);
                $data["users_id"] = $input["users_id"];
                $this->fields     = $data;
                $this->addToDB();
            }
        } else {
            // No items in the global config
            $searchopt = SearchOption::getOptionsForItemtype($input["itemtype"]);
            if (count($searchopt) > 1) {
                $done = false;

                foreach ($searchopt as $key => $val) {
                    if (
                        is_int($key)
                        && is_array($val)
                        && ($key != 1)
                        && !$done
                    ) {
                        $data["users_id"] = $input["users_id"];
                        $data["itemtype"] = $input["itemtype"];
                        $data["rank"]     = 1;
                        $data["num"]      = $key;
                        $this->fields     = $data;
                        $this->addToDB();
                        $done = true;
                    }
                }
            }
        }
    }

    /**
     * @param string $itemtype
     * @param int $users_id
     * @param array $order
     * @param string $interface
     *
     * @return void
     */
    public function updateOrder(string $itemtype, int $users_id, array $order, string $interface = 'central')
    {
        global $DB;

        // Fixed columns are not kept in the DB, so we should remove them from the order
        $fixed_cols = SearchOption::getDefaultToView($itemtype);
        $official_order = array_diff($order, $fixed_cols);

        // Remove duplicates (in case of UI bug not preventing them)
        $official_order = array_unique($official_order);

        // Remove options not in the new order
        $DB->delete(
            self::getTable(),
            [
                'itemtype' => $itemtype,
                'users_id' => $users_id,
                'interface' => $interface,
                'NOT'      => [
                    'num' => $official_order,
                ],
            ]
        );

        foreach ($official_order as $rank => $num) {
            $DB->updateOrInsert(
                self::getTable(),
                [
                    'itemtype' => $itemtype,
                    'users_id' => $users_id,
                    'num'      => $num,
                    'rank'     => $rank,
                    'interface' => $interface,
                ],
                [
                    'itemtype'  => $itemtype,
                    'users_id'  => $users_id,
                    'num'       => $num,
                    'interface' => $interface,
                ]
            );
        }
    }

    /**
     * Order to move an item
     *
     * @param array  $input  array parameter (id,itemtype,users_id)
     * @param string $action       up or down
     *
     * @return void|false
     */
    public function orderItem(array $input, $action)
    {
        global $DB;

        // Get current item
        $criteria = [];
        if (isset($input['num'])) {
            $criteria = [
                'itemtype'  => $input['itemtype'],
                'users_id'  => $input['users_id'],
                'num'       => $input['num'],
            ];
        } else {
            $criteria['id'] = $input['id'];
        }
        $result = $DB->request([
            'SELECT' => ['id', 'rank'],
            'FROM'   => $this->getTable(),
            'WHERE'  => $criteria,
        ])->current();
        if (!$result) {
            return false;
        }
        $rank1  = $result['rank'];
        $input['id'] = $result['id'];

        // Get previous or next item
        $where = [];
        $order = 'rank ';
        switch ($action) {
            case "up":
                $where['rank'] = ['<', $rank1];
                $order .= 'DESC';
                break;

            case "down":
                $where['rank'] = ['>', $rank1];
                $order .= 'ASC';
                break;

            default:
                return false;
        }

        $result = $DB->request([
            'SELECT' => ['id', 'rank'],
            'FROM'   => $this->getTable(),
            'WHERE'  => [
                'itemtype'  => $input['itemtype'],
                'users_id'  => $input["users_id"],
            ] + $where,
            'ORDER'  => $order,
            'LIMIT'  => 1,
        ])->current();

        $rank2  = $result['rank'];
        $ID2    = $result['id'];

        // Update items
        $DB->update(
            $this->getTable(),
            ['rank' => $rank2],
            ['id' => $input['id']]
        );

        $DB->update(
            $this->getTable(),
            ['rank' => $rank1],
            ['id' => $ID2]
        );
    }

    /**
     * Return the group name of an element in the searchopt array
     *
     * The group names are located before the items that belong to it, and are the only string keys, every item's key are integer.
     *
     * We first get the keys of the array to be able to iterate trought his items, including the group names.
     * So we iterate trought the array key's in a reverse order,
     * starting from the position before the item which we want to get the group name.
     * The first key of string type we encouter, is our item's group name.
     *
     * @param array $search_options
     * @param int   $search_option_key
     *
     * @return string Return the name of the group or an empty string.
     *
     * @since 10.0.8
     */
    private function nameOfGroupForItemInSearchopt(array $search_options, int $search_option_key): string
    {
        $search_options_keys = array_keys($search_options);

        for ($key = array_search($search_option_key, $search_options_keys) - 1; $key > 0; $key--) {
            if (is_string($search_options_keys[$key])) {
                return ($search_options[$search_options_keys[$key]]['name'] ?? $key) . " - ";
            }
        }

        return "";
    }

    public function isNewItem()
    {
        // For tab management : force isNewItem
        return false;
    }

    public function getRights($interface = 'central')
    {
        //TRANS: short for : Search result user display
        $values[self::PERSONAL]  = ['short' => __('User display'),
            'long'  => __('Search result user display'),
        ];
        //TRANS: short for : Search result default display
        $values[self::GENERAL]  =  ['short' => __('Default display'),
            'long'  => __('Search result default display'),
        ];

        return $values;
    }

    /**
     * Change display preferences for an itemtype back to its defaults.
     * This only works with core itemtypes that have their default preferences specified in the empty_data.php script
     * or itemtypes from plugins that provide the defaults via the {@link Hooks::DEFAULT_DISPLAY_PREFS} hook.
     *
     * @param class-string<CommonDBTM> $itemtype
     */
    public static function resetToDefaultOptions(string $itemtype): bool
    {
        global $DB;
        $tables = require(GLPI_ROOT . '/install/empty_data.php');
        $prefs = array_filter($tables[self::getTable()], static fn($pref) => $pref['itemtype'] === $itemtype);
        if (!count($prefs)) {
            // plugin type or not supported
            $plugin_opts = Plugin::doHookFunction(Hooks::DEFAULT_DISPLAY_PREFS, [
                'itemtype' => $itemtype,
                'prefs'    => [],
            ]);
            if (count($plugin_opts['prefs'])) {
                $prefs = $plugin_opts['prefs'];
            } else {
                return false;
            }
        }

        $existing_opts = array_column($prefs, 'num');
        $DB->delete(
            self::getTable(),
            [
                'itemtype' => $itemtype,
                'users_id' => 0,
                'NOT'      => [
                    'num' => $existing_opts,
                ],
            ]
        );
        foreach ($prefs as $pref) {
            $result = $DB->updateOrInsert(
                self::getTable(),
                [
                    'itemtype'  => $itemtype,
                    'users_id'  => 0,
                    'num'       => $pref['num'],
                    'rank'      => $pref['rank'],
                    'interface' => $pref['interface'],
                ],
                [
                    'itemtype'  => $itemtype,
                    'users_id'  => 0,
                    'num'       => $pref['num'],
                    'interface' => $pref['interface'],
                ]
            );
            if (!$result) {
                return false;
            }
        }
        return true;
    }

    
    public function rawSearchOptions()
    {
        $search_options = parent::rawSearchOptions();

        $search_options[] = [
            'id'            => '2',
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'massiveaction' => false,
            'datatype'      => 'number',
        ];
        $search_options[] = [
            'id'            => '3',
            'table'         => $this->getTable(),
            'field'         => 'itemtype',
            'name'          => __('Itemtype'),
            'massiveaction' => false,
            'datatype'      => 'itemtypename',
        ];
        $search_options[] = [
            'id'            => '4',
            'table'         => $this->getTable(),
            'field'         => 'num',
            'name'          => __('Search option ID'),
            'massiveaction' => false,
            'datatype'      => 'number',
        ];

        return $search_options;
    }
}
