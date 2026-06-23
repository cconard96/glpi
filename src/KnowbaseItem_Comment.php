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

/**
 * Class KnowbaseItem_Comment
 * @since 9.2.0
 * @todo Extend CommonDBChild
 */
class KnowbaseItem_Comment extends CommonDBTM
{
    public static function getTypeName($nb = 0)
    {
        return _n('Comment', 'Comments', $nb);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(KnowbaseItem::$rightname, KnowbaseItem::COMMENTS);
    }

    public static function canView(): bool
    {
        return Session::haveRight(KnowbaseItem::$rightname, KnowbaseItem::COMMENTS);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(KnowbaseItem::$rightname, KnowbaseItem::COMMENTS);
    }

    public static function canDelete(): bool
    {
        return Session::haveRight(KnowbaseItem::$rightname, KnowbaseItem::COMMENTS);
    }

    public static function canPurge(): bool
    {
        return self::canDelete();
    }

    public function canCreateItem(): bool
    {
        return $this->canComment();
    }

    public function canViewItem(): bool
    {
        return $this->canComment();
    }

    public function canUpdateItem(): bool
    {
        if (!$this->canComment()) {
            return false;
        }
        // Users can edit their own comments and admins can edit all comments
        return Session::getLoginUserID() === $this->fields['users_id']
            || Session::haveRight(KnowbaseItem::$rightname, KnowbaseItem::KNOWBASEADMIN);
    }

    public function canDeleteItem(): bool
    {
        return $this->canUpdateItem();
    }

    public function canPurgeItem(): bool
    {
        return $this->canDeleteItem();
    }

    private function canComment(): bool
    {
        $kbitem = new KnowbaseItem();
        if (!$kbitem->getFromDB($this->fields['knowbaseitems_id'])) {
            return false;
        }
        return $kbitem->canComment();
    }

    /**
     * Gat all comments for specified KB entry
     *
     * @param int $kbitem_id KB entry ID
     * @param string  $lang      Requested language
     * @param int $parent    Parent ID (defaults to 0)
     * @param array   $user_data_cache
     *
     * @return array
     */
    public static function getCommentsForKbItem($kbitem_id, $lang, $parent = null, &$user_data_cache = [])
    {
        global $DB;

        $where = [
            'knowbaseitems_id'  => $kbitem_id,
            'language'          => $lang,
            'parent_comment_id' => $parent,
        ];

        $db_comments = $DB->request([
            'FROM' => 'glpi_knowbaseitems_comments',
            'WHERE' => $where,
            'ORDER' => 'id ASC',
        ]);

        $comments = [];
        foreach ($db_comments as $db_comment) {
            if (!isset($user_data_cache[$db_comment['users_id']])) {
                $user = new User();
                if ($user->getFromDB($db_comment['users_id'])) {
                    $user_data_cache[$db_comment['users_id']] = [
                        'avatar' => User::getThumbnailURLForPicture($user->fields['picture']),
                        'link'   => $user->getLinkURL(),
                        'initials' => $user->getUserInitials(),
                        'initials_bg_color' => $user->getUserInitialsBgColor(),
                    ];
                } else {
                    // User has been deleted, use default values
                    $user_data_cache[$db_comment['users_id']] = [
                        'avatar' => User::getThumbnailURLForPicture(''),
                        'link'   => '',
                        'initials' => '',
                        'initials_bg_color' => '#cccccc',
                    ];
                }
            }
            $db_comment['answers'] = self::getCommentsForKbItem($kbitem_id, $lang, $db_comment['id'], $user_data_cache);
            $db_comment['user_info'] = $user_data_cache[$db_comment['users_id']];
            $comments[] = $db_comment;
        }

        return $comments;
    }

    public function prepareInputForAdd($input)
    {
        if (!isset($input["users_id"])) {
            $input["users_id"] = 0;
            if ($uid = Session::getLoginUserID()) {
                $input["users_id"] = $uid;
            }
        }

        return $input;
    }
}
