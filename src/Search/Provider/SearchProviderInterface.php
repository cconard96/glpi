<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
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

namespace Glpi\Search\Provider;

/**
 * Interface for all search providers.
 *
 * Search providers query one or more types of data sources to get matching data.
 *
 * @internal Not for use outside {@link Search} class and the "Glpi\Search" namespace.
 * @phpstan-type SearchDataColumn array{itemtype: string, id: int, name: string, meta: '0'|'1', searchopt: array}
 * @phpstan-type SearchData array{data: array{execution_time: float, totalcount: int, begin: int, end: int, cols: array, rows: array, items: array, currentuser: string, count: int}}
 */
interface SearchProviderInterface
{
    /**
     * Injects prepared data into the data array and also returns it.
     * @param array $data
     * @param-out SearchData $data
     * @param array $options
     * @return array
     * @phpstan-return SearchData
     */
    public static function prepareData(array &$data, array $options = []): array;
}
