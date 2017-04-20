<?php

namespace Hail\Container;

use RuntimeException;

/**
 * This class implements a simple dependency injection container.
 */
class Compiler
{
	protected $config;

	protected $points = [];
	protected $methods = [];

	public function __construct(array $config)
	{
		$this->config = $config;
	}

	public function compile(): string
	{
		$this->parseServices();

		$code = "<?php\n";
		$code .= "class Container extends Hail\\Container\\Container\n";
		$code .= "{\n";

		$code .= "\tprotected static \$entryPoints = [\n";
		foreach ($this->points as $k => $v) {
			$code .= "\t\t" . $this->classname($k) . " => $v,\n";
		}
		$code .= "\t];\n\n";
		$code .= "\tpublic function get(\$name)\n";
		$code .= "\t{\n";
		$code .= "\t\tif (isset(\$this->active[\$name])) {\n";
		$code .= "\t\t\treturn \$this->values[\$name];\n";
		$code .= "\t\t}\n\n";
		$code .= "\t\tif (isset(static::\$entryPoints[\$name])) {\n";
        $code .= "\t\t\t\$this->active[\$name] = true;\n";
		$code .= "\t\t\treturn \$this->values[\$name] = \$this->{static::\$entryPoints[\$name]}();\n";
		$code .= "\t\t}\n\n";
		$code .= "\t\treturn parent::get(\$name);\n";
		$code .= "\t}\n\n";
		$code .= implode("\n\n", $this->methods) . "\n";
		$code .= '}';

		return $code;
	}

	protected function parseServices()
	{
		$services = $this->config ?? [];
		$alias = [];

		foreach ($services as $k => $v) {
			if (is_string($v)) {
				if ($v[0] === '@') {
				    $v = substr($v, 1);
				    if ($v !== '') {
                        $alias[$k] = $v;
                    }
				} else {
					$factory = $this->parseStrToClass($v);
					if ($this->isClassname($v)) {
                        $alias[$v] = $k;
					}
					$this->toMethod($k, "{$factory}()");
				}

				continue;
			}

            if ($v === []) {
                if ($this->isClassname($k)) {
                    $this->toMethod($k, "new {$k}()");
                }
                continue;
            }

			if (!is_array($v)) {
				continue;
			}

			if (isset($v['alias'])) {
			    $alias[$k] = $v['alias'];
				continue;
			}

			$to = (array) ($v['to'] ?? []);
            if (isset($v['class|to']) && $v['class|to'] !== $k) {
                $to[] = $v['class|to'];
            }

            foreach ($to as $ref) {
                $alias[$ref] = $k;
            }

			$arguments = '';
			if (isset($v['arguments'])) {
				$arguments = $this->parseArguments($v['arguments']);
			}

			$suffix = array_merge(
				$this->parseProperty($v['property'] ?? []),
				$this->parseCalls($v['calls'] ?? [])
			);

			if (isset($v['factory'])) {
				$factory = $v['factory'];
				if (is_array($v['factory'])) {
					[$c, $m] = $v['factory'];
					$factory = "{$c}::{$m}";
				}

				if (!is_string($factory)) {
					continue;
				}
			} elseif (isset($v['class|to'])) {
                $factory = $v['class|to'];
            } elseif (isset($v['class'])) {
				$factory = $v['class'];
			} elseif ($this->isClassname($k)) {
				$factory = $k;
			} else {
				throw new RuntimeException('Component not defined any build arguments: ' . $k);
			}

			$factory = $this->parseStrToClass($factory);
			$this->toMethod($k, "{$factory}($arguments)", $suffix);
		}

		$refs = [];
		foreach ($alias as $k => $v) {
            if (isset($refs[$v])) {
                $this->toPoint($k, $refs[$v]);
            } elseif (isset($this->points[$v])) {
                $this->toMethod($k, $this->parseRef($v));
                $refs[$v] = $k;
            }
        }
	}

	protected function parseArguments(array $args): string
	{
		return implode(', ', array_map([$this, 'parseStr'], $args));
	}

	protected function parseProperty(array $props): array
	{
		if ($props === []) {
			return [];
		}

		$return = [];
		foreach ($props as $k => $v) {
			$return[] = $k . ' = ' . $this->parseStr($v);
		}

		return $return;
	}

	protected function parseCalls(array $calls): array
	{
		if ($calls === []) {
			return [];
		}

		$return = [];
		foreach ($calls as $method => $v) {
		    $args = '';
			if (is_array($v)) {
				$args = $this->parseArguments($v);
			}

            $return[] = $method . '(' . $args . ')';
		}

		return $return;
	}

	protected function parseStrToClass($str)
	{
		if (strpos($str, '::') !== false) {
			[$class, $method] = explode('::', $str);
			return "{$class}::{$method}";
		}

		if (strpos($str, ':') !== false) {
			[$ref, $method] = explode(':', $str);
			return $this->parseRef($ref) . "->{$method}";
		}

		if ($this->isClassname($str)) {
            return "new $str";
        }

        throw new RuntimeException("Given value can not convert to build function : $str");
	}

	protected function parseStr($str)
	{
		if (is_string($str)) {
			if (strpos($str, 'CONFIG.') === 0) {
				$str = var_export(substr($str, 7), true);

				return $this->parseRef('config') . '->get(' . $str . ')';
			}

			if (isset($str[0]) && $str[0] === '@') {
				$str = substr($str, 1);
				if ($str === '') {
					$str = '@';
				} elseif ($str[0] !== '@') {
					return $this->parseRef($str);
				}
			}
		}

		return var_export($str, true);
	}

	protected function parseRef($name)
	{
		return '$this->get(' . $this->classname($name) . ')';
	}

	protected function isClassname($name)
	{
		return (class_exists($name) || interface_exists($name) || trait_exists($name)) && strtoupper($name[0]) === $name[0];
	}

	protected function classname($name)
	{
		if ($name[0] === '\\') {
			$name = ltrim($name, '\\');
		}

		if ($this->isClassname($name)) {
			return "$name::class";
		}

		return var_export($name, true);
	}

	protected function methodName($string)
	{
		if ($string[0] === '\\') {
			$string = ltrim($string, '\\');
		}

		$name = 'HAIL_';
		if ($this->isClassname($string)) {
			$name .= 'CLASS__';
		} else {
			$name .= 'PARAM__';
		}

		$name .= str_replace(['\\', '.'], '__', $string);

		return $name;
	}

	protected function toPoint($name, $point)
    {
        $method = $this->methodName($point);
        $this->points[$name] = "'$method'";
    }

	protected function toMethod($name, $return, array $suffix = [])
	{
		$method = $this->methodName($name);
		$this->points[$name] = "'$method'";

		$code = "\tprotected function {$method}() {\n";
		if ($suffix !== []) {
			$code .= "\t\t\$object = $return;\n";
			$code .= "\t\t\$object->" . implode(";\n\t\t\$object->", $suffix) . ";\n";
			$return = '$object';
		}

		$code .= "\t\treturn $return;\n";
		$code .= "\t}";

		$this->methods[] = $code;
	}
}
