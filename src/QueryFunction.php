<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2022 Teclib' and contributors.
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
 *  Query function class
 **/
class QueryFunction
{
    private $raw;

    /**
     * Create a query expression
     *
     * @param string $raw Query function expression value
     */
    public function __construct($raw)
    {
        if (empty($raw)) {
            throw new \RuntimeException('Cannot build an empty function');
        }
        $this->raw = $raw;
    }

    public static function build(string $name, array $params = []): self
    {
        $raw = $name . '(';
        if (!empty($params)) {
            $raw .= implode(', ', $params);
        }
        $raw = rtrim($raw, ', ') . ')';
        $raw .= ')';
        return new self($raw);
    }

    public function getRawValue(): string
    {
        return $this->raw;
    }

    /**
     * Query expression value
     *
     * @return string
     */
    public function getValue()
    {
        global $DB;

        $raw = $this->getRawValue();
        // Tokenize the expression to get the function name and an array of parameters
        // Sample "CONCAT('foo', 'bar')" where CONCAT is the function name and 'foo' and 'bar' are parameters
        $tokens = preg_split('/\s*\(\s*/', $raw, 2);
        $function = $tokens[0];
        $parameters = [];
        if (count($tokens) > 1) {
            $parameters = preg_split('/\s*,\s*/', rtrim($tokens[1], ')'));
        }

        // Quote parameters if needed
        foreach ($parameters as &$parameter) {
            $parameter = trim($parameter);
            // Check if the parameter is an unquoted table column name (table.column)
            if (preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+$/', $parameter)) {
                // quote the table name and column name, then recombine
                $parts = explode('.', $parameter);
                $parts = array_map([$DB, 'quoteName'], $parts);
                $parameter = implode('.', $parts);
            }
        }

        return $function . '(' . implode(', ', $parameters) . ')';
    }


    public function __toString()
    {
        return $this->getValue();
    }
}
