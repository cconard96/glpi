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

use DBConnection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver\PDO;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Glpi\Application\ErrorHandler;
use Glpi\Cache\CacheManager;
use Glpi\System\Requirement\DbTimezones;
use Html;
use Psr\Cache\CacheItemPoolInterface;
use Timer;
use Toolbox;

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

    /**
     * @param Connection $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
//        $this->feature_flags = [
//            self::FEATURE_TIMEZONES => false,
//            self::FEATURE_UTF8MB4 => false,
//            self::FEATURE_MYISAM => true,
//            self::FEATURE_DATETIMES => true,
//            self::FEATURE_LOG_DEPRECATION_WARNINGS => false,
//        ];
        $this->feature_flags = [
            self::FEATURE_TIMEZONES => true,
            self::FEATURE_UTF8MB4 => true,
            self::FEATURE_MYISAM => false,
            self::FEATURE_DATETIMES => false,
            self::FEATURE_LOG_DEPRECATION_WARNINGS => false,
        ];
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

    public static function establishDBConnection($use_slave, $required, $display = true) {
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
            $connection = DriverManager::getConnection($config);
            $conn_config = $connection->getConfiguration();
            if ($conn_config !== null) {
                //TODO Configure caching
                $conn_config->setMiddlewares([
                    new DebugMiddleware(),
                ]);
            }
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

    public function query($sql, $params = [], $types = [])
    {
        return $this->connection->executeQuery($sql, $params, $types);
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
        //TODO
        return [];
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
        $fields = $this->listFields($table);
        $field = array_filter($fields, static function($f) use ($field) {
            return $f->getName() === $field;
        });
        return count($field) > 0;
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
}
