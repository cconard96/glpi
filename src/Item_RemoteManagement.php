<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2026 Teclib' and contributors.
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

class Item_RemoteManagement extends CommonDBChild
{
    public static $itemtype        = 'itemtype';
    public static $items_id        = 'items_id';
    public $dohistory              = true;

    public const TEAMVIEWER = 'teamviewer';
    public const LITEMANAGER = 'litemanager';
    public const ANYDESK = 'anydesk';
    public const MESHCENTRAL = 'meshcentral';
    public const SUPREMO = 'supremo';
    public const RUSTDESK = 'rustdesk';


    public static function getTypeName($nb = 0)
    {
        return __('Remote management');
    }

    /**
     * Get remote managements related to a given item
     *
     * @param CommonDBTM $item  Item instance
     * @param string     $sort  Field to sort on
     * @param string     $order Sort order
     *
     * @return DBmysqlIterator
     */
    public static function getFromItem(CommonDBTM $item, $sort = null, $order = null): DBmysqlIterator
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'      => self::getTable(),
            'WHERE'     => [
                'itemtype'     => $item->getType(),
                'items_id'     => $item->fields['id'],
                'is_deleted'   => 0,
            ],
        ]);
        return $iterator;
    }

    /**
     * Get remote management system link
     *
     * @return string
     */
    public function getRemoteLink(): string
    {
        $link = '<a href="%s" target="_blank">%s</a>';
        $id = htmlescape($this->fields['remoteid']);
        $href = null;
        switch ($this->fields['type']) {
            case self::TEAMVIEWER:
                $href = "https://start.teamviewer.com/$id";
                break;
            case self::ANYDESK:
                $href = "anydesk:$id";
                break;
            case self::SUPREMO:
                $href = "supremo:$id";
                break;
            case self::RUSTDESK:
                $href = "rustdesk://$id";
                break;
        }

        if ($href === null) {
            return $id;
        } else {
            return sprintf(
                $link,
                $href,
                $id
            );
        }
    }

    public function rawSearchOptions()
    {

        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __('Characteristics'),
        ];

        $tab[] = [
            'id'                 => '1',
            'table'              => $this->getTable(),
            'field'              => 'remoteid',
            'name'               => __('ID'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'type',
            'name'               => _n('Type', 'Types', 1),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        return $tab;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @return array
     */
    public static function rawSearchOptionsToAdd($itemtype)
    {
        $tab = [];

        $name = self::getTypeName(Session::getPluralNumber());
        $tab[] = [
            'id'                 => 'remote_management',
            'name'               => $name,
        ];

        $tab[] = [
            'id'                 => '1220',
            'table'              => self::getTable(),
            'field'              => 'remoteid',
            'name'               => __('ID'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        $tab[] = [
            'id'                 => '1221',
            'table'              => self::getTable(),
            'field'              => 'type',
            'name'               => _n('Type', 'Types', 1),
            'forcegroupby'       => true,
            'width'              => 1000,
            'datatype'           => 'dropdown',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        return $tab;
    }

    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }
}
