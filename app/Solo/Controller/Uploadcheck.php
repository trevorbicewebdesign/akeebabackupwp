<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Akeeba\Engine\Platform;
use Awf\Container\Container;
use Awf\Text\Language;
use Awf\Text\Text;

class Uploadcheck extends ControllerDefault
{
	private bool $noFlush = false;

	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$this->noFlush = $this->container->appConfig->get('no_flush', 0);
	}

	public function execute($task)
	{
		$this->checkPermissions();

		if (!defined('AKEEBA_BACKUP_ORIGIN'))
		{
			define('AKEEBA_BACKUP_ORIGIN', 'frontend');
		}

		return parent::execute('main');
	}

	public function main()
	{
		$cpanelModel = $this->container->mvcFactory->makeModel('Main');
		$result = $cpanelModel->notifyFailedUploads();

		$message  = $result['result'] ? '200 ' : '500 ';
		$message .= implode(', ', $result['message']);

		@ob_end_clean();
		header('Content-type: text/plain');
		header('Connection: close');
		echo $message;

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}

	/**
	 * Check that the user has sufficient permissions, or die in error
	 *
	 */
	private function checkPermissions()
	{
		// Is frontend backup enabled?
		$febEnabled = Platform::getInstance()->get_platform_configuration_option('legacyapi_enabled', 0);
		$febEnabled = in_array($febEnabled, array('on', 'checked', 'true', 1, 'yes'));

		$validKey = Platform::getInstance()->get_platform_configuration_option('frontend_secret_word', '');

		if (!\Akeeba\Engine\Util\Complexify::isStrongEnough($validKey, false))
		{
			$febEnabled = false;
		}

		$validKeyTrim = trim($validKey);

		if (!$febEnabled || empty($validKey))
		{
			throw new \RuntimeException(Text::_('SOLO_REMOTE_ERROR_NOT_ENABLED'), 403);
		}

		// Is the key good?
		$key = $this->input->get('key', '', 'none', 2);

		if (($key != $validKey) || (empty($validKeyTrim)))
		{
			throw new \RuntimeException(Text::_('SOLO_REMOTE_ERROR_INVALID_KEY'), 403);
		}
	}
}
