<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
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

namespace Glpi\Cache;

use DirectoryIterator;
use Glpi\Kernel\Kernel;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Safe\Exceptions\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Toolbox;

use function Safe\glob;
use function Safe\json_encode;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\rmdir;
use function Safe\unlink;

class CacheManager
{
    /**
     * GLPI core cache context.
     * @var string
     */
    public const CONTEXT_CORE = 'core';

    /**
     * GLPI translations cache context.
     * @var string
     */
    public const CONTEXT_TRANSLATIONS = 'translations';

    /**
     * Memcached scheme.
     * @var string
     */
    public const SCHEME_MEMCACHED  = 'memcached';

    /**
     * Redis scheme (TCP connection).
     * @var string
     */
    public const SCHEME_REDIS      = 'redis';

    /**
     * Redis scheme (TLS connection).
     * @var string
     */
    public const SCHEME_REDISS     = 'rediss';

    /**
     * Core cache configuration filename.
     * @var string
     */
    public const CONFIG_FILENAME = 'cache.php';

    /**
     * Configuration directory.
     *
     * @var string
     */
    private $config_dir;

    /**
     * Cache directory.
     *
     * @var string
     */
    private $cache_dir;

    public function __construct(string $config_dir = GLPI_CONFIG_DIR, ?string $cache_dir = null)
    {
        if ($cache_dir === null) {
            $cache_dir = Kernel::getCacheRootDir();
        }

        $this->config_dir = $config_dir;
        $this->cache_dir = $cache_dir;
    }

    /**
     * Defines cache namespace prefix.
     *
     * @param string $namespace_prefix
     *
     * @return bool
     */
    public function setNamespacePrefix(string $namespace_prefix): bool
    {
        $config = $this->getRawConfig();
        $config['namespace_prefix'] = $namespace_prefix ?: null;

        return $this->writeConfig($config);
    }

    /**
     * Defines cache configuration for given context.
     *
     * @param string          $context
     * @param string|string[] $dsn
     * @param array           $options
     *
     * @return bool
     */
    public function setConfiguration(string $context, $dsn, array $options = []): bool
    {
        if (!$this->isContextValid($context, true)) {
            throw new InvalidArgumentException(sprintf('Invalid or non configurable context: "%s".', $context));
        }
        if (!$this->isDsnValid($dsn)) {
            throw new InvalidArgumentException(sprintf('Invalid DSN: %s.', json_encode($dsn, JSON_UNESCAPED_SLASHES)));
        }

        $config = $this->getRawConfig();
        $config['contexts'][$context] = [
            'dsn'       => $dsn,
            'options'   => $options,
        ];

        return $this->writeConfig($config);
    }

    /**
     * Unset cache configuration for given context.
     *
     * @param string $context
     *
     * @return bool
     */
    public function unsetConfiguration(string $context): bool
    {
        if (!$this->isContextValid($context, true)) {
            throw new InvalidArgumentException(sprintf('Invalid or non configurable context: "%s".', $context));
        }

        $config = $this->getRawConfig();
        unset($config['contexts'][$context]);

        return $this->writeConfig($config);
    }

    /**
     * Test connection to given DSN. Conection failure will trigger an exception.
     *
     * @param string|string[] $dsn
     * @param array           $options
     *
     * @return void
     */
    public function testConnection($dsn, array $options = []): void
    {
        switch ($this->extractScheme($dsn)) {
            case self::SCHEME_MEMCACHED:
                // Init Memcached connection to find potential connection errors.
                $client = MemcachedAdapter::createConnection($dsn, $options);
                $stats = $client->getStats();
                if ($stats === false) {
                    // Memcached::getStats() will return false if server cannot be reached.
                    throw new RuntimeException('Unable to connect to Memcached server.');
                }
                break;
            case self::SCHEME_REDIS:
            case self::SCHEME_REDISS:
                // Init Redis connection to find potential connection errors.
                $options['lazy'] = false; //force instant connection
                RedisAdapter::createConnection($dsn, $options);
                break;
            default:
                break;
        }
    }

    /**
     * Get cache instance for given context.
     *
     * @param string $context
     *
     * @return CacheInterface
     */
    public function getCacheInstance(string $context): CacheInterface
    {
        return new SimpleCache($this->getCacheStorageAdapter($context));
    }

