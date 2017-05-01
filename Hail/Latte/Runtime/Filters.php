<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Hail\Latte\Runtime;

use Hail\Latte\Exception\RegexpException;
use Hail\Latte\Engine;


/**
 * Template filters. Uses UTF-8 only.
 */
class Filters
{
    /** @deprecated */
    public static $dateFormat = '%x';

    /** @internal @var bool  use XHTML syntax? */
    public static $xhtml = false;


    /**
     * Escapes string for use inside HTML.
     *
     * @param  mixed  plain text
     *
     * @return string HTML
     */
    public static function escapeHtml($s): string
    {
        return htmlSpecialChars((string) $s, ENT_QUOTES, 'UTF-8');
    }


    /**
     * Escapes string for use inside HTML.
     *
     * @param  mixed  plain text or HtmlStringInterface
     *
     * @return string HTML
     */
    public static function escapeHtmlText($s): string
    {
        return $s instanceof HtmlStringInterface
            ? $s->__toString()
            : htmlSpecialChars((string) $s, ENT_NOQUOTES, 'UTF-8');
    }


    /**
     * Escapes string for use inside HTML attribute value.
     *
     * @param  string plain text
     *
     * @return string HTML
     */
    public static function escapeHtmlAttr($s, bool $double = true): string
    {
        $double = $double && $s instanceof HtmlStringInterface ? false : $double;
        $s = (string) $s;
        if (strpos($s, '`') !== false && strpbrk($s, ' <>"\'') === false) {
            $s .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
        }

        return htmlSpecialChars($s, ENT_QUOTES, 'UTF-8', $double);
    }


    /**
     * Escapes HTML for use inside HTML attribute.
     *
     * @param  mixed  HTML text
     *
     * @return string HTML
     */
    public static function escapeHtmlAttrConv($s): string
    {
        return self::escapeHtmlAttr($s, false);
    }


    /**
     * Escapes string for use inside HTML attribute name.
     *
     * @param  string plain text
     *
     * @return string HTML
     */
    public static function escapeHtmlAttrUnquoted($s): string
    {
        $s = (string) $s;

        return preg_match('#^[a-z0-9:-]+$#i', $s)
            ? $s
            : '"' . self::escapeHtmlAttr($s) . '"';
    }


    /**
     * Escapes string for use inside HTML comments.
     *
     * @param  string plain text
     *
     * @return string HTML
     */
    public static function escapeHtmlComment($s): string
    {
        $s = (string) $s;
        if ($s && ($s[0] === '-' || $s[0] === '>' || $s[0] === '!')) {
            $s = ' ' . $s;
        }
        $s = str_replace('--', '- - ', $s);
        if (substr($s, -1) === '-') {
            $s .= ' ';
        }

        return $s;
    }


    /**
     * Escapes string for use inside XML 1.0 template.
     *
     * @param  string plain text
     *
     * @return string XML
     */
    public static function escapeXml($s): string
    {
        // XML 1.0: \x09 \x0A \x0D and C1 allowed directly, C0 forbidden
        // XML 1.1: \x00 forbidden directly and as a character reference,
        //   \x09 \x0A \x0D \x85 allowed directly, C0, C1 and \x7F allowed as character references
        return htmlSpecialChars(preg_replace('#[\x00-\x08\x0B\x0C\x0E-\x1F]+#', '', (string) $s), ENT_QUOTES, 'UTF-8');
    }


    /**
     * Escapes string for use inside XML attribute name.
     *
     * @param  string plain text
     *
     * @return string XML
     */
    public static function escapeXmlAttrUnquoted($s): string
    {
        $s = (string) $s;

        return preg_match('#^[a-z0-9:-]+$#i', $s)
            ? $s
            : '"' . self::escapeXml($s) . '"';
    }


    /**
     * Escapes string for use inside CSS template.
     *
     * @param  string plain text
     *
     * @return string CSS
     */
    public static function escapeCss($s): string
    {
        // http://www.w3.org/TR/2006/WD-CSS21-20060411/syndata.html#q6
        return addcslashes((string) $s, "\x00..\x1F!\"#$%&'()*+,./:;<=>?@[\\]^`{|}~");
    }


