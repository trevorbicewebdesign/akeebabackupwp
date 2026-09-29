<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Container\Container;
use Awf\Input\Input;
use Awf\Text\Language;
use Awf\Text\Text;
use Solo\Model\Profiles;

/**
 * The Controller for the Configuration view
 */
class Configuration extends ControllerDefault
{
	private bool $noFlush = false;

	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$this->noFlush = $this->container->appConfig->get('no_flush', 0);
	}

	/**
	 * Handle the apply task which saves settings and shows the editor again
	 *
	 * @return  void
	 */
	public function apply()
	{
		// CSRF prevention
		$this->csrfProtection();

		// Which input am I going to use?
		$jsonFormData = $this->input->getString('jsonForm', null);
		$jsonFormData = is_string($jsonFormData) ? @json_decode($jsonFormData, true) : $jsonFormData;

		if (empty($jsonFormData))
		{
			$input = $this->input;
		}
		else
		{
			$rawData = [];

			foreach ($jsonFormData as $k => $v)
			{
				if (substr($k, 0, 4) !== 'var[')
				{
					$rawData[$k] = $v;

					continue;
				}

				$k                  = substr($k, 4, -1);
				$rawData['var']     ??= [];
				$rawData['var'][$k] = $v;
			}

			$input = new Input($rawData);
		}

		// Get the var array from the request
		$data = $input->get('var', array(), 'array');

		// Mark this profile as configured
		$data['akeeba.flag.confwiz'] = 1;

		/** @var \Solo\Model\Configuration $model */
		$model = $this->getModel();
		$model->setState('engineconfig', $data);
		$model->saveEngineConfig();

		// Finally, save the profile description if it has changed
		$profileID = \Akeeba\Engine\Platform::getInstance()->get_active_profile();

		// Get profile name
		/** @var Profiles $profileRecord */
		$profileRecord = $this->getModel('Profiles')->getClone()->setIgnoreRequest(1);
		$profileRecord->reset(true, true)->find($profileID);
		$oldProfileName = $profileRecord->description;
		$oldQuickIcon = $profileRecord->quickicon;
		$newProfileName = $input->getString('profilename', null);
		$newProfileName = trim($newProfileName);
		$newQuickIcon = $input->getCmd('quickicon', '');
		$newQuickIcon = !empty($newQuickIcon);

		$mustSaveProvile = !empty($newProfileName) && ($newProfileName != $oldProfileName);
		$mustSaveProvile = $mustSaveProvile || ($newQuickIcon != $oldQuickIcon);

		if ($mustSaveProvile)
		{
			$profileRecord->save(array(
				'description' => $newProfileName,
				'quickicon'   => $newQuickIcon,
			));
		}

		$router = $this->container->router;

		$this->setRedirect($router->route('index.php?view=configuration'), Text::_('COM_AKEEBA_CONFIG_SAVE_OK'));
	}

	/**
	 * Handle the save task which saves settings and returns to the main page
	 *
	 * @return  void
	 */
	public function save()
	{
		$this->apply();

		$router = $this->container->router;

		$this->setRedirect($router->route('index.php?view=main'), Text::_('COM_AKEEBA_CONFIG_SAVE_OK'));
	}

	/**
	 * Handle the save task which saves settings, creates a new backup profile, activates it and proceed to the
	 * configuration page once more.
	 *
	 * @return  void
	 */
	public function savenew()
	{
		// Save the current profile
		$this->apply();

		// Create a new profile
		/** @var Profiles $profileModel */
		$profileModel = $this->getModel('Profiles')->getClone();
		$profileID = \Akeeba\Engine\Platform::getInstance()->get_active_profile();
		$profileModel->find($profileID);
		$profileModel->id = null;
		$profileModel->save(array(
			'id' => 0,
			'description' => Text::_('COM_AKEEBA_CONFIG_SAVENEW_DEFAULT_PROFILE_NAME')
		));
		$newProfileId = (int)($profileModel->getId());

		// Activate and edit the new profile
		$returnUrl = base64_encode($this->redirect);
		$router = $this->container->router;
		$token = $this->container->session->getCsrfToken()->getValue();
		$url = $router->route('index.php?view=main&task=switchProfile&profile=' . $newProfileId .
			'&returnurl=' . $returnUrl . '&' . $token . '=1');
		$this->setRedirect($url);
	}

	/**
	 * Handle the cancel task which doesn't save anything and returns to the cpanel
	 *
	 * @return  void
	 */
	public function cancel()
	{
		$this->csrfProtection();

		$router = $this->container->router;
		$this->setRedirect($router->route('index.php?view=main'));
	}

	/**
	 * Tests the validity of the FTP connection details
	 *
	 * @return  void
	 */
	public function testftp()
	{
		/** @var \Solo\Model\Configuration $model */
		$model = $this->getModel();

		$model->setState('isCurl', $this->input->get('isCurl', 0, 'int'));
		$model->setState('host', $this->input->get('host', '', 'raw'));
		$model->setState('port', $this->input->get('port', 21, 'int'));
		$model->setState('user', $this->input->get('user', '', 'raw'));
		$model->setState('pass', $this->input->get('pass', '', 'raw'));
		$model->setState('initdir', $this->input->get('initdir', '', 'raw'));
		$model->setState('usessl', (bool) $this->input->getInt('usessl', 0));
		$model->setState('passive', (bool) $this->input->getInt('passive', 0));
		$model->setState('passive_mode_workaround', (bool) $this->input->getInt('passive_mode_workaround', 0));

		$result = true;

		try
		{
			$model->testFTP();
		}
		catch (\Exception $e)
		{
			$result = $e->getMessage();
		}

		@ob_end_clean();

		echo '#"\#\"#' . json_encode([
                'status' => $result
            ]) . '#"\#\"#';

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}

	/**
	 * Tests the validity of the SFTP connection details
	 *
	 * @return  void
	 */
	public function testsftp()
	{
		/** @var \Solo\Model\Configuration $model */
		$model = $this->getModel();

		$model->setState('isCurl', $this->input->get('isCurl', 0, 'int'));
		$model->setState('host', $this->input->get('host', '', 'raw'));
		$model->setState('port', $this->input->get('port', 21, 'int'));
		$model->setState('user', $this->input->get('user', '', 'raw'));
		$model->setState('pass', $this->input->get('pass', '', 'raw'));
		$model->setState('privkey', $this->input->get('privkey', '', 'raw'));
		$model->setState('pubkey', $this->input->get('pubkey', '', 'raw'));
		$model->setState('initdir', $this->input->get('initdir', '', 'raw'));

		$result = true;

		try
		{
			$model->testSFTP();
		}
		catch (\Exception $e)
		{
			$result = $e->getMessage();
		}

		@ob_end_clean();

		echo '#"\#\"#' . json_encode([
                'status' => $result
            ]) . '#"\#\"#';

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}

	/**
	 * Opens an OAuth window for the selected data processing engine
	 *
	 * @return  void
	 */
	public function dpeoauthopen()
	{
		/** @var \Solo\Model\Configuration $model */
		$model = $this->getModel();

		$model->setState('engine', $this->input->get('engine', '', 'raw'));
		$model->setState('params', $this->input->get('params', array(), 'array'));

		@ob_end_clean();

		$model->dpeOAuthOpen();

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}

	/**
	 * Runs a custom API call against the selected data processing engine
	 *
	 * @return  void
	 */
	public function dpecustomapi()
	{
		/** @var \Solo\Model\Configuration $model */
		$model = $this->getModel();

		$model->setState('engine', $this->input->get('engine', '', 'raw'));
		$model->setState('method', $this->input->getVar('method', '', 'raw'));
		$model->setState('params', $this->input->get('params', array(), 'array'));

        $result = $model->dpeCustomAPICall();

        if (is_array($result) && $this->isNumericIndexedArray($result))
        {
            $result = ['list' => $result];
        }

		@ob_end_clean();

		echo '#"\#\"#' . json_encode($result) . '#"\#\"#';

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}

	/**
	 * Runs a custom API call against the selected data processing engine
	 *
	 * @return  void
	 */
	public function dpecustomapiraw()
	{
		/** @var \Solo\Model\Configuration $model */
		$model = $this->getModel();

		$model->setState('engine', $this->input->get('engine', '', 'raw'));
		$model->setState('method', $this->input->getVar('method', '', 'raw'));
		$model->setState('params', $this->input->get('params', array(), 'array'));

		@ob_end_clean();

		echo $model->dpeCustomAPICall();

		if (!$this->noFlush)
		{
			flush();
		}

		$this->container->application->close();
	}

    private function isNumericIndexedArray(array $result)
    {
        return array_reduce(
            array_keys($result),
            function (bool $carry, $key) {
                return $carry || (is_numeric($key) && intval($key) == $key);
            },
            false
        );
    }
}
