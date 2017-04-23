<?php

namespace Hail\Util;


use InvalidArgumentException;
use Hail\Exception\CryptoException;

/**
 * Class Crypto
 *
 * @package Hail\Util
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Crypto
{
    const HEADER_VERSION_SIZE = 4;
    const MINIMUM_CIPHERTEXT_SIZE = 84;

    const CURRENT_VERSION = "\xDE\xF5\x02\x00";

    const CIPHER_METHOD = 'aes-256-ctr';
    const BLOCK_BYTE_SIZE = 16;
    const KEY_BYTE_SIZE = 32;
    const SALT_BYTE_SIZE = 32;
    const MAC_BYTE_SIZE = 32;
    const HASH_TYPE = 'sha256';
    const ENCRYPTION_INFO_STRING = 'Hail|V1|KeyForEncryption';
    const AUTHENTICATION_INFO_STRING = 'Hail|V1|KeyForAuthentication';

    const PBKDF2_ITERATIONS = 100000;

    /**
     * @var array
     */
    public static $hashList = [
//		'md5' => 16,
        'sha1' => 20,
        'sha224' => 28,
        'sha256' => 32,
        'sha384' => 48,
        'sha512' => 64,
//		'ripemd128' => 16,
        'ripemd160' => 20,
        'ripemd256' => 32,
        'ripemd320' => 40,
        'whirlpool' => 64,
    ];

    const FORMAT_RAW = 'raw';
    const FORMAT_HEX = 'hex';
    const FORMAT_STR = 'str';
    const FORMAT_BASE64 = 'base64';

    protected static $format = self::FORMAT_RAW;

    public static function format(string $format): void
    {
        if (!in_array($format, [
            self::FORMAT_RAW,
            self::FORMAT_HEX,
            self::FORMAT_STR,
            self::FORMAT_BASE64,
        ], true)
        ) {
            throw new InvalidArgumentException("Crypto return format not defined: $format");
        }

        self::$format = $format;
    }

    /**
     * @param string $password
     *
     * @return bool|string
     */
    public static function password(string $password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * @param $password
     * @param $hash
     *
     * @return bool|string
     */
    public static function verifyPassword(string $password, string $hash)
    {
        if (password_verify($password, $hash)) {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                return static::password($password);
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $text
     * @param string $format
     *
     * @return string
     */
    public static function hash(string $text, string $format = null): string
    {
        $format = $format === null ? self::$format : self::check($format);

        if (self::FORMAT_HEX === $format) {
            return hash(self::HASH_TYPE, $text, false);
        }

        $raw = hash(self::HASH_TYPE, $text, true);

        return self::fromRaw($raw, $format);
    }

    /**
     * @param string $text
     * @param string $hash
     * @param string $format
     *
     * @return bool
     */
    public static function verifyHash(string $hash, string $text, string $format = null): bool
    {
        return hash_equals($hash, self::hash($text, $format));
    }

    /**
     * @param string $text
     * @param string $salt
     * @param string $format
     *
     * @return string
     */
    public function hmac(string $text, string $salt, string $format = null): string
    {
        $format = $format === null ? self::$format : self::check($format);

        if (self::FORMAT_HEX === $format) {
            return hash_hmac(self::HASH_TYPE, $text, $salt, false);
        }

        $raw = hash_hmac(self::HASH_TYPE, $text, $salt, true);

        return self::fromRaw($raw, $format);
    }

    /**
     * @param string $text
     * @param string $salt
     * @param string $hash
     * @param string $format
     *
     * @return bool
     */
    public function verifyHMAC(string $hash, string $text, string $salt, string $format = null): bool
    {
        return hash_equals($hash, $this->hmac($text, $salt, $format));
    }

    public function createKey(string $format = null)
    {
        $format = $format === null ? self::$format : self::check($format);

        return self::fromRaw(
            random_bytes(self::KEY_BYTE_SIZE), $format
        );
    }

    /**
     * Encrypts a string with a Key.
     *
     * @param string $plaintext
     * @param string $key
     * @param string $format
     * @param bool   $password
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function encrypt(string $plaintext, string $key, string $format = null, bool $password = false): string
    {
        if (mb_strlen($key, '8bit') !== self::KEY_BYTE_SIZE) {
            throw new CryptoException('Bad key length.');
        }

        $salt = random_bytes(self::SALT_BYTE_SIZE);

        [$authKey, $encryptKey] = $this->deriveKeys($key, $salt, $password);

        $iv = random_bytes(self::BLOCK_BYTE_SIZE);

        $cipherText = openssl_encrypt(
            $plaintext,
            self::CIPHER_METHOD,
            $encryptKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipherText === false) {
            throw new CryptoException(
                'openssl_encrypt() failed.'
            );
        }

        $cipherText = self::CURRENT_VERSION . $salt . $iv . $cipherText;
        $auth = hash_hmac(self::HASH_TYPE, $cipherText, $authKey, true);
        $cipherText .= $auth;

        return self::fromRaw($cipherText, $format);
    }

    /**
     * Encrypts a string with a password, using a slow key derivation function
     * to make password cracking more expensive.
     *
     * @param string $plaintext
     * @param string $password
     * @param string $format
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function encryptWithPassword(string $plaintext, string $password, string $format = null): string
    {
        return $this->encrypt($plaintext, $password, $format, true);
    }

    /**
     * Decrypts a cipher text to a string with a Key.
     *
     * @param string $cipherText
     * @param string $key
     * @param string $format
     * @param bool   $password
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function decrypt(string $cipherText, string $key, string $format = null, $password = false): string
    {
        $cipherText = self::toRaw($cipherText, $format);
        if ($cipherText === false) {
            throw new CryptoException('Ciphertext has invalid base64 encoding.');
        }

        if (mb_strlen($cipherText, '8bit') < self::MINIMUM_CIPHERTEXT_SIZE) {
            throw new CryptoException('Ciphertext is too short.');
        }

        // Get and check the version header.
        $header = mb_substr($cipherText, 0, self::HEADER_VERSION_SIZE, '8bit');
        if ($header !== self::CURRENT_VERSION) {
            throw new CryptoException('Bad version header.');
        }

        // Get the salt.
        $salt = mb_substr(
            $cipherText,
            self::HEADER_VERSION_SIZE,
            self::SALT_BYTE_SIZE,
            '8bit'
        );
        if ($salt === false) {
            throw new CryptoException('Environment is broken');
        }

        // Get the IV.
        $iv = mb_substr(
            $cipherText,
            self::HEADER_VERSION_SIZE + self::SALT_BYTE_SIZE,
            self::BLOCK_BYTE_SIZE,
            '8bit'
        );
        if ($iv === false) {
            throw new CryptoException('Environment is broken');
        }

        // Get the HMAC.
        $hmac = mb_substr(
            $cipherText,
            mb_strlen($cipherText, '8bit') - self::MAC_BYTE_SIZE,
            self::MAC_BYTE_SIZE,
            '8bit'
        );
        if ($hmac === false) {
            throw new CryptoException('Environment is broken');
        }

        // Get the actual encrypted ciphertext.
        $encrypted = mb_substr(
            $cipherText,
            self::HEADER_VERSION_SIZE + self::SALT_BYTE_SIZE +
            self::BLOCK_BYTE_SIZE,
            mb_strlen($cipherText, '8bit') - self::MAC_BYTE_SIZE - self::SALT_BYTE_SIZE -
            self::BLOCK_BYTE_SIZE - self::HEADER_VERSION_SIZE,
            '8bit'
        );
        if ($encrypted === false) {
            throw new CryptoException('Environment is broken');
        }
        // Derive the separate encryption and authentication keys from the key
        // or password, whichever it is.
        list($authKey, $encryptKey) = $this->deriveKeys($key, $salt, $password);

        if (false === hash_equals($hmac,
                hash_hmac(self::HASH_TYPE, $header . $salt . $iv . $encrypted, $authKey, true))
        ) {
            throw new CryptoException('Integrity check failed.');
        }

        $plaintext = openssl_decrypt(
            $encrypted,
            self::CIPHER_METHOD,
            $encryptKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false) {
            throw new CryptoException('openssl_decrypt() failed.');
        }

        return $plaintext;
    }

    /**
     * Decrypts a ciphertext to a string with a password, using a slow key
     * derivation function to make password cracking more expensive.
     *
     * @param string $cipherText
     * @param string $password
     * @param string $format
     *
     * @throws CryptoException
     *
     * @return string
     */
    public function decryptWithPassword(string $cipherText, string $password, string $format = null): string
    {
        return $this->decrypt($cipherText, $password, $format, true);
    }

    /**
     * Derives authentication and encryption keys from the secret, using a slow
     * key derivation function if the secret is a password.
     *
     * @param string $key
     * @param string $salt
     * @param bool   $password
     *
     * @throws CryptoException
     *
     * @return array
     */
    private function deriveKeys(string $key, string $salt, bool $password = false)
    {
        if ($password) {
            $preHash = hash(self::HASH_TYPE, $key, true);
            $key = static::pbkdf2(
                self::HASH_TYPE,
                $preHash,
                $salt,
                self::PBKDF2_ITERATIONS,
                self::KEY_BYTE_SIZE,
                true
            );
        }

        $authKey = static::hkdf(
            self::HASH_TYPE,
            $key,
            self::KEY_BYTE_SIZE,
            self::AUTHENTICATION_INFO_STRING,
            $salt
        );

        $encryptKey = static::hkdf(
            self::HASH_TYPE,
            $key,
            self::KEY_BYTE_SIZE,
            self::ENCRYPTION_INFO_STRING,
            $salt
        );

        return [$authKey, $encryptKey];
    }

    /**
     * @param string $raw
     * @param string $format
     *
     * @return string
     */
    public static function fromRaw(string $raw, string $format = null): string
    {
        $format = $format ?? self::$format;

        switch ($format) {
            case self::FORMAT_HEX:
                return bin2hex($raw);

            case self::FORMAT_BASE64:
                return base64_encode($raw);

            case self::FORMAT_STR:
                $field = strtoupper(bin2hex($raw));
                $field = chunk_split($field, 2, '\x');

                return '\x' . substr($field, 0, -2);

            case self::FORMAT_RAW:
                return $raw;

            default:
                throw new InvalidArgumentException("Crypto return format not defined: $format");
        }
    }

    /**
     * @param string $raw
     * @param string $format
     *
     * @return string
     */
    public static function toRaw(string $raw, string $format = null): string
    {
        $format = $format ?? self::$format;

        switch ($format) {
            case self::FORMAT_HEX:
                return hex2bin($raw);

            case self::FORMAT_BASE64:
                return base64_decode($raw);

            case self::FORMAT_STR:
                return hex2bin(str_replace('\x', '', $raw));

            case self::FORMAT_RAW:
                return $raw;

            default:
                throw new InvalidArgumentException("Crypto return format not defined: $format");
        }
    }

    /**
     * Computes the HKDF key derivation function specified in
     * http://tools.ietf.org/html/rfc5869.
     *
     * @param string $hash   Hash Function
     * @param string $ikm    Initial Keying Material
     * @param int    $length How many bytes?
     * @param string $info   What sort of key are we deriving?
     * @param string $salt
     *
     * @throws CryptoException
     *
     * @return string
     */
    public static function hkdf(string $hash, string $ikm, int $length, $info = '', $salt = null)
    {
        if (PHP_VERSION_ID >= 70102) {
            return hash_hkdf($hash, $ikm, $length, $info, $salt);
        }

        $hashLength = static::$hashList[$hash] ?? mb_strlen(hash_hmac($hash, '', '', true), '8bit');
        if (empty($length) || !is_int($length) || $length < 0 || $length > 255 * $hashLength) {
            throw new CryptoException('Bad output length requested of HKDF.');
        }

        if ($salt === null) {
            $salt = str_repeat("\x00", $hashLength);
        }

        $prk = hash_hmac($hash, $ikm, $salt, true);
        $t = '';
        $lastBlock = '';
        $blocks = ceil($length / $hashLength);
        for ($i = 1; $i <= $blocks; ++$i) {
            $lastBlock = hash_hmac(
                $hash,
                $lastBlock . $info . chr($i),
                $prk,
                true
            );
            $t .= $lastBlock;
        }

        return mb_substr($t, 0, $length, '8bit');
    }

    /**
     * Computes the PBKDF2 password-based key derivation function.
     *
     * The PBKDF2 function is defined in RFC 2898. Test vectors can be found in
     * RFC 6070. This implementation of PBKDF2 was originally created by Taylor
     * Hornby, with improvements from http://www.variations-of-shadow.com/.
     *
     * @param string $algorithm The hash algorithm to use. Recommended: SHA256
     * @param string $password  The password.
     * @param string $salt      A salt that is unique to the password.
     * @param int    $count     Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * @param int    $length    The length of the derived key in bytes.
     * @param bool   $raw       If true, the key is returned in raw binary format. Hex encoded otherwise.
     *
     * @throws CryptoException
     *
     * @return string A $key_length-byte key derived from the password and salt.
     */
    public static function pbkdf2(
        string $algorithm,
        string $password,
        string $salt,
        int $count,
        int $length,
        bool $raw = false
    ) {
        $algorithm = strtolower($algorithm);
        // Whitelist, or we could end up with people using CRC32.
        if (!isset(self::$hashList[$algorithm])) {
            throw new CryptoException('Algorithm is not a secure cryptographic hash function.');
        }

        if ($count <= 0 || $length <= 0) {
            throw new CryptoException('Invalid PBKDF2 parameters.');
        }

        // The output length is in NIBBLES (4-bits) if $raw_output is false!
        if (!$raw) {
            $length *= 2;
        }

        return hash_pbkdf2($algorithm, $password, $salt, $count, $length, $raw);
    }

    /**
     * @param string $data
     * @param string $publicKey
     * @param string $format
     *
     * @return string
     * @throws CryptoException
     */
    public function encryptRsaPublic(string $data, string $publicKey, string $format = null): string
    {
        $key = openssl_pkey_get_public($publicKey);

        $encrypted = '';
        if (!openssl_public_encrypt($data, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new CryptoException('RSA public encrypt error: ' . openssl_error_string());
        }
        openssl_pkey_free($key);

        return self::fromRaw($encrypted, $format);
    }

    /**
     * @param string $data
     * @param string $privateKey
     * @param string $format
     *
     * @return string
     * @throws CryptoException
     */
    public function decryptRsaPrivate(string $data, string $privateKey, string $format = null): string
    {
        $data = self::toRaw($data, $format);
        $key = openssl_pkey_get_private($privateKey);

        $decrypted = '';
        if (!openssl_private_decrypt($data, $decrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new CryptoException('RSA private decrypt error: ' . openssl_error_string());
        }
        openssl_pkey_free($key);

        return $decrypted;
    }

    /**
     * @param $data
     * @param $privateKey
     * @param $format
     *
     * @return string
     * @throws CryptoException
     */
    public function encryptRsaPrivate(string $data, string $privateKey, string $format = null): string
    {
        $key = openssl_pkey_get_private($privateKey);

        $encrypted = '';
        if (!openssl_private_encrypt($data, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new CryptoException('RSA private encrypt error: ' . openssl_error_string());
        }
        openssl_pkey_free($key);

        return self::fromRaw($encrypted, $format);
    }

    /**
     * @param string $data
     * @param string $publicKey
     * @param string $format
     *
     * @return string
     * @throws CryptoException
     */
    public function decryptRsaPublic(string $data, string $publicKey, string $format = null): string
    {
        $data = self::toRaw($data, $format);
        $key = openssl_pkey_get_public($publicKey);

        $decrypted = '';
        if (!openssl_public_decrypt($data, $decrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new CryptoException('RSA public decrypt error: ' . openssl_error_string());
        }
        openssl_pkey_free($key);

        return $decrypted;
    }

    /**
     * @param string $data
     * @param string $privateKey
     * @param string $format
     *
     * @return string
     * @throws CryptoException
     */
    public function signatureRsa(string $data, string $privateKey, string $format = null): string
    {
        $key = openssl_pkey_get_private($privateKey);
        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new CryptoException('RSA signature error: ' . openssl_error_string());
        }

        return self::fromRaw($signature, $format);
    }

    /**
     * @param string $data
     * @param string $signature
     * @param string $publicKey
     * @param string $format
     *
     * @return bool
     * @throws CryptoException
     */
    public function verifyRsa(string $data, string $signature, string $publicKey, string $format = null): bool
    {
        $signature = self::toRaw($signature, $format);
        $key = openssl_pkey_get_public($publicKey);

        $return = openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256);
        if ($return === -1) {
            throw new CryptoException('RSA signature check error: ' . openssl_error_string());
        }

        return $return === 1;
    }
}