    /**
     * Escapes variables for use inside <script>.
     *
     * @param  mixed  plain text
     *
     * @return string JSON
     */
    public static function escapeJs($s): string
    {
        if ($s instanceof HtmlStringInterface) {
            $s = $s->__toString();
        }

        $json = json_encode($s, JSON_UNESCAPED_UNICODE);
        if ($error = json_last_error()) {
            throw new \RuntimeException(json_last_error_msg(), $error);
        }

        return str_replace(["\u{2028}", "\u{2029}", ']]>', '<!'], ['\u2028', '\u2029', ']]\x3E', '\x3C!'], $json);
    }


    /**
     * Escapes string for use inside iCal template.
     *
     * @param  string plain text
     */
    public static function escapeICal($s): string
    {
        // https://www.ietf.org/rfc/rfc5545.txt
        return addcslashes(preg_replace('#[\x00-\x08\x0B\x0C-\x1F]+#', '', (string) $s), "\";\\,:\n");
    }


    /**
     * Escapes CSS/JS for usage in <script> and <style>..
     *
     * @param  string CSS/JS
     *
     * @return string HTML RAWTEXT
     */
    public static function escapeHtmlRawText($s): string
    {
        return preg_replace('#</(script|style)#i', '<\\/$1', (string) $s);
    }


    /**
     * Converts HTML to plain text.
     *
     * @param
     * @param  string HTML
     *
     * @return string plain text
     */
    public static function stripHtml(FilterInfo $info, $s): string
    {
        if (!in_array($info->contentType, [null, 'html', 'xhtml', 'htmlAttr', 'xhtmlAttr', 'xml', 'xmlAttr'], true)) {
            trigger_error("Filter |stripHtml used with incompatible type " . strtoupper($info->contentType),
                E_USER_WARNING);
        }
        $info->contentType = Engine::CONTENT_TEXT;

        return html_entity_decode(strip_tags((string) $s), ENT_QUOTES, 'UTF-8');
    }


    /**
     * Removes tags from HTML (but remains HTML entites).
     *
     * @param
     * @param  string HTML
     *
     * @return string HTML
     */
    public static function stripTags(FilterInfo $info, $s): string
    {
        if (!in_array($info->contentType, [null, 'html', 'xhtml', 'htmlAttr', 'xhtmlAttr', 'xml', 'xmlAttr'], true)) {
            trigger_error("Filter |stripTags used with incompatible type " . strtoupper($info->contentType),
                E_USER_WARNING);
        }

        return strip_tags((string) $s);
    }


    /**
     * Converts ... to ...
     */
    public static function convertTo(FilterInfo $info, $dest, $s): string
    {
        $source = $info->contentType ?: Engine::CONTENT_TEXT;
        if ($source === $dest) {
            return $s;
        }

        if ($conv = self::getConvertor($source, $dest)) {
            $info->contentType = $dest;

            return $conv($s);
        }

        trigger_error("Filters: unable to convert content type " . strtoupper($source) . " to " . strtoupper($dest),
            E_USER_WARNING);

        return $s;
    }


