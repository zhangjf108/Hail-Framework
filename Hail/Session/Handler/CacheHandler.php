<?php

namespace Hail\Session;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Class CachePoolHandler.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class CacheHandler extends BaseHandler
{
	/**
	 * @type CacheItemPoolInterface Cache driver.
	 */
	private $cache;

	public function __construct(CacheItemPoolInterface $cache, array $settings)
	{
		$settings += [
			'prefix' => 'PSR6Ses',
		];

		if (!isset($settings['lifetime']) || $settings['lifetime'] === 0) {
			$settings['lifetime'] = (int) ini_get('session.gc_maxlifetime');
		}

		$settings['lifetime'] = $settings['lifetime'] ?: 86400;

		$this->cache = $cache;

		parent::__construct($settings);
	}

	/**
	 * {@inheritdoc}
	 */
	public function open($savePath, $sessionName)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function close()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($id)
	{
		$item = $this->cache->getItem(
			$this->key($id)
		);

		if ($item->isHit()) {
			return $item->get();
		}

		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($id, $data)
	{
		$item = $this->cache->getItem(
			$this->key($id)
		);

		$item->set($data)
			->expiresAfter($this->settings['lifetime']);

		return $this->cache->save($item);
	}

	/**
	 * {@inheritdoc}
	 */
	public function destroy($id)
	{
		return $this->cache->deleteItem(
			$this->key($id)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function gc($lifetime)
	{
		// not required here because cache will auto expire the records anyhow.
		return true;
	}
}
