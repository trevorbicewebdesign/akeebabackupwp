<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model\OAuth2;

use Awf\Input\Input;

/**
 * OAuth2 Helper provider interface
 *
 * @since    8.2.2
 */
interface ProviderInterface
{
	/**
	 * Get the URL to redirect to for the first authentication step (consent screen).
	 *
	 * @return  string
	 * @since   8.2.2
	 */
	public function getAuthenticationUrl(): string;

	/**
	 * Handles the second step of the authentication (exchange code for tokens)
	 *
	 * @param   Input  $input  The raw application input object
	 *
	 * @return  TokenResponse
	 * @since   8.2.2
	 */
	public function handleResponse(Input $input): TokenResponse;

	/**
	 * Handles exchanging a refresh token for an access token
	 *
	 * @param   Input  $input  The raw application input object
	 *
	 * @return  TokenResponse
	 * @since   8.2.2
	 */
	public function handleRefresh(Input $input): TokenResponse;

	public function getEngineNameForHumans(): string;
}