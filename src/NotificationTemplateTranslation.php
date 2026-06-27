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

use Glpi\RichText\RichText;

/**
 * NotificationTemplateTranslation Class
 **/
class NotificationTemplateTranslation extends CommonDBChild
{
    // From CommonDBChild
    public static $itemtype = NotificationTemplate::class;
    public static $items_id  = 'notificationtemplates_id';

    public $dohistory = true;


    
    public static function getTypeName($nb = 0)
    {
        return _n('Template translation', 'Template translations', $nb);
    }

    
    public static function getNameField()
    {
        return 'id';
    }

    
    public function getForbiddenStandardMassiveAction()
    {
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        return $forbidden;
    }

    
    protected function computeFriendlyName()
    {
        global $CFG_GLPI;

        if ($this->getField('language') !== '') {
            return $CFG_GLPI['languages'][$this->getField('language')][0];
        }
        return __('Default translation');
    }

    /**
     * @param array $input
     * @return array
     */
    public static function cleanContentHtml(array $input)
    {
        // Get as text plain text
        $txt = RichText::getTextFromHtml($input['content_html'], true, false, false, true);

        if (!$txt) {
            // No HTML (nothing to display)
            $input['content_html'] = '';
        } elseif (!$input['content_text']) {
            // Use cleaned HTML
            $input['content_text'] = $txt;
        }
        return $input;
    }

    
    public function prepareInputForAdd($input)
    {
        return parent::prepareInputForAdd(self::cleanContentHtml($input));
    }

    
    public function prepareInputForUpdate($input)
    {
        return parent::prepareInputForUpdate(self::cleanContentHtml($input));
    }

    
    public function post_addItem()
    {
        // Handle rich-text images and uploaded documents
        $this->input = $this->addFiles($this->input, [
            'force_update' => true,
            'name' => 'content_html',
            'content_field' => 'content_html',
            '_add_link' => false,
        ]);

        parent::post_addItem();
    }

    
    public function post_updateItem($history = true)
    {
        // Handle rich-text images and uploaded documents
        $this->input = $this->addFiles($this->input, [
            'force_update' => true,
            'name' => 'content_html',
            'content_field' => 'content_html',
            '_add_link' => false,
        ]);

        parent::post_updateItem($history);
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
            'table'              => static::getTable(),
            'field'              => 'language',
            'name'               => __('Language'),
            'datatype'           => 'language',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '2',
            'table'              => static::getTable(),
            'field'              => 'subject',
            'name'               => __('Subject'),
            'massiveaction'      => false,
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => static::getTable(),
            'field'              => 'content_html',
            'name'               => __('Email HTML body'),
            'datatype'           => 'text',
            'htmltext'           => 'true',
            'massiveaction'      => false,
        ];

        $tab[] = [
            'id'                 => '4',
            'table'              => static::getTable(),
            'field'              => 'content_text',
            'name'               => __('Email text body'),
            'datatype'           => 'text',
            'massiveaction'      => false,
        ];

        return $tab;
    }

    /**
     * @param array<string> $language_id
     * @return array
     */
    public static function getAllUsedLanguages($language_id)
    {
        global $DB;

        $used_languages = $DB->request([
            'SELECT' => ['language'],
            'FROM' => 'glpi_notificationtemplatetranslations',
            'WHERE' => [
                'notificationtemplates_id' => $language_id,
            ],
        ]);

        $used           = [];
        foreach ($used_languages as $used_language) {
            $used[$used_language['language']] = $used_language['language'];
        }

        return $used;
    }
}
