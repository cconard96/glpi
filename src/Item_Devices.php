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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\Features\State;
use Glpi\Features\StateInterface;

/**
 * @since 0.84
 */


/**
 * Relation between item and devices
 * We completely relies on CommonDBConnexity to manage the can* and the history and the deletion ...
 **/
class Item_Devices extends CommonDBRelation implements StateInterface
{
    use State;

    public static $itemtype_1            = 'itemtype';
    public static $items_id_1            = 'items_id';
    public static $mustBeAttached_1      = false;
    public static $take_entity_1         = false;
    // static public $checkItem_1_Rights    = self::DONT_CHECK_ITEM_RIGHTS;

    protected static $notable            = true;

    public static $logs_for_item_2       = false;
    public static $take_entity_2         = true;

    public static $log_history_1_add     = Log::HISTORY_ADD_DEVICE;
    public static $log_history_1_update  = Log::HISTORY_UPDATE_DEVICE;
    public static $log_history_1_delete  = Log::HISTORY_DELETE_DEVICE;
    public static $log_history_1_lock    = Log::HISTORY_LOCK_DEVICE;
    public static $log_history_1_unlock  = Log::HISTORY_UNLOCK_DEVICE;

    // This var is defined by CommonDBRelation ...
    public $no_form_page                 = false;

    public $dohistory = true;

    protected static $forward_entity_to  = ['Infocom'];

    public static $undisclosedFields      = [];

    public static $mustBeAttached_2 = false; // Mandatory to display creation form

    public static $rightname = 'device';

    public function getCloneRelations(): array
    {
        $relations = parent::getCloneRelations();

        $relations[] = Contract_Item::class;

        return $relations;
    }

    protected function computeFriendlyName()
    {
        $itemtype = static::$itemtype_2;
        $item = false;
        if (!empty($this->fields[static::$itemtype_1])) {
            $item  = getItemForItemtype($this->fields[static::$itemtype_1]);
        }

        if ($item !== false && $item->getFromDB($this->fields[static::$items_id_1])) {
            $name = sprintf(__('%1$s of item "%2$s"'), $itemtype::getTypeName(1), $item->getName());
        } else {
            $name = $itemtype::getTypeName(1);
        }

        return $name;
    }

    public static function getTypeName($nb = 0)
    {
        $device_type = static::getDeviceType();
        $device_typename = $device_type::getTypeName(1);
        return sprintf(
            _n('%s item', '%s items', $nb),
            $device_typename
        );
    }


    /**
     * Get type name for device (used in Log)
     *
     * @param int $nb Count
     *
     * @return string
     */
    public static function getDeviceTypeName($nb = 0)
    {
        $device_type = static::getDeviceType();
        //TRANS: %s is the type of the component
        return sprintf(__('Item - %s link'), $device_type::getTypeName($nb));
    }


    public function getForbiddenStandardMassiveAction()
    {

        $forbidden = parent::getForbiddenStandardMassiveAction();

        if (
            (count(static::getSpecificities()) == 0)
            && !Infocom::canApplyOn($this)
        ) {
            $forbidden[] = 'update';
        }

        return $forbidden;
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
            'field'              => 'id',
            'name'               => __('ID'),
            'datatype'           => 'itemlink',
            'massiveaction'      => false,
        ];