    /**
     * Get cache storage adapter for given context.
     *
     * @return CacheItemPoolInterface
     */
    public function getCacheStorageAdapter(string $context): CacheItemPoolInterface
    {
        /** @var LoggerInterface $PHPLOGGER */
        global $PHPLOGGER;

        if (!$this->isContextValid($context)) {
            throw new InvalidArgumentException(sprintf('Invalid context: "%s".', $context));
        }

        $raw_config = $this->getRawConfig();

        $namespace_prefix = $raw_config['namespace_prefix'] ?? '';
        if (!empty($namespace_prefix)) {
            $namespace_prefix .= '-';
        }

        if ($context === self::CONTEXT_TRANSLATIONS) {
            // 'translations' context is not supposed to be configured
            // and should always use a filesystem adapter.
            $namespace = $this->normalizeNamespace($namespace_prefix . $context);
            $adapter = new FilesystemAdapter($namespace, 0, $this->cache_dir);
        } elseif (!array_key_exists($context, $raw_config['contexts'])) {
            // Default to filesystem, in a different directory for each context.
            $adapter = new FilesystemAdapter($this->normalizeNamespace($namespace_prefix . $context), 0, $this->cache_dir);
        } else {
            $context_config = $raw_config['contexts'][$context];

            $dsn       = $context_config['dsn'];
            $options   = $context_config['options'] ?? [];
            $scheme    = $this->extractScheme($dsn);
            $namespace = $this->normalizeNamespace($namespace_prefix . $context);

            switch ($scheme) {
                case self::SCHEME_MEMCACHED:
                    $adapter = new MemcachedAdapter(
                        MemcachedAdapter::createConnection($dsn, $options),
                        $namespace
                    );
                    break;

                case self::SCHEME_REDIS:
                case self::SCHEME_REDISS:
                    $adapter = new RedisAdapter(
                        RedisAdapter::createConnection($dsn, $options),
                        $namespace
                    );
                    break;

                default:
                    throw new RuntimeException(sprintf('Invalid cache DSN %s.', var_export($dsn, true)));
            }
        }

        $adapter->setLogger($PHPLOGGER);

        return $adapter;
    }

    /**
     * Get core cache instance.
     *
     * @return CacheInterface
     */
    public function getCoreCacheInstance(): CacheInterface
    {

        return $this->getCacheInstance(self::CONTEXT_CORE);
    }

    /**
     * Get translations cache instance.
     *
     * @return CacheInterface
     */
    public function getTranslationsCacheInstance(): CacheInterface
    {
        return $this->getCacheInstance(self::CONTEXT_TRANSLATIONS);
    }

