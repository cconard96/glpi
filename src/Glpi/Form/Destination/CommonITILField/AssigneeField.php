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

namespace Glpi\Form\Destination\CommonITILField;

use Glpi\Form\Form;
use Glpi\Form\QuestionType\QuestionTypeAssignee;
use Glpi\Form\QuestionType\QuestionTypeEmail;
use Group;
use Override;
use Session;
use Supplier;
use User;

final class AssigneeField extends ITILActorField
{
    
    public function getAllowedQuestionType(): array
    {
        return [new QuestionTypeAssignee(), new QuestionTypeEmail()];
    }

    
    public function getAllowedQuestionItemTypes(): array
    {
        return [User::class, Group::class, Supplier::class];
    }

    
    public function getActorType(): string
    {
        return 'assign';
    }

    
    public function getLabel(): string
    {
        return _n('Assignee', 'Assignees', Session::getPluralNumber());
    }

    
    public function getWeight(): int
    {
        return 120;
    }

    
    public function getConfigClass(): string
    {
        return AssigneeFieldConfig::class;
    }

    
    public function getDefaultConfig(Form $form): AssigneeFieldConfig
    {
        return new AssigneeFieldConfig(
            [ITILActorFieldStrategy::FROM_TEMPLATE],
        );
    }
}
