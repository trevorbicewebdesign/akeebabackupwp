<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Oauth2;

use Awf\Mvc\DataView\Raw as BaseView;
use Exception;
use Solo\Model\OAuth2\ProviderInterface;
use Solo\Model\OAuth2\TokenResponse;

class Raw extends BaseView
{
	public ?ProviderInterface $provider = null;

	public ?TokenResponse $tokens = null;

	public ?Exception $exception = null;

	public ?string $step1url = null;
}