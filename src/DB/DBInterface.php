<?php

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2021 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace Glpi\DB;

/**
 * Interface for the DB abstraction layer.
 * This interface is to enforce BC with the old DBmysql class.
 */
interface DBInterface
{
    /**
     * Connect using current database settings
     * Use dbhost, dbuser, dbpassword and dbdefault
     *
     * @param integer $choice host number (default NULL)
     *
     * @return void
     */
    public function connect($choice = null);

    /**
     * Guess timezone
     *
     * Will  check for an existing loaded timezone from user,
     * then will check in preferences and finally will fallback to system one.
     *
     * @return string
     *
     * @since 9.5.0
     */
    public function guessTimezone();

    /**
     * Escapes special characters in a string for use in an SQL statement,
     * taking into account the current charset of the connection
     *
     * @param string $string String to escape
     *
     * @return string escaped string
     * @since 0.84
     *
     */
    public function escape($string);

    /**
     * Execute a MySQL query
     *
     * @param string $query Query to execute
     *
     * @return mysqli_result|boolean Query result handler
     * @var array $DEBUG_SQL
     * @var integer $SQL_TOTAL_REQUEST
     *
     * @var array $CFG_GLPI
     */
    public function query($query);

    /**
     * Execute a MySQL query and die
     * (optionnaly with a message) if it fails
     *
     * @param string $query Query to execute
     * @param string $message Explanation of query (default '')
     *
     * @return mysqli_result Query result handler
     * @since 0.84
     *
     */
    public function queryOrDie($query, $message = '');

    /**
     * Prepare a MySQL query
     *
     * @param string $query Query to prepare
     *
     * @return mysqli_stmt|boolean statement object or FALSE if an error occurred.
     */
    public function prepare($query);

    /**
     * Give result from a sql result
     *
     * @param mysqli_result $result MySQL result handler
     * @param int $i Row offset to give
     * @param string $field Field to give
     *
     * @return mixed Value of the Row $i and the Field $field of the Mysql $result
     */
    public function result($result, $i, $field);

    /**
     * Number of rows
     *
     * @param mysqli_result $result MySQL result handler
     *
     * @return integer number of rows
     */
    public function numrows($result);

    /**
     * Fetch array of the next row of a Mysql query
     * Please prefer fetchRow or fetchAssoc
     *
     * @param mysqli_result $result MySQL result handler
     *
     * @return string[]|null array results
     */
    public function fetchArray($result);

    /**
     * Fetch row of the next row of a Mysql query
     *
     * @param mysqli_result $result MySQL result handler
     *
     * @return mixed|null result row
     */
    public function fetchRow($result);

    /**
     * Fetch assoc of the next row of a Mysql query
     *
     * @param mysqli_result $result MySQL result handler
     *
     * @return string[]|null result associative array
     */
    public function fetchAssoc($result);

    /**
     * Fetch object of the next row of an SQL query
     *
     * @param mysqli_result $result MySQL result handler
     *
     * @return object|null
     */
    public function fetchObject($result);

    /**
     * Move current pointer of a Mysql result to the specific row
     *
     * @param mysqli_result $result MySQL result handler
     * @param integer $num Row to move current pointer
     *
     * @return boolean
     */
    public function dataSeek($result, $num);

    /**
     * Give ID of the last inserted item by Mysql
     *
     * @return mixed
     */
    public function insertId();

    /**
     * Give number of fields of a Mysql result
     *
     * @param mysqli_result $result MySQL result handler
     *
     * @return int number of fields
     */
    public function numFields($result);

    /**
     * Give name of a field of a Mysql result
     *
     * @param mysqli_result $result MySQL result handler
     * @param integer $nb ID of the field
     *
     * @return string name of the field
     */
    public function fieldName($result, $nb);

    /**
     * List tables in database
     *
     * @param string $table Table name condition (glpi_% as default to retrieve only glpi tables)
     * @param array $where Where clause to append
     *
     * @return DBmysqlIterator
     */
    public function listTables($table = 'glpi\_%', array $where = []);