    /**
     * Reset all caches.
     *
     * @return bool
     */
    public function resetAllCaches(): bool
    {

        $success = true;

        // Clear all cache contexts
        $known_contexts = $this->getKnownContexts();
        foreach ($known_contexts as $context) {
            $success = $this->getCacheInstance($context)->clear() && $success;
        }

        // Clear Symfony cache
        $this->clearSymfonyCache();

        // Clear compiled templates
        $tpl_cache_dir = $this->cache_dir . '/templates';
        if (file_exists($tpl_cache_dir)) {
            $tpl_files = glob($tpl_cache_dir . '/**/*.php');
            foreach ($tpl_files as $tpl_file) {
                try {
                    unlink($tpl_file);
                } catch (FilesystemException $e) {
                    $success = false;
                }
            }

            $tpl_dirs = glob($tpl_cache_dir . '/*', GLOB_ONLYDIR);
            foreach ($tpl_dirs as $tpl_dir) {
                try {
                    rmdir($tpl_dir);
                } catch (FilesystemException $e) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Return list of all know cache contexts.
     *
     * @return string[]
     */
    public function getKnownContexts(): array
    {
        // Core contexts
        $contexts = [
            self::CONTEXT_CORE,
            self::CONTEXT_TRANSLATIONS,
        ];

        // Contexts defined in configuration.
        // These may not be find in directories if they are configured to use a remote service.
        $config = $this->getRawConfig();
        array_push($contexts, ...array_keys($config['contexts']));

        // Context found from cache directories.
        // These may not be find in configuration if they are using default configuration.
        $directory_iterator = new DirectoryIterator($this->cache_dir);
        foreach ($directory_iterator as $file) {
            if ($file->isDot() || !$file->isDir() || !preg_match('/^plugin_/', $file->getFilename())) {
                continue;
            }

            $context = preg_replace('/^plugin_([a-zA-Z]+)$/', 'plugin:$1', $file->getFilename());
            if ($this->isContextValid($context)) {
                $contexts[] = $context;
            }
        }

        return array_unique($contexts);
    }

    /**
     * Extract scheme from DSN.
     *
     * @param string|string[] $dsn
     *
     * @return string|null
     */
    public function extractScheme($dsn): ?string
    {
        if (is_array($dsn)) {
            if (count($dsn) === 0) {
                return null;
            }

            $schemes = [];
            foreach ($dsn as $entry) {
                $schemes[] = $this->extractScheme($entry);
            }
            $schemes = array_unique($schemes);

            if (count($schemes) !== 1) {
                return null; // Mixed schemes are not allowed
            }
            $scheme = reset($schemes);
            // Only Memcached system accept multiple DSN.
            return $scheme === self::SCHEME_MEMCACHED ? $scheme : null;
        }

        if (!is_string($dsn)) {
            return null;
        }

        $matches = [];
        if (preg_match('/^(?<scheme>[a-z]+):\/\//', $dsn, $matches) !== 1) {
            return null;
        }
        $scheme = $matches['scheme'];

        return in_array($scheme, array_keys(static::getAvailableAdapters())) ? $scheme : null;
    }

    /**
     * Returns raw configuration from configuration file.
     *
     * @return array
     */
    private function getRawConfig(): array
    {
        $config_file = $this->config_dir . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME;

        $config = [];
        if (file_exists($config_file)) {
            $config  = include($config_file);
            $contexts = $config['contexts'] ?? [];
            foreach ($contexts as $context => $context_config) {
                if (!$this->isContextValid($context, true)) {
                    trigger_error(sprintf('Invalid or non configurable context: "%s".', $context), E_USER_NOTICE);
                    unset($config['contexts'][$context]);
                    continue;
                }
                if (
                    !is_array($context_config)
                    || !array_key_exists('dsn', $context_config)
                    || !$this->isDsnValid($context_config['dsn'])
                    || (array_key_exists('options', $context_config) && !is_array($context_config['options']))
                ) {
                    trigger_error(sprintf('Invalid configuration for cache context "%s".', $context), E_USER_WARNING);
                    unset($config['contexts'][$context]);
                    continue;
                }
            }
        }

        if (!array_key_exists('contexts', $config)) {
            $config['contexts'] = [];
        }

        return $config;
    }

    /**
     * Write cache configuration to disk.
     *
     * @param array $config
     *
     * @return bool
     */
    private function writeConfig(array $config): bool
    {
        $config_export = var_export($config, true);

        $config_file_contents = <<<PHP
<?php
return {$config_export};
PHP;

        return Toolbox::writeConfig(self::CONFIG_FILENAME, $config_file_contents, $this->config_dir);
    }

    /**
     * Check if context key is valid.
     *
     * @param string $context
     * @param bool $only_configurable
     *
     * @return bool
     */
    public function isContextValid(string $context, bool $only_configurable = false): bool
    {
        $core_contexts = ['core'];

        if (!$only_configurable) {
            // 'translations' cache storage cannot not be configured (it always use the filesystem storage)
            $core_contexts[] = self::CONTEXT_TRANSLATIONS;
        }

        return in_array($context, $core_contexts, true) || preg_match('/^plugin:\w+$/', $context) === 1;
    }

    /**
     * Check if DSN is valid.
     *
     * @param string|string[] $dsn
     *
     * @return bool
     */
    public function isDsnValid($dsn): bool
    {
        if (is_array($dsn)) {
            if (count($dsn) === 0) {
                return false;
            }

            $schemes = [];
            foreach ($dsn as $entry) {
                $schemes[] = $this->extractScheme($entry);
            }
            $schemes = array_unique($schemes);

            if (count($schemes) !== 1) {
                return false; // Mixed schemes are not allowed
            }

            // Only Memcached system accept multiple DSN.
            return reset($schemes) === self::SCHEME_MEMCACHED;
        }

        return in_array($this->extractScheme($dsn), array_keys(static::getAvailableAdapters()));
    }

    /**
     * Normalize namespace to prevent usage of reserved chars.
     *
     * @param string $namespace
     *
     * @return string
     */
    private function normalizeNamespace(string $namespace): string
    {
        return preg_replace(
            '/[' . preg_quote(CacheItem::RESERVED_CHARACTERS, '/') . ']/',
            '_',
            $namespace
        );
    }

    /**
     * Returns a list of available adapters.
     * Keys are adapter schemes (see self::SCHEME_*).
     * Values are translated names.
     *
     * @return array
     */
    public static function getAvailableAdapters(): array
    {
        return [
            self::SCHEME_MEMCACHED  => __('Memcached'),
            self::SCHEME_REDIS      => __('Redis (TCP)'),
            self::SCHEME_REDISS     => __('Redis (TLS)'),
        ];
    }

    /**
     * Clears the Symfony cache.
     */
    private function clearSymfonyCache(): void
    {
        /** @var Kernel|null $kernel */
        global $kernel;

        $localKernel = $kernel;

        if (!$localKernel instanceof Kernel) {
            // This must be usable in non-kernel contexts, env vars will get the proper Kernel env.
            $localKernel = new Kernel();
        }

        // Execute the `cache:clear` command provided by Symfony itself, not our own `cache:clear` command.
        // This command will clear the Symfony cache gracefully.
        $app = new Application($localKernel);
        $app->setAutoExit(false);
        $app->run(new ArrayInput(['command' => 'cache:clear']), new NullOutput());
    }
}
