<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Api\HL\Controller;

use Entity;
use Entity_KnowbaseItem;
use Glpi\Api\HL\Doc as Doc;
use Glpi\Api\HL\Route;
use Glpi\UI\IllustrationManager;
use Group;
use Group_KnowbaseItem;
use KnowbaseItem;
use KnowbaseItem_Comment;
use KnowbaseItem_Profile;
use KnowbaseItem_Revision;
use KnowbaseItem_User;
use KnowbaseItemCategory;
use KnowbaseItemTranslation;
use Profile;
use User;

#[Route(path: '/Knowledgebase', requirements: [
    'id' => '\d+',
], tags: ['Knowledgebase'])]
#[Doc\Route(
    parameters: [
        new Doc\Parameter(
            name: 'id',
            schema: new Doc\Schema(type: Doc\Schema::TYPE_INTEGER),
            location: Doc\Parameter::LOCATION_PATH,
        ),
    ]
)]
class KnowbaseController extends AbstractController
{
    protected static function getRawKnownSchemas(): array
    {
        $schemas = [
            'KBArticle' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => KnowbaseItem::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'name' => ['type' => Doc\Schema::TYPE_STRING],
                    'content' => [
                        //TODO how to handle this? Content may be really long so searches will have a lot of data to process.
                        'type' => Doc\Schema::TYPE_STRING,
                        'x-field' => 'answer',
                    ],
                    'categories' => [
                        'type' => Doc\Schema::TYPE_ARRAY,
                        'items' => [
                            'type' => Doc\Schema::TYPE_OBJECT,
                            'x-full-schema' => 'KBCategory',
                            'x-join' => [
                                'table' => KnowbaseItemCategory::getTable(),
                                'fkey' => KnowbaseItemCategory::getForeignKeyField(),
                                'field' => 'id',
                                'ref-join' => [
                                    'table' => \KnowbaseItem_KnowbaseItemCategory::getTable(),
                                    'fkey' => KnowbaseItem::getForeignKeyField(),
                                ],
                            ],
                            'properties' => [
                                'id' => [
                                    'type' => Doc\Schema::TYPE_INTEGER,
                                    'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                                    'readOnly' => true,
                                ],
                                'name' => ['type' => Doc\Schema::TYPE_STRING, 'maxLength' => 255],
                            ],
                        ],
                    ],
                    'is_faq' => ['type' => Doc\Schema::TYPE_BOOLEAN, 'default' => false],
                    'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                    'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN, 'default' => false],
                    'user' => self::getDropdownTypeSchema(class: User::class, full_schema: 'User'),
                    'views' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'x-field' => 'view'
                    ],
                    'show_in_service_catalog' => ['type' => Doc\Schema::TYPE_BOOLEAN, 'default' => false],
                    'description' => [
                        //TODO same as content
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'Short description of the article which may be shown in the service catalog. If null, the content field will be used as description.',
                    ],
                    'illustration' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'Name of the illustration to show in the service catalog.',
                        'enum' => (new IllustrationManager())->getAllIconsIds(),
                    ],
                    'is_pinned' => [
                        'type' => Doc\Schema::TYPE_BOOLEAN,
                        'description' => 'Whether the article is pinned in the service catalog.',
                        'default' => false,
                    ],
                    'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                    'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                    'date_begin' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                        'description' => 'The date and time when the article becomes visible. If null, the article is visible immediately.',
                        'x-field' => 'begin_date',
                    ],
                    'date_end' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'format' => Doc\Schema::FORMAT_STRING_DATE_TIME,
                        'description' => 'The date and time when the article is no longer visible. If null, the article is visible indefinitely.',
                        'x-field' => 'end_date',
                    ],
                ],
            ],
            'KBCategory' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => KnowbaseItemCategory::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'name' => ['type' => Doc\Schema::TYPE_STRING, 'maxLength' => 255],
                    'completename' => ['type' => Doc\Schema::TYPE_STRING, 'readOnly' => true],
                    'comment' => ['type' => Doc\Schema::TYPE_STRING],
                    'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                    'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN, 'default' => false],
                    'parent' => self::getDropdownTypeSchema(class: KnowbaseItemCategory::class, full_schema: 'KBCategory'),
                    'level' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'description' => 'Level',
                        'readOnly' => true,
                    ],
                    'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                    'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                ],
            ],
            'KBArticleComment' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => KnowbaseItem_Comment::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'kbarticle' => self::getDropdownTypeSchema(class: KnowbaseItem::class, full_schema: 'KBArticle'),
                    'user' => self::getDropdownTypeSchema(class: User::class, full_schema: 'User'),
                    'language' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'Language code (POSIX compliant format e.g. en_US or fr_FR)',
                    ],
                    'comment' => ['type' => Doc\Schema::TYPE_STRING],
                    'parent' => [
                        'type' => Doc\Schema::TYPE_OBJECT,
                        'x-full-schema' => 'KBArticleComment',
                        'x-field' => 'parent_comment_id',
                        'x-itemtype' => KnowbaseItem_Comment::class,
                        'x-join' => [
                            'table' => KnowbaseItem_Comment::getTable(),
                            'fkey' => 'parent_comment_id',
                            'field' => 'id',
                        ],
                        'properties' => [
                            'id' => [
                                'type' => Doc\Schema::TYPE_INTEGER,
                                'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                                'readOnly' => true,
                            ],
                        ],
                    ],
                    'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                    'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                ],
            ],
            'KBArticleRevision' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => KnowbaseItem_Revision::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'kbarticle' => self::getDropdownTypeSchema(class: KnowbaseItem::class, full_schema: 'KBArticle'),
                    'revision' => ['type' => Doc\Schema::TYPE_INTEGER],
                    'name' => ['type' => Doc\Schema::TYPE_STRING],
                    'content' => [
                        //TODO same as KB article content
                        'type' => Doc\Schema::TYPE_STRING,
                        'x-field' => 'answer',
                    ],
                    'language' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'Language code (POSIX compliant format e.g. en_US or fr_FR)',
                    ],
                    'user' => self::getDropdownTypeSchema(class: User::class, full_schema: 'User'),
                    'date' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                ],
            ],
            'KBArticleTranslation' => [
                //TODO How will a user request the base content vs a translation?
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => KnowbaseItemTranslation::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'kbarticle' => self::getDropdownTypeSchema(class: KnowbaseItem::class, full_schema: 'KBArticle'),
                    'language' => [
                        'type' => Doc\Schema::TYPE_STRING,
                        'description' => 'Language code (POSIX compliant format e.g. en_US or fr_FR)',
                    ],
                    'name' => ['type' => Doc\Schema::TYPE_STRING],
                    'content' => [
                        //TODO same as KB article content
                        'type' => Doc\Schema::TYPE_STRING,
                        'x-field' => 'answer',
                    ],
                    'user' => self::getDropdownTypeSchema(class: User::class, full_schema: 'User'),
                    'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                    'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME],
                ],
            ],
            'KBArticle_EntityTarget' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => Entity_KnowbaseItem::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'kbarticle' => self::getDropdownTypeSchema(class: KnowbaseItem::class, full_schema: 'KBArticle'),
                    'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                    'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN, 'default' => false],
                ],
            ],
            'KBArticle_GroupTarget' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => Group_KnowbaseItem::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'kbarticle' => self::getDropdownTypeSchema(class: KnowbaseItem::class, full_schema: 'KBArticle'),
                    'group' => self::getDropdownTypeSchema(class: Group::class, full_schema: 'Group'),
                    'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                    'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN, 'default' => false],
                    //TODO What does the no_entity_restriction field do and is it possible to set it anywhere?
                ],
            ],
            'KBArticle_ProfileTarget' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => KnowbaseItem_Profile::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'kbarticle' => self::getDropdownTypeSchema(class: KnowbaseItem::class, full_schema: 'KBArticle'),
                    'profile' => self::getDropdownTypeSchema(class: Profile::class, full_schema: 'Profile'),
                    'entity' => self::getDropdownTypeSchema(class: Entity::class, full_schema: 'Entity'),
                    'is_recursive' => ['type' => Doc\Schema::TYPE_BOOLEAN, 'default' => false],
                    //TODO What does the no_entity_restriction field do and is it possible to set it anywhere?
                ],
            ],
            'KBArticle_UserTarget' => [
                'x-version-introduced' => '2.2.0',
                'x-itemtype' => KnowbaseItem_User::class,
                'type' => Doc\Schema::TYPE_OBJECT,
                'properties' => [
                    'id' => [
                        'type' => Doc\Schema::TYPE_INTEGER,
                        'format' => Doc\Schema::FORMAT_INTEGER_INT64,
                        'readOnly' => true,
                    ],
                    'kbarticle' => self::getDropdownTypeSchema(class: KnowbaseItem::class, full_schema: 'KBArticle'),
                    'user' => self::getDropdownTypeSchema(class: User::class, full_schema: 'User'),
                ],
            ],
        ];

        return $schemas;
    }
}
