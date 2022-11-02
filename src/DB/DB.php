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

use DBConnection;
use DBmysqlIterator;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Type;
use Glpi\DB\Type\Char;
use Glpi\DB\Type\TinyInt;
use Html;
use Timer;

final class DB
{
    /**
     * Determine if timezones should be used for timestamp fields.
     * Defaults to false to keep backward compatibility with old DB.
     *
     * @var bool
     */
    public const FEATURE_TIMEZONES = 'feature_timezones';

    /**
     * Determine if utf8mb4 should be used for DB connection and tables altering operations.
     * Defaults to false to keep backward compatibility with old DB.
     *
     * @var bool
     */
    public const FEATURE_UTF8MB4 = 'feature_utf8mb4';

    /**
     * Determine if MyISAM engine usage should be allowed for tables creation/altering operations.
     * Defaults to true to keep backward compatibility with old DB.
     *
     * @var bool
     */
    public const FEATURE_MYISAM = 'feature_myisam';

    /**
     * Determine if datetime fields usage should be allowed for tables creation/altering operations.
     * Defaults to true to keep backward compatibility with old DB.
     *
     * @var bool
     */
    public const FEATURE_DATETIMES = 'feature_datetimes';

    /**
     * Determine if warnings related to MySQL deprecations should be logged too.
     * Defaults to false as this option should only on development/test environment.
     *
     * @var bool
     */
    public const FEATURE_LOG_DEPRECATION_WARNINGS = 'feature_log_deprecation_warnings';

    /**
     * @var PrimaryReadReplicaConnection|Connection
     */
    private $connection;

    private $feature_flags;

    private int $affected_rows = 0;

    public bool $connected = false;

    /**
     * To calculate execution time
     * @var bool|int
     */
    public bool $execution_time = false;

    private Timer $timer;