    /**
     * Returns tables using "MyIsam" engine.
     *
     * @param bool $exclude_plugins
     *
     * @return DBmysqlIterator
     */
    public function getMyIsamTables(bool $exclude_plugins = false): DBmysqlIterator;

    /**
     * Returns tables not using "utf8mb4_unicode_ci" collation.
     *
     * @param bool $exclude_plugins
     *
     * @return DBmysqlIterator
     */
    public function getNonUtf8mb4Tables(bool $exclude_plugins = false): DBmysqlIterator;

    /**
     * Returns tables not compatible with timezone usage, i.e. having "datetime" columns.
     *
     * @param bool $exclude_plugins
     *
     * @return DBmysqlIterator
     *
     * @since 10.0.0
     */
    public function getTzIncompatibleTables(bool $exclude_plugins = false): DBmysqlIterator;

    /**
     * Returns columns that corresponds to signed primary/foreign keys.
     *
     * @return DBmysqlIterator
     *
     * @since 9.5.7
     */
    public function getSignedKeysColumns();

    /**
     * Returns foreign keys constraints.
     *
     * @return DBmysqlIterator
     *
     * @since 9.5.7
     */
    public function getForeignKeysContraints();

    /**
     * List fields of a table
     *
     * @param string $table Table name condition
     * @param boolean $usecache If use field list cache (default true)
     *
     * @return mixed list of fields
     */
    public function listFields($table, $usecache = true);

    /**
     * Get field of a table
     *
     * @param string $table
     * @param string $field
     * @param boolean $usecache
     *
     * @return array|null Field characteristics
     */
    public function getField(string $table, string $field, $usecache = true): ?array;

    /**
     * Get number of affected rows in previous MySQL operation
     *
     * @return int number of affected rows on success, and -1 if the last query failed.
     */
    public function affectedRows();

    /**
     * Free result memory
     *
     * @param mysqli_result $result MySQL result handler
     *
     * @return boolean
     */
    public function freeResult($result);

    /**
     * Returns the numerical value of the error message from previous MySQL operation
     *
     * @return int error number from the last MySQL function, or 0 (zero) if no error occurred.
     */
    public function errno();

    /**
     * Returns the text of the error message from previous MySQL operation
     *
     * @return string error text from the last MySQL function, or '' (empty string) if no error occurred.
     */
    public function error();

    /**
     * Close MySQL connection
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function close();

    /**
     * is a slave database ?
     *
     * @return boolean
     */
    public function isSlave();

    /**
     * Execute all the request in a file
     *
     * @param string $path with file full path
     *
     * @return boolean true if all query are successfull
     */
    public function runFile($path);

    /**
     * Instanciate a Simple DBIterator
     *
     * Examples =
     *  foreach ($DB->request("select * from glpi_states") as $data) { ... }
     *  foreach ($DB->request("glpi_states") as $ID => $data) { ... }
     *  foreach ($DB->request("glpi_states", "ID=1") as $ID => $data) { ... }
     *  foreach ($DB->request("glpi_states", "", "name") as $ID => $data) { ... }
     *  foreach ($DB->request("glpi_computers",array("name"=>"SBEI003W","entities_id"=>1),array("serial","otherserial")) { ... }
     *
     * Examples =
     *   array("id"=>NULL)
     *   array("OR"=>array("id"=>1, "NOT"=>array("state"=>3)));
     *   array("AND"=>array("id"=>1, array("NOT"=>array("state"=>array(3,4,5),"toto"=>2))))
     *
     * FIELDS name or array of field names
     * ORDER name or array of field names
     * LIMIT max of row to retrieve
     * START first row to retrieve
     *
     * @param string|string[] $tableorsql Table name, array of names or SQL query
     * @param string|string[] $crit String or array of filed/values, ex array("id"=>1), if empty => all rows
     *                                    (default '')
     * @param boolean $debug To log the request (default false)
     *
     * @return DBmysqlIterator
     */
    public function request($tableorsql, $crit = "", $debug = false);