    /**
     * @return callable|NULL
     */
    public static function getConvertor($source, $dest)
    {
        static $table = [
            Engine::CONTENT_TEXT => [
                'html' => 'escapeHtmlText',
                'xhtml' => 'escapeHtmlText',
                'htmlAttr' => 'escapeHtmlAttr',
                'xhtmlAttr' => 'escapeHtmlAttr',
                'htmlAttrJs' => 'escapeHtmlAttr',
                'xhtmlAttrJs' => 'escapeHtmlAttr',
                'htmlAttrCss' => 'escapeHtmlAttr',
                'xhtmlAttrCss' => 'escapeHtmlAttr',
                'htmlAttrUrl' => 'escapeHtmlAttr',
                'xhtmlAttrUrl' => 'escapeHtmlAttr',
                'htmlComment' => 'escapeHtmlComment',
                'xhtmlComment' => 'escapeHtmlComment',
                'xml' => 'escapeXml',
                'xmlAttr' => 'escapeXml',
            ],
            Engine::CONTENT_JS => [
                'html' => 'escapeHtmlText',
                'xhtml' => 'escapeHtmlText',
                'htmlAttr' => 'escapeHtmlAttr',
                'xhtmlAttr' => 'escapeHtmlAttr',
                'htmlAttrJs' => 'escapeHtmlAttr',
                'xhtmlAttrJs' => 'escapeHtmlAttr',
                'htmlJs' => 'escapeHtmlRawText',
                'xhtmlJs' => 'escapeHtmlRawText',
                'htmlComment' => 'escapeHtmlComment',
                'xhtmlComment' => 'escapeHtmlComment',
            ],
            Engine::CONTENT_CSS => [
                'html' => 'escapeHtmlText',
                'xhtml' => 'escapeHtmlText',
                'htmlAttr' => 'escapeHtmlAttr',
                'xhtmlAttr' => 'escapeHtmlAttr',
                'htmlAttrCss' => 'escapeHtmlAttr',
                'xhtmlAttrCss' => 'escapeHtmlAttr',
                'htmlCss' => 'escapeHtmlRawText',
                'xhtmlCss' => 'escapeHtmlRawText',
                'htmlComment' => 'escapeHtmlComment',
                'xhtmlComment' => 'escapeHtmlComment',
            ],
            Engine::CONTENT_HTML => [
                'htmlAttr' => 'escapeHtmlAttrConv',
                'htmlAttrJs' => 'escapeHtmlAttrConv',
                'htmlAttrCss' => 'escapeHtmlAttrConv',
                'htmlAttrUrl' => 'escapeHtmlAttrConv',
                'htmlComment' => 'escapeHtmlComment',
            ],
            Engine::CONTENT_XHTML => [
                'xhtmlAttr' => 'escapeHtmlAttrConv',
                'xhtmlAttrJs' => 'escapeHtmlAttrConv',
                'xhtmlAttrCss' => 'escapeHtmlAttrConv',
                'xhtmlAttrUrl' => 'escapeHtmlAttrConv',
                'xhtmlComment' => 'escapeHtmlComment',
            ],
        ];

        return isset($table[$source][$dest]) ? [self::class, $table[$source][$dest]] : null;
    }


    /**
     * Sanitizes string for use inside href attribute.
     *
     * @param  string plain text
     *
     * @return string plain text
     */
    public static function safeUrl($s): string
    {
        $s = (string) $s;

        return preg_match('~^(?:(?:https?|ftp)://[^@]+(?:/.*)?|mailto:.+|[/?#].*|[^:]+)\z~i', $s) ? $s : '';
    }


    /**
     * Replaces all repeated white spaces with a single space.
     *
     * @param
     * @param  string text|HTML
     *
     * @return string text|HTML
     */
    public static function strip(FilterInfo $info, string $s): string
    {
        return in_array($info->contentType, [Engine::CONTENT_HTML, Engine::CONTENT_XHTML], true)
            ? trim(self::spacelessHtml($s))
            : trim(self::spacelessText($s));
    }


    /**
     * Replaces all repeated white spaces with a single space.
     *
     * @param  string HTML
     * @param  int    output buffering phase
     * @param  bool   stripping mode
     *
     * @return string HTML
     */
    public static function spacelessHtml(string $s, int $phase = null, bool &$strip = true): string
    {
        if ($phase & PHP_OUTPUT_HANDLER_START) {
            $s = ltrim($s);
        }
        if ($phase & PHP_OUTPUT_HANDLER_FINAL) {
            $s = rtrim($s);
        }

        return preg_replace_callback(
            '#[ \t\r\n]+|<(/)?(textarea|pre|script)(?=\W)#si',
            function ($m) use (&$strip) {
                if (empty($m[2])) {
                    return $strip ? ' ' : $m[0];
                } else {
                    $strip = !empty($m[1]);

                    return $m[0];
                }
            },
            $s
        );
    }


    /**
     * Replaces all repeated white spaces with a single space.
     *
     * @param  string text
     *
     * @return string text
     */
    public static function spacelessText(string $s): string
    {
        return preg_replace('#[ \t\r\n]+#', ' ', $s);
    }