    /**
     * @param Connection $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->connected = $connection instanceof Connection && $connection->isConnected();
        $this->timer = new Timer();
//        $this->feature_flags = [
//            self::FEATURE_TIMEZONES => false,
//            self::FEATURE_UTF8MB4 => false,
//            self::FEATURE_MYISAM => true,
//            self::FEATURE_DATETIMES => true,
//            self::FEATURE_LOG_DEPRECATION_WARNINGS => false,
//        ];
        //TODO These flags should be set from config
        $this->feature_flags = [
            self::FEATURE_TIMEZONES => true,
            self::FEATURE_UTF8MB4 => true,
            self::FEATURE_MYISAM => false,
            self::FEATURE_DATETIMES => false,
            self::FEATURE_LOG_DEPRECATION_WARNINGS => false,
        ];
        $this->addCustomTypes();
    }

    private function addCustomTypes(): void
    {
        Type::addType('tinyint', TinyInt::class);
        Type::addType('char', Char::class);
    }

    public function getFeatureFlag(string $flag): bool
    {
        return $this->feature_flags[$flag] ?? false;
    }

    public function setFeatureFlag(string $flag, bool $value): void
    {
        $this->feature_flags[$flag] = $value;
    }

    public static function getSupportedDrivers(): array
    {
        return [
            'pdo_mysql'          => [
                'name'          => 'MySQL / MariaDB',
                'driver_class'  => PDO\MySQL\Driver::class
            ],
            'pdo_sqlite'         => [
                'name'          => 'SQLite',
                'driver_class'  => PDO\SQLite\Driver::class
            ],
            'pdo_pgsql'          => [
                'name'          => 'PostgreSQL',
                'driver_class'  => PDO\PgSQL\Driver::class
            ],
            'pdo_oci'            => [
                'name'          => 'Oracle',
                'driver_class'  => PDO\OCI\Driver::class
            ],
            'pdo_sqlsrv'         => [
                'name'          => 'Microsoft SQL Server',
                'driver_class'  => PDO\SQLSrv\Driver::class
            ]
        ];
    }

    public function getSchemaManager(): AbstractSchemaManager
    {
        static $schema_manager;
        if (!isset($schema_manager)) {
            try {
                $schema_manager = $this->connection->createSchemaManager();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }
        return $schema_manager;
    }

    public static function establishDBConnection($use_slave = false, $required = true, $display = true) {
        global $DB_PDO;

        $DB_PDO  = null;

        $handle_db_error = static function ($msg, $die = true) use ($display) {
            if ($display) {
                if (!isCommandLine()) {
                    Html::nullHeader("DB Connection Error", '');
                    echo "<div class='center'><p class ='b'>{$msg}</p></div>";
                    Html::nullFooter();
                } else {
                    echo "{$msg}\n";
                }
            }
            if ($die) {
                trigger_error('DB Connection Error: '.$msg, E_USER_ERROR);
            }
        };
        try {
            $config = Config::getFromConfigFile();
            //TODO Configure caching
            $conn_config = (new Configuration())->setMiddlewares([
                new DebugMiddleware()
            ]);
            $connection = DriverManager::getConnection($config, $conn_config);
            $DB_PDO = new self($connection);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $handle_db_error($msg, true);
        }
    }

    public function hasReplicaSupport() {
        return $this->connection instanceof PrimaryReadReplicaConnection;
    }

    public function useReplica()
    {
        if ($this->hasReplicaSupport()) {
            $this->connection->ensureConnectedToReplica();
        }
    }

    public function usePrimary()
    {
        if ($this->hasReplicaSupport()) {
            $this->connection->ensureConnectedToPrimary();
        }
    }

    public function isSlave()
    {
        return false;
    }

    public function insertId()
    {
        return $this->connection->lastInsertId(null);
    }

    public function inTransaction()
    {
        return $this->connection->isTransactionActive();
    }

    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            trigger_error('A database transaction has already been started!', E_USER_WARNING);
        }
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollBack($savepoint = null): bool
    {
        //TODO Doctrine doesn't use named savepoints
        //They aren't used in GLPI and this param was only added for a potential feature of a plugin that was never implemented
        return $this->connection->rollBack();
    }

    public function setGroupConcatMaxLen(int $length): void
    {
        // Only needs done for MySQL/MariaDB
        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->connection->executeStatement("SET SESSION group_concat_max_len = {$length}");
        }
    }

    public function escape($string)
    {
        return $string;
    }

    public function affectedRows(): int
    {
        return $this->affected_rows ?? 0;
    }

    public function quoteIdentifier($identifier): string
    {
        //handle verbatim names
        if ($identifier instanceof \QueryExpression || $identifier instanceof \QueryFunction) {
            return $identifier->getValue();
        }
        if ($identifier === '*') {
            return $identifier;
        }

        $names = preg_split('/\s+AS\s+/i', $identifier);
        if (count($names) > 2) {
            throw new \RuntimeException(
                'Invalid field name ' . $identifier
            );
        }

        if (count($names) == 2) {
            $identifier = self::quoteIdentifier($names[0]);
            $identifier .= ' AS ' . self::quoteIdentifier($names[1]);
            return $identifier;
        }
        if (strpos($identifier, '.')) {
            $n = explode('.', $identifier, 2);
            $table = self::quoteIdentifier($n[0]);
            $field = ($n[1] === '*') ? $n[1] : self::quoteIdentifier($n[1]);
            return "$table.$field";
        }
        return $this->connection->quoteIdentifier($identifier);
    }

    /**
     * @param $name
     * @return string
     * @deprecated Used for DBmysql compatibility only
     */
    public static function quoteName($name)
    {
        global $DB_PDO;
        return $DB_PDO->quoteIdentifier($name);
    }

    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }

    /**
     * @throws Exception
     */
    public function prepare($sql)
    {
        return $this->connection->prepare($sql);
    }

    /**
     * @throws Exception
     */
    public function query($sql, $params = [], $types = []): Result
    {
        if ($this->execution_time) {
            $this->timer->start();
        }
        $result = $this->connection->executeQuery($sql, $params);
        if  ($this->execution_time) {
            $this->execution_time = $this->timer->getTime();
        }
        return $result;
    }

    public function request(array $criteria, bool $debug = false)
    {
        $iterator = new DBmysqlIterator(null);
        $iterator->execute($criteria, $debug);
        return $iterator;
    }

    /**
     * @throws Exception
     */
    public function prepareInsert(string $table, array $data): Statement
    {
        $columns = array_map(fn ($k) => $this->quoteIdentifier($k), array_keys($data));
        $values_placeholders = array_map(static fn ($k) => ":$k", array_keys($data));
        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values_placeholders) . ")";
        return $this->connection->prepare($sql);
    }

    /**
     * @throws Exception
     */
    public function insert(string $table, array $data): int
    {
        $rows = $this->prepareInsert($table, $data)->executeStatement($data);
        $this->affected_rows = $rows;
        return $rows;
    }

    public function insertOrDie(string $table, array $data, string $message = ''): int
    {
        try {
            return $this->insert($table, $data);
        } catch (Exception $e) {
            $message = sprintf(
                __('%1$s - Error during the database query: %2$s'),
                $message,
                $e->getMessage()
            );
            if (isCommandLine()) {
                throw new \RuntimeException($message);
            }

            echo $message . "\n";
            die(1);
        }
    }

    /**
     * @throws Exception
     */
    public function prepareUpdate(string $table, array $data, array $where): Statement
    {
        $sql = "UPDATE $table SET ".implode(', ', array_map(static fn ($key) => "$key = ?", array_keys($data)));
        $sql .= " WHERE " . implode(' AND ', array_map(static fn ($key) => "$key = ?", array_keys($where)));
        return $this->connection->prepare($sql);
    }

    /**
     * @throws Exception
     */
    public function update(string $table, array $data, array $where): int
    {
        $rows = $this->prepareUpdate($table, $data, $where)->executeStatement(array_merge(array_values($data), array_values($where)));
        $this->affected_rows = $rows;
        return $rows;
    }

    public function updateOrDie(string $table, array $data, array $where, string $message = ''): int
    {
        try {
            return $this->update($table, $data, $where);
        } catch (Exception $e) {
            $message = sprintf(
                __('%1$s - Error during the database query: %2$s'),
                $message,
                $e->getMessage()
            );
            if (isCommandLine()) {
                throw new \RuntimeException($message);
            }

            echo $message . "\n";
            die(1);
        }
    }

    public function guessTimezone()
    {
        if ($this->getFeatureFlag(self::FEATURE_TIMEZONES)) {
            if (isset($_SESSION['glpi_tz'])) {
                $zone = $_SESSION['glpi_tz'];
            } else {
                $conf_tz = ['value' => null];
                $config_table = \Config::getTable();
                if ($this->tableExists($config_table) && $this->fieldExists($config_table, 'value')) {
                    $conf_tz = $this->connection->fetchOne('SELECT value FROM '.$config_table.' WHERE context = "core" AND name = "timezone"');
                }
                $zone = !empty($conf_tz['value']) ? $conf_tz['value'] : date_default_timezone_get();
            }
        } else {
            $zone = date_default_timezone_get();
        }

        return $zone;
    }

    public function listTables($table_name_pattern = 'glpi_.+')
    {
        $tables = [];
        $schema_manager = $this->getSchemaManager();
        if ($schema_manager !== null) {
            try {
                $all_tables = $schema_manager->listTables();
                $tables = array_filter($all_tables, static function ($table) use ($table_name_pattern) {
                    return preg_match('/^'.$table_name_pattern.'$/', $table->getName());
                });
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }
        return $tables;
    }

    /**
     * @param bool $exclude_plugins
     * @return Table[]
     */
    public function getMyIsamTables(bool $exclude_plugins = false): array
    {
        $tables = $this->listTables();
        if ($exclude_plugins) {
            $tables = array_filter($tables, static function($table) {
                return !preg_match('/^glpi_plugin_.+/', $table->getName());
            });
        }
        $tables = array_filter($tables, static function($table) {
            return $table->getOption('engine') === 'MyISAM';
        });

        return $tables;
    }

    /**
     * @param bool $exclude_plugins
     * @return Table[]
     */
    public function getNonUtf8mb4Tables(bool $exclude_plugins = false): array
    {
        $tables = $this->listTables();
        if ($exclude_plugins) {
            $tables = array_filter($tables, static function($table) {
                return !preg_match('/^glpi_plugin_.+/', $table->getName());
            });
        }
        $tables = array_filter($tables, static function($table) {
            $columns = $table->getColumns();
            $columns = array_filter($columns, static function($column) {
                return $column->getOption('collation') !== 'utf8mb4_unicode_ci';
            });
            return $table->getOption('collate') === 'utf8mb4_unicode_ci' || count($columns) > 0;
        });

        return $tables;
    }

    /**
     * @param bool $exclude_plugins
     * @return Table[]
     */
    public function getTzIncompatibleTables(bool $exclude_plugins = false): array
    {
        $tables = $this->listTables();
        if ($exclude_plugins) {
            $tables = array_filter($tables, static function($table) {
                return !preg_match('/^glpi_plugin_.+/', $table->getName());
            });
        }
        $tables = array_filter($tables, static function($table) {
            $columns = $table->getColumns();
            $columns = array_filter($columns, static function($column) {
                return $column->getType()->getName() === 'datetime';
            });
            return count($columns) > 0;
        });

        return $tables;
    }

    /**
     * @return Column[]
     */
    public function getSignedKeysColumns(): array
    {
        $columns = [];
        $tables = $this->listTables();
        foreach ($tables as $table) {
            $columns = array_filter($table->getColumns(), static function($column) {
                return $column->getOption('unsigned') === false;
            });
        }

        return $columns;
    }

    /**
     * @return ForeignKeyConstraint[]
     */
    public function getForeignKeysContraints(): array
    {
        $constraints = [];
        $tables = $this->listTables();
        foreach ($tables as $table) {
            $constraints = array_merge($constraints, $table->getForeignKeys());
        }

        return $constraints;
    }

    /**
     * @param $table
     * @return false|Column[]
     */
    public function listFields($table)
    {
        $tables = $this->listTables();
        $table = array_filter($tables, static function($t) use ($table) {
            return $t->getName() === $table;
        });
        if (count($table) === 0) {
            return false;
        }
        $table = reset($table);
        return $table->getColumns();
    }

    /**
     * @param string $table
     * @param string $field
     * @return Column|null
     */
    public function getField(string $table, string $field): ?Column
    {
        $fields = $this->listFields($table);
        $field = array_filter($fields, static function($f) use ($field) {
            return $f->getName() === $field;
        });
        if (count($field) === 0) {
            return null;
        }
        return reset($field);
    }

    public function close()
    {
        $this->connection->close();
    }

    public function getInfo()
    {
        // No translation, used in sysinfo
        $ret = [];
        $driver = $this->getDriver();
        $platform = $this->connection->getDatabasePlatform();

        if ($driver instanceof Driver\AbstractMySQLDriver) {
            $req = $this->query("SELECT @@sql_mode as mode, @@version AS vers, @@version_comment AS stype")->fetchAllAssociative();
        } else {
            $req = $this->query("SELECT version() AS vers")->fetchAllAssociative();
        }
        $data = reset($req);

        if (!isset($data['stype'])) {
            if ($platform instanceof AbstractMySQLPlatform) {
                $data['stype'] = $platform instanceof MariaDBPlatform ? 'MariaDB' : 'MySQL';
            } else if ($platform instanceof PostgreSQLPlatform) {
                $data['stype'] = 'PostgreSQL';
            } else if ($platform instanceof SqlitePlatform) {
                $data['stype'] = 'SQLite';
            } else if ($platform instanceof SQLServerPlatform) {
                $data['stype'] = 'SQLServer';
            } else if ($platform instanceof OraclePlatform) {
                $data['stype'] = 'Oracle';
            } else {
                $data['stype'] = 'Unknown';
            }
        }

        $ret['Server Software'] = $data['stype'];
        $ret['Server Version'] = $data['vers'];
        if (isset($data['mode'])) {
            $ret['Server SQL Mode'] = $data['mode'];
        } else {
            $ret['Server SQL Mode'] = '';
        }

        //$ret['Parameters'] = $this->dbuser . "@" . $this->dbhost . "/" . $this->dbdefault;
        //$ret['Host info']  = $this->dbh->host_info;

        return $ret;
    }

    public function getLock($name)
    {
        // TODO: Implement getLock() method.
    }

    public function releaseLock($name)
    {
        // TODO: Implement releaseLock() method.
    }

    public function tableExists($tablename, $usecache = true)
    {
        $tables = $this->listTables();
        $table = array_filter($tables, static function($table) use ($tablename) {
            return $table->getName() === $tablename;
        });
        return count($table) > 0;
    }

    public function fieldExists($table, $field, $usecache = true)
    {
        static $fields = [];

        if (!array_key_exists($table, $fields) || !$usecache) {
            $fields[$table] = $this->listFields($table);
        }
        if (isset($fields[$table]) && is_array($fields[$table])) {
            $field = array_filter($fields[$table], static function ($f) use ($field) {
                return $f->getName() === $field;
            });
            return count($field) > 0;
        }
        return false;
    }

    public function getVersion()
    {
        return $this->connection->fetchOne('SELECT version()');
    }

    public function setTimezone($timezone)
    {
        if ($this->getFeatureFlag(self::FEATURE_TIMEZONES)) {
            date_default_timezone_set($timezone);
            $this->connection->executeQuery('SET SESSION time_zone = ?', [$timezone]);
            $_SESSION['glpi_currenttime'] = date("Y-m-d H:i:s");
        }
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function getTimezones()
    {
        if (!$this->getFeatureFlag(self::FEATURE_TIMEZONES)) {
            return [];
        }
        $list = []; //default $tz is empty

        $from_php = \DateTimeZone::listIdentifiers();
        $now = new \DateTime();

        if ($this->connection->getDriver() instanceof PDO\MySQL\Driver) {
            $result = $this->connection->fetchAllAssociative('SELECT Name as name FROM mysql.time_zone_name WHERE name IN ?', [$from_php]);
        } else if ($this->connection->getDriver() instanceof PDO\PostgreSQL\Driver) {
            $result = $this->connection->fetchAllAssociative('SELECT name FROM pg_timezone_names WHERE name IN ?', [$from_php]);
        } else {
            throw new Exception('Unsupported driver');
        }
        foreach ($result as $from_mysql) {
            $now->setTimezone(new \DateTimeZone($from_mysql['name']));
            $list[$from_mysql['name']] = $from_mysql['name'] . $now->format(" (T P)");
        }

        return $list;
    }

    /**
     * Return configuration boolean properties computed using current state of tables.
     *
     * @return array
     */
    public function getComputedConfigBooleanFlags(): array
    {
        $config_flags = [];

        if (count($this->getTzIncompatibleTables(true)) === 0) {
            // Disallow datetime if there is no core table still using this field type.
            $config_flags[DBConnection::PROPERTY_ALLOW_DATETIME] = false;

            //TODO
        }

        if (count($this->getNonUtf8mb4Tables(true)) === 0) {
            // Use utf8mb4 charset for update process if there all core table are using this charset.
            $config_flags[DBConnection::PROPERTY_USE_UTF8MB4] = true;
        }

        if (count($this->getMyIsamTables(true)) === 0) {
            // Disallow MyISAM if there is no core table still using this engine.
            $config_flags[DBConnection::PROPERTY_ALLOW_MYISAM] = false;
        }

        return $config_flags;
    }

    /**
     * Remove SQL comments
     * © 2011 PHPBB Group
     *
     * @param string $sql SQL statements
     *
     * @return string
     */
    private function removeSqlComments(string $sql): string
    {
        $lines = explode("\n", $sql);
        $output = "";

        // try to keep mem. use down
        $linecount = count($lines);

        $in_comment = false;
        for ($i = 0; $i < $linecount; $i++) {
            if (preg_match("/^\/\*/", $lines[$i])) {
                $in_comment = true;
            }

            if (!$in_comment) {
                $output .= $lines[$i] . "\n";
            }

            if (preg_match("/\*\/$/", preg_quote($lines[$i], '/'))) {
                $in_comment = false;
            }
        }

        unset($lines);
        return trim($output);
    }

    private function removeSqlRemarks(string $sql): string
    {
        $lines = explode("\n", $sql);

        // try to keep mem. use down
        $sql = "";

        $linecount = count($lines);
        $output = "";

        for ($i = 0; $i < $linecount; $i++) {
            if (($i != ($linecount - 1)) || (strlen($lines[$i]) > 0)) {
                if (isset($lines[$i][0])) {
                    if ($lines[$i][0] != "#" && substr($lines[$i], 0, 2) != "--") {
                        $output .= $lines[$i] . "\n";
                    } else {
                        $output .= "\n";
                    }
                }
                // Trading a bit of speed for lower mem. use here.
                $lines[$i] = "";
            }
        }
        return trim($this->removeSqlComments($output));
    }

    /**
     * @return Driver The driver used by the current connection. If the driver is the {@link DebugDriver} wrapper, the wrapped driver is returned.
     */
    public function getDriver(): Driver
    {
        $driver = $this->connection->getDriver();
        if ($driver instanceof DebugDriver) {
            $driver = $driver->getDriver();
        }
        return $driver;
    }

    public function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    public function disableForeignKeyChecks()
    {
        $driver = $this->getDriver();
        if ($driver instanceof PDO\MySQL\Driver) {
            $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
        } else if ($driver instanceof PDO\PgSQL\Driver) {
            $this->connection->executeQuery('SET CONSTRAINTS ALL DEFERRED');
        } else {
            throw new Exception('Unsupported driver');
        }
    }

    public function enableForeignKeyChecks()
    {
        $driver = $this->getDriver();
        if ($driver instanceof PDO\MySQL\Driver) {
            $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS = 1');
        } else if ($driver instanceof PDO\PgSQL\Driver) {
            $this->connection->executeQuery('SET CONSTRAINTS ALL IMMEDIATE');
        } else {
            throw new Exception('Unsupported driver');
        }
    }

    public function syncAutoIncrementSequence(string $table, string $field): void
    {
        $driver = $this->getDriver();
        if ($driver instanceof PDO\MySQL\Driver) {
            //MySQL and MariaDB seem to not need this
            //$this->connection->executeQuery("ALTER TABLE `$table` AUTO_INCREMENT = (SELECT MAX(`$field`) FROM `$table`)");
        } else if ($driver instanceof PDO\PgSQL\Driver) {
            $this->connection->executeQuery("SELECT setval(pg_get_serial_sequence('$table', '$field'), coalesce(max(id),0) + 1, false) FROM $table;");
        } else {
            throw new Exception('Unsupported driver');
        }
    }

    /**
     * Execute all SQL queries from a file.
     * @param string $file_path Path to the file containing the SQL queries.
     * @return bool True if all queries were executed successfully, false otherwise.
     */
    public function runFile(string $file_path): bool
    {
        try {
            $script = fopen($file_path, 'r');
            if (!$script) {
                return false;
            }
            $sql_query = @fread(
                    $script,
                    @filesize($file_path)
                ) . "\n";
            $sql_query = html_entity_decode($sql_query, ENT_COMPAT, 'UTF-8');

            $sql_query = $this->removeSqlRemarks($sql_query);
            $queries = preg_split('/;\s*$/m', $sql_query);

            foreach ($queries as $sql) {
                $sql = trim($sql);
                if ($sql !== '') {
                    $sql = htmlentities($sql, ENT_COMPAT, 'UTF-8');
                    $this->connection->executeQuery($sql);
                    if (!isCommandLine()) {
                        // Flush will prevent proxy to timeout as it will receive data.
                        // Flush requires a content to be sent, so we sent spaces as multiple spaces
                        // will be shown as a single one on browser.
                        echo ' ';
                        Html::glpi_flush();
                    }
                }
            }
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
