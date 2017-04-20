<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Hail\Cache;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class HierarchicalCachePool extends SimpleCachePool
{
    const HIERARCHY_SEPARATOR = '|';

    /**
     * A temporary cache for keys.
     *
     * @type array
     */
    protected $keyCache = [];

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        return $this->cache->getDirectValue($this->getHierarchyKey($key));
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $keyString = $this->getHierarchyKey($key, $path);
        if ($path) {
            $index = (int) $this->cache->get($path, 0);
            $this->cache->set($path, ++$index, 0);
        }
        $this->clearHierarchyKeyCache();

        return $this->cache->delete($keyString);
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(CacheItemInterface $item)
    {
        $this->cache->setDirectValue(
            $this->getHierarchyKey($item->getKey()),
            $item->get(),
            $item->getTags(),
            $item->getExpirationTimestamp()
        );
    }

    /**
     * Get a key to use with the hierarchy. If the key does not start with HierarchicalPoolInterface::SEPARATOR
     * this will return an unalterered key. This function supports a tagged key. Ie "foo:bar".
     *
     * @param string $key      The original key
     * @param string &$pathKey A cache key for the path. If this key is changed everything beyond that path is changed.
     *
     * @return string
     */
    protected function getHierarchyKey($key, &$pathKey = null)
    {
        if (!$this->isHierarchyKey($key)) {
            return $key;
        }

        $key = $this->explodeKey($key);

        $keyString = '';
        // The comments below is for a $key = ["foo!tagHash", "bar!tagHash"]
        foreach ($key as $name) {
            // 1) $keyString = "foo!tagHash"
            // 2) $keyString = "foo!tagHash![foo_index]!bar!tagHash"
            $keyString .= $name;
            $pathKey = sha1('path' . self::SEPARATOR_TAG . $keyString);

            if (isset($this->keyCache[$pathKey])) {
                $index = $this->keyCache[$pathKey];
            } else {
                $index = $this->cache->get($pathKey);
                $this->keyCache[$pathKey] = $index;
            }

            // 1) $keyString = "foo!tagHash![foo_index]!"
            // 2) $keyString = "foo!tagHash![foo_index]!bar!tagHash![bar_index]!"
            $keyString .= self::SEPARATOR_TAG . $index . self::SEPARATOR_TAG;
        }

        // Assert: $pathKey = "path!foo!tagHash![foo_index]!bar!tagHash"
        // Assert: $keyString = "foo!tagHash![foo_index]!bar!tagHash![bar_index]!"

        // Make sure we do not get awfully long (>250 chars) keys
        return sha1($keyString);
    }

    /**
     * Clear the cache for the keys.
     */
    protected function clearHierarchyKeyCache()
    {
        $this->keyCache = [];
    }

    /**
     * A hierarchy key MUST begin with the separator.
     *
     * @param string $key
     *
     * @return bool
     */
    private function isHierarchyKey(string $key)
    {
        return $key[0] === self::HIERARCHY_SEPARATOR;
    }

    /**
     * This will take a hierarchy key ("|foo|bar") with tags ("|foo|bar!tagHash") and return an array with
     * each level in the hierarchy appended with the tags. ["foo!tagHash", "bar!tagHash"].
     *
     * @param string $string
     *
     * @return array
     */
    private function explodeKey(string $string)
    {
        list($key, $tag) = explode(self::SEPARATOR_TAG, $string . self::SEPARATOR_TAG);

        if ($key === self::HIERARCHY_SEPARATOR) {
            $parts = ['root'];
        } else {
            $parts = explode(self::HIERARCHY_SEPARATOR, $key);
            // remove first element since it is always empty and replace it with 'root'
            $parts[0] = 'root';
        }

        return array_map(function ($level) use ($tag) {
            return $level . self::SEPARATOR_TAG . $tag;
        }, $parts);
    }
}
