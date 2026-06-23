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
 * Class to link a certificate to an item
 */
class Certificate_Item extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = Certificate::class;
    public static $items_id_1    = 'certificates_id';
    public static $take_entity_1 = false;

    public static $itemtype_2    = 'itemtype';
    public static $items_id_2    = 'items_id';
    public static $take_entity_2 = true;

    /**
     * @since 9.2
     *
     **/
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }


    /**
     * @param CommonDBTM $item
     *
     * @return void
     */
    public static function cleanForItem(CommonDBTM $item)
    {
        $temp = new self();
        $temp->deleteByCriteria(['itemtype' => $item->getType(),
            'items_id' => $item->getField('id'),
        ]);
    }

    /**
     * @param int $certificates_id
     * @param int $items_id
     * @param class-string<CommonDBTM> $itemtype
     *
     * @return bool
     */
    public function getFromDBbyCertificatesAndItem($certificates_id, $items_id, $itemtype)
    {

        $certificate  = new self();
        $certificates = $certificate->find([
            'certificates_id' => $certificates_id,
            'itemtype'        => $itemtype,
            'items_id'        => $items_id,
        ]);
        if (count($certificates) != 1) {
            return false;
        }

        $cert         = current($certificates);
        $this->fields = $cert;

        return true;
    }

    /**
     * Link a certificate to an item
     *
     * @since 9.2
     * @param array $values
     *
     * @return void
     */
    public function addItem($values)
    {

        $this->add(['certificates_id' => $values["certificates_id"],
            'items_id'        => $values["items_id"],
            'itemtype'        => $values["itemtype"],
        ]);
    }

    /**
     * Delete a certificate link to an item
     *
     * @since 9.2
     *
     * @param int $certificates_id the certificate ID
     * @param int $items_id the item's id
     * @param string $itemtype the itemtype
     *
     * @return bool
     */
    public function deleteItemByCertificatesAndItem($certificates_id, $items_id, $itemtype)
    {

        if (
            $this->getFromDBbyCertificatesAndItem(
                $certificates_id,
                $items_id,
                $itemtype
            )
        ) {
            return $this->delete(['id' => $this->fields["id"]]);
        }
        return false;
    }
}