    /**
     * Get information about DB connection for showSystemInformation
     *
     * @return string[] Array of label / value
     * @since 0.84
     *
     */
    public function getInfo();

    /**
     * Get a global DB lock
     *
     * @param string $name lock's name
     *
     * @return boolean
     * @since 0.84
     *
     */
    public function getLock($name);

    /**
     * Release a global DB lock
     *
     * @param string $name lock's name
     *
     * @return boolean
     * @since 0.84
     *
     */
    public function releaseLock($name);

    /**
     * Check if a table exists
     *
     * @param string $tablename Table name
     * @param boolean $usecache If use table list cache
     *
     * @return boolean
     **@since 9.5 Added $usecache parameter.
     *
     * @since 9.2
     */
    public function tableExists($tablename, $usecache = true);

    /**
     * Check if a field exists
     *
     * @param string $table Table name for the field we're looking for
     * @param string $field Field name
     * @param Boolean $usecache Use cache; @see DBmysql::listFields(), defaults to true
     *
     * @return boolean
     **@since 9.2
     *
     */
    public function fieldExists($table, $field, $usecache = true);

    /**
     * Disable table cache globally; usefull for migrations
     *
     * @return void
     */
    public function disableTableCaching();

    /**
     * Builds an insert statement
     *
     * @param string $table Table name
     * @param array $params Query parameters ([field name => field value)
     *
     * @return string
     * @since 9.3
     *
     */
    public function buildInsert($table, $params);

    /**
     * Insert a row in the database
     *
     * @param string $table Table name
     * @param array $params Query parameters ([field name => field value)
     *
     * @return mysqli_result|boolean Query result handler
     * @since 9.3
     *
     */
    public function insert($table, $params);

    /**
     * Insert a row in the database and die
     * (optionnaly with a message) if it fails
     *
     * @param string $table Table name
     * @param array $params Query parameters ([field name => field value)
     * @param string $message Explanation of query (default '')
     *
     * @return mysqli_result|boolean Query result handler
     * @since 9.3
     *
     */
    public function insertOrDie($table, $params, $message = '');

    /**
     * Builds an update statement
     *
     * @param string $table Table name
     * @param array $params Query parameters ([field name => field value)
     * @param array $clauses Clauses to use. If not 'WHERE' key specified, will b the WHERE clause (@see DBmysqlIterator capabilities)
     * @param array $joins JOINS criteria array
     *
     * @return string
     * @since 9.4.0 $joins parameter added
     * @since 9.3
     *
     */
    public function buildUpdate($table, $params, $clauses, array $joins = []);

    /**
     * Update a row in the database
     *
     * @param string $table Table name
     * @param array $params Query parameters ([:field name => field value)
     * @param array $where WHERE clause
     * @param array $joins JOINS criteria array
     *
     * @return mysqli_result|boolean Query result handler
     * @since 9.4.0 $joins parameter added
     * @since 9.3
     *
     */
    public function update($table, $params, $where, array $joins = []);

    /**
     * Update a row in the database or die
     * (optionnaly with a message) if it fails
     *
     * @param string $table Table name
     * @param array $params Query parameters ([:field name => field value)
     * @param array $where WHERE clause
     * @param string $message Explanation of query (default '')
     * @param array $joins JOINS criteria array
     *
     * @return mysqli_result|boolean Query result handler
     * @since 9.4.0 $joins parameter added
     * @since 9.3
     *
     */
    public function updateOrDie($table, $params, $where, $message = '', array $joins = []);

    /**
     * Update a row in the database or insert a new one
     *
     * @param string $table Table name
     * @param array $params Query parameters ([:field name => field value)
     * @param array $where WHERE clause
     * @param boolean $onlyone Do the update only one one element, defaults to true
     *
     * @return mysqli_result|boolean Query result handler
     * @since 9.4
     *
     */
    public function updateOrInsert($table, $params, $where, $onlyone = true);

