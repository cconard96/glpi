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

namespace Glpi\Api\HL\GraphQL;

use GraphQL\Executor\ReferenceExecutor;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * A reduced GraphQL request executor based on {@link ReferenceExecutor}
 */
class RequestExecutor extends ReferenceExecutor
{
    protected function completeValue(Type $returnType, \ArrayObject $fieldNodes, ResolveInfo $info, array $path, array $unaliasedPath, &$result, $contextValue)
    {
        // If result is an Error, throw a located error.
        if ($result instanceof \Throwable) {
            throw $result;
        }
        // Do not process values as the API Search already handles them
        return $result;
    }
}
