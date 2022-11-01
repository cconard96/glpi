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

use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\VersionAwarePlatformDriver;

/**
 *  Query function class
 * @phpstan-type FunctionPlatformMapping array{driver: class-string<VersionAwarePlatformDriver>, name: string, output_callback?: callable(string, mixed[], string|null): string}
 * @phpstan-type FunctionMapping array{name: string, platforms: FunctionPlatformMapping[]}
 **/
class QueryFunction
{
    private $raw;

    private string $name;

    private array $params;

    private ?string $alias;

    /**
     * Create a query expression
     *
     * @param string $raw Query function expression value
     */
    public function __construct(string $name, array $params = [], ?string $alias = null)
    {
        $this->name = self::getFunctionNameForCurrentPlatform($name);
        $this->params = $params;
        $this->alias = $alias;
    }

    public function getRawValue(): string
    {
        return $this->raw;
    }

    private function getDefaultOutput(): string
    {
        global $DB_PDO;

        // Quote parameters if needed
        foreach ($this->params as &$parameter) {
            $parameter = trim($parameter);
            // Check if the parameter is an unquoted table column name (table.column)
            if (preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+$/', $parameter)) {
                // quote the table name and column name, then recombine
                $parts = explode('.', $parameter);
                $parts = array_map([$DB_PDO, 'quoteName'], $parts);
                $parameter = implode('.', $parts);
            } else if (!is_numeric($parameter)) {
                // Quote the parameter if it is not a number
                $parameter = $DB_PDO->quote($parameter);
            }
        }

        return $this->name . '(' . implode(', ', $this->params) . ')' . ($this->alias ? ' AS ' . $DB_PDO->quoteIdentifier($this->alias) : '');
    }

    /**
     * Query expression value
     *
     * @return string
     */
    public function getValue()
    {
        $output = $this->getDefaultOutput();

        // Check if the function has a custom output callback
        $function = $this->getFunctionMapping($this->name);
        if (isset($function['platforms'])) {
            foreach ($function['platforms'] as $platform) {
                if (is_a($GLOBALS['DB']->getDriver(), $platform['driver'])) {
                    if (isset($platform['output_callback'])) {
                        $output = $platform['output_callback']($this->name, $this->params, $this->alias);
                    }
                    break;
                }
            }
        }

        return $output;
    }

    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * @return array[]
     * @phpstan-return FunctionMapping[]
     */
    private static function getSupportedFunctions(): array
    {
        global $DB_PDO;

        return [
            [
                'name' => 'CONCAT',
                'platforms' => []
            ],
            [
                'name' => 'GROUP_CONCAT',
                'platforms' => [
                    [
                        'driver' => AbstractPostgreSQLDriver::class,
                        'name' => 'STRING_AGG'
                    ]
                ]
            ],
            [
                'name' => 'DATE_ADD',
                'platforms' => [
                    [
                        'driver' => AbstractPostgreSQLDriver::class,
                        'name' => '', // Not a true function
                        'output_callback' => function ($name, $params, $alias) use ($DB_PDO): string {
                            $output = sprintf(
                                '(%s + INTERVAL \'%s %s %s\')',
                                $params[0],
                                $params[1], // Interval
                                $params[2] !== null ? '-' . $params[2] : '', // Optional minus field/value (subtraction from interval)
                                strtoupper($params[3]) // Unit
                            );
                            if ($alias !== null) {
                                $output .= ' AS ' . $DB_PDO->quoteIdentifier($alias);
                            }
                            return $output;
                        }
                    ],
                    [
                        'driver' => AbstractMySQLDriver::class,
                        'name' => '', // Not a true function
                        'output_callback' => function ($name, $params, $alias) use ($DB_PDO): string {
                            $output = sprintf(
                                'DATE_ADD(%s, INTERVAL %s %s %s)',
                                $params[0], // Date
                                $params[1], // Interval
                                $params[2] !== null ? '-' . $params[2] : '', // Optional minus field/value (subtraction from interval)
                                strtoupper($params[3]) // Unit
                            );
                            if ($alias !== null) {
                                $output .= ' AS ' . $DB_PDO->quoteIdentifier($alias);
                            }
                            return $output;
                        }
                    ]
                ]
            ]
        ];
    }

    /**
     * @param string $function
     * @return array
     * @phpstan-return FunctionMapping
     */
    public function getFunctionMapping(string $function): array
    {
        $function = strtoupper($function);
        foreach (self::getSupportedFunctions() as $supported_function) {
            if (strpos($function, $supported_function['name']) === 0) {
                $parameters = [];
                if (preg_match('/\((.*)\)/', $function, $matches)) {
                    $parameters = explode(',', $matches[1]);
                }
                $supported_function['parameters'] = $parameters;
                return $supported_function;
            }
        }
        return [];
    }

    private static function getFunctionNameForCurrentPlatform(string $function): string
    {
        global $DB_PDO;

        $driver = $DB_PDO->getDriver();
        $supported_functions = self::getSupportedFunctions();

        foreach ($supported_functions as $supported_function) {
            if ($supported_function['name'] === $function) {
                foreach ($supported_function['platforms'] as $platform) {
                    if ($driver instanceof $platform['driver']) {
                        return $platform['name'];
                    }
                }
                return $function;
            }
        }

        return $function;
    }

    public static function concat(array $params, ?string $alias = null): self
    {
        return new self('CONCAT', $params, $alias);
    }

    public static function groupConcat(string $field, string $separator = ',', ?string $alias = null): self
    {
        return new self('GROUP_CONCAT', [$field, "SEPARATOR '$separator'"], $alias);
    }

    public static function addDate(string $date, string $interval, string $interval_unit, ?string $minus = null, ?string $alias = null): self
    {
        return new self('DATE_ADD', [$date, $interval, $interval_unit, $minus], $alias);
    }
}
