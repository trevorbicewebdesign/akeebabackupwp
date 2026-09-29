<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Inflector\Inflector;
use Awf\Text\Text;
use RuntimeException;

class Profiles extends DataControllerDefault
{
	/**
	 * Imports an exported profile .json file
	 *
	 * @return  void
	 */
	public function import()
	{
		// CSRF prevention
		$this->csrfProtection();

		// Get the reference to the uploaded file
		$file = $_FILES['importfile'];

		// Get a URL router
		$router = $this->container->router;

		if (!isset($file['name']))
		{
			$this->setRedirect($router->route('index.php?view=profiles'), Text::_('MSG_UPLOAD_INVALID_REQUEST'), 'error');
		}

		/** @var \Solo\Model\Profiles $model */
		$model = $this->getModel();

		// Load the file data
		$data = file_get_contents($file['tmp_name']);
		@unlink($file['tmp_name']);

		// JSON decode
		$data = json_decode($data, true);

		// Import
		$message     = Text::_('COM_AKEEBA_PROFILES_MSG_IMPORT_COMPLETE');
		$messageType = null;

		try
		{
			$model->reset()->import($data);
		}
		catch (RuntimeException $e)
		{
			$message     = $e->getMessage();
			$messageType = 'error';
		}

		// Redirect back to the main page
		$this->setRedirect($router->route('index.php?view=profiles'), $message, $messageType);
	}

	/**
	 * Enable the Quick Icon for a record
	 *
	 * @since   3.1.2
	 * @throws  \Exception
	 */
	public function publish()
	{
		$this->setQuickIcon(1);
	}

	/**
	 * Disable the Quick Icon for a record
	 *
	 * @since   3.1.2
	 * @throws  \Exception
	 */
	public function unpublish()
	{
		$this->setQuickIcon(0);
	}

	/**
	 * Sets the Quick Icon status for the record.
	 *
	 * @param   int|bool  $published  Should this profile have a Quick Icon?
	 *
	 * @return  void
	 * @throws  \Exception
	 *
	 * @since   3.1.2
	 */
	public function setQuickIcon($published)
	{
		// CSRF prevention
		$this->csrfProtection();

		/** @var \Solo\Model\Profiles $model */
		$model = $this->getModel();
		$ids   = $this->getIDsFromRequest($model, false);

		try
		{
			$status = true;

			foreach ($ids as $id)
			{
				$model->find($id);
				$model->save(array(
					'quickicon' => $published ? 1 : 0
				));
			}
		}
		catch (\Exception $e)
		{
			$status = false;
			$error  = $e->getMessage();
		}

		// Redirect
		if ($customURL = $this->input->getBase64('returnurl', ''))
		{
			$customURL = base64_decode($customURL);
		}

		$router = $this->container->router;
		$url = !empty($customURL) ? $customURL : $router->route('index.php?view=' . Inflector::pluralize($this->view));

		if (!$status)
		{
			$this->setRedirect($url, $error, 'error');
		}
		else
		{
			$this->setRedirect($url);
		}
	}

	/**
	 * Resets one or more backup profiles.
	 *
	 * @return  void
	 * @since   9.0.3
	 * @throws  \Exception
	 */
	public function reset(): void
	{
		// CSRF prevention
		$this->csrfProtection();

		/** @var \Solo\Model\Profiles $model */
		$model = $this->getModel();
		$ids   = $this->getIDsFromRequest($model, false);

		try
		{
			$status = true;

			foreach ($ids as $id)
			{
				$model->resetConfiguration($id);
			}
		}
		catch (\Exception $e)
		{
			$status = false;
			$error  = $e->getMessage();
		}

		// Redirect
		if ($customURL = $this->input->getBase64('returnurl', ''))
		{
			$customURL = base64_decode($customURL);
		}

		$router = $this->container->router;
		$url    = !empty($customURL)
			? $customURL
			: $router->route(
				'index.php?view=' . Inflector::pluralize($this->view)
			);

		if (!$status)
		{
			$this->setRedirect($url, $error, 'error');
		}
		else
		{
			$this->setRedirect($url);
		}
	}
}
