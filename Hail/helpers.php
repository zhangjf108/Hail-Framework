<?php
use Hail\Framework;

if (!function_exists('base_path')) {
	function base_path($path = null)
	{
		Framework::path(BASE_PATH, $path);
	}
}

if (!function_exists('storage_path')) {
	function storage_path($path = null)
	{
        Framework::path(STORAGE_PATH, $path);
	}
}

if (!function_exists('hail_path')) {
	function hail_path($path = null)
	{
        Framework::path(HAIL_PATH, $path);
	}
}