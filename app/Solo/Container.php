<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo;

use Solo\Application\UserManager;
use Solo\Helper\HashHelper;

/**
 * Dependency injection container for Solo
 *
 * @property-read  string $iconBaseName  The base name for logo icon files
 */
class Container extends \Awf\Container\Container
{
	public function __construct(array $values = [])
	{
		$this->iconBaseName = 'solo';

		$values['application_name']     = $values['application_name'] ?? 'Solo';
		$values['applicationNamespace'] = $values['applicationNamespace'] ?? '\\Solo';

		$values['session_segment_name'] = $values['session_segment_name'] ?? call_user_func(
			function () use ($values) {
				$installationId = 'default';

				if (function_exists('base64_encode'))
				{
					$installationId = base64_encode(__DIR__);
				}

				if (function_exists('md5'))
				{
					$installationId = HashHelper::md5(__DIR__);
				}

				if (function_exists('sha1'))
				{
					$installationId = HashHelper::sha1(__DIR__);
				}

				return $values['application_name'] . '_' . $installationId;
			}
		);

		$values['userManager'] = $values['userManager'] ?? function (Container $c)
		{
			return new UserManager($c);
		};

		parent::__construct($values);
	}
}