    /**
     * Indents plain text or HTML the content from the left.
     * @return string
     */
    public static function indent(FilterInfo $info, string $s, int $level = 1, string $chars = "\t"): string
    {
        if ($level < 1) {
            // do nothing
        } elseif (in_array($info->contentType, [Engine::CONTENT_HTML, Engine::CONTENT_XHTML], true)) {
            $s = preg_replace_callback('#<(textarea|pre).*?</\\1#si', function ($m) {
                return strtr($m[0], " \t\r\n", "\x1F\x1E\x1D\x1A");
            }, $s);
            if (preg_last_error()) {
                throw new RegexpException(null, preg_last_error());
            }
            $s = preg_replace('#(?:^|[\r\n]+)(?=[^\r\n])#', '$0' . str_repeat($chars, $level), $s);
            $s = strtr($s, "\x1F\x1E\x1D\x1A", " \t\r\n");
        } else {
            $s = preg_replace('#(?:^|[\r\n]+)(?=[^\r\n])#', '$0' . str_repeat($chars, $level), $s);
        }

        return $s;
    }


    /**
     * Repeats text.
     *
     * @param
     *
     * @return string plain text
     */
    public static function repeat(FilterInfo $info, $s, int $count): string
    {
        return str_repeat((string) $s, $count);
    }


    /**
     * Date/time formatting.
     *
     * @param  string|int|\DateTimeInterface|\DateInterval
     *
     * @return string|NULL
     */
    public static function date($time, string $format = null)
    {
        if ($time == null) { // intentionally ==
            return null;
        }

        if (!isset($format)) {
            $format = self::$dateFormat;
        }

        if ($time instanceof \DateInterval) {
            return $time->format($format);
        }

        if (is_numeric($time)) {
            $time = new \DateTime('@' . $time);
            $time->setTimeZone(new \DateTimeZone(date_default_timezone_get()));
        } elseif (!$time instanceof \DateTimeInterface) {
            $time = new \DateTime($time);
        }

        return strpos($format, '%') === false
            ? $time->format($format) // formats using date()
            : strftime($format, $time->format('U') + 0); // formats according to locales
    }


    /**
     * Converts to human readable file size.
     * @return string plain text
     */
    public static function bytes(float $bytes, int $precision = 2): string
    {
        $bytes = round($bytes);
        $units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];
        foreach ($units as $unit) {
            if (abs($bytes) < 1024 || $unit === end($units)) {
                break;
            }
            $bytes = $bytes / 1024;
        }

