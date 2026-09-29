<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model\OAuth2;

use RuntimeException;
use Throwable;

/**
 * OAuth2 Helper error redirecting to a URL
 *
 * @since   8.2.2
 */
class OAuth2UriException extends RuntimeException
{
	private string $url;

	public function __construct(string $url, ?Throwable $previous = null)
	{
		$message = sprintf('For more information please visit %s', $url);
		$this->url = $url;

		parent::__construct($message, 500, $previous);
	}

	public function getUrl(): string
	{
		return $this->url;
	}
}