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

namespace Glpi\Form\QuestionType;

use Glpi\DBAL\JsonFieldInterface;
use Glpi\Form\Condition\ConditionHandler\ConditionHandlerInterface;
use Glpi\Form\Condition\ConditionHandler\EmptyConditionHandler;
use Glpi\Form\Condition\ConditionHandler\RegexConditionHandler;
use Glpi\Form\Condition\ConditionHandler\VisibilityConditionHandler;
use Glpi\Form\Export\Context\DatabaseMapper;
use Glpi\Form\Export\Serializer\DynamicExportDataField;
use Glpi\Form\Question;
use InvalidArgumentException;
use Override;

abstract class AbstractQuestionType implements QuestionTypeInterface
{
    public function __construct() {}

    
    public function formatDefaultValueForDB(mixed $value): ?string
    {
        return $value; // Default value is already formatted
    }

    
    public function prepareEndUserAnswer(Question $question, mixed $answer): mixed
    {
        return $answer;
    }

    
    public function validateExtraDataInput(array $input): bool
    {
        return $input === []; // No extra data by default
    }

    
    public function prepareExtraData(array $input): array
    {
        return $input; // No need to prepare the extra data
    }

    
    public function getFormEditorJsOptions(): string
    {
        return <<<JS
            {
                "extractDefaultValue": function (question) { return null; },
                "convertDefaultValue": function (question, value) {
                    return value;
                }
            }
        JS;
    }

    
    public function renderAdministrationOptionsTemplate(?Question $question): string
    {
        return ''; // No options by default
    }

    
    public function renderAdvancedConfigurationTemplate(?Question $question): ?string
    {
        return null; // No advanced configuration by default
    }

    
    public function formatRawAnswer(mixed $answer, Question $question): string
    {
        // By default only return the string answer
        if (!is_string($answer) && !is_numeric($answer)) {
            throw new InvalidArgumentException(
                'Raw answer must be a string or a method must be implemented to format the answer'
            );
        }

        return (string) $answer;
    }

    
    public function getName(): string
    {
        return $this->getCategory()->getLabel();
    }

    
    public function getIcon(): string
    {
        return 'ti ti-icons-off';
    }

    
    public function getWeight(): int
    {
        return 20;
    }

    
    public function isAllowedForUnauthenticatedAccess(): bool
    {
        return false;
    }

    
    public function getExtraDataConfigClass(): ?string
    {
        return null;
    }

    
    public function getExtraDataConfig(array $serialized_data): ?JsonFieldInterface
    {
        $config_class = $this->getExtraDataConfigClass();
        if ($config_class === null || $serialized_data === []) {
            return null;
        }

        return $config_class::jsonDeserialize($serialized_data);
    }

    
    public function getDefaultValueConfigClass(): ?string
    {
        return null;
    }

    
    public function getDefaultValueConfig(array $serialized_data): ?JsonFieldInterface
    {
        $config_class = $this->getDefaultValueConfigClass();
        if ($config_class === null || $serialized_data === []) {
            return null;
        }

        return $config_class::jsonDeserialize($serialized_data);
    }

    
    public function getSubTypes(): array
    {
        return [];
    }

    
    public function getSubTypeFieldName(): string
    {
        return 'sub_type';
    }

    
    public function getSubTypeFieldAriaLabel(): string
    {
        return __('Question sub type');
    }

    
    public function getSubTypeDefaultValue(?Question $question): ?string
    {
        return '';
    }

    
    public function formatPredefinedValue(string $value): ?string
    {
        // Do nothing by default
        return null;
    }

    
    public function exportDynamicExtraData(
        ?array $extra_data_config,
    ): DynamicExportDataField {
        return new DynamicExportDataField($extra_data_config, []);
    }

    
    public function exportDynamicDefaultValue(
        ?JsonFieldInterface $extra_data_config,
        array|int|float|bool|string|null $default_value_config,
    ): DynamicExportDataField {
        return new DynamicExportDataField($default_value_config, []);
    }

    
    public static function prepareDynamicExtraDataForImport(
        ?array $extra_data,
        DatabaseMapper $mapper,
    ): ?array {
        return $extra_data;
    }

    
    public static function prepareDynamicDefaultValueForImport(
        ?array $extra_data,
        array|int|float|bool|string|null $default_value_data,
        DatabaseMapper $mapper,
    ): array|int|float|bool|string|null {
        return $default_value_data;
    }

    /**
     * Get all condition handlers that can process this question type
     *
     * @param JsonFieldInterface|null $question_config Configuration for the question
     * @return array<ConditionHandlerInterface> List of applicable condition handlers
     */
    
    public function getConditionHandlers(
        ?JsonFieldInterface $question_config
    ): array {
        return [

            new VisibilityConditionHandler(),
            new RegexConditionHandler($this, $question_config),
            new EmptyConditionHandler($this, $question_config),
        ];
    }

    
    public function getSupportedValueOperators(
        ?JsonFieldInterface $question_config
    ): array {
        return array_merge(
            ...array_map(
                fn(ConditionHandlerInterface $handler) => $handler->getSupportedValueOperators(),
                $this->getConditionHandlers($question_config)
            )
        );
    }

    
    public function isHiddenInput(): bool
    {
        return false;
    }
}
