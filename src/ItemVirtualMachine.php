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

use Glpi\DBAL\QueryFunction;
use Glpi\Exception\TooManyResultsException;

use function Safe\preg_match;
use function Safe\preg_replace;

/**
 * ItemVirtualMachine Class
 *
 * Class to manage virtual machines
 **/
class ItemVirtualMachine extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';
    public $dohistory       = true;


    public static function getTypeName($nb = 0)
    {
        return __('Virtualization');
    }

    public static function getIcon()
    {
        return 'ti ti-box';
    }

    public function useDeletedToLockIfDynamic()
    {
        return false;
    }

    public function post_getEmpty()
    {

        $this->fields["vcpu"] = '0';
        $this->fields["ram"]  = '0';
    }

    /**
     * Get correct uuid SQL search for virtual machines
     *
     * @since 9.3.1
     *
     * @param string $uuid the uuid given
     *
     * @return string[] the restrict SQL clause which contains uuid, uuid with first block flipped,
     * uuid with 3 first block flipped
     */
    public static function getUUIDRestrictCriteria($uuid)
    {
        //More infos about uuid, please see wikipedia :
        //http://en.wikipedia.org/wiki/Universally_unique_identifier
        //Some uuid are not conform, so preprocessing is necessary
        //A good uuid looks like : 550e8400-e29b-41d4-a716-446655440000

        //Case one : for example some uuid are like that :
        //56 4d 77 d0 6b ef 3d da-4d 67 5c 80 a9 52 e2 c9
        $pattern  = "/([\w]{2})\ ([\w]{2})\ ([\w]{2})\ ([\w]{2})\ ";
        $pattern .= "([\w]{2})\ ([\w]{2})\ ([\w]{2})\ ([\w]{2})-";
        $pattern .= "([\w]{2})\ ([\w]{2})\ ([\w]{2})\ ([\w]{2})\ ";
        $pattern .= "([\w]{2})\ ([\w]{2})\ ([\w]{2})\ ([\w]{2})/";
        if (preg_match($pattern, $uuid)) {
            $uuid = preg_replace($pattern, "$1$2$3$4-$5$6-$7$8-$9$10-$11$12$13$14$15$16", $uuid);
        }

        //Case two : why this code ? Because some dmidecode < 2.10 is buggy.
        //On unix is flips first block of uuid and on Windows flips 3 first blocks...
        $in      = [strtolower($uuid)];
        $regexes = [
            "/([\w]{2})([\w]{2})([\w]{2})([\w]{2})(.*)/"                                        => "$4$3$2$1$5",
            "/([\w]{2})([\w]{2})([\w]{2})([\w]{2})-([\w]{2})([\w]{2})-([\w]{2})([\w]{2})(.*)/"  => "$4$3$2$1-$6$5-$8$7$9",
        ];
        foreach ($regexes as $pattern => $replace) {
            $reverse_uuid = preg_replace($pattern, $replace, $uuid);
            if ($reverse_uuid) {
                $in[] = strtolower($reverse_uuid);
            }
        }

        return $in;
    }


    /**
     * Find a virtual machine by uuid
     *
     * @param array<string,mixed> $fields  Array of virtual machine fields
     *
     * @return int|false ID of the asset that have this uuid or false otherwise
     **/
    public static function findVirtualMachine($fields = [])
    {
        global $DB;

        if (!isset($fields['uuid']) || empty($fields['uuid'])) {
            return false;
        }

        $itemtype = $fields['itemtype'];
        $item = getItemForItemtype($itemtype);
        if (!$item->isField('uuid')) {
            return false;
        }

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => $itemtype::getTable(),
            'WHERE'  => [
                'RAW' => [
                    (string) QueryFunction::lower('uuid')  => self::getUUIDRestrictCriteria($fields['uuid']),
                ],
            ],
        ]);

        //Virtual machine found, return ID
        if (count($iterator) == 1) {
            $result = $iterator->current();
            return $result['id'];
        } elseif (count($iterator) > 1) {
            throw new TooManyResultsException(
                sprintf(
                    '`%1$s::findVirtualMachine()` expects to get one result, %2$s found in query "%3$s".',
                    static::class,
                    count($iterator),
                    $iterator->getSql()
                )
            );
        }

        return false;
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
            'field'              => 'name',
            'name'               => __('Name'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'uuid',
            'name'               => __('UUID'),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'ram',
            'name'               => _n('Memory', 'Memories', 1),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => $this->getTable(),
            'field'              => 'vcpu',
            'name'               => __('processor number'),
            'datatype'           => 'string',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => Computer::getTable(),
            'field'              => 'uuid',
            'name'               => __('Computer UUID'),
            'datatype'           => 'string',
            'linkfield'          => 'items_id',
            'massiveaction'      => false,
            'forcegroupby'       => true,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => ItemVirtualMachine::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'item_itemtype',
                        'specific_itemtype'  => 'Computer',
                    ],
                ],
            ],
        ];

        return $tab;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return array<array<string,mixed>>
     */
    public static function rawSearchOptionsToAdd($itemtype)
    {
        $tab = [];

        $name = _n('Virtual machine', 'Virtual machines', Session::getPluralNumber());
        $tab[] = [
            'id'                 => 'virtualmachine',
            'name'               => $name,
        ];

        $tab[] = [
            'id'                 => '160',
            'table'              => self::getTable(),
            'field'              => 'name',
            'name'               => __('Name'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        $tab[] = [
            'id'                 => '161',
            'table'              => 'glpi_virtualmachinestates',
            'field'              => 'name',
            'name'               => _n('State', 'States', 1),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => self::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '162',
            'table'              => 'glpi_virtualmachinesystems',
            'field'              => 'name',
            'name'               => VirtualMachineSystem::getTypeName(1),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'datatype'           => 'dropdown',
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => self::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '163',
            'table'              => 'glpi_virtualmachinetypes',
            'field'              => 'name',
            'name'               => VirtualMachineType::getTypeName(1),
            'datatype'           => 'dropdown',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => self::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'itemtype_item',
                    ],
                ],
            ],
        ];

        $tab[] = [
            'id'                 => '164',
            'table'              => self::getTable(),
            'field'              => 'vcpu',
            'name'               => __('processor number'),
            'datatype'           => 'number',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        $tab[] = [
            'id'                 => '165',
            'table'              => self::getTable(),
            'field'              => 'ram',
            'name'               => _n('Memory', 'Memories', 1),
            'datatype'           => 'string',
            'unit'               => 'auto',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        $tab[] = [
            'id'                 => '166',
            'table'              => self::getTable(),
            'field'              => 'uuid',
            'name'               => __('UUID'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        $tab[] = [
            'id'                 => '179',
            'table'              => self::getTable(),
            'field'              => 'comment',
            'name'               => __('Virtual machine Comment'),
            'forcegroupby'       => true,
            'datatype'           => 'string',
            'massiveaction'      => false,
            'joinparams'         => [
                'jointype'           => 'itemtype_item',
            ],
        ];

        return $tab;
    }
}
