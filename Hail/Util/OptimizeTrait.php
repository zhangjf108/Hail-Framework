<?php
namespace Hail\Util;

defined('HAIL_OPTIMIZE_CHECK_DELAY') || define('HAIL_OPTIMIZE_CHECK_DELAY', 5);

trait OptimizeTrait
{
	protected static function optimizeGet($key, $file = null)
	{
		$prefix = static::class;
		if (HAIL_OPTIMIZE_CHECK_DELAY > 0 && $file !== null) {
			$time = $key . '|time';
			$check = Optimize::get($prefix, $time);
			if ($check !== false && NOW >= ($check[0] + HAIL_OPTIMIZE_CHECK_DELAY)) {
				if (static::optimizeVerifyMTime($file, $check[1])) {
					return false;
				}

				$check[0] = NOW;
				Optimize::set($prefix, $time, $check);
			}
		}

		return Optimize::get(
			$prefix, $key
		);
	}

	protected static function optimizeSet($key, $value = null, $file = null)
	{
		if ($file !== null) {
			$mtime = static::optimizeFileMTime($file);
			if ($mtime !== []) {
				$key = [
					$key => $value,
					$key . '|time' => [NOW, $mtime],
				];
			}
		}

		if (is_array($key)) {
			return Optimize::setMultiple(
				static::class, $key
			);
		}

		return Optimize::set(
			static::class, $key, $value
		);
	}

	protected static function optimizeVerifyMTime($file, array $check)
	{
		if (!is_array($file)) {
			$file = [$file];
		} else {
			$file = array_unique($file);
		}

		foreach ($file as $v) {
			if (file_exists($v)) {
				if (!isset($check[$v]) || filemtime($v) !== $check[$v]) {
					return true;
				}
			} elseif (isset($check[$v])) {
				return true;
			}

			unset($check[$v]);
		}

		return [] !== $check;
	}

	protected static function optimizeFileMTime($file)
	{
		$file = array_unique((array) $file);

		$mtime = [];
		foreach ($file as $v) {
			if (file_exists($v)) {
				$mtime[$v] = filemtime($v);
			}
		}

		return $mtime;
	}
}