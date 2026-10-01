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

namespace Akeeba\Engine\DevPlatform\Command;

defined('AKEEBAENGINE') || die();

use Akeeba\Engine\DevPlatform\Command\Mixin\ResolvesOAuth2Engine;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use ReflectionMethod;
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class AuthTest
{
	use ResolvesOAuth2Engine;

	public static function register(Application $app)
	{
		$app
			->command('auth:test profile', new self())
			->descriptions(
				'Test whether the remote storage connection of a backup profile works',
				[
					'profile' => 'Backup profile ID',
				]
			);
	}

	public function __invoke(
		int $profile,
		InputInterface $input, OutputInterface $output, SymfonyStyle $io
	)
	{
		define('AKEEBA_PROFILE', $profile);
		Platform::getInstance()->load_configuration($profile);

		$engineName = Factory::getConfiguration()->get('akeeba.advanced.postproc_engine', '');

		// No remote storage engine configured.
		if (empty($engineName) || $engineName === 'none')
		{
			return $this->output(null, false, 'Not a remote backup profile', $io);
		}

		$engine = Factory::getPostprocEngine($engineName);

		// The configured engine does not use OAuth2.
		if (!$this->isOAuth2Engine($engine))
		{
			return $this->output($engineName, false, 'Not an OAuth2 engine', $io);
		}

		// Attempt a live connection. getConnector() builds the connector and pings the remote service, refreshing the
		// tokens if necessary. This is exactly what a backup does.
		try
		{
			$method = new ReflectionMethod($engine, 'getConnector');

			if (PHP_VERSION_ID < 80100)
			{
				$method->setAccessible(true);
			}

			$method->invoke($engine, true);
		}
		catch (Throwable $e)
		{
			return $this->output($engineName, false, $e->getMessage(), $io);
		}

		return $this->output($engineName, true, null, $io);
	}

	/**
	 * Emit the JSON result document and return the appropriate exit code.
	 *
	 * @param   string|null   $engine     The remote storage engine short name, or null.
	 * @param   bool          $connected  Whether the connection works.
	 * @param   string|null   $error      The error message, or null when connected.
	 * @param   SymfonyStyle  $io
	 *
	 * @return  int  0 when connected, 1 otherwise.
	 */
	private function output(?string $engine, bool $connected, ?string $error, SymfonyStyle $io): int
	{
		$io->writeln(
			json_encode(
				[
					'engine'    => $engine,
					'connected' => $connected,
					'error'     => $error,
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			)
		);

		return $connected ? 0 : 1;
	}
}
