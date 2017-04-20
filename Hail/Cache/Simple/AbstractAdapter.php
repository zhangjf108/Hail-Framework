<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Hail\Cache\Simple;

use Hail\Cache\SimpleCachePool;
use Hail\Util\ArrayTrait;

use Hail\Cache\Simple\Exception\InvalidArgumentException;

/**
 * Base class for cache provider implementations.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
abstract class AbstractAdapter implements CacheInterface
{
	use ArrayTrait;

	/**
	 * @var string control characters for keys, reserved by PSR-16
	 */
	const PSR16_RESERVED = '/\{|\}|\(|\)|\/|\\\\|\@|\:/u';

	const CACHE_KEY = 'CacheVersion';

	const SEPARATOR = '#';

	/**
	 * The namespace to prefix all cache ids with.
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * The namespace version.
	 *
	 * @var integer|null
	 */
	private $namespaceVersion;

	private $pool;

	/**
	 * @var int
	 */
	protected $ttl;

	public function __construct($params)
	{
		$this->ttl = $this->convertTtl($params['ttl'] ?? 0);
		$this->setNamespace($params['namespace'] ?? '');
	}

	public function getPool()
	{
		return $this->pool ?? $this->pool = new SimpleCachePool($this);
	}

	/**
	 * Sets the namespace to prefix all cache ids with.
	 *
	 * @param string $namespace
	 *
	 * @return void
	 */
	public function setNamespace(string $namespace)
	{
		$this->namespace = $namespace;
		$this->namespaceVersion = null;
	}

	/**
	 * Retrieves the namespace that prefixes all cache ids.
	 *
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}

	protected function convertTtl($ttl)
	{
		if ($ttl === null) {
			return null;
		}

		if ($ttl instanceof \DateInterval) {
			$ttl = $ttl->s
				+ $ttl->i * 60
				+ $ttl->h * 3600
				+ $ttl->d * 86400
				+ $ttl->m * 2592000
				+ $ttl->y * 31536000;
		}

		return $ttl > 0 ? $ttl : 0;
	}

	protected function ttl($ttl)
	{
		$expire = $this->convertTtl($ttl);
		$ttl = $expire ?? $this->ttl;

		if ($expire !== null && $expire > 0) {
			$expire += NOW;
		}

		return [$ttl, $expire];
	}

	protected function expireToTtl($expire)
	{
		if ($expire === 0) {
			return 0;
		}

		if ($expire === null) {
			return $this->ttl;
		}

		return $expire - NOW;
	}

	/**
	 * Fetches an entry from the cache.
	 *
	 * @param string $key The id of the cache entry to fetch.
	 * @param mixed  $default
	 *
	 * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
	 */
	public function get($key, $default = null)
	{
		[$found, $value] = $this->doGet($this->getNamespacedKey($key)) ?? [false, null];

		return $found === true ? $value : $default;
	}

	public function getDirectValue(string $key)
	{
		return $this->doGet($this->getNamespacedKey($key)) ?? [false, null, [], null];
	}

	/**
	 * Returns an associative array of values for keys is found in cache.
	 *
	 * @param string[] $keys Array of keys to retrieve from cache
	 * @param mixed    $default
	 *
	 * @return mixed[] Array of retrieved values, indexed by the specified keys.
	 *                 Values that couldn't be retrieved are not contained in this array.
	 */
	public function getMultiple($keys, $default = null)
	{
		if (empty($keys)) {
			return [];
		}

		// note: the array_combine() is in place to keep an association between our $keys and the $namespacedKeys
		$namespacedKeys = array_combine($keys, array_map([$this, 'getNamespacedKey'], $keys));
		$items = $this->doGetMultiple($namespacedKeys);

		$return = [];
		// no internal array function supports this sort of mapping: needs to be iterative
		// this filters and combines keys in one pass
		foreach ($namespacedKeys as $k => $v) {
			[$found, $value] = $items[$v] ?? [false, null];
			$return[$k] = $found === true ? $value : $default;
		}

		return $return;
	}

	/**
	 * Tests if an entry exists in the cache.
	 *
	 * @param string $key The cache id of the entry to check for.
	 *
	 * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
	 */
	public function has($key)
	{
		return $this->doHas(
			$this->getNamespacedKey($key)
		);
	}

	/**
	 * Returns a boolean value indicating if the operation succeeded.
	 *
	 * @param iterable               $values Array of keys and values to save in cache
	 * @param null|int|\DateInterval $ttl    The lifetime. If != 0, sets a specific lifetime for these
	 *                                       cache entries (0 => infinite lifeTime).
	 *
	 * @return bool TRUE if the operation was successful, FALSE if it wasn't.
	 */
	public function setMultiple($values, $ttl = null)
	{
		[$ttl, $expireTime] = $this->ttl($ttl);

		$namespacedValues = [];
		foreach ($values as $key => $value) {
			$namespacedValues[$this->getNamespacedKey($key)] = [true, $value, $expireTime];
		}

		return $this->doSetMultiple($namespacedValues, $ttl);
	}

	/**
	 * Puts data into the cache.
	 *
	 * If a cache entry with the given id already exists, its data will be replaced.
	 *
	 * @param string                 $key   The cache id.
	 * @param mixed                  $value The cache entry/data.
	 * @param null|int|\DateInterval $ttl   The lifetime in number of seconds for this cache entry.
	 *                                      If zero (the default), the entry never expires (although it may be deleted from the cache
	 *                                      to make place for other entries).
	 *
	 * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
	 */
	public function set($key, $value, $ttl = null)
	{
		[$ttl, $expireTime] = $this->ttl($ttl);

		return $this->doSet(
			$this->getNamespacedKey($key), [true, $value, [], $expireTime], $ttl
		);
	}

	public function setDirectValue(string $key, $value, array $tags = [], int $expireTime = null)
	{
		return $this->doSet(
			$this->getNamespacedKey($key),
			[true, $value, $tags, $expireTime],
			$this->expireToTtl($expireTime)
		);
	}

	/**
	 * Deletes a cache entry.
	 *
	 * @param string $key The cache id.
	 *
	 * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise.
	 *              Deleting a non-existing entry is considered successful.
	 */
	public function delete($key)
	{
		return $this->doDelete(
			$this->getNamespacedKey($key)
		);
	}

	/**
	 * Deletes several cache entries.
	 *
	 * @param string[] $keys Array of keys to delete from cache
	 *
	 * @return bool TRUE if the operation was successful, FALSE if it wasn't.
	 */
	public function deleteMultiple($keys)
	{
		return $this->doDeleteMultiple(array_map([$this, 'getNamespacedKey'], $keys));
	}

	/**
	 * Flushes all cache entries, globally.
	 *
	 * @return bool TRUE if the cache entries were successfully flushed, FALSE otherwise.
	 */
	public function clear()
	{
		return $this->doClear();
	}

	/**
	 * Deletes all cache entries in the current cache namespace.
	 *
	 * @return bool TRUE if the cache entries were successfully deleted, FALSE otherwise.
	 */
	public function deleteAll()
	{
	    if (!$this->namespace) {
	        return $this->clear();
        }

		$namespaceCacheKey = $this->getNamespaceCacheKey();
		$namespaceVersion = $this->getNamespaceVersion() + 1;

		if ($this->doSet($namespaceCacheKey, [true, $namespaceVersion, 0])) {
			$this->namespaceVersion = $namespaceVersion;

			return true;
		}

		return false;
	}

	/**
	 * Prefixes the passed id with the configured namespace value.
	 *
	 * @param string $key The id to namespace.
	 *
	 * @return string The namespaced id.
	 * @throws InvalidArgumentException
	 */
	protected function getNamespacedKey(string $key): string
	{
		if (preg_match(self::PSR16_RESERVED, $key, $match) === 1) {
			throw new InvalidArgumentException("invalid character in key: {$match[0]}");
		}

		if (!$this->namespace) {
		    return $key;
        }

		if (null === ($version = $this->namespaceVersion)) {
			$version = $this->getNamespaceVersion();
		}

		return $this->namespace . static::SEPARATOR .
			$version . static::SEPARATOR . $key;
	}


	/**
	 * Returns the namespace cache key.
	 *
	 * @return string
	 */
	private function getNamespaceCacheKey()
	{
		return $this->namespace . static::SEPARATOR . self::CACHE_KEY;
	}

	/**
	 * Returns the namespace version.
	 *
	 * @return integer
	 */
	private function getNamespaceVersion()
	{
		if (null !== $this->namespaceVersion) {
			return $this->namespaceVersion;
		}

		$namespaceCacheKey = $this->getNamespaceCacheKey();
		[$found, $version] = $this->doGet($namespaceCacheKey) ?? [false, null];

		if ($found === true) {
			return $this->namespaceVersion = $version;
		}

		return $this->namespaceVersion = 1;
	}

	/**
	 * Default implementation of doSetMultiple. Each driver that supports multi-put should overwrite it.
	 *
	 * @param array $values         Array of keys and values to save in cache
	 * @param int   $ttl            The lifetime. If != 0, sets a specific lifetime for these
	 *                              cache entries (0 => infinite lifeTime).
	 *
	 * @return bool TRUE if the operation was successful, FALSE if it wasn't.
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		$success = true;

		foreach ($values as $key => $value) {
			if (!$this->doSet($key, $value, $ttl)) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Default implementation of doGetMultiple. Each driver that supports multi-get should owerwrite it.
	 *
	 * @param array $keys Array of keys to retrieve from cache
	 *
	 * @return array Array of values retrieved for the given keys.
	 */
	protected function doGetMultiple(array $keys)
	{
		$return = [];

		foreach ($keys as $key) {
			$return[$key] = $this->doGet($key);
		}

		return $return;
	}

	/**
	 * Default implementation of doDeleteMultiple. Each driver that supports multi-delete should override it.
	 *
	 * @param array $keys Array of keys to delete from cache
	 *
	 * @return bool TRUE if the operation was successful, FALSE if it wasn't
	 */
	protected function doDeleteMultiple(array $keys)
	{
		$success = true;

		foreach ($keys as $key) {
			if (!$this->doDelete($key)) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Fetches an entry from the cache.
	 *
	 * @param string $key The id of the cache entry to fetch.
	 *
	 * @return mixed|null The cached data or NULL, if no cache entry exists for the given id.
	 */
	abstract protected function doGet(string $key);

	/**
	 * Tests if an entry exists in the cache.
	 *
	 * @param string $key The cache id of the entry to check for.
	 *
	 * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
	 */
	abstract protected function doHas(string $key);

	/**
	 * Puts data into the cache.
	 *
	 * @param string $key        The cache id.
	 * @param mixed  $value      The cache entry/data.
	 * @param int    $ttl        The lifetime. If != 0, sets a specific lifetime for this
	 *                           cache entry (0 => infinite lifetime).
	 *
	 * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
	 */
	abstract protected function doSet(string $key, $value, int $ttl = 0);

	/**
	 * Deletes a cache entry.
	 *
	 * @param string $key The cache id.
	 *
	 * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise.
	 */
	abstract protected function doDelete(string $key);

	/**
	 * Flushes all cache entries.
	 *
	 * @return bool TRUE if the cache entries were successfully flushed, FALSE otherwise.
	 */
	abstract protected function doClear();
}
