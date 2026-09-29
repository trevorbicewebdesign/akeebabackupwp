<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model\OAuth2;

use Awf\Container\Container;
use Awf\Container\ContainerAwareInterface;
use Awf\Container\ContainerAwareTrait;
use Awf\Input\Input;
use Awf\Uri\Uri;

abstract class AbstractProvider implements ProviderInterface, ContainerAwareInterface
{
	use ContainerAwareTrait;

	protected string $tokenEndpoint = '';

	protected string $engineNameForHumans = '';

	public function __construct(Container $container)
	{
		$this->setContainer($container);
	}

	public function doRedirect(string $uri)
	{
		if (defined('WPINC'))
		{
			wp_redirect($uri, 307, 'Akeeba Backup for WordPress');

			// If we're triggering the backup using the ajax endpoint, we have to explicitly set the redirection code,
			// otherwise WordPress will automatically set an HTTP status of 200
			if (wp_doing_ajax())
			{
				wp_die('0', '', ['response' => 307]);
			}

			return;
		}

		$this->getContainer()->application->redirect($uri);
	}

	public function getEngineNameForHumans(): string
	{
		return $this->engineNameForHumans;
	}

	public final function handleResponse(Input $input): TokenResponse
	{
		$this->checkConfiguration();

		$code = $input->getRaw('code');

		if (!$code)
		{
			throw new OAuth2Exception('no_code', 'No code has been provided in the URL.');
		}

		$query = http_build_query($this->getResponseCustomFields($input), '', '&');
		$ch    = curl_init($this->tokenEndpoint);

		$options = [
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_VERBOSE        => true,
			CURLOPT_HEADER         => false,
			CURLINFO_HEADER_OUT    => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CAINFO         => AKEEBA_CACERT_PEM,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_POST           => 1,
			CURLOPT_POSTFIELDS     => $query,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/x-www-form-urlencoded',
			],
		];

		curl_setopt_array($ch, $options);

		// Get the tokens
		$response = curl_exec($ch);
		$errNo    = curl_errno($ch);
		$error    = curl_error($ch);

		if (version_compare(PHP_VERSION, '8.5.0', 'lt'))
		{
			curl_close($ch);
		}

		// Did cURL die?
		if ($errNo)
		{
			throw new OAuth2Exception(
				'curl_error', <<< HTML
An error occurred communicating with $this->engineNameForHumans. Technical information:<br/><br/>
Error Number: $errNo<br/>
Error Description: $error<br/>
HTML
			);
		}

		// Decode the response
		$result = @json_decode($response, true);

		// Did we receive invalid JSON?
		if (!$result)
		{
			throw new OAuth2Exception(
				'invalid_json',
				sprintf("%s failed to response with a valid token. Please try again later.", $this->engineNameForHumans)
			);
		}

		// Do we have an error reported by the remote endpoint?
		if (isset($result['error']))
		{
			$error            = $result['error'];
			$errorUri         = $result['error_uri'] ?? null;
			$errorDescription = $result['error_uri'] ?? null;

			if ($errorUri)
			{
				throw new OAuth2UriException($errorUri);
			}

			throw new OAuth2Exception($error, $errorDescription);
		}

		$ret                 = new TokenResponse();
		$ret['accessToken']  = $result['access_token'] ?? '';
		$ret['refreshToken'] = $result['refresh_token'] ?? '';

		return $ret;
	}

	public final function handleRefresh(Input $input): TokenResponse
	{
		$refreshToken = $input->getRaw('refresh_token', null);
		$this->checkConfiguration();

		if (empty($refreshToken))
		{
			throw new OAuth2Exception(
				'no_refresh_token', 'A refresh token was not provided. Operation aborted.'
			);
		}

		// Prepare the request to get the tokens
		$query = http_build_query($this->getRefreshCustomFields($input), '', '&');
		$ch    = curl_init($this->tokenEndpoint);

		$options = [
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_VERBOSE        => true,
			CURLOPT_HEADER         => false,
			CURLINFO_HEADER_OUT    => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CAINFO         => AKEEBA_CACERT_PEM,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_POST           => 1,
			CURLOPT_POSTFIELDS     => $query,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/x-www-form-urlencoded',
			],
		];

		curl_setopt_array($ch, $options);

		// Get the tokens
		$response = curl_exec($ch);
		$errNo    = curl_errno($ch);
		$error    = curl_error($ch);

		if (version_compare(PHP_VERSION, '8.5.0', 'lt'))
		{
			curl_close($ch);
		}

		// Did cURL die?
		if ($errNo)
		{
			throw new OAuth2Exception(
				'curl_error', sprintf(
					"An error occurred refreshing the token. Error Number: %s -- Error Description: %s", $errNo, $error
				)
			);
		}

		// Decode the response
		$result = @json_decode($response, true);

		// Did we receive invalid JSON?
		if (!$result)
		{
			throw new OAuth2Exception(
				'invalid_json', sprintf(
					"%s failed to respond with a valid token to our token refresh request.", $this->engineNameForHumans
				)
			);
		}

		$ret                 = new TokenResponse();
		$ret['accessToken']  = $result['access_token'] ?? '';
		$ret['refreshToekn'] = $result['refresh_token'] ?? '';

		return $ret;
	}

	protected function getResponseCustomFields(Input $input): array
	{
		[$id, $secret] = $this->getIdAndSecret();

		$code = $input->getRaw('code');

		return [
			'code'          => $code,
			'client_id'     => $id,
			'client_secret' => $secret,
			'grant_type'    => 'authorization_code',
		];
	}

	protected function getRefreshCustomFields(Input $input): array
	{
		$refreshToken = $input->getRaw('refresh_token', null);
		[$id, $secret] = $this->getIdAndSecret();

		return [
			'refresh_token' => $refreshToken,
			'client_id'     => $id,
			'client_secret' => $secret,
			'grant_type'    => 'refresh_token',
		];
	}

	protected final function getEngineName(): string
	{
		$parts = explode('\\', rtrim(get_class($this), '\\'));
		$name  = array_pop($parts);

		if (str_ends_with($name, 'Engine'))
		{
			$name = substr($name, 0, -6);
		}

		return strtolower($name);
	}

	protected final function checkConfiguration(): void
	{
		$engine = $this->getEngineName();

		// Is the engine enabled?
		$cParams = $this->getContainer()->appConfig;

		if ($cParams->get('oauth2_client_' . $engine, 0) == 0)
		{
			throw new OAuth2Exception('no_access', $this->getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'));
		}

		$id     = $cParams->get($engine . '_client_id', null);
		$secret = $cParams->get($engine . '_client_secret', null);

		if (empty($id) || empty($secret))
		{
			throw new OAuth2Exception('no_access', $this->getContainer()->language->text('AWF_APPLICATION_ERROR_ACCESS_FORBIDDEN'));
		}
	}

	protected final function getIdAndSecret(): array
	{
		$cParams = $this->getContainer()->appConfig;
		$engine  = $this->getEngineName();
		$id      = $cParams->get($engine . '_client_id', null);
		$secret  = $cParams->get($engine . '_client_secret', null);

		return [$id, $secret];
	}

	protected final function getUri(string $task = 'step1')
	{
		if (defined('WPINC'))
		{
			$uri = new Uri(admin_url('admin-ajax.php?action=akeebabackup_oauth2'));
			$uri->setVar('engine', $this->getEngineName());
			$uri->setVar('task', $task);

			return $uri->toString();
		}

		return $this->container->router->route(
			sprintf(
				'index.php?view=oauth2&task=step2&format=raw&engine=%s',
				$this->getEngineName()
			)
		);
	}
}