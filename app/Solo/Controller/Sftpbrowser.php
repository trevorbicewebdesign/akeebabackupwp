<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Container\Container;
use Awf\Text\Language;
use Awf\Text\Text;

/**
 * The controller for FTP browser
 */
class Sftpbrowser extends ControllerDefault
{
	private bool $noFlush = false;

	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$this->noFlush = $this->container->appConfig->get('no_flush', 0);
	}

	public function execute($task)
	{
		// This legacy SFTP directory browser is no longer supported and is hard-disabled. Besides being unused, it
		// would otherwise let a request open an outbound connection to an arbitrary host, i.e. act as an SSRF /
		// port-scanning oracle.
		throw new \RuntimeException(Text::_('SOLO_ERR_ACLDENIED'), 403);
	}


	public function main()
	{
		/** @var \Solo\Model\Sftpbrowser $model */
		$model = $this->getModel();

		// Grab the data and push them to the model
		$directory = $this->input->getRaw('directory', '');
		$directory = '/' . ltrim($directory, '/');

		$model->setState('host',		$this->input->getString('host', ''));
		$model->setState('port',		$this->input->getInt('port', 22));
		$model->setState('username',	$this->input->getRaw('username', ''));
		$model->setState('password',	$this->input->getRaw('password', ''));
		$model->setState('directory', $directory);
		$model->setState('privKey',		$this->input->getRaw('privkey', ''));
		$model->setState('pubKey',		$this->input->getRaw('pubkey', ''));

		$ret = $model->doBrowse();

		@ob_end_clean();

		echo '#"\#\"#'.json_encode($ret).'#"\#\"#';

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}
} 
