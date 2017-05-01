<?php
namespace Hail\Tracy\Bar;

use Hail\Tracy\Profiler;

class ProfilerPanel implements PanelInterface
{
	/**
	 * @inheritdoc
	 */
	public function getTab()
	{
		ob_start();
		$title = 'disabled';
		if (Profiler::isEnabled()) {
			$title = Profiler::count();
			$title .= $title > 1 ? 'profiles' : 'profile';
		}
		require __DIR__ . '/templates/profiler.tab.phtml';
		return ob_get_clean();
	}
	/**
	 * @inheritdoc
	 */
	public function getPanel()
	{
		ob_start();
		require __DIR__ . '/templates/profiler.panel.phtml';
		return ob_get_clean();
	}
}