        return round($bytes, $precision) . ' ' . $unit;
    }


    /**
     * Performs a search and replace.
     *
     * @param
     */
    public static function replace(FilterInfo $info, $subject, string $search, string $replacement = ''): string
    {
        return str_replace($search, $replacement, (string) $subject);
    }


    /**
     * Perform a regular expression search and replace.
     */
    public static function replaceRe(string $subject, string $pattern, string $replacement = ''): string
    {
        $res = preg_replace($pattern, $replacement, $subject);
        if (preg_last_error()) {
            throw new RegexpException(null, preg_last_error());
        }

        return $res;
    }


    /**
     * The data: URI generator.
     *
     * @param  string plain text
     *
     * @return string plain text
     */
    public static function dataStream(string $data, string $type = null): string
    {
        if ($type === null) {
            $type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);
        }

        return 'data:' . ($type ? "$type;" : '') . 'base64,' . base64_encode($data);
    }


    /**
     * @param  string plain text
     */
    public static function breaklines($s): Html
    {
        return new Html(nl2br(htmlSpecialChars((string) $s, ENT_NOQUOTES, 'UTF-8'), self::$xhtml));
    }


    /**
     * Returns a part of string.
     */
    public static function substring($s, int $start, int $length = null): string
    {
        $s = (string) $s;
        if ($length === null) {
            $length = mb_strlen($s);
        }

        return mb_substr($s, $start, $length);
    }


    /**
     * Truncates string to maximal length.
     *
     * @param string $s      plain text
     * @param string $append plain text
     *
     * @return string plain text
     */
    public static function truncate($s, $maxLen, $append = "\u{2026}"): string
    {
        $s = (string) $s;
        if (mb_strlen($s) > $maxLen) {
            $maxLen -= mb_strlen($append);
            if ($maxLen < 1) {
                return $append;
            }

            if (preg_match('#^.{1,' . $maxLen . '}(?=[\s\x00-/:-@\[-`{-~])#us', $s, $matches)) {
                return $matches[0] . $append;
            }

            return self::substring($s, 0, $maxLen) . $append;
        }

        return $s;
    }


    /**
     * Convert to lower case.
     *
     * @param  string plain text
     *
     * @return string plain text
     */
    public static function lower($s): string
    {
        return mb_strtolower((string) $s);
    }


    /**
     * Convert to upper case.
     *
     * @param  string plain text
     *
     * @return string plain text
     */
    public static function upper($s): string
    {
        return mb_strtoupper((string) $s);
    }


    /**
     * Convert first character to upper case.
     *
     * @param  string plain text
     *
     * @return string plain text
     */
    public static function firstUpper($s): string
    {
        $s = (string) $s;

        return self::upper(self::substring($s, 0, 1)) . self::substring($s, 1);
    }


    /**
     * Capitalize string.
     *
     * @param  string plain text
     *
     * @return string plain text
     */
    public static function capitalize($s): string
    {
        return mb_convert_case((string) $s, MB_CASE_TITLE);
    }


    /**
     * Returns string length.
     *
     * @param  array|\Countable|\Traversable|string
     */
    public static function length($val): int
    {
        if (is_array($val) || $val instanceof \Countable) {
            return count($val);
        }

        if ($val instanceof \Traversable) {
            return iterator_count($val);
        }

        return mb_strlen($val);
    }


    /**
     * Strips whitespace.
     *
     * @param  string $s
     * @param  string $charlist
     *
     * @return string plain text
     */
    public static function trim($s, $charlist = " \t\n\r\0\x0B\u{A0}"): string
    {
        $charlist = preg_quote($charlist, '#');
        $s = preg_replace('#^[' . $charlist . ']+|[' . $charlist . ']+\z#u', '', (string) $s);
        if (preg_last_error()) {
            throw new RegexpException(null, preg_last_error());
        }

        return $s;
    }


    /**
     * Pad a string to a certain length with another string.
     *
     * @param string $s
     * @param int    $length
     * @param string $pad
     *
     * @return string
     */
    public static function padLeft($s, int $length, string $pad = ' '): string
    {
        $s = (string) $s;
        $length = max(0, $length - mb_strlen($s));
        $padLen = mb_strlen($pad);

        return str_repeat($pad, (int) ($length / $padLen)) . self::substring($pad, 0, $length % $padLen) . $s;
    }


    /**
     * Pad a string to a certain length with another string.
     *
     * @param string $s
     * @param int    $length
     * @param string $pad
     *
     * @return string
     */
    public static function padRight($s, int $length, string $pad = ' '): string
    {
        $s = (string) $s;
        $length = max(0, $length - mb_strlen($s));
        $padLen = mb_strlen($pad);

        return $s . str_repeat($pad, (int) ($length / $padLen)) . self::substring($pad, 0, $length % $padLen);
    }


    /**
     * Returns element's attributes.
     */
    public static function htmlAttributes($attrs): string
    {
        if (!is_array($attrs)) {
            return '';
        }

        $s = '';
        foreach ($attrs as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                if (static::$xhtml) {
                    $s .= ' ' . $key . '="' . $key . '"';
                } else {
                    $s .= ' ' . $key;
                }
                continue;
            }

            if (is_array($value)) {
                $tmp = null;
                foreach ($value as $k => $v) {
                    if ($v !== null) { // intentionally ==, skip NULLs & empty string
                        //  composite 'style' vs. 'others'
                        $tmp[] = $v === true ? $k : (is_string($k) ? $k . ':' . $v : $v);
                    }
                }
                if ($tmp === null) {
                    continue;
                }

                $value = implode($key === 'style' || !strncmp($key, 'on', 2) ? ';' : ' ', $tmp);

            } else {
                $value = (string) $value;
            }

            $q = strpos($value, '"') === false ? '"' : "'";
            $s .= ' ' . $key . '=' . $q
                . str_replace(
                    ['&', $q, '<'],
                    ['&amp;', $q === '"' ? '&quot;' : '&#39;', self::$xhtml ? '&lt;' : '<'],
                    $value
                )
                . (strpos($value, '`') !== false && strpbrk($value, ' <>"\'') === false ? ' ' : '')
                . $q;
        }

        return $s;
    }

}
