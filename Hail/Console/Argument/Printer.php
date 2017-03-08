<?php
/**
 * This file is part of the GetOptionKit package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Console\Input;

class Printer
{
	public $screenWidth = 78;

	/**
	 * Render readable spec.
	 *
	 * @param Option $opt
	 *
	 * @return string
	 */
	public function renderOption(Option $opt)
	{
		$c1 = '';
		if ($opt->short && $opt->long) {
			$c1 = "-{$opt->short}, --{$opt->long}";
		} else if ($opt->short) {
			$c1 = "-{$opt->short}";
		} else if ($opt->long) {
			$c1 = "--{$opt->long}";
		}
		$c1 .= $opt->renderValueHint();

		return $c1;
	}

	/**
	 * render option descriptions.
	 *
	 * @param Collection $options
	 *
	 * @return string output
	 */
	public function render(Collection $options)
	{
		# echo "* Available options:\n";
		$lines = [];
		foreach ($options as $option) {
			$c1 = $this->renderOption($option);
			$lines[] = "\t" . $c1;
			$lines[] = wordwrap("\t\t" . $option->desc, $this->screenWidth, "\n\t\t");  # wrap text
			$lines[] = '';
		}

		return implode("\n", $lines);
	}
}