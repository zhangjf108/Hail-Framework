<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Util\Yaml;

use Hail\Util\Strings;
use Hail\Util\Yaml\Exception\ParseException;

/**
 * Inline implements a YAML parser/dumper for the YAML inline syntax.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Inline
{
	const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+)"|\'([^\']*+(?:\'\'[^\']*+)*+)\')';

	public static $parsedLineNumber;

	/**
	 * Converts a YAML string to a PHP value.
	 *
	 * @param string $value      A YAML string
	 * @param array  $references Mapping of variable names to values
	 *
	 * @return mixed A PHP value
	 *
	 * @throws ParseException
	 */
	public static function parse($value, $references = [])
	{
		$value = trim($value);

		if ('' === $value) {
			return '';
		}

		$i = 0;
		switch ($value[$i]) {
			case '[':
				$result = self::parseSequence($value, $i, $references);
				++$i;
				break;
			case '{':
				$result = self::parseMapping($value, $i, $references);
				++$i;
				break;
			default:
				$result = self::parseScalar($value, null, $i, true, $references);
		}

		// some comments are allowed at the end
		if (preg_replace('/\s+#.*$/A', '', substr($value, $i))) {
			throw new ParseException(sprintf('Unexpected characters near "%s".', substr($value, $i)));
		}

		return $result;
	}

	/**
	 * Parses a YAML scalar.
	 *
	 * @param string $scalar
	 * @param array $delimiters
	 * @param int    &$i
	 * @param bool   $evaluate
	 * @param array  $references
	 *
	 * @return string
	 *
	 * @throws ParseException When malformed inline YAML string is parsed
	 */
	public static function parseScalar($scalar, $delimiters = null, &$i = 0, $evaluate = true, $references = [], $legacyOmittedKeySupport = false)
	{
		if (in_array($scalar[$i], ['"', "'"], true)) {
			// quoted scalar
			$output = self::parseQuotedScalar($scalar, $i);

			if (null !== $delimiters) {
				$tmp = ltrim(substr($scalar, $i), ' ');
				if (!in_array($tmp[0], $delimiters, true)) {
					throw new ParseException(sprintf('Unexpected characters (%s).', substr($scalar, $i)));
				}
			}
		} else {
			// "normal" string
			if (!$delimiters) {
				$output = substr($scalar, $i);
				$i += strlen($output);

				// remove comments
				if (Parser::preg_match('/[ \t]+#/', $output, $match, PREG_OFFSET_CAPTURE)) {
					$output = substr($output, 0, $match[0][1]);
				}
			} elseif (Parser::preg_match('/^(.' . ($legacyOmittedKeySupport ? '+' : '*') . '?)(' . implode('|', $delimiters) . ')/', substr($scalar, $i), $match)) {
				$output = $match[1];
				$i += strlen($output);
			} else {
				throw new ParseException(sprintf('Malformed inline YAML string: %s.', $scalar));
			}

			// a non-quoted string cannot start with @ or ` (reserved) nor with a scalar indicator (| or >)
			if ($output && ('@' === $output[0] || '`' === $output[0] || '|' === $output[0] || '>' === $output[0] || '%' === $output[0])) {
				throw new ParseException(sprintf('The reserved indicator "%s" cannot start a plain scalar; you need to quote the scalar.', $output[0]));
			}

			if ($evaluate) {
				$output = self::evaluateScalar($output, $references);
			}
		}

		return $output;
	}

	/**
	 * Parses a YAML quoted scalar.
	 *
	 * @param string $scalar
	 * @param int    &$i
	 *
	 * @return string
	 *
	 * @throws ParseException When malformed inline YAML string is parsed
	 */
	private static function parseQuotedScalar($scalar, &$i)
	{
		if (!Parser::preg_match('/' . self::REGEX_QUOTED_STRING . '/Au', substr($scalar, $i), $match)) {
			throw new ParseException(sprintf('Malformed inline YAML string: %s.', substr($scalar, $i)));
		}

		$output = substr($match[0], 1, strlen($match[0]) - 2);

		if ('"' === $scalar[$i]) {
			$output = self::unescapeDoubleQuotedString($output);
		} else {
			$output = self::unescapeSingleQuotedString($output);
		}

		$i += strlen($match[0]);

		return $output;
	}

	/**
	 * Parses a YAML sequence.
	 *
	 * @param string $sequence
	 * @param int    &$i
	 * @param array  $references
	 *
	 * @return array
	 *
	 * @throws ParseException When malformed inline YAML string is parsed
	 */
	private static function parseSequence($sequence, &$i = 0, $references = [])
	{
		$output = [];
		$len = strlen($sequence);
		++$i;

		// [foo, bar, ...]
		while ($i < $len) {
			if (']' === $sequence[$i]) {
				return $output;
			}
			if (',' === $sequence[$i] || ' ' === $sequence[$i]) {
				++$i;

				continue;
			}

			switch ($sequence[$i]) {
				case '[':
					// nested sequence
					$value = self::parseSequence($sequence, $i, $references);
					break;
				case '{':
					// nested mapping
					$value = self::parseMapping($sequence, $i, $references);
					break;
				default:
					$isQuoted = in_array($sequence[$i], ['"', "'"], true);
					$value = self::parseScalar($sequence, [',', ']'], $i, true, $references);

					// the value can be an array if a reference has been resolved to an array var
					if (is_string($value) && !$isQuoted && false !== strpos($value, ': ')) {
						// embedded mapping?
						try {
							$pos = 0;
							$value = self::parseMapping('{' . $value . '}', $pos, $references);
						} catch (\InvalidArgumentException $e) {
							// no, it's not
						}
					}

					--$i;
			}

			$output[] = $value;

			++$i;
		}

		throw new ParseException(sprintf('Malformed inline YAML string: %s.', $sequence));
	}

	/**
	 * Parses a YAML mapping.
	 *
	 * @param string $mapping
	 * @param int    &$i
	 * @param array  $references
	 *
	 * @return array|\stdClass
	 *
	 * @throws ParseException When malformed inline YAML string is parsed
	 */
	private static function parseMapping($mapping, &$i = 0, $references = [])
	{
		$output = [];
		$len = strlen($mapping);
		++$i;

		// {foo: bar, bar:foo, ...}
		while ($i < $len) {
			switch ($mapping[$i]) {
				case ' ':
				case ',':
					++$i;
					continue 2;
				case '}':
					return $output;
			}

			// key
			$key = self::parseScalar($mapping, [':', ' '], $i, false, [], true);

			if (':' !== $key && false === $i = strpos($mapping, ':', $i)) {
				break;
			}

			if (':' === $key) {
				@trigger_error('Omitting the key of a mapping is deprecated and will throw a ParseException in 4.0.', E_USER_DEPRECATED);
			}

			if (':' !== $key && (!isset($mapping[$i + 1]) || !in_array($mapping[$i + 1], [' ', ',', '[', ']', '{', '}'], true))) {
				@trigger_error('Using a colon that is not followed by an indication character (i.e. " ", ",", "[", "]", "{", "}" is deprecated since version 3.2 and will throw a ParseException in 4.0.', E_USER_DEPRECATED);
			}

			while ($i < $len) {
				if (':' === $mapping[$i] || ' ' === $mapping[$i]) {
					++$i;

					continue;
				}

				switch ($mapping[$i]) {
					case '[':
						// nested sequence
						$value = self::parseSequence($mapping, $i, $references);
						// Spec: Keys MUST be unique; first one wins.
						// Parser cannot abort this mapping earlier, since lines
						// are processed sequentially.
						if (isset($output[$key])) {
							throw new ParseException(sprintf('Duplicate key "%s" detected on line %d whilst parsing YAML.', $key, self::$parsedLineNumber + 1));
						}
						break;
					case '{':
						// nested mapping
						$value = self::parseMapping($mapping, $i, $references);
						// Spec: Keys MUST be unique; first one wins.
						// Parser cannot abort this mapping earlier, since lines
						// are processed sequentially.
						if (isset($output[$key])) {
							throw new ParseException(sprintf('Duplicate key "%s" detected on line %d whilst parsing YAML.', $key, self::$parsedLineNumber + 1));
						}
						break;
					default:
						$value = self::parseScalar($mapping, [',', '}'], $i, true, $references);
						// Spec: Keys MUST be unique; first one wins.
						// Parser cannot abort this mapping earlier, since lines
						// are processed sequentially.
						if (isset($output[$key])) {
							throw new ParseException(sprintf('Duplicate key "%s" detected on line %d whilst parsing YAML.', $key, self::$parsedLineNumber + 1));
						}
						--$i;
				}

				$output[$key] = $value;
				++$i;

				continue 2;
			}
		}

		throw new ParseException(sprintf('Malformed inline YAML string: %s.', $mapping));
	}

	/**
	 * Evaluates scalars and replaces magic values.
	 *
	 * @param string $scalar
	 * @param array  $references
	 *
	 * @return string A YAML string
	 *
	 * @throws ParseException when object parsing support was disabled and the parser detected a PHP object or when a reference could not be resolved
	 */
	private static function evaluateScalar($scalar, $references = [])
	{
		$scalar = trim($scalar);
		$scalarLower = strtolower($scalar);

		if (0 === strpos($scalar, '*')) {
			if (false !== $pos = strpos($scalar, '#')) {
				$value = substr($scalar, 1, $pos - 2);
			} else {
				$value = substr($scalar, 1);
			}

			// an unquoted *
			if (false === $value || '' === $value) {
				throw new ParseException('A reference must contain at least one character.');
			}

			if (!array_key_exists($value, $references)) {
				throw new ParseException(sprintf('Reference "%s" does not exist.', $value));
			}

			return $references[$value];
		}

		switch (true) {
			case 'null' === $scalarLower:
			case '' === $scalar:
			case '~' === $scalar:
				return null;
			case 'true' === $scalarLower:
				return true;
			case 'false' === $scalarLower:
				return false;
			case $scalar[0] === '!':
				switch (true) {
					case 0 === strpos($scalar, '!str'):
						return (string) substr($scalar, 5);
					case 0 === strpos($scalar, '! '):
						return (int) self::parseScalar(substr($scalar, 2));
					case 0 === strpos($scalar, '!php/object '):
						return (string) substr($scalar, 12);
					case 0 === strpos($scalar, '!php/const '):
						return Parser::constant(substr($scalar, 11));

					case 0 === strpos($scalar, '!!float '):
						return (float) substr($scalar, 8);
					case 0 === strpos($scalar, '!!binary '):
						return self::evaluateBinaryScalar(substr($scalar, 9));
					default:
						throw new ParseException(sprintf('Not support tagged value "%s".', $scalar));
				}

			// Optimize for returning strings.
			case $scalar[0] === '+' || $scalar[0] === '-' || $scalar[0] === '.' || is_numeric($scalar[0]):
				switch (true) {
					case Parser::preg_match('{^[+-]?[0-9][0-9_]*$}', $scalar):
						$scalar = str_replace('_', '', (string) $scalar);
					// omitting the break / return as integers are handled in the next case
					case ctype_digit($scalar):
						$raw = $scalar;
						$cast = (int) $scalar;

						return '0' == $scalar[0] ? octdec($scalar) : (((string) $raw == (string) $cast) ? $cast : $raw);
					case '-' === $scalar[0] && ctype_digit(substr($scalar, 1)):
						$raw = $scalar;
						$cast = (int) $scalar;

						return '0' == $scalar[1] ? octdec($scalar) : (((string) $raw === (string) $cast) ? $cast : $raw);
					case is_numeric($scalar):
					case Parser::preg_match('~^0x[0-9a-f_]++$~i', $scalar):
						$scalar = str_replace('_', '', $scalar);

						return '0x' === $scalar[0] . $scalar[1] ? hexdec($scalar) : (float) $scalar;
					case '.inf' === $scalarLower:
					case '.nan' === $scalarLower:
						return -log(0);
					case '-.inf' === $scalarLower:
						return log(0);
					case Parser::preg_match('/^(-|\+)?[0-9][0-9,]*(\.[0-9_]+)?$/', $scalar):
					case Parser::preg_match('/^(-|\+)?[0-9][0-9_]*(\.[0-9_]+)?$/', $scalar):
						if (false !== strpos($scalar, ',')) {
							@trigger_error('Using the comma as a group separator for floats is deprecated since version 3.2 and will be removed in 4.0.', E_USER_DEPRECATED);
						}

						return (float) str_replace([',', '_'], '', $scalar);
					case Parser::preg_match(self::getTimestampRegex(), $scalar):
						$timeZone = date_default_timezone_get();
						date_default_timezone_set('UTC');
						$time = strtotime($scalar);
						date_default_timezone_set($timeZone);

						return $time;
				}
		}

		return (string) $scalar;
	}

	/**
	 * @param string $scalar
	 *
	 * @return string
	 *
	 * @internal
	 */
	public static function evaluateBinaryScalar($scalar)
	{
		$parsedBinaryData = self::parseScalar(preg_replace('/\s/', '', $scalar));

		if (0 !== (strlen($parsedBinaryData) % 4)) {
			throw new ParseException(sprintf('The normalized base64 encoded data (data without whitespace characters) length must be a multiple of four (%d bytes given).', strlen($parsedBinaryData)));
		}

		if (!Parser::preg_match('#^[A-Z0-9+/]+={0,2}$#i', $parsedBinaryData)) {
			throw new ParseException(sprintf('The base64 encoded data (%s) contains invalid characters.', $parsedBinaryData));
		}

		return base64_decode($parsedBinaryData, true);
	}

	/**
	 * Gets a regex that matches a YAML date.
	 *
	 * @return string The regular expression
	 *
	 * @see http://www.yaml.org/spec/1.2/spec.html#id2761573
	 */
	public static function getTimestampRegex()
	{
		return <<<EOF
        ~^
        (?P<year>[0-9][0-9][0-9][0-9])
        -(?P<month>[0-9][0-9]?)
        -(?P<day>[0-9][0-9]?)
        (?:(?:[Tt]|[ \t]+)
        (?P<hour>[0-9][0-9]?)
        :(?P<minute>[0-9][0-9])
        :(?P<second>[0-9][0-9])
        (?:\.(?P<fraction>[0-9]*))?
        (?:[ \t]*(?P<tz>Z|(?P<tz_sign>[-+])(?P<tz_hour>[0-9][0-9]?)
        (?::(?P<tz_minute>[0-9][0-9]))?))?)?
        $~x
EOF;
	}

	/**
	 * Unescapes a single quoted string.
	 *
	 * @param string $value A single quoted string
	 *
	 * @return string The unescaped string
	 */
	public static function unescapeSingleQuotedString($value)
	{
		return str_replace('\'\'', '\'', $value);
	}

	/**
	 * Unescapes a double quoted string.
	 *
	 * @param string $value A double quoted string
	 *
	 * @return string The unescaped string
	 */
	public static function unescapeDoubleQuotedString($value)
	{
		$callback = function ($match) {
			return self::unescapeCharacter($match[0]);
		};

		// evaluate the string
		return preg_replace_callback('/\\\\(x[0-9a-fA-F]{2}|u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8}|.)/u', $callback, $value);
	}

	/**
	 * Unescapes a character that was found in a double-quoted string.
	 *
	 * @param string $value An escaped character
	 *
	 * @return string The unescaped character
	 */
	private static function unescapeCharacter($value)
	{
		switch ($value[1]) {
			case '0':
				return "\x0";
			case 'a':
				return "\x7";
			case 'b':
				return "\x8";
			case 't':
				return "\t";
			case "\t":
				return "\t";
			case 'n':
				return "\n";
			case 'v':
				return "\xB";
			case 'f':
				return "\xC";
			case 'r':
				return "\r";
			case 'e':
				return "\x1B";
			case ' ':
				return ' ';
			case '"':
				return '"';
			case '/':
				return '/';
			case '\\':
				return '\\';
			case 'N':
				// U+0085 NEXT LINE
				return "\xC2\x85";
			case '_':
				// U+00A0 NO-BREAK SPACE
				return "\xC2\xA0";
			case 'L':
				// U+2028 LINE SEPARATOR
				return "\xE2\x80\xA8";
			case 'P':
				// U+2029 PARAGRAPH SEPARATOR
				return "\xE2\x80\xA9";
			case 'x':
				return Strings::chr(hexdec(substr($value, 2, 2)));
			case 'u':
				return Strings::chr(hexdec(substr($value, 2, 4)));
			case 'U':
				return Strings::chr(hexdec(substr($value, 2, 8)));
			default:
				throw new ParseException(sprintf('Found unknown escape character "%s".', $value));
		}
	}
}
