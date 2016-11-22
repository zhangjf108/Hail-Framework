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

namespace Hail\Cache\Driver;

use Hail\Cache\Driver;

/**
 * WinCache cache provider.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author David Abdemoulaie <dave@hobodave.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class WinCache extends Driver
{
	public function __construct($params)
	{
		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		return wincache_ucache_get($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return wincache_ucache_exists($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifetime = 0)
	{
		return wincache_ucache_set($id, $data, $lifetime);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
	{
		$result = wincache_ucache_set($keysAndValues, null, $lifetime);

		if ($result === false || count($result)) {
			return false;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		return wincache_ucache_delete($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		return wincache_ucache_clear();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetchMultiple(array $keys)
	{
		return wincache_ucache_get($keys);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		$info = wincache_ucache_info();
		$meminfo = wincache_ucache_meminfo();

		return array(
			Driver::STATS_HITS => $info['total_hit_count'],
			Driver::STATS_MISSES => $info['total_miss_count'],
			Driver::STATS_UPTIME => $info['total_cache_uptime'],
			Driver::STATS_MEMORY_USAGE => $meminfo['memory_total'],
			Driver::STATS_MEMORY_AVAILABLE => $meminfo['memory_free'],
		);
	}
}
