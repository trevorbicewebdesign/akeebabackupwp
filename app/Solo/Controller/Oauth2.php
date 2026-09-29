<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Awf\Container\Container;
use Awf\Mvc\DataView\Raw;
use Awf\Text\Language;
use Solo\Model\OAuth2\OAuth2Exception;
use Solo\Model\OAuth2\OAuth2UriException;
use Solo\Model\OAuth2\ProviderInterface;

class Oauth2 extends ControllerDefault
{
	public function __construct(?Container $container = null, ?Language $language = null)
	{
		parent::__construct($container, $language);

		$this->registerDefaultTask('step1');
		$this->registerTask('step2', 'step2');
		$this->registerTask('refresh', 'refresh');
	}

	/**
	 * Handle the first step of authentication: open the consent page
	 *
	 * @return  void
	 * @since   8.2.2
	 */
	public function step1(): void
	{
		/** @var Raw $view */
		$viewName       = $this->input->get('view', $this->default_view);
		$view           = $this->getView($viewName);
		$view->provider = $this->getProvider();
		$view->step1url = $view->provider->getAuthenticationUrl();

		$view->setLayout('step1');

		$this->display();
	}

	/**
	 * Handle the second step of authentication: exchange the code for a set of tokens
	 *
	 * @return  void
	 * @since   8.2.2
	 */
	public function step2(): void
	{
		/** @var Raw $view */
		$viewName       = $this->input->get('view', $this->default_view);
		$view           = $this->getView($viewName);
		$provider       = $this->getProvider();
		$view->provider = $provider;

		try
		{
			$view->tokens = $provider->handleResponse($this->input);

			$view->setLayout('default');
		}
		catch (OAuth2Exception $e)
		{
			$view->exception = $e;

			$view->setLayout('error');
		}
		catch (OAuth2UriException $e)
		{
			/** @var \Solo\Model\OAuth2 $model */
			$model = $this->getModel();

			$model->redirect($e->getUrl());
		}

		$this->display(false);
	}

	/**
	 * Handle exchanging a refresh token for a new set of tokens
	 *
	 * @return  void
	 * @since   8.2.2
	 */
	public function refresh(): void
	{
		$provider = $this->getProvider();

		try
		{
			$tokens = $provider->handleRefresh($this->input);

			$ret = [
				'access_token'      => $tokens['accessToken'],
				'refresh_token'     => $tokens['refreshToken'],
				'error'             => null,
				'error_description' => null,
				'error_url'         => null,
			];
		}
		catch (OAuth2Exception $e)
		{
			$ret = [
				'access_token'      => null,
				'refresh_token'     => null,
				'error'             => 'error',
				'error_description' => $e->getMessage(),
				'error_url'         => null,
			];
		}
		catch (OAuth2UriException $e)
		{
			$ret = [
				'access_token'      => null,
				'refresh_token'     => null,
				'error'             => 'error',
				'error_description' => $e->getMessage(),
				'error_url'         => $e->getUrl(),
			];
		}

		@ob_end_clean();

		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public", false);

		header('Content-type: application/json');
		header('Connection: close');

		echo json_encode($ret);

		$this->getContainer()->application->close(200);
	}

	/**
	 * Returns the OAuth2 helper provider for the requested engine
	 *
	 * @return  ProviderInterface
	 * @since   8.2.2
	 */
	protected function getProvider(): ProviderInterface
	{
		$engine = $this->input->get->getCmd('engine', '');

		if (empty($engine))
		{
			throw new \RuntimeException(
				$this->getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403
			);
		}

		/** @var \Solo\Model\OAuth2 $model */
		$model = $this->getModel();

		if (!$model->isEnabled($engine))
		{
			throw new \RuntimeException(
				$this->getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403
			);
		}

		try
		{
			return $model->getProvider($engine);
		}
		catch (\InvalidArgumentException $e)
		{
			throw new \RuntimeException(
				$this->getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403
			);
		}
	}
}