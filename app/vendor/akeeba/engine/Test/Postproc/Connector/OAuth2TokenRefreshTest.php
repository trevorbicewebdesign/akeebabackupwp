<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see
 * <https://www.gnu.org/licenses/>.
 */

namespace Akeeba\Engine\Test\Postproc\Connector;

use Akeeba\Engine\Postproc\Connector\Box;
use Akeeba\Engine\Postproc\Connector\Dropbox2;
use Akeeba\Engine\Postproc\Connector\GoogleDrive;
use Akeeba\Engine\Postproc\Connector\OneDrive;
use Composer\CaBundle\CaBundle;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Replaces a connector's fetch() with a canned relay response and records whether it was reached.
 *
 * Everything else — the ping() decision, the refresh bookkeeping, the guards — remains the production code. fetch() is
 * unreachable in these tests other than through a token refresh, so "fetch was called" means "a refresh was attempted".
 */
trait SpyOAuth2Connector
{
	/** @var bool */
	private $refreshAttempted = false;

	/** @var array The relay response to hand back. Defaults to a successful refresh. */
	private $refreshResponse = [
		'access_token' => 'a-fresh-access-token',
		'expires_in'   => 3600,
	];

	public function refreshWasAttempted(): bool
	{
		return $this->refreshAttempted;
	}

	public function setRefreshResponse(array $response): void
	{
		$this->refreshResponse = $response;
	}

	protected function cannedResponse(): array
	{
		$this->refreshAttempted = true;

		return $this->refreshResponse;
	}
}

/**
 * Unit tests for the token-refresh decision every OAuth2 connector makes in ping().
 *
 * These make NO network calls — see {@see SpyOAuth2Connector}.
 *
 * The behaviour under test is what happens when a backup profile holds a still-unexpired token_expiration but NO access
 * token. That pairing is not hypothetical: a refresh which came back HTTP 200 carrying an error payload rather than a
 * token used to be recorded as a success, writing a blank access token into the profile while leaving the old expiry in
 * place. From then on the connector believed its absent token was good for another hour, so it never refreshed, and
 * every request failed with an opaque "not authorised" from the provider.
 *
 * @see  \Akeeba\Engine\Postproc\Connector\GoogleDrive::ping()
 */
final class OAuth2TokenRefreshTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// The connectors' default cURL options reference this constant at instantiation time. In production the
		// platform defines it; here we point it at the CA bundle shipped with composer/ca-bundle.
		if (!defined('AKEEBA_CACERT_PEM'))
		{
			define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());
		}
	}

	public static function connectorProvider(): array
	{
		return [
			'Box'          => ['box'],
			'Dropbox'      => ['dropbox'],
			'Google Drive' => ['googledrive'],
			'OneDrive'     => ['onedrive'],
		];
	}

	/**
	 * A blank access token must trigger a refresh even when the recorded expiry says there is nothing to worry about.
	 *
	 * Without this the connector short-circuits on the expiry alone, never refreshes, and sends an empty bearer token
	 * to the API — which is how "Fetch back to server" came back with Google's "Method doesn't allow unregistered
	 * callers" instead of the file.
	 *
	 * @dataProvider connectorProvider
	 */
	public function testABlankAccessTokenForcesARefresh(string $provider): void
	{
		$connector = $this->makeSpyConnector($provider, '');

		// An hour of validity left — on the token we no longer have.
		$connector->setTokenExpiration(time() + 3600);

		$result = $connector->ping();

		$this->assertTrue(
			$connector->refreshWasAttempted(),
			'A connector with no access token must refresh, whatever its recorded expiry claims'
		);

		$this->assertTrue($result['needs_refresh'], 'ping() must report the refresh it just performed');

		$this->assertSame(
			'a-fresh-access-token', $result['access_token'],
			'The freshly minted access token must be handed back to the caller for persisting'
		);
	}

	/**
	 * An unexpired token which we actually hold must NOT be thrown away.
	 *
	 * This is the other half of the guard: proving the blank-token check did not degrade the proactive-expiry
	 * short-circuit into "refresh on every single call". Box and Dropbox refresh tokens are single-use, so that would
	 * spend a credential on every backup step.
	 *
	 * @dataProvider connectorProvider
	 */
	public function testAValidUnexpiredAccessTokenIsLeftAlone(string $provider): void
	{
		$connector = $this->makeSpyConnector($provider, 'a-perfectly-good-access-token');
		$connector->setTokenExpiration(time() + 3600);

		$result = $connector->ping();

		$this->assertFalse(
			$connector->refreshWasAttempted(),
			'A token which is present and unexpired must not be refreshed'
		);

		$this->assertFalse($result['needs_refresh'], 'ping() must report that no refresh happened');
	}

	/**
	 * A refresh which comes back without a token must be reported, not silently banked.
	 *
	 * The relays answer with HTTP 200 whatever the outcome, and fetch() only throws for the payload shapes it
	 * recognises as errors. A 200 carrying neither an error nor a token therefore reaches the caller intact, and
	 * banking the absent token poisons the profile — which is the state the first test has to recover from.
	 *
	 * The message has to be actionable: the user's way out of a dead authorisation is always to reconnect the account,
	 * so that instruction must survive into the exception rather than the caller seeing a bare failure.
	 *
	 * @dataProvider connectorProvider
	 */
	public function testARefreshReturningNoTokenIsReported(string $provider): void
	{
		$connector = $this->makeSpyConnector($provider, '');
		$connector->setRefreshResponse(['token_type' => 'Bearer', 'expires_in' => 3600]);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/reconnect/i');

		$connector->ping();
	}

	/**
	 * Builds a connector whose fetch() is stubbed out.
	 *
	 * The anonymous classes cannot share one fetch() override: Box and Dropbox take a base URL and a relative URL,
	 * Google Drive and OneDrive take only a relative URL.
	 *
	 * @param   string  $provider     Which connector to build.
	 * @param   string  $accessToken  The access token to seed it with.
	 *
	 * @return  Box|Dropbox2|GoogleDrive|OneDrive
	 */
	private function makeSpyConnector(string $provider, string $accessToken)
	{
		$refreshToken = 'a-refresh-token';
		$dlid         = 'a-download-id';

		switch ($provider)
		{
			case 'box':
				return new class($accessToken, $refreshToken, $dlid) extends Box {
					use SpyOAuth2Connector;

					protected function fetch($method, $baseUrl, $relativeUrl, array $additional = [], $explicitPost = null)
					{
						return $this->cannedResponse();
					}
				};

			case 'dropbox':
				return new class($accessToken, $refreshToken, $dlid) extends Dropbox2 {
					use SpyOAuth2Connector;

					protected function fetch($method, $baseUrl, $relativeUrl, array $additional = [], $explicitPost = null)
					{
						return $this->cannedResponse();
					}
				};

			case 'googledrive':
				return new class($accessToken, $refreshToken, $dlid) extends GoogleDrive {
					use SpyOAuth2Connector;

					protected function fetch($method, $relativeUrl, array $additional = [], $explicitPost = null)
					{
						return $this->cannedResponse();
					}
				};

			case 'onedrive':
				return new class($accessToken, $refreshToken, $dlid) extends OneDrive {
					use SpyOAuth2Connector;

					protected function fetch($method, $relativeUrl, array $additional = [], $explicitPost = null)
					{
						return $this->cannedResponse();
					}
				};
		}

		throw new InvalidArgumentException(sprintf('Unknown connector "%s"', $provider));
	}
}