        $deviceType = static::getDeviceType();
        $tab[] = [
            'id'                 => '4',
            'table'              => getTableForItemType($deviceType),
            'field'              => 'designation',
            'name'               => $deviceType::getTypeName(1),
            'datatype'           => 'itemlink',
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => $this->getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                    ],
                ],
            ],
        ];

        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
            'id'                 => '5',
            'table'              => $this->getTable(),
            'field'              => 'items_id',
            'name'               => _n('Associated element', 'Associated elements', Session::getPluralNumber()),
            'datatype'           => 'specific',
            'comments'           => true,
            'nosort'             => true,
            'additionalfields'   => ['itemtype'],
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'itemtype',
            'name'               => _n('Associated item type', 'Associated item types', Session::getPluralNumber()),
            'datatype'           => 'itemtypename',
            'itemtype_list'      => 'itemdevices_types',
            'nosort'             => true,
        ];

        foreach (static::getSpecificities() as $field => $attributs) {
            if (isForeignKeyField($field)) {
                $table = getTableNameForForeignKeyField($field);
                $linked_itemtype = getItemTypeForTable($table);
                $field = $linked_itemtype::getNameField();
            } else {
                $table = $this->getTable();
            }
            if (array_key_exists('field', $attributs)) {
                $field = $attributs['field'];
            }

            if (
                !array_key_exists('datatype', $attributs)
                && $table === static::getTable()
                && $field === static::getNameField()
            ) {
                // if the specific field corresponds to the "name" field of the item,
                // set its datatype to itemlink to ensure a link to the item is present in default search columns
                $attributs['datatype'] = 'itemlink';
            }

            $newtab = [
                'id'                 => $attributs['id'],
                'table'              => $table,
                'field'              => $field,
                'name'               => $attributs['long name'],
                'massiveaction'      => $attributs['massiveaction'] ?? true,
            ];

            if (isset($attributs['datatype'])) {
                $newtab['datatype'] = $attributs['datatype'];
            }
            if (isset($attributs['joinparams'])) {
                $newtab['joinparams'] = $attributs['joinparams'];
            }
            if (isset($attributs['joinparams'])) {
                $newtab['joinparams'] = $attributs['joinparams'];
            }
            if (isset($attributs['forcegroupby'])) {
                $newtab['forcegroupby'] = $attributs['forcegroupby'];
            }
            if (isset($attributs['nosearch'])) {
                $newtab['nosearch'] = $attributs['nosearch'];
            }
            if (isset($attributs['nodisplay'])) {
                $newtab['nodisplay'] = $attributs['nodisplay'];
            }
            $tab[] = $newtab;
        }

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'datatype'           => 'dropdown',
        ];

        if ($this->isField('comment')) {
            $tab[] = [
                'id'                 => '7',
                'table'              => $this->getTable(),
                'field'              => 'comment',
                'name'               => _n('Comment', 'Comments', Session::getPluralNumber()),
                'datatype'           => 'text',
            ];
        }

        return $tab;
    }

    /**
     * @param class-string<CommonDBTM> $itemtype
     * @return array
     */
    public static function rawSearchOptionsToAdd($itemtype)
    {
        global $CFG_GLPI;

        $options = [];
        $device_types = $CFG_GLPI['device_types'];

        $main_joinparams = [
            'jointype'           => 'itemtype_item',
            'specific_itemtype'  => $itemtype,
        ];

        foreach ($device_types as $device_type) {
            $cfg_key = 'item' . strtolower($device_type) . '_types';
            if ($plug = isPluginItemType($device_type)) {
                // For plugins, 'item' prefix should be placed between plugin name and class name.
                // Nota: 'self::itemAffinity()' and 'self::getConcernedItems()' also expect this order in config key.
                $cfg_key = strtolower('plugin' . $plug['plugin'] . 'item' . $plug['class']) . '_types';
            }

            if (isset($CFG_GLPI[$cfg_key])) {
                $itemtypes = $CFG_GLPI[$cfg_key];
                if ($itemtypes == '*' || in_array($itemtype, $itemtypes)) {
                    if (method_exists($device_type, 'rawSearchOptionsToAdd')) {
                        /** @var class-string $device_type */
                        $options = array_merge(
                            $options,
                            $device_type::rawSearchOptionsToAdd(
                                $itemtype,
                                $main_joinparams
                            )
                        );
                    }
                }
            }
        }

        if (count($options)) {
            //add title if there are options
            $options = array_merge(
                [[
                    'id'                => 'devices',
                    'name'              => _n('Component', 'Components', Session::getPluralNumber()),
                ],
                ],
                $options
            );
        }

        return $options;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'items_id':
                if (isset($values['itemtype'])) {
                    $table = getTableForItemType($values['itemtype']);
                    $value = (int) $values[$field];
                    $name = Dropdown::getDropdownName($table, $value);
                    if (isset($options['comments']) && $options['comments']) {
                        $comments = Dropdown::getDropdownComments($table, $value);
                        return sprintf(
                            __s('%1$s %2$s'),
                            htmlescape($name),
                            Html::showToolTip($comments, ['display' => false])
                        );
                    }
                    return htmlescape($name);
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
        $options['display'] = false;
        switch ($field) {
            case 'items_id':
                if (isset($values['itemtype']) && !empty($values['itemtype'])) {
                    $options['name']  = $name;
                    $options['value'] = $values[$field];
                    return Dropdown::show($values['itemtype'], $options);
                }
                break;
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }


    /**
     * Get the specificities of the given device. For instance, the
     * serial number, the size of the memory, the frequency of the CPUs ...
     *
     * @param string $specif specificity to display
     *
     * Should be overloaded by Item_Device*
     *
     * @return array Array of the specificities: index is the field name and the values are the attributes of the specificity
     **/
    public static function getSpecificities($specif = '')
    {

        return match ($specif) {
            'serial' => [
                'long name' => __('Serial number'),
                'short name' => __('Serial number'),
                'size' => 20,
                'id' => 10,
            ],
            'busID' => [
                'long name' => __('Position of the device on its bus'),
                'short name' => __('bus ID'),
                'size' => 10,
                'id' => 11,
            ],
            'otherserial' => [
                'long name' => __('Inventory number'),
                'short name' => __('Inventory number'),
                'size' => 20,
                'id' => 12,
            ],
            'locations_id' => [
                'long name' => Location::getTypeName(1),
                'short name' => Location::getTypeName(1),
                'field' => 'completename',
                'size' => 20,
                'id' => 13,
                'datatype' => 'dropdown',
            ],
            'states_id' => [
                'long name' => __('Status'),
                'short name' => __('Status'),
                'size' => 20,
                'id' => 14,
                'datatype' => 'dropdown',
            ],
            default => [],
        };
    }


    /**
     * Get the items on which this Item_Device can be attached. For instance, a computer can have
     * any kind of device. Conversely, a soundcard does not concern a NetworkEquipment
     * A configuration entry is automatically checked in $CFG_GLPI (must be the name of
     * the class, lowercase, without "_" with extra "_types" at the end; for example
     * "itemdevicesoundcard_types").
     *
     * Alternatively, it could be overloaded from subclasses
     *
     * @since 0.85
     *
     * @return array of the itemtype that can have this Item_Device
     **/
    public static function itemAffinity()
    {
        global $CFG_GLPI;

        $conf_param = str_replace('_', '', strtolower(static::class)) . '_types';

        return $CFG_GLPI[$conf_param] ?? $CFG_GLPI["itemdevices_itemaffinity"];
    }


    /**
     * Get all the kind of devices available inside the system.
     *
     * @return array
     * @phpstan-return class-string<Item_Devices>[]
     **/
    public static function getDeviceTypes()
    {
        $types = [];

        foreach (CommonDevice::getDeviceTypes() as $device_class) {
            /** @var CommonDevice $device_class */
            $types[] = $device_class::getItem_DeviceType();
        }

        return $types;
    }


    /**
     * Get the Item_Device* a given item type can have
     *
     * @param string $itemtype the type of the item that we want to know its devices
     *
     * @since 0.85
     *
     * @return class-string<Item_Devices>[]
     **/
    public static function getItemAffinities($itemtype)
    {
        global $CFG_GLPI;

        if (!in_array($itemtype, $CFG_GLPI['itemdevices_types'], true)) {
            // Itemtype does not support devices.
            return [];
        }

        $result = [];

        foreach (CommonDevice::getDeviceTypes() as $device_class) {
            $item_device_class = $device_class::getItem_DeviceType();
            $item_device_affinities = $item_device_class::itemAffinity();

            if (
                in_array($itemtype, $item_device_affinities, true)
                || in_array('*', $item_device_affinities, true)
            ) {
                $result[] = $item_device_class;
            }
        }

        return $result;
    }


    /**
     * Get all kind of items that can be used by Item_Device*
     *
     * @since 0.85
     *
     * @return array of the available items
     **/
    public static function getConcernedItems()
    {
        global $CFG_GLPI;

        $itemtypes = $CFG_GLPI['itemdevices_types'];

        $conf_param = str_replace('_', '', strtolower(static::class)) . '_types';
        if (isset($CFG_GLPI[$conf_param]) && !in_array('*', $CFG_GLPI[$conf_param])) {
            $itemtypes = array_intersect($itemtypes, $CFG_GLPI[$conf_param]);
        }

        return $itemtypes;
    }


    /**
     * Get associated device to the current item_device
     *
     * @since 0.85
     *
     * @return class-string<CommonDevice>
     **/
    public static function getDeviceType()
    {
        $devicetype = static::class;

        if ($plug = isPluginItemType($devicetype)) {
            return 'Plugin' . $plug['plugin'] . str_replace('Item_', '', $plug['class']);
        }

        $class = str_replace('Item_', '', $devicetype);

        if (!is_a($class, CommonDevice::class, true)) {
            throw new RuntimeException(
                sprintf('`%s` is not a valid `%s` class.', $class, CommonDevice::class)
            );
        }

        return $class;
    }

    /**
     * get items associated to the given one (defined by $itemtype and $items_id)
     *
     * @param string  $itemtype          the type of the item we want the resulting items to be associated to
     * @param string  $items_id          the name of the item we want the resulting items to be associated to
     *
     * @return array the items associated to the given one (empty if none was found)
     **/
    public static function getItemsAssociatedTo($itemtype, $items_id)
    {
        global $DB;

        $res = [];
        foreach (self::getItemAffinities($itemtype) as $link_type) {
            $table = $link_type::getTable();
            $iterator = $DB->request([
                'SELECT' => 'id',
                'FROM'   => $table,
                'WHERE'  => [
                    'itemtype'  => $itemtype,
                    'items_id'  => $items_id,
                ],
            ]);

            foreach ($iterator as $row) {
                $input = $row;
                $item = getItemForItemtype($link_type);
                $item->getFromDB($input['id']);
                $res[] = $item;
            }
        }
        return $res;
    }

    /**
     * @return string
     */
    public static function getDeviceForeignKey()
    {
        return getForeignKeyFieldForTable(getTableForItemType(static::getDeviceType()));
    }

    /**
     * @param CommonDBTM $item
     * @param class-string<CommonDBTM>|null $peer_type
     * @return array
     */
    public function getTableGroupCriteria($item, $peer_type = null)
    {
        $is_device = ($item instanceof CommonDevice);
        $ctable = $this->getTable();
        $criteria = [
            'SELECT' => "$ctable.*",
            'FROM'   => $ctable,
        ];
        if ($is_device) {
            $fk = 'items_id';

            // Entity restrict
            $criteria['WHERE'] = [
                static::getDeviceForeignKey()  => $item->getID(),
                "$ctable.itemtype"            => $peer_type,
                "$ctable.is_deleted"          => 0,
            ];
            $criteria['ORDERBY'] = [
                "$ctable.itemtype",
                "$ctable.$fk",
            ];
            if (!empty($peer_type)) {
                $criteria['LEFT JOIN'] = [
                    getTableForItemType($peer_type) => [
                        'ON' => [
                            $ctable                          => 'items_id',
                            getTableForItemType($peer_type)  => 'id', [
                                'AND' => [
                                    "$ctable.itemtype"   => $peer_type,
                                ],
                            ],
                        ],
                    ],
                ];
                $criteria['WHERE'] += getEntitiesRestrictCriteria(getTableForItemType($peer_type));
            } else {
                //peer_type not defined is related to Item_DeviceXXX without associated assets
                //so restrict entity criteria to current Item_DeviceXXX
                $criteria['WHERE'] += getEntitiesRestrictCriteria($ctable);
            }
        } else {
            $fk = static::getDeviceForeignKey();

            $criteria['WHERE'] = [
                'itemtype'     => $item->getType(),
                'items_id'     => $item->getID(),
                'is_deleted'   => 0,
            ];
            $criteria['ORDERBY'] = $fk;
        }

        return $criteria;
    }

    /**
     * @param positive-int $numberToAdd
     * @param class-string<CommonDBTM>|string $itemtype
     * @param int $items_id
     * @param int $devices_id
     * @param array $input Array to complete (permit to define values)
     * @return void
     **/
    public function addDevices($numberToAdd, $itemtype, $items_id, $devices_id, $input = [])
    {
        if ($numberToAdd == 0) {
            return;
        }

        $input['itemtype']                    = $itemtype;
        $input['items_id']                    = $items_id;
        $input[static::getDeviceForeignKey()] = $devices_id;

        $device_type = static::getDeviceType();
        $device      = getItemForItemtype($device_type);
        $device->getFromDB($devices_id);

        foreach (static::getSpecificities() as $field => $attributs) {
            if (isset($device->fields[$field . '_default'])) {
                $input[$field] = $device->fields[$field . '_default'];
            }
        }

        if ($this->can(-1, CREATE, $input)) {
            for ($i = 0; $i < $numberToAdd; $i++) {
                $this->add($input);
            }
        }
    }


    /**
     * Add one or several device(s) from front/item_devices.form.php.
     *
     * @param array $input Array of input: should be $_POST
     * @return void
     * @since 0.85
     **/
    public static function addDevicesFromPOST($input)
    {
        if (isset($input['devicetype']) && !$input['devicetype']) {
            Session::addMessageAfterRedirect(
                __s('Please select a device type'),
                false,
                ERROR
            );
            return;
        } elseif (isset($_POST['devices_id']) && !$_POST['devices_id']) {
            Session::addMessageAfterRedirect(
                __s('Please select a device'),
                false,
                ERROR
            );
            return;
        }

        if (isset($input['devicetype'])) {
            $devicetype = $input['devicetype'];
            $linktype   = $devicetype::getItem_DeviceType();
            $link = getItemForItemtype($linktype);
            if ($link instanceof Item_Devices) {
                if (
                    !isset($input[$linktype::getForeignKeyField()])
                    && (!isset($input['new_devices']) || !$input['new_devices'])
                ) {
                    Session::addMessageAfterRedirect(
                        __s('You must choose any unaffected device or ask to add new.'),
                        false,
                        ERROR
                    );
                    return;
                }

                if (
                    isset($input[$linktype::getForeignKeyField()])
                    && is_array($input[$linktype::getForeignKeyField()])
                    && count($input[$linktype::getForeignKeyField()])
                ) {
                    $update_input = ['itemtype' => $input['itemtype'],
                        'items_id' => $input['items_id'],
                    ];
                    foreach ($input[$linktype::getForeignKeyField()] as $id) {
                        $update_input['id'] = $id;
                        $link->update($update_input);
                    }
                }
                if (isset($input['new_devices'])) {
                    $link->addDevices(
                        $input['new_devices'],
                        $input['itemtype'],
                        $input['items_id'],
                        $input['devices_id']
                    );
                }
            }
        } else {
            if (!$item = getItemForItemtype($input['itemtype'])) {
                throw new NotFoundHttpException();
            }
            if ($item instanceof CommonDevice) {
                $link = getItemForItemtype($item->getItem_DeviceType());
                if ($link instanceof Item_Devices) {
                    $link->addDevices($input['number_devices_to_add'], '', 0, $input['items_id']);
                }
            }
        }
    }


    /**
     * @param array $input Array of input: should be $_POST
     * @return void
     **/
    public static function updateAll($input)
    {

        if (
            !isset($input['itemtype'])
            || !isset($input['items_id'])
        ) {
            throw new NotFoundHttpException();
        }

        $itemtype = $input['itemtype'];
        $items_id = $input['items_id'];
        if (!$item = getItemForItemtype($itemtype)) {
            throw new NotFoundHttpException();
        }
        $item->check($input['items_id'], UPDATE, $_POST);

        $is_device = ($item instanceof CommonDevice);
        $link_type = $is_device ? $itemtype::getItem_DeviceType() : '';

        $links   = [];
        // Update quantity or values
        $device_type = '';
        foreach ($input as $key => $val) {
            $data = explode("_", $key);
            if (!empty($data[0])) {
                $command = $data[0];
            } else {
                continue;
            }
            if (($command != 'quantity') && ($command != 'value')) {
                // items_id, itemtype, devicetype ...
                continue;
            }
            if (!$is_device) {
                if (empty($data[1])) {
                    continue;
                }
                $device_type = $data[1];
                if (in_array($device_type::getItem_DeviceType(), self::getItemAffinities($itemtype))) {
                    $link_type = $device_type::getItem_DeviceType();
                }
            }
            if (!empty($data[2])) {
                $links_id = $data[2];
            } else {
                continue;
            }
            if (!isset($links[$link_type])) {
                $links[$link_type] = ['add'    => [],
                    'update' => [],
                ];
            }

            switch ($command) {
                case 'quantity':
                    $links[$link_type]['add'][$links_id] = $val;
                    break;

                case 'value':
                    if (!isset($links[$link_type]['update'][$links_id])) {
                        $links[$link_type]['update'][$links_id] = [];
                    }
                    if (isset($data[3])) {
                        $links[$link_type]['update'][$links_id][$data[3]] = $val;
                    }
                    break;
            }
        }

        foreach ($links as $type => $commands) {
            $link = getItemForItemtype($type);
            if ($link instanceof Item_Devices) {
                foreach ($commands['add'] as $link_to_add => $number) {
                    $link->addDevices($number, $itemtype, $items_id, (int) $link_to_add);
                }
                foreach ($commands['update'] as $link_to_update => $input) {
                    $input['id'] = $link_to_update;
                    $link->update($input);
                }
                unset($link);
            }
        }
    }


    /**
     * @since 0.85
     *
     * @param positive-int $item_devices_id
     * @param positive-int $items_id
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return bool
     **/
    public static function affectItem_Device($item_devices_id, $items_id, $itemtype)
    {

        $link = new static();
        return $link->update(['id'       => $item_devices_id,
            'items_id' => $items_id,
            'itemtype' => $itemtype,
        ]);
    }


    /**
     * @param class-string<CommonDBTM> $itemtype
     * @param positive-int $items_id
     * @param bool $unaffect
     * @return void
     **/
    public static function cleanItemDeviceDBOnItemDelete($itemtype, $items_id, $unaffect)
    {
        global $DB;

        foreach (self::getItemAffinities($itemtype) as $link_type) {
            $link = getItemForItemtype($link_type);
            if ($link) {
                if ($unaffect) {
                    $DB->update(
                        $link->getTable(),
                        [
                            'items_id'  => 0,
                            'itemtype'  => '',
                        ],
                        [
                            'items_id'  => $items_id,
                            'itemtype'  => $itemtype,
                        ]
                    );
                } elseif (method_exists($link, 'cleanDBOnItemDelete')) {
                    $link->cleanDBOnItemDelete($itemtype, $items_id);
                }
            }
        }
    }


    public function getRights($interface = 'central')
    {

        $values = parent::getRights();
        return $values;
    }


    /**
     * @since 0.85
     *
     * @see CommonDBConnexity::getConnexityMassiveActionsSpecificities()
     **/
    public static function getConnexityMassiveActionsSpecificities()
    {

        $specificities              = parent::getConnexityMassiveActionsSpecificities();

        $specificities['reaffect']  = 1;
        $specificities['itemtypes'] = self::getConcernedItems();

        return $specificities;
    }


    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(Infocom::class, $ong, $options);
        $this->addStandardTab(Document_Item::class, $ong, $options);
        $this->addStandardTab(Lock::class, $ong, $options);
        $this->addStandardTab(Log::class, $ong, $options);
        $this->addStandardTab(Contract_Item::class, $ong, $options);

        return $ong;
    }

    public function prepareInputForAdd($input)
    {
        global $CFG_GLPI;

        if (!isset($input[static::$items_id_2]) || !$input[static::$items_id_2]) {
            Session::addMessageAfterRedirect(
                htmlescape(sprintf(
                    __('%1$s: %2$s'),
                    static::getTypeName(),
                    __('A device ID is mandatory')
                )),
                false,
                ERROR
            );
            return false;
        }

        $computer = static::getItemFromArray(static::$itemtype_1, static::$items_id_1, $input);

        if ($computer instanceof CommonDBTM) {
            if (
                Entity::getUsedConfig('is_location_autoupdate', $computer->getEntityID())
                && (!isset($input['locations_id'])
                || $computer->fields['locations_id'] != $input['locations_id'])
            ) {
                $input['locations_id'] = $computer->fields['locations_id'];
            }

            $state_autoupdate_mode = Entity::getUsedConfig('state_autoupdate_mode', $computer->getEntityID());
            if (
                $state_autoupdate_mode < 0
                && (!isset($input['states_id'])
                || $computer->fields['states_id'] != $input['states_id'])
            ) {
                $input['states_id'] = $computer->fields['states_id'];
            }

            if (
                $state_autoupdate_mode > 0
                && (!isset($input['states_id'])
                || $input['states_id'] != $state_autoupdate_mode)
            ) {
                $input['states_id'] = $state_autoupdate_mode;
            }
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        foreach (static::getSpecificities() as $field => $attributs) {
            if (!isset($attributs['right'])) {
                $canUpdate = true;
            } else {
                $canUpdate = (Session::haveRightsOr($attributs['right'], [UPDATE]));
            }
            if (isset($input[$field]) && !$canUpdate) {
                unset($input[$field]);
                Session::addMessageAfterRedirect(htmlescape(__('Update of ' . $attributs['short name'] . ' denied')));
            }
        }

        return $input;
    }

    public static function unsetUndisclosedFields(&$fields)
    {
        foreach (static::getSpecificities() as $key => $attributs) {
            if (isset($attributs['right'])) {
                if (!Session::haveRightsOr($attributs['right'], [READ])) {
                    unset($fields[$key]);
                }
            }
        }
    }

    public static function getSearchURL($full = true)
    {
        global $CFG_GLPI;

        $dir = ($full ? $CFG_GLPI['root_doc'] : '');
        $itemtype = static::class;
        $link = "$dir/front/item_device.php?itemtype=$itemtype";

        return $link;
    }


    public static function getIcon()
    {
        $device_class = static::$itemtype_2 ?? "CommonDevice";
        return $device_class::getIcon();
    }

    public function getImportCriteria(): array
    {
        return [];
    }
}
