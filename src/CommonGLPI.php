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

use Glpi\Debug\Profiler;
use Glpi\Plugin\Hooks;
use Glpi\Search\CriteriaFilter;
use Glpi\Search\FilterableInterface;
use Symfony\Component\HttpFoundation\Request;
use function Safe\parse_url;

/**
 * Common GLPI object
 *
 * @phpstan-type GlpiMenuOption array{
 *   title: string,
 *   page: string,
 *   icon?: string,
 *   links?: array{
 *     search: string,
 *     add?: string,
 *     template?: string,
 *   }
 * }
 *
 * @phpstan-type GlpiMenuEntry array{
 *   title: string,
 *   page: string,
 *   shortcut?: string,
 *   icon?: string,
 *   links?: array{
 *     search?: string,
 *     add?: string,
 *     template?: string,
 *     lists?: '',
 *     lists_itemtype?: class-string<CommonDBTM>
 *   },
 *   options?: array<string, GlpiMenuOption>
 * }
 */
class CommonGLPI implements CommonGLPIInterface
{
    /**
     * Rightname used to check rights to do actions on item.
     *
     * @var string
     */
    public static $rightname = '';

    /**
     * List of tabs to add (registered by `self::registerStandardTab()`).
     * Array structure looks like:
     *  [
     *      "Computer" => [ // item on which the tab will be added
     *          "PluginAwesomeItem" // item that will provide the tab
     *              => 100, // weight value used when sorting tabs
     *      ]
     *  ]
     *
     * @var array<class-string<CommonGLPI>, array<class-string<CommonGLPI>, int>>
     */
    private static $othertabs = [];

    public function __construct() {}

    /**
     * Return the localized name of the current item type.
     *
     * @param int   $nb Number of items
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return __('General');
    }

    /**
     * Return the type of the object, i.e. its class name.
     *
     * @return string
     *
     * @final
     */
    public static function getType()
    {
        return static::class;
    }

    /**
     * Check right on an item.
     *
     * @param int                  $ID    ID of the item (-1 if new item)
     * @param int                  $right Right to check : READ / UPDATE / DELETE / PURGE / CREATE / ...
     * @param ?array<string,mixed> $input array of input data (used for adding item)
     *
     * @return bool
     */
    public function can($ID, int $right, ?array &$input = null): bool
    {
        return match ($right) {
            READ => static::canView(),
            UPDATE => static::canUpdate(),
            DELETE => static::canDelete(),
            PURGE => static::canPurge(),
            CREATE => static::canCreate(),
            default => false,
        };
    }

