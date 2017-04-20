<?php

namespace Hail\Factory;

use Hail\Cache\CacheItemPoolInterface;
use Hail\Cache\HierarchicalCachePool;
use Hail\Cache\NamespacedCachePool;
use Hail\Cache\Simple\{
    CacheInterface,
    Chain
};
use Hail\Facade\{
    Config, Serialize
};


class Cache extends Factory
{
    /**
     * @param array $config
     *
     * @return CacheInterface
     */
    public static function simple(array $config = []): CacheInterface
    {
        $config += Config::get('cache.simple');
        $hash = sha1(Serialize::encode($config));

        if (isset(static::$pool[$hash])) {
            return static::$pool[$hash];
        }

        if (isset($config['drivers'])) {
            $drivers = $config['drivers'];
            if (count($drivers) > 1) {
                return static::$pool[$hash] = new Chain($config);
            }

            unset($config['drivers']);

            $driver = key($drivers);
            $config += $drivers[$driver];
        } else {
            $driver = $config['driver'] ?? 'void';
            unset($config['driver']);
        }

        switch ($driver) {
            case 'array':
            case 'zend':
                $driver = ucfirst($driver) . 'Data';
                break;
            case 'apc':
                $driver = 'Apcu';
                break;
            default:
                $driver = ucfirst($driver);
        }

        $class = 'Hail\\Cache\\Simple\\' . $driver;

        return static::$pool[$hash] = new $class($config);
    }

    public static function pool(array $config = []): CacheItemPoolInterface
    {
        $config += Config::get('cache.pool');
        $hash = sha1(Serialize::encode($config));

        if (isset(static::$pool[$hash])) {
            return static::$pool[$hash];
        }

        $cache = static::simple($config['config']);

        $class = 'Hail\\Cache\\' . ucfirst(strtolower($config['driver'])) . 'CachePool';
        if (!class_exists($class)) {
            throw new \LogicException("PSR6 cache adapter {$config['adapter']} not defined! ");
        }

        if ($class === NamespacedCachePool::class) {
            $namespace = (string) ($config['namespace'] ?? '');
            if ($namespace !== '') {
                return static::$pool[$hash] = new NamespacedCachePool($cache, $namespace);
            }

            $class = HierarchicalCachePool::class;
        }

        return static::$pool[$hash] = new $class($cache);
    }
}
