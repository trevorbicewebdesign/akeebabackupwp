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
use Silly\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AuthLogin
{
	use ResolvesOAuth2Engine;

	public static function register(Application $app)
	{
		$app
			->command('auth:login engine [--profile=] [--dlid=]', new self())
			->defaults(['profile' => null, 'dlid' => null])
			->descriptions(
				'Open the browser to authenticate with an OAuth2 remote storage provider',
				[
					'engine'    => 'OAuth2 storage provider (onedrive, onedrivebusiness, onedriveapp, dropbox2, googledrive, box)',
					'--profile' => 'Backup profile ID, to use a custom OAuth2 provider defined in that profile',
					'--dlid'    => 'Download ID to pass to the engine, overriding the profile\'s configured Download ID',
				]
			);
	}

	public function __invoke(
		string $engine, $profile, $dlid,
		InputInterface $input, OutputInterface $output, SymfonyStyle $io
	)
	{
		// Validate the requested engine is an OAuth2-capable provider.
		if (!in_array($engine, $this->oauth2EngineNames(), true))
		{
			$io->error(
				sprintf(
					'“%s” is not an OAuth2 storage provider. Valid providers: %s',
					$engine,
					implode(', ', $this->oauth2EngineNames())
				)
			);

			return 1;
		}

		// When a profile is given, load its configuration so a custom OAuth2 provider and the profile's Download ID are
		// honoured. Without a profile we rely on the default configuration, which uses the Akeeba Ltd mediator.
		if (!empty($profile))
		{
			define('AKEEBA_PROFILE', (int) $profile);
			Platform::getInstance()->load_configuration((int) $profile);
		}

		$engineObject = Factory::getPostprocEngine($engine);
		$helperUrl    = $this->getOAuth2HelperUrl($engineObject);

		// Build the OAuth2 URL the same way Postproc\Base::oauthOpen() does. The callback is ignored by modern browsers,
		// so a dummy value is fine; the helper script prints out the tokens in the browser on its final step.
		$callback = 'cli&method=oauthCallback';
		$dlid     = !empty($dlid)
			? $dlid
			: Platform::getInstance()->get_platform_configuration_option('update_dlid', '');

		$url = $helperUrl;
		$url .= (strpos($url, '?') !== false) ? '&' : '?';
		$url .= 'callback=' . urlencode($callback);
		$url .= '&dlid=' . urlencode($dlid);

		// Try to open the URL in the default browser.
		if ($this->openInBrowser($url))
		{
			$io->success('Your browser has been opened to complete the authentication.');
		}
		else
		{
			$io->warning('Could not open a browser automatically. Please visit the following URL manually:');
			$io->writeln($url);
		}

		$io->note('When the authentication completes, the browser will display the access and refresh tokens. Copy them manually where you need them.');

		return 0;
	}

	/**
	 * Open a URL in the default browser using the OS-specific opener.
	 *
	 * @param   string  $url  The URL to open.
	 *
	 * @return  bool  True if the opener ran successfully.
	 */
	private function openInBrowser(string $url): bool
	{
		$opener = (PHP_OS_FAMILY === 'Darwin') ? 'open' : 'xdg-open';

		$output     = [];
		$returnCode = 1;

		@exec(escapeshellarg($opener) . ' ' . escapeshellarg($url) . ' > /dev/null 2>&1', $output, $returnCode);

		return $returnCode === 0;
	}
}
