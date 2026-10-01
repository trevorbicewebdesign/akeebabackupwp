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

namespace Akeeba\Engine\DevPlatform\Command\Mixin;

defined('AKEEBAENGINE') || die();

use Akeeba\Engine\Postproc\Base;
use ReflectionMethod;

/**
 * Helpers for working with OAuth2-capable post-processing engines.
 *
 * The OAuth2-capable engines are the only ones that override the protected getOAuth2HelperUrl() method of
 * Postproc\Base. We use that fact, via reflection, both to detect them and to obtain their helper URL (which already
 * resolves the Akeeba mediator vs. custom provider branch internally).
 */
trait ResolvesOAuth2Engine
{
	/**
	 * Is the given post-processing engine an OAuth2-capable engine?
	 *
	 * @param   object  $engine  The post-processing engine instance.
	 *
	 * @return  bool
	 */
	protected function isOAuth2Engine(object $engine): bool
	{
		if (!method_exists($engine, 'getOAuth2HelperUrl'))
		{
			return false;
		}

		$method = new ReflectionMethod($engine, 'getOAuth2HelperUrl');

		return $method->getDeclaringClass()->getName() !== Base::class;
	}

	/**
	 * Returns the OAuth2 helper URL for the given engine.
	 *
	 * This invokes the engine's protected getOAuth2HelperUrl() method, which already returns either the Akeeba Ltd
	 * mediator URL or the custom OAuth2 provider URL depending on the engine's configuration.
	 *
	 * @param   object  $engine  The post-processing engine instance.
	 *
	 * @return  string
	 */
	protected function getOAuth2HelperUrl(object $engine): string
	{
		$method = new ReflectionMethod($engine, 'getOAuth2HelperUrl');

		if (PHP_VERSION_ID < 80100)
		{
			$method->setAccessible(true);
		}

		return (string) $method->invoke($engine);
	}

	/**
	 * The canonical list of OAuth2-capable post-processing engine short names.
	 *
	 * @return  string[]
	 */
	protected function oauth2EngineNames(): array
	{
		return ['onedrive', 'onedrivebusiness', 'onedriveapp', 'dropbox2', 'googledrive', 'box'];
	}
}
