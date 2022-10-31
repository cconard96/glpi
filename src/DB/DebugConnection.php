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

namespace Glpi\DB;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\ServerException;
use Doctrine\DBAL\ParameterType;
use Glpi\Application\ErrorHandler;
use Session;
use Timer;
use Toolbox;

final class DebugConnection implements Driver\Connection
{
    /**
     * @var Driver\Connection
     */
    private $connection;

    /**
     * @var bool|int
     */
    private $execution_time = true;

    /**
     * @var array
     */
    private $feature_flags;

    private $timer;

    public function __construct($connection, array $feature_flags = [])
    {
        $this->connection = $connection;
        $this->feature_flags = $feature_flags;
        $this->timer = new Timer();
    }

    public function prepare(string $sql): Statement
    {
        return $this->connection->prepare($sql);
    }

    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->connection->quote($value, $type);
    }

    public function lastInsertId($name = null)
    {
        return $this->connection->lastInsertId($name);
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollBack()
    {
        return $this->connection->rollBack();
    }

    public function getFeatureFlag(string $flag): bool
    {
        return $this->feature_flags[$flag] ?? false;
    }

    public function setFeatureFlag(string $flag, bool $value): void
    {
        $this->feature_flags[$flag] = $value;
    }

    /**
     * Check for deprecated table options during ALTER/CREATE TABLE queries.
     *
     * @param string $query
     *
     * @return void
     */
    private function checkForDeprecatedTableOptions(string $query): void
    {
        if (preg_match('/(ALTER|CREATE)\s+TABLE\s+/', $query) !== 1) {
            return;
        }

        // Wrong UTF8 charset/collation
        $matches = [];
        if ($this->getFeatureFlag(DB::FEATURE_UTF8MB4) && preg_match('/(?<invalid>(utf8(_[^\';\s]+)?))([\';\s]|$)/', $query, $matches)) {
            trigger_error(
                sprintf(
                    'Usage of "%s" charset/collation detected, should be "%s"',
                    $matches['invalid'],
                    str_replace('utf8', 'utf8mb4', $matches['invalid'])
                ),
                E_USER_WARNING
            );
        } else if (!$this->getFeatureFlag(DB::FEATURE_UTF8MB4) && preg_match('/(?<invalid>(utf8mb4(_[^\';\s]+)?))([\';\s]|$)/', $query, $matches)) {
            trigger_error(
                sprintf(
                    'Usage of "%s" charset/collation detected, should be "%s"',
                    $matches['invalid'],
                    str_replace('utf8mb4', 'utf8', $matches['invalid'])
                ),
                E_USER_WARNING
            );
        }

        // Usage of MyISAM
        $matches = [];
        if (!$this->getFeatureFlag(DB::FEATURE_MYISAM) && preg_match('/[)\s]engine\s*=\s*\'?myisam([\';\s]|$)/i', $query, $matches)) {
            trigger_error('Usage of "MyISAM" engine is discouraged, please use "InnoDB" engine.', E_USER_WARNING);
        }

        // Usage of datetime
        $matches = [];
        if (!$this->getFeatureFlag(DB::FEATURE_DATETIMES) && preg_match('/ datetime /i', $query, $matches)) {
            trigger_error('Usage of "DATETIME" fields is discouraged, please use "TIMESTAMP" fields instead.', E_USER_WARNING);
        }
    }

    private function preQuery(string $sql)
    {
        global $CFG_GLPI, $DEBUG_SQL, $SQL_TOTAL_REQUEST;

        $is_debug = isset($_SESSION['glpi_use_mode']) && ((int) $_SESSION['glpi_use_mode'] === Session::DEBUG_MODE);
        if ($is_debug && $CFG_GLPI["debug_sql"]) {
            $SQL_TOTAL_REQUEST++;
            $DEBUG_SQL["queries"][$SQL_TOTAL_REQUEST] = $sql;
        }
        if (($is_debug && $CFG_GLPI["debug_sql"]) || $this->execution_time === true) {
            $this->timer->start();
        }

        $this->checkForDeprecatedTableOptions($sql);
    }

    private function postQuery(Result $result)
    {
        global $DEBUG_SQL, $SQL_TOTAL_REQUEST;

        $DEBUG_SQL["times"][$SQL_TOTAL_REQUEST] = $this->timer->getTime();
        $DEBUG_SQL['rows'][$SQL_TOTAL_REQUEST] = $result->rowCount();
//        if ($this->execution_time === true) {
//            $this->execution_time = $this->timer->getTime(0, true);
//        }s
    }

    private function preExec(string $sql): void
    {
        $this->preQuery($sql);
    }

    private function postExec(int $num_affected_rows): void
    {
        global $DEBUG_SQL, $SQL_TOTAL_REQUEST;

        $DEBUG_SQL["times"][$SQL_TOTAL_REQUEST] = $this->timer->getTime();
        $DEBUG_SQL['rows'][$SQL_TOTAL_REQUEST] = $num_affected_rows;
    }

    /**
     * @param \Exception $e The exception that was thrown during the query execution
     * @throws \Exception The server exception that was passed to this method.
     */
    private function handleException(string $sql, \Exception $e)
    {
        global $DEBUG_SQL, $SQL_TOTAL_REQUEST;

        // no translation for error logs
        $error = "  *** DB query error:\n  SQL: " . $sql . "\n  Error: " .
            $e->getMessage() . "\n";
        // Find the method that called this method
        $calling_method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'];
        $conn_class = self::class;

        $error .= Toolbox::backtrace(false, "{$conn_class}->{$calling_method}()", ['Toolbox::backtrace()']);

        Toolbox::logSqlError($error);

        ErrorHandler::getInstance()->handleSqlError($e->getCode(), $e->getMessage(), $sql);

        $DEBUG_SQL["errors"][$SQL_TOTAL_REQUEST] = $e->getMessage();
        throw $e;
    }

    public function query(string $sql): Result
    {
        $this->preQuery($sql);
        try {
            $res = $this->connection->query($sql);
        } catch (\Exception $e) {
            $this->handleException($sql, $e);
        }
        $this->postQuery($res);

        return $res;
    }

    public function exec(string $sql): int
    {
        $this->preExec($sql);
        $res = $this->connection->exec($sql);
        $this->postExec($res);

        return $res;
    }
}
