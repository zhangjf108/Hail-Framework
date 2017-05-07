<?php

namespace Hail\Util;


class MimeType
{
    protected static $mimes;
    protected static $extensions;

    protected static function mimes()
    {
        if (static::$mimes === null) {
            static::$mimes = require __DIR__ . '/config/mimes.php';
        }

        return static::$mimes;
    }

    protected static function extensions()
    {
        if (static::$extensions === null) {
            static::$extensions = require __DIR__ . '/config/extensions.php';
        }

        return static::$extensions;
    }

    public static function getMimeType($extension)
    {
        return self::getMimeTypes($extension)[0] ?? null;
    }

    public static function getExtension($mimeType)
    {
        return static::getExtensions($mimeType)[0] ?? null;
    }

    public static function getMimeTypes($extension)
    {
        $mimes = static::$mimes ?? static::mimes();
        $extension = strtolower(trim($extension));

        if (strpos($extension, '.') !== false) {
            $extension = substr(strrchr($extension, '.'), 1);
        }

        return $mimes[$extension] ?? [];
    }

    public static function getExtensions($mimeType)
    {
        $extensions = static::$extensions ?? static::extensions();
        $mimeType = strtolower(trim($mimeType));

        return $extensions[$mimeType] ?? null;
    }
}