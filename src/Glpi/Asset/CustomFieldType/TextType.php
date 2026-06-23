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

namespace Glpi\Asset\CustomFieldType;

use Glpi\Asset\CustomFieldOption\BooleanOption;
use InvalidArgumentException;

class TextType extends AbstractType
{
    public static function getName(): string
    {
        return __('Text');
    }

    public function getOptions(): array
    {
        $opts = parent::getOptions();
        $opts[] = new BooleanOption($this->custom_field, 'enable_richtext', __('Rich text'), true, false);
        $opts[] = new BooleanOption($this->custom_field, 'enable_images', __('Allow images'), false, false);
        return $opts;
    }

    public function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('The value must be a string');
        }
        return $value;
    }

    public function getSearchOption(): ?array
    {
        $opt = $this->getCommonSearchOptionData();
        $opt['datatype'] = 'text';
        return $opt;
    }
}
