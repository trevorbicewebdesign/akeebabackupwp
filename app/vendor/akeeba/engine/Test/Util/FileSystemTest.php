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

namespace Akeeba\Engine\Test\Util;

use Akeeba\Engine\Platform;
use Akeeba\Engine\Test\Stub\Platform\FileSystemTestPlatform;
use Akeeba\Engine\Util\FileSystem;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FileSystemTest extends TestCase
{
	/** @var array Stock directories used across platform-dependent tests */
	private static $testStockDirs = [
		'[SITEROOT]' => '/var/www/html',
		'[TEMP]'     => '/tmp/mysite',
		'[BACKUP]'   => '/var/www/html/backup',
	];

	/**
	 * Reset the Platform singleton and the FileSystem static cache before each test
	 * that touches platform-dependent methods.
	 */
	private function resetPlatformAndCache(): void
	{
		// Reset the Platform singleton so we can inject our stub
		$platformRef = new ReflectionClass(Platform::class);

		$instanceProp = $platformRef->getProperty('instance');
		$instanceProp->setAccessible(true);
		$instanceProp->setValue(null, null);

		$connectorProp = $platformRef->getProperty('platformConnectorInstance');
		$connectorProp->setAccessible(true);
		$connectorProp->setValue(null, null);

		// Reset the FileSystem static stock dir cache
		$fsRef      = new ReflectionClass(FileSystem::class);
		$stockDirsProp = $fsRef->getProperty('stockDirs');
		$stockDirsProp->setAccessible(true);
		$stockDirsProp->setValue(null, null);
	}

	/**
	 * Inject a FileSystemTestPlatform as the active platform connector.
	 */
	private function injectPlatform(array $stockDirs): void
	{
		$this->resetPlatformAndCache();

		$stub = new FileSystemTestPlatform($stockDirs);

		$platformRef = new ReflectionClass(Platform::class);

		// We set the connector instance directly (Platform proxies __call to it)
		$connectorProp = $platformRef->getProperty('platformConnectorInstance');
		$connectorProp->setAccessible(true);
		$connectorProp->setValue(null, $stub);

		// We also need a Platform $instance so getInstance() returns without creating a new one
		// We create a dummy Platform shell; but Platform constructor calls loadPlatform which
		// fails without a real platform. Instead we override the static $instance with a mock
		// that already has the connector set up.
		//
		// Actually: Platform::getInstance() checks static::$instance, and if it's an object it
		// returns it. The __call / get_stock_directories delegation goes through $platformConnectorInstance.
		// So we just need $instance to be a non-null object — we reuse the stub itself since
		// Platform proxies through the connector anyway.
		//
		// Build a minimal Platform shell without invoking the constructor (which tries to detect/load
		// a real platform).
		$instance = $platformRef->newInstanceWithoutConstructor();

		$instanceProp = $platformRef->getProperty('instance');
		$instanceProp->setAccessible(true);
		$instanceProp->setValue(null, $instance);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// TranslateWinPath (UNIX host)
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\FileSystemProvider::translateWinPathUnixProvider()
	 */
	public function testTranslateWinPathOnUnix(string $input, string $expected): void
	{
		$fs = new FileSystem();

		$this->assertSame($expected, $fs->TranslateWinPath($input));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// TrimTrailingSlash
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\FileSystemProvider::trimTrailingSlashProvider()
	 */
	public function testTrimTrailingSlash(string $input, string $expected): void
	{
		$fs = new FileSystem();

		$this->assertSame($expected, $fs->TrimTrailingSlash($input));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// stringUrlUnicodeSlug
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\FileSystemProvider::stringUrlUnicodeSlugProvider()
	 */
	public function testStringUrlUnicodeSlug(string $input, string $expected): void
	{
		$fs = new FileSystem();

		$this->assertSame($expected, $fs->stringUrlUnicodeSlug($input));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// translateStockDirs
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\FileSystemProvider::translateStockDirsProvider()
	 */
	public function testTranslateStockDirs(
		string $folder,
		bool $translateWinDirs,
		bool $trimTrailingSlash,
		string $expected
	): void
	{
		$this->injectPlatform(self::$testStockDirs);

		$fs = new FileSystem();

		$this->assertSame($expected, $fs->translateStockDirs($folder, $translateWinDirs, $trimTrailingSlash));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// rebaseFolderToStockDirs
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\FileSystemProvider::rebaseFolderToStockDirsProvider()
	 */
	public function testRebaseFolderToStockDirs(string $input, string $expected): void
	{
		$this->injectPlatform(self::$testStockDirs);

		$fs = new FileSystem();

		$this->assertSame($expected, $fs->rebaseFolderToStockDirs($input));
	}

	/**
	 * Rebase with an empty stock dir value is skipped (the method guards against empty stockPath).
	 */
	public function testRebaseFolderToStockDirsSkipsEmptyStockPath(): void
	{
		$this->injectPlatform([
			'[SITEROOT]' => '',
			'[TEMP]'     => '/tmp',
		]);

		$fs = new FileSystem();

		// /tmp should match [TEMP]; empty [SITEROOT] must not eat the path
		$this->assertSame('[TEMP]/cache', $fs->rebaseFolderToStockDirs('/tmp/cache'));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// TrimTrailingSlash edge cases
	// ─────────────────────────────────────────────────────────────────────────

	public function testTrimTrailingSlashOnlyRemovesOneSlash(): void
	{
		$fs = new FileSystem();

		// The method removes only a single trailing slash per call, not recursively
		$this->assertSame('/var/www/', $fs->TrimTrailingSlash('/var/www//'));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// TranslateWinPath multiple-slash reduction is idempotent
	// ─────────────────────────────────────────────────────────────────────────

	public function testTranslateWinPathCollapsesUpToThreeConsecutiveSlashes(): void
	{
		$fs = new FileSystem();

		// The source uses two sequential str_replace passes (/// → / then // → /)
		// so four slashes: //// → // → /  (fully collapsed)
		$this->assertSame('/a/b', $fs->TranslateWinPath('/a////b'));
	}

	// ─────────────────────────────────────────────────────────────────────────
	// PHP 8.4+ null-safety: none of these methods must pass null to a
	// str_replace()/substr()/strlen() parameter, e.g. when a platform stock
	// directory (such as [SITETMP]) or a registry value is unset (null).
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Installs a deprecation collector and returns a closure that asserts none were raised.
	 */
	private function assertNoDeprecations(): callable
	{
		$deprecations = [];

		$previousHandler = set_error_handler(
			function (int $errno, string $errstr) use (&$deprecations) {
				if ($errno === E_DEPRECATED)
				{
					$deprecations[] = $errstr;
				}

				return true;
			}
		);

		return function () use (&$deprecations, $previousHandler) {
			restore_error_handler();

			$this->assertSame([], $deprecations, 'Unexpected PHP deprecation notice(s) raised: ' . implode('; ', $deprecations));
		};
	}

	public function testTrimTrailingSlashWithNullInputDoesNotDeprecate(): void
	{
		$assert = $this->assertNoDeprecations();

		$fs     = new FileSystem();
		$result = $fs->TrimTrailingSlash(null);

		$assert();

		$this->assertSame('', $result);
	}

	public function testTranslateWinPathWithNullInputDoesNotDeprecate(): void
	{
		$assert = $this->assertNoDeprecations();

		$fs     = new FileSystem();
		$result = $fs->TranslateWinPath(null);

		$assert();

		$this->assertSame('', $result);
	}

	public function testTranslateStockDirsWithNullFolderDoesNotDeprecate(): void
	{
		$this->injectPlatform(self::$testStockDirs);

		$assert = $this->assertNoDeprecations();

		$fs     = new FileSystem();
		$result = $fs->translateStockDirs(null);

		$assert();

		$this->assertSame('', $result);
	}

	/**
	 * Reproduces the bug reported against Akeeba Backup for Joomla ticket #43211: on some CLI
	 * environments a platform stock directory (e.g. [SITETMP], sourced from an application
	 * config value that is not set) resolves to null. Expanding it used to pass null to
	 * str_replace()'s $replace parameter, which is deprecated since PHP 8.4.
	 */
	public function testTranslateStockDirsWithNullStockDirectoryValueDoesNotDeprecate(): void
	{
		$this->injectPlatform([
			'[SITEROOT]' => '/var/www/html',
			'[SITETMP]'  => null,
		]);

		$assert = $this->assertNoDeprecations();

		$fs     = new FileSystem();
		$result = $fs->translateStockDirs('[SITETMP]/cache');

		$assert();

		$this->assertSame('/cache', $result);
	}
}
