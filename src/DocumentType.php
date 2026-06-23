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

use function Safe\preg_match;

/**
 * DocumentType Class
 **/
class DocumentType extends CommonDropdown
{
    public static $rightname      = 'typedoc';

    private static ?string $uploadable_patterns = null;

    public function getAdditionalFields()
    {

        return [['name'  => 'icon',
            'label' => __('Icon'),
            'type'  => 'icon',
        ],
            ['name'  => 'is_uploadable',
                'label' => __('Authorized upload'),
                'type'  => 'bool',
            ],
            ['name'    => 'ext',
                'label'   => __('Extension'),
                'type'    => 'text',
                'comment' => __('May be a regular expression'),
            ],
            ['name'  => 'mime',
                'label' => __('MIME type'),
                'type'  => 'text',
            ],
        ];
    }


    public static function getTypeName($nb = 0)
    {
        return _n('Document type', 'Document types', $nb);
    }


    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'ext',
            'name'               => __('Extension'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => $this->getTable(),
            'field'              => 'icon',
            'name'               => __('Icon'),
            'massiveaction'      => false,
            'datatype'           => 'specific',
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => $this->getTable(),
            'field'              => 'mime',
            'name'               => __('MIME type'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '5',
            'table'              => $this->getTable(),
            'field'              => 'is_uploadable',
            'name'               => __('Authorized upload'),
            'datatype'           => 'bool',
        ];

        return $tab;
    }

    /**
     * Return pattern that can be used to validate that name of an uploaded file matches accepted extensions.
     *
     * @return string
     */
    public static function getUploadableFilePattern(): string
    {
        global $DB;

        if (self::$uploadable_patterns === null) {
            $valid_type_iterator = $DB->request([
                'FROM'   => 'glpi_documenttypes',
                'WHERE'  => [
                    'is_uploadable'   => 1,
                ],
            ]);

            $valid_ext_patterns = [];
            foreach ($valid_type_iterator as $valid_type) {
                $valid_ext = $valid_type['ext'];
                if (preg_match('/\/.+\//', $valid_ext)) {
                    // Filename matches pattern
                    // Remove surrounding '/' as it will be included in a larger pattern
                    // and protect by surrounding parenthesis to prevent conflict with other patterns
                    $valid_ext_patterns[] = '(' . substr($valid_ext, 1, -1) . ')';
                } else {
                    // Filename ends with allowed ext
                    $valid_ext_patterns[] = '\.' . preg_quote($valid_type['ext'], '/') . '$';
                }
            }

            self::$uploadable_patterns = '/(' . implode('|', $valid_ext_patterns) . ')/i';
        }

        return self::$uploadable_patterns;
    }

    #[Override]
    public function post_addItem()
    {
        $this->clearCachedUploadablePatterns();
        parent::post_addItem();
    }

    #[Override]
    public function post_updateItem($history = true)
    {
        $this->clearCachedUploadablePatterns();
        parent::post_updateItem($history);
    }

    #[Override]
    public function post_deleteItem()
    {
        $this->clearCachedUploadablePatterns();
        parent::post_deleteItem();
    }

    #[Override]
    public function post_purgeItem()
    {
        $this->clearCachedUploadablePatterns();
        parent::post_purgeItem();
    }
    private function clearCachedUploadablePatterns(): void
    {
        self::$uploadable_patterns = null;
    }
}
