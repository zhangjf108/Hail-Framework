<?php

namespace Hail\Util;

/**
 * 小数组
 * 尺寸:     msgpack < swoole = swoole(fast) < igbinary < json < hprose < serialize
 * 序列化速度:   swoole(fast) << serialize < msgpack < json < swoole << igbinary << hprose
 * 反序列化速度: swoole ~ swoole(fast) << igbinary < msgpack < serialize < hprose << json
 *
 * 大数组
 * 尺寸:     swoole < igbinary << hprose << msgpack < swoole(fast) < json << serialize
 * 序列化速度:   swoole(fast) < swoole << msgpack < serialize < igbinary =< json < hprose
 * 反序列化速度: swoole(fast) < swoole << igbinary < hprose < serialize < msgpack << json
 *
 */

/**
 * Class Serialize
 *
 * @package Hail\Util
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Serialize
{
    const SWOOLE = 'swoole';
    const SWOOLE_FAST = 'swoole_fast';
    const MSGPACK = 'msgpack';
    const IGBINARY = 'igbinary';
    const HPROSE = 'hprose';
    const JSON = 'json';
    const SERIALIZE = 'serialize';

    private const EXTENSION = 'ext';
    private const ENCODER = 'encoder';
    private const DECODER = 'decoder';

    private static $set = [
        self::MSGPACK => [
            self::EXTENSION => 'msgpack',
            self::ENCODER => 'msgpack_pack',
            self::DECODER => 'msgpack_unpack',
        ],
        self::SWOOLE => [
            self::EXTENSION => 'swoole_serialize',
            self::ENCODER => 'swoole_pack',
            self::DECODER => 'swoole_unpack',
        ],
        self::SWOOLE_FAST => [
            self::EXTENSION => 'swoole_serialize',
            self::ENCODER => 'swoole_fast_pack',
            self::DECODER => 'swoole_unpack',
        ],
        self::IGBINARY => [
            self::EXTENSION => 'igbinary',
            self::ENCODER => 'igbinary_serialize',
            self::DECODER => 'igbinary_unserialize',
        ],
        self::HPROSE => [
            self::EXTENSION => 'hprose',
            self::ENCODER => 'hprose_serialize',
            self::DECODER => 'hprose_unserialize',
        ],
        self::JSON => [
            self::EXTENSION => null,
            self::ENCODER => 'Hail\Util\Json::encode',
            self::DECODER => 'Hail\Util\Json::decode',
        ],
        self::SERIALIZE => [
            self::EXTENSION => null,
            self::ENCODER => 'serialize',
            self::DECODER => 'unserialize',
        ],
    ];

    private static $default = self::SERIALIZE;

    public static function default(string $type): void
    {
        self::check($type);

        self::$default = $type;
    }

    private static function check(string $type)
    {
        if (!isset(self::$set[$type])) {
            throw new \InvalidArgumentException('Serialize type not defined: ' . $type);
        }

        $extension = self::$set[$type][self::EXTENSION];
        if ($extension && !extension_loaded($extension)) {
            throw new \LogicException('Extension not loaded: ' . $extension);
        }
    }

    private static function getFunction($key, string $type = null)
    {
        if ($type === null) {
            $type = self::$default;
        } else {
            self::check($type);
        }

        return self::$set[$type][$key];
    }

    /**
     * @param mixed       $value
     * @param string|null $type
     *
     * @return string
     */
    public static function encode($value, string $type = null): string
    {
        $fn = self::$set[$type ?? self::$default]['encoder'] ??
            self::getFunction('encoder', $type);

        return $fn($value);
    }

    /**
     * @param string      $value
     * @param string|null $type
     *
     * @return mixed
     */
    public static function decode(string $value, string $type = null)
    {
        $fn = self::$set[$type ?? self::$default]['decoder'] ??
            self::getFunction('decoder', $type);

        return $fn($value);
    }

    /**
     * @param string      $value
     * @param string|null $type
     *
     * @return string
     */
    public static function encodeToBase64($value, string $type = null): string
    {
        return base64_encode(
            self::encode($value, $type)
        );
    }

    /**
     * @param string      $value
     * @param string|null $type
     *
     * @return mixed
     */
    public static function decodeFromBase64(string $value, string $type = null)
    {
        return self::decode(
            base64_decode($value), $type
        );
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function encodeArray(array $array, string $type = null): array
    {
        $fn = self::$set[$type ?? self::$default]['encoder'] ??
            self::getFunction('encoder', $type);

        return array_map($fn, $array);
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function decodeArray(array $array, string $type = null): array
    {
        $fn = self::$set[$type ?? self::$default]['encoder'] ??
            self::getFunction('encoder', $type);

        return array_map($fn, $array);
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function encodeArrayToBase64(array $array, string $type = null): array
    {
        $fn = self::$set[$type ?? self::$default]['encoder'] ??
            self::getFunction('encoder', $type);

        foreach ($array as &$v) {
            $v = base64_encode($fn($v));
        }

        return $array;
    }

    /**
     * @param array       $array
     * @param string|null $type
     *
     * @return array
     */
    public static function decodeArrayFromBase64(array $array, string $type = null): array
    {
        $fn = self::$set[$type ?? self::$default]['decoder'] ??
            self::getFunction('decoder', $type);

        foreach ($array as &$v) {
            $v = $fn(base64_encode($v));
        }

        return $array;
    }
}