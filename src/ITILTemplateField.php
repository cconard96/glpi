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
 * ITILTemplateMandatoryField Class
 *
 * Predefined fields for ITIL template class
 *
 * @since 9.5.0
 **/
abstract class ITILTemplateField extends CommonDBChild
{
    /**
     * @var class-string<ITILTemplate>
     */
    public static $itemtype; //to be filled in subclass

    public static $items_id; //to be filled in subclass

    /**
     * @var class-string<CommonITILObject>
     */
    public static $itiltype; //to be filled in subclass

    private array $all_fields;

    // From CommonDBTM
    public $dohistory = true;

    public static function getMultiplePredefinedValues(): array
    {
        // List of fields that are allowed to be defined multiples times.
        return [];
    }


    public function getForbiddenStandardMassiveAction()
    {

        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'clone';
        $forbidden[] = 'update';
        return $forbidden;
    }


    /**
     * Get fields list
     *
     * @param ITILTemplate $tt ITIL Template
     *
     * @return array
     */
    public function getAllFields(ITILTemplate $tt)
    {
        $this->all_fields = $tt->getAllowedFieldsNames(true);
        $this->all_fields = array_diff_key($this->all_fields, static::getExcludedFields());
        return $this->all_fields;
    }


    protected function computeFriendlyName()
    {
        $tt     = getItemForItemtype(static::$itemtype);
        $fields = $tt->getAllowedFieldsNames(true);
        return $fields[$this->fields["num"]] ?? '';
    }

    /**
     * Return fields who doesn't need to be used for this part of template
     *
     * @since 9.2
     *
     * @return array the excluded fields (keys and values are equals)
     */
    abstract public static function getExcludedFields();

    /**
     * Get field num from its name
     *
     * @param ITILTemplate $tt   ITIL Template
     * @param string       $name Field name to look for
     *
     * @return int|false
     */
    public function getFieldNum(ITILTemplate $tt, $name)
    {
        if (!isset($this->all_fields)) {
            $this->getAllFields($tt);
        }
        return array_search($name, $this->all_fields);
    }


    public function getItem($getFromDB = true, $getEmpty = true)
    {
        $item_class = static::$itemtype;
        if ($item_class == 'ITILTemplate') {
            if (isset($this->fields['itiltype'])) {
                $item_class = $this->fields['itiltype'] . 'Template';
            }
            if (isset($this->input['itiltype'])) {
                $item_class = $this->input['itiltype'] . 'Template';
            }
        }

        return $this->getConnexityItem(
            $item_class,
            static::$items_id,
            $getFromDB,
            $getEmpty
        );
    }
}
