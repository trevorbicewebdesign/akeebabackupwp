<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo;


use Awf\Dispatcher\Dispatcher as BaseDispatcher;
use Awf\Utils\Template;

class Dispatcher extends BaseDispatcher
{
	public function onBeforeDispatch()
	{
		$this->loadCommonCSS();
		$this->loadCommonJavascript();

		return true;
	}

	/**
	 * Loads JavaScript required throughout the application
	 *
	 * @return  void
	 */
	private function loadCommonJavascript()
	{
		// FEF JavaScript
		Template::addJS('media://js/fef/menu.min.js', $this->container->application);
		Template::addJS('media://js/fef/tabs.min.js', $this->container->application);

		$this->container->application->getDocument()->addScriptDeclaration(<<< JS
window.addEventListener("DOMContentLoaded", function (event)
{
    akeeba.fef.menuButton();
    akeeba.fef.tabs();
});
JS
		);

		// Application JavaScript
		Template::addJS('media://js/solo/gui-helpers.js', $this->container->application);
		Template::addJS('media://js/solo/modal.js', $this->container->application);
		Template::addJS('media://js/solo/ajax.js', $this->container->application);
		Template::addJS('media://js/solo/system.js', $this->container->application);
		Template::addJS('media://js/solo/tooltip.js', $this->container->application);
	}

	/**
	 * Loads CSS files required throughout the application
	 */
	private function loadCommonCSS()
	{
		$darkMode = $this->container->appConfig->get('darkmode', -1);

		Template::addCss('media://css/fef.css', $this->container->application);
		Template::addCss('media://css/theme.css', $this->container->application);

		if ($darkMode != 0)
		{
			Template::addCss('media://css/dark.css', $this->container->application);
			Template::addCss('media://css/theme_dark.css', $this->container->application);
		}
	}
}