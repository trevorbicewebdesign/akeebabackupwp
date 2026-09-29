<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Awf\Mvc\Model;
use Solo\Model\OAuth2\ProviderInterface;

class Oauth2 extends Model
{
	/**
	 * Returns the provider object for the requested engine
	 *
	 * @param   string  $engine  The requested engine
	 *
	 * @return  ProviderInterface  The provider object
	 * @throws  \InvalidArgumentException  If the engine is not available
	 * @since   9.9.1
	 */
	public function getProvider(string $engine): ProviderInterface
	{
		$className = __NAMESPACE__ . '\\OAuth2\\' . ucfirst(strtolower($engine)) . 'Engine';

		if (!class_exists($className))
		{
			throw new \InvalidArgumentException(sprintf("Invalid engine: %s", $engine));
		}

		return new $className($this->getContainer());
	}

	/**
	 * Is the requested provider enabled in the component options?
	 *
	 * @param   string  $engine  The requested engine
	 *
	 * @return  bool
	 * @since   9.9.1
	 */
	public function isEnabled(string $engine): bool
	{
		$key     = sprintf('oauth2_client_%s', strtolower($engine));

		return $this->getContainer()->appConfig->get($key, 0) != 0;
	}
}