    /**
     * Builds a delete statement
     *
     * @param string $table Table name
     * @param array $params Query parameters ([field name => field value)
     * @param array $where WHERE clause (@see DBmysqlIterator capabilities)
     * @param array $joins JOINS criteria array
     *
     * @return string
     * @since 9.4.0 $joins parameter added
     * @since 9.3
     *
     */
    public function buildDelete($table, $where, array $joins = []);

    /**
     * Delete rows in the database
     *
     * @param string $table Table name
     * @param array $where WHERE clause
     * @param array $joins JOINS criteria array
     *
     * @return mysqli_result|boolean Query result handler
     * @since 9.4.0 $joins parameter added
     * @since 9.3
     *
     */
    public function delete($table, $where, array $joins = []);

    /**
     * Delete a row in the database and die
     * (optionnaly with a message) if it fails
     *
     * @param string $table Table name
     * @param array $where WHERE clause
     * @param string $message Explanation of query (default '')
     * @param array $joins JOINS criteria array
     *
     * @return mysqli_result|boolean Query result handler
     * @since 9.4.0 $joins parameter added
     * @since 9.3
     *
     */
    public function deleteOrDie($table, $where, $message = '', array $joins = []);

    /**
     * Truncate table in the database
     *
     * @param string $table Table name
     *
     * @return mysqli_result|boolean Query result handler
     * @since 10.0.0
     *
     */
    public function truncate($table);

    /**
     * Truncate table in the database or die
     * (optionally with a message) if it fails
     *
     * @param string $table Table name
     * @param string $message Explanation of query (default '')
     *
     * @return mysqli_result|boolean Query result handler
     * @since 10.0.0
     *
     */
    public function truncateOrDie($table, $message = '');

    /**
     * Get database raw version
     *
     * @return string
     */
    public function getVersion();

    /**
     * Starts a transaction
     *
     * @return boolean
     */
    public function beginTransaction();

    public function setSavepoint(string $name, $force = false);

    /**
     * Commits a transaction
     *
     * @return boolean
     */
    public function commit();

    /**
     * Rollbacks a transaction completely or to a specified savepoint
     *
     * @return boolean
     */
    public function rollBack($savepoint = null);

    /**
     * Are we in a transaction?
     *
     * @return boolean
     */
    public function inTransaction();

    /**
     * Defines timezone to use.
     *
     * @param string $timezone
     *
     * @return \DBmysql
     */
    public function setTimezone($timezone);

    /**
     * Returns list of timezones.
     *
     * @return string[]
     *
     * @since 9.5.0
     */
    public function getTimezones();

    /**
     * Clear cached schema information.
     *
     * @return void
     */
    public function clearSchemaCache();

    /**
     * Quote a value for a specified type
     * Should be used for PDO, but this will prevent heavy
     * replacements in the source code in the future.
     *
     * @param mixed $value Value to quote
     * @param integer $type Value type, defaults to PDO::PARAM_STR
     *
     * @return mixed
     *
     * @since 9.5.0
     */
    public function quote($value, int $type = 2);

    /**
     * Remove SQL comments
     * © 2011 PHPBB Group
     *
     * @param string $output SQL statements
     *
     * @return string
     */
    public function removeSqlComments($output);

    /**
     * Remove remarks and comments from SQL
     * @param $string $sql SQL statements
     *
     * @return string
     * @see DBmysql::removeSqlComments()
     * © 2011 PHPBB Group
     *
     */
    public function removeSqlRemarks($sql);

    /**
     * Set charset to use for DB connection.
     *
     * @return void
     */
    public function setConnectionCharset(): void;

    /**
     * Executes a prepared statement
     *
     * @param mysqli_stmt $stmt STatement to execute
     *
     * @return void
     */
    public function executeStatement(mysqli_stmt $stmt): void;

    /**
     * Return configuration boolean properties computed using current state of tables.
     *
     * @return array
     */
    public function getComputedConfigBooleanFlags(): array;
}