    /**
     * Check the global "creation" right on the itemtype.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, CREATE);
        }
        return false;
    }

    /**
     * Check the global "view" right on the itemtype.
     *
     * @return bool
     */
    public static function canView(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, READ);
        }
        return false;
    }

    /**
     * Check the global "update" right on the itemtype.
     *
     * @return bool
     */
    public static function canUpdate(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, UPDATE);
        }
        return false;
    }

    /**
     * Check the global "delete" right on the itemtype.
     *
     * @return bool
     */
    public static function canDelete(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, DELETE);
        }
        return false;
    }

    /**
     * Check the global "purge" right on the itemtype.
     *
     * @return bool
     */
    public static function canPurge(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, PURGE);
        }
        return false;
    }

    /**
     * Register tab on an objet
     *
     * @since 0.83
     *
     * @param class-string<CommonGLPI>  $typeform  object class name to add tab on form
     * @param class-string<CommonGLPI>  $typetab   object class name which manage the tab
     * @param int                       $order     Weight value used when sorting tabs.
     *                                             Lower values will be displayed before higher values.
     *
     * @return void
     *
     * @final
     */
    public static function registerStandardTab($typeform, $typetab, int $order = 500)
    {
        if (isset(self::$othertabs[$typeform])) {
            self::$othertabs[$typeform][$typetab] = $order;
        } else {
            self::$othertabs[$typeform] = [$typetab => $order];
        }
    }

    /**
     * Get the array of Tab managed by other types.
     *
     * @since 0.83
     *
     * @param class-string<CommonGLPI>  $typeform   object class name on which we want to get managed tabs
     *
     * @return class-string<CommonGLPI>[]
     *
     * @final
     */
    public static function getOtherTabs($typeform)
    {
        if (isset(self::$othertabs[$typeform])) {
            $othertabs = self::$othertabs[$typeform];
            asort($othertabs);
            return array_keys($othertabs);
        }
        return [];
    }

    /**
     * Define tabs to display.
     *
     * @param array{withtemplate?: int} $options Options
     *     - withtemplate is a template view ?
     *
     * @return array<string, string|bool> Array where keys are tabs identifier (e.g. `Ticket$main`)
     *                                    and values are the HTML snippet corresponding to the tab name,
     *                                    or key is `no_all_tab` and value is a boolean.
     */
    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addImpactTab($ong, $options);

        if ($this instanceof FilterableInterface) {
            $this->addStandardTab(CriteriaFilter::class, $ong, $options);
        }

        return $ong;
    }

    /**
     * Add a standard tab.
     *
     * @param class-string<CommonGLPI>   $itemtype itemtype link to the tab
     * @param array<string, string|bool> $ong      defined tabs (see `defineTabs()` return value)
     * @param array{withtemplate?: int}  $options  options (for withtemplate)
     *
     * @return static
     *
     * @final
     */
    public function addStandardTab($itemtype, array &$ong, array $options)
    {
        $withtemplate = 0;
        if (isset($options['withtemplate'])) {
            $withtemplate = $options['withtemplate'];
        }

        switch ($itemtype) {
            default:
                if (
                    !is_integer($itemtype)
                    && ($obj = getItemForItemtype($itemtype))
                ) {
                    $titles = $obj->getTabNameForItem($this, $withtemplate);
                    if (!is_array($titles)) {
                        $titles = [1 => $titles];
                    }

                    foreach ($titles as $key => $val) {
                        if (!empty($val)) {
                            $ong[$itemtype . '$' . $key] = $val;
                        }
                    }
                }
                break;
        }
        return $this;
    }

    /**
     * Add the impact tab if enabled for this item type.
     *
     * @param array<string, string|bool> $ong        defined tabs (see `defineTabs()` return value)
     * @param array{withtemplate?: int}  $options    options (for withtemplate)
     *
     * @return static
     *
     * @final
     */
    public function addImpactTab(array &$ong, array $options)
    {
        // Check if impact analysis is enabled for this item type
        if (Impact::isEnabled(static::class)) {
            $this->addStandardTab(Impact::class, $ong, $options);
        }

        return $this;
    }

    /**
     * Add the default tab for form.
     *
     * @since 0.85
     *
     * @param array<string, string> $ong    defined tabs (see `defineTabs()` return value)
     *
     * @return static
     */
    public function addDefaultFormTab(array &$ong)
    {
        $icon = '';
        if (method_exists(static::class, 'getIcon')) {
            $icon = static::getIcon();
        }
        $icon = $icon ? "<i class='" . htmlescape($icon) . " me-2'></i>" : '';
        $ong[static::getType() . '$main'] = '<span>' . $icon . htmlescape(static::getTypeName(1)) . '</span>';
        return $this;
    }

    /**
     * Get additional menu specs.
     *
     * @since 0.85
     *
     * @return false|array<string, GlpiMenuEntry>  Additional menu specs, or false if no additional menu content.
     */
    public static function getAdditionalMenuContent()
    {
        return false;
    }

    /**
     * Get forbidden actions for menu : may be add / template.
     *
     * @since 0.85
     *
     * @return list<'add'|'template'>
     */
    public static function getForbiddenActionsForMenu()
    {
        return [];
    }

    /**
     * Get additional menu options.
     *
     * @since 0.85
     *
     * @return false|array<string, GlpiMenuOption>  Additional menu options, or false if no additional options.
     */
    public static function getAdditionalMenuOptions()
    {
        return false;
    }

    /**
     * Get additional menu links.
     *
     * @since 0.85
     *
     * @return false|array<string, string>  Additional menu links, or false if no additional links.
     **/
    public static function getAdditionalMenuLinks()
    {
        return false;
    }

    /**
     * Get tab name (or array of tabs names) for the given item.
     *
     * @since 0.83
     *
     * @param CommonGLPI $item          Item on which the tab need to be displayed
     * @param int    $withtemplate  is a template object ?
     *
     *  @return string|string[] The tab(s) name(s).
     *      Must be:
     *          a string if there is a single tab;
     *          an array of string if there are multiple tabs;
     *          an empty string if there is no tabs.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return '';
    }

    /**
     * Get the sector/item/option definition.
     *
     * @return array{0?: string, 1?: class-string, 2?: class-string}
     *      An array containing optionaly:
     *          the sector as first element;
     *          the itemtype as second element;
     *          the option (sub itemtype) as third element.
     */
    public static function getSectorizedDetails(): array
    {
        return [];
    }

    /**
     * Get the parameters to be used in the `Html::header()` method.
     *
     * @return array{0: string, 1: '', 2?: string, 3?: class-string, 4?: class-string}
     *      An array containing optionaly:
     *          the page title as first element;
     *          an unused string as second element;
     *          the sector as third element;
     *          the itemtype as fourth element;
     *          the option (sub itemtype) as fifth element.
     */
    public static function getHeaderParameters(): array
    {
        return [
            static::getTypeName(Session::getPluralNumber()),
            '',
            ...static::getSectorizedDetails(),
        ];
    }

    /**
     * Show the content of the tab having given index.
     *
     * @since 0.83
     *
     * @param CommonGLPI    $item           Item on which the tab need to be displayed
     * @param int           $tabnum         The tab index
     * @param int           $withtemplate   Is a template object ?
     *
     * @return bool
     *
     * @TODO In GLPI 12.0, do something with the return value that is currently not used.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        return false;
    }

    /**
     * @param class-string<CommonGLPI>|null $form_itemtype
     * @return string
     */
    private static function getTabIconClass(?string $form_itemtype = null): string
    {
        $default_icon = CommonDBTM::getIcon();
        $icon = $default_icon;
        $tab_itemtype = static::class;
        $itemtype = $tab_itemtype;
        $form_item = $form_itemtype === null ? null : getItemForItemtype($form_itemtype);

        if (is_subclass_of($tab_itemtype, CommonDBRelation::class) && $form_item instanceof CommonDBTM) {
            // Get opposite itemtype than this
            $new_itemtype = $tab_itemtype::getOppositeItemtype($form_item::class);
            if ($new_itemtype !== null) {
                $itemtype = $new_itemtype;
            }
        }
        if ($icon === $default_icon && !class_exists($itemtype)) {
            $itemtype = $tab_itemtype;
        }
        if ($icon === $default_icon && method_exists($itemtype, 'getIcon')) {
            $icon = $itemtype::getIcon();
        }
        return $icon;
    }

    /**
     * Create tab text entry.
     *
     * This should be called on the itemtype whose form is being displayed and not on the tab itemtype for the correct
     * icon to be displayed, unless you manually specify the icon.
     *
     * @param string                        $text           text to display
     * @param int                           $nb             number of items displayed
     * @param class-string<CommonGLPI>|null $form_itemtype  itemtype whose form is being displayed
     * @param string                        $icon           icon class
     * @param ?int                          $total_nb       total number of items
     *
     * @return string The tab HTML snippet (including icon and counter if applicable)
     *
     * @final
     */
    public static function createTabEntry($text, $nb = 0, ?string $form_itemtype = null, string $icon = '', ?int $total_nb = null)
    {
        if ($icon === '') {
            $icon = self::getTabIconClass($form_itemtype);
        }
        if (str_contains($icon, 'fa-empty-icon')) {
            $icon = '';
        }

        $icon_html = $icon !== '' ? sprintf('<i class="%s me-2"></i>', htmlescape($icon)) : '';
        $counter_html = '';
        if ($nb > 0) {
            $badge_content = $total_nb !== null ? "$nb/$total_nb" : "$nb";
            $counter_html = sprintf(' <span class="badge glpi-badge" data-testid="tab-count-badge">%s</span>', htmlescape($badge_content));
        }

        return sprintf(
            '<span class="d-flex align-items-center">%s%s%s</span>',
            $icon_html,
            htmlescape($text),
            $counter_html
        );
    }

    /**
     * Is the current object a new one?
     *
     * @since 0.83
     *
     * @return bool
     */
    public function isNewItem()
    {
        return false;
    }

    /**
     * Is the given ID an ID used for new items?
     *
     * @since 0.84
     *
     * @param int $ID
     *
     * @return bool
     */
    public static function isNewID($ID)
    {
        return true;
    }

    /**
     * Get the tabs URL for the current class.
     *
     * @param bool  $full   If true, will return the full path of the URL,
     *                      otherwise, it will return the path relative to GLPI root.
     *
     * @return string
     *
     * @final
     */
    public static function getTabsURL($full = true)
    {
        return Toolbox::getItemTypeTabsURL(static::class, $full);
    }

    /**
     * Get the search page URL for the current class.
     *
     * @param bool  $full   If true, will return the full path of the URL,
     *                      otherwise, it will return the path relative to GLPI root.
     *
     * @return string
     */
    public static function getSearchURL($full = true)
    {
        return Toolbox::getItemTypeSearchURL(static::class, $full);
    }

    /**
     * Get the form page URL for the current class.
     *
     * @param bool  $full   If true, will return the full path of the URL,
     *                      otherwise, it will return the path relative to GLPI root.
     *
     * @return string
     **/
    public static function getFormURL($full = true)
    {
        return Toolbox::getItemTypeFormURL(static::class, $full);
    }

    /**
     * Get the form page URL for the current class and point to a specific ID.
     *
     * @since 0.90
     *
     * @param int   $id     Item ID.
     * @param bool  $full   If true, will return the full path of the URL,
     *                      otherwise, it will return the path relative to GLPI root.
     *
     * @return string
     */
    public static function getFormURLWithID($id = 0, $full = true)
    {
        $link     = static::getFormURL($full);
        $link    .= (strpos($link, '?') ? '&' : '?') . 'id=' . ((int) $id);
        return $link;
    }

    /**
     * Compute the name to be used in the main header of this item.
     *
     * @return string
     *
     * @final
     */
    public function getHeaderName(): string
    {
        $name = '';
        if (isset($this->fields['id']) && ($this instanceof CommonDBTM)) {
            $name = sprintf(__('%1$s - ID %2$d'), $this->getName(), $this->fields['id']);
        }

        return $name;
    }

    /**
     * Get error message for item
     *
     * @since 0.85
     *
     * @param int       $error  error type (ERROR_* constant)
     * @param string    $object string to use instead of item link
     *
     * @phpstan-param ERROR_ALREADY_DEFINED|ERROR_COMPAT|ERROR_NOT_FOUND|ERROR_ON_ACTION|ERROR_RIGHT $error
     *
     * @return string
     *
     * @final
     *
     * @psalm-taint-specialize (to report each unsafe usage as a distinct error)
     * @psalm-taint-sink html $object (string will be added to HTML source)
     */
    public function getErrorMessage($error, $object = '')
    {
        if (empty($object) && $this instanceof CommonDBTM) {
            $object = $this->getLink();
        }
        return match ($error) {
            ERROR_NOT_FOUND => sprintf(__s('%1$s: %2$s'), $object, __s('Unable to get item')),
            ERROR_RIGHT => sprintf(__s('%1$s: %2$s'), $object, __s('Authorization error')),
            ERROR_COMPAT => sprintf(__s('%1$s: %2$s'), $object, __s('Incompatible items')),
            ERROR_ON_ACTION => sprintf(__s('%1$s: %2$s'), $object, __s('Error on executing the action')),
            ERROR_ALREADY_DEFINED => sprintf(__s('%1$s: %2$s'), $object, __s('Item already defined')),
            default => '',
        };
    }

    /**
     * Get links to Faq.
     *
     * @return string
     *
     * @final
     */
    public function getKBLinks()
    {
        global $CFG_GLPI, $DB;

        if (!($this instanceof CommonDBTM)) {
            return '';
        }

        $ret = '';
        $iterator = $DB->request([
            'SELECT' => [KnowbaseItem::getTable() . '.*'],
            'FROM'   => KnowbaseItem::getTable(),
            'WHERE'  => [
                KnowbaseItem_Item::getTable() . '.items_id'  => $this->fields['id'],
                KnowbaseItem_Item::getTable() . '.itemtype'  => static::getType(),
            ],
            'INNER JOIN'   => [
                KnowbaseItem_Item::getTable() => [
                    'ON'  => [
                        KnowbaseItem_Item::getTable() => KnowbaseItem::getForeignKeyField(),
                        KnowbaseItem::getTable()      => 'id',
                    ],
                ],
            ],
            'ORDER' => [
                KnowbaseItem::getTable()      => 'name',
            ],
        ]);

        $found_kbitem = [];
        $kbitem_ids = [];
        foreach ($iterator as $line) {
            $found_kbitem[$line['id']] = $line;
            $kbitem_ids[$line['id']] = $line['id'];
        }

        if (count($found_kbitem)) {
            $rand = mt_rand();
            $kbitem = new KnowbaseItem();
            $kbitem->getFromDB(reset($found_kbitem)['id']);
            $ret .= "<div class='faqadd_block'>";
            $ret .= "<label for='display_faq_chkbox$rand'>";
            $ret .= "<i class='ti ti-zoom-question cursor-pointer'></i>";
            $ret .= "</label>";
            $ret .= "<input type='checkbox' class='display_faq_chkbox' id='display_faq_chkbox$rand'>";
            $ret .= "<div class='faqadd_entries' style='position:relative;'>";
            if (count($found_kbitem) == 1) {
                $ret .= "<div class='faqadd_block_content' id='faqadd_block_content$rand'>";
                $ret .= $kbitem->showFull(['display' => false]);
                $ret .= "</div>"; // .faqadd_block_content
            } else {
                $ret .= Html::scriptBlock("
                var getKnowbaseItemAnswer$rand = function() {
                    var knowbaseitems_id = $('#dropdown_knowbaseitems_id$rand').val();
                    $('#faqadd_block_content$rand').load(
                        '" . $CFG_GLPI['root_doc'] . "/ajax/getKnowbaseItemAnswer.php',
                        {
                            'knowbaseitems_id': knowbaseitems_id
                        }
                    );
                };
                ");
                $ret .= "<label for='dropdown_knowbaseitems_id$rand'>"
                    . htmlescape(KnowbaseItem::getTypeName()) . "</label>&nbsp;";
                $ret .= KnowbaseItem::dropdown([
                    'value'     => reset($found_kbitem)['id'],
                    'display'   => false,
                    'rand'      => $rand,
                    'condition' => [
                        KnowbaseItem::getTable() . '.id' => $kbitem_ids,
                    ],
                    'on_change' => "getKnowbaseItemAnswer$rand()",
                ]);
                $ret .= "<div class='faqadd_block_content' id='faqadd_block_content$rand'>";
                $ret .= $kbitem->showFull(['display' => false]);
                $ret .= "</div>"; // .faqadd_block_content
            }
            $ret .= "</div>"; // .faqadd_entries
            $ret .= "</div>"; // .faqadd_block
        }
        return $ret;
    }

    /**
     * Get array of extra form header toolbar buttons.
     *
     * @return string[] Array of HTML elements
     */
    protected function getFormHeaderToolbar(): array
    {
        return [];
    }
}
