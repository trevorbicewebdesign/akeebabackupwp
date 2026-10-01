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

namespace Akeeba\Engine\Test\Integration\Backup;

use PHPUnit\Framework\TestCase;

/**
 * Integration test: backing up files that grow, shrink, or disappear mid-backup.
 *
 * This test drives the dev_platform CLI as a sub-process to take a real backup of the dev_platform/ directory while
 * dev_platform/makebigfile.php concurrently misbehaves with a 15 MiB file (dev_platform/misbehaving.dat). It verifies
 * that the engine reacts correctly in each case:
 *
 * - **grow**   The file grows past its recorded size. The engine backs up the recorded amount of data and the backup
 *              completes successfully; the (partial) archive can still be extracted. We confirm extraction with Akeeba
 *              Kickstart's dry-run mode when it is available.
 * - **shrink** The file shrinks below its recorded size. The engine aborts the backup with a "shrunk" error and a
 *              matching "shrunk during backup" warning.
 * - **nuke**   The file disappears mid-backup. The engine aborts the backup with a "went away" error.
 *
 * The test is timing-sensitive by nature (it races a background process against the backup engine). To make the window
 * wide and reliable the dev_platform CLI is run with AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY set, a deliberately slow
 * timing profile, and a generously slow makebigfile.php delay. All of these can be tuned through environment variables
 * (see Test/.env.sample).
 *
 * The whole test self-skips unless INTEGRATION_BACKUP is set to a truthy value.
 *
 * @group integration
 * @group backup
 */
class MisbehavingFileTest extends TestCase
{
	/** @var string Absolute path to the repository root. */
	private static string $repoRoot;

	/** @var string Absolute path to the dev_platform directory (also the backup "site root"). */
	private static string $devPlatform;

	/** @var string Absolute path to the dev_platform CLI entry point. */
	private static string $cli;

	/** @var string Absolute path to the makebigfile.php helper. */
	private static string $makeBigFile;

	/** @var string Absolute path to the misbehaving file we create under dev_platform. */
	private static string $misbehavingFile;

	/** @var string Absolute path to the temporary archive output directory. */
	private static string $outputDir;

	/** @var int The dedicated backup profile ID created for this test run. */
	private static int $profileId = 0;

	/** @var int Per-chunk artificial delay (microseconds) applied to the backup engine. */
	private static int $multipartDelay;

	/** @var int makebigfile.php inter-step delay (milliseconds) for grow/shrink. */
	private static int $misbehaveDelay;

	/** @var int makebigfile.php delay (milliseconds) before deleting the file in nuke mode. */
	private static int $nukeDelay;

	public static function setUpBeforeClass(): void
	{
		if (!self::isTruthy(getenv('INTEGRATION_BACKUP')))
		{
			self::markTestSkipped(
				'The misbehaving-file backup integration test is disabled. Set INTEGRATION_BACKUP=1 to enable it '
				. '(see Test/.env.sample).'
			);
		}

		self::$repoRoot        = dirname(__DIR__, 3);
		self::$devPlatform     = self::$repoRoot . '/dev_platform';
		self::$cli             = self::$devPlatform . '/index.php';
		self::$makeBigFile     = self::$devPlatform . '/makebigfile.php';
		self::$misbehavingFile = self::$devPlatform . '/misbehaving.dat';
		self::$outputDir       = sys_get_temp_dir() . '/akeeba-misbehaving-test';

		// The engine reads the 15 MiB file roughly one 1 MiB chunk per backup step, sleeping $multipartDelay between
		// chunks, so the file takes about 15 * $multipartDelay to read. The defaults give a ~4.5 s reading window which
		// is wide enough to reliably overlap with makebigfile.php's misbehaviour. Tune via the environment if needed.
		self::$multipartDelay = (int) (getenv('AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY') ?: 300000);
		self::$misbehaveDelay = (int) (getenv('BACKUP_MISBEHAVE_DELAY') ?: 80);
		self::$nukeDelay      = (int) (getenv('BACKUP_NUKE_DELAY') ?: 2000);

		if (!is_file(self::$cli) || !is_file(self::$makeBigFile))
		{
			self::markTestSkipped('The dev_platform CLI or makebigfile.php could not be found.');
		}

		if (!is_file(self::$devPlatform . '/dev.sqlite'))
		{
			self::markTestSkipped(
				'dev_platform is not initialised. Run `php dev_platform/index.php init` before running this test.'
			);
		}

		// Prepare a clean output directory.
		self::recursiveDelete(self::$outputDir);

		if (!is_dir(self::$outputDir) && !@mkdir(self::$outputDir, 0777, true) && !is_dir(self::$outputDir))
		{
			self::markTestSkipped('Could not create the temporary output directory ' . self::$outputDir);
		}

		// Create a dedicated backup profile and configure it for a slow, files-only backup of dev_platform.
		self::$profileId = self::createProfile();
		self::configureProfile(self::$profileId);
	}

	public static function tearDownAfterClass(): void
	{
		// Stop any stray makebigfile.php helper and remove the misbehaving file.
		if (isset(self::$misbehavingFile) && is_file(self::$misbehavingFile))
		{
			@unlink(self::$misbehavingFile);
		}

		// Delete the dedicated profile.
		if (self::$profileId > 1)
		{
			self::runCli(['profile:delete', (string) self::$profileId]);
		}

		// Remove the temporary output directory.
		if (isset(self::$outputDir))
		{
			self::recursiveDelete(self::$outputDir);
		}
	}

	protected function setUp(): void
	{
		// Start every scenario from a clean slate: no leftover archives, no leftover misbehaving file.
		self::emptyDirectory(self::$outputDir);

		if (is_file(self::$misbehavingFile))
		{
			@unlink(self::$misbehavingFile);
		}
	}

	/**
	 * A file that keeps growing is backed up partially and the resulting archive can still be extracted.
	 */
	public function testGrowingFile(): void
	{
		$result = $this->runMisbehavingBackup('grow');

		$this->assertTrue(
			$result['success'],
			"A backup of a growing file should still succeed. Output:\n" . $result['raw']
		);

		$this->assertEmpty(
			$result['error'],
			"A backup of a growing file should not error out. Error: " . $result['error']
		);

		// The engine should have noticed the file growing past its recorded size and only backed up the recorded
		// amount of data (a partial backup). If this warning is missing the file was fully read before it had a chance
		// to grow — widen the window with AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY / BACKUP_MISBEHAVE_DELAY.
		$this->assertStringContainsStringIgnoringCase(
			'grew while putting it in the backup archive',
			implode("\n", $result['warnings']),
			"The backup should emit a 'grew' warning, proving the growing file was caught mid-backup. Output:\n"
			. $result['raw']
		);

		$archives = $this->collectArchives();

		$this->assertNotEmpty($archives, 'The backup should have produced at least one archive part.');

		// The archive must be extractable. We use Akeeba Kickstart's dry-run mode when available.
		$this->assertArchiveExtractable(reset($archives));
	}

	/**
	 * A file that shrinks below its recorded size aborts the backup with a sensible error and warning.
	 */
	public function testShrinkingFile(): void
	{
		$result = $this->runMisbehavingBackup('shrink');

		$this->assertFalse(
			$result['success'],
			"A backup of a shrinking file must not report success. Output:\n" . $result['raw']
		);

		$this->assertStringContainsStringIgnoringCase(
			'shrunk while putting it in the backup archive',
			$result['error'],
			"The backup should fail with a 'shrunk' error. Output:\n" . $result['raw']
		);

		$this->assertStringContainsStringIgnoringCase(
			'shrunk during backup',
			implode("\n", $result['warnings']),
			"The backup should emit a 'shrunk during backup' warning. Output:\n" . $result['raw']
		);
	}

	/**
	 * A file that disappears mid-backup aborts the backup with a sensible "went away" error.
	 */
	public function testDisappearingFile(): void
	{
		$result = $this->runMisbehavingBackup('nuke');

		$this->assertFalse(
			$result['success'],
			"A backup of a disappearing file must not report success. Output:\n" . $result['raw']
		);

		$this->assertStringContainsStringIgnoringCase(
			'went away while putting it in the backup archive',
			$result['error'],
			"The backup should fail with a 'went away' error. Output:\n" . $result['raw']
		);
	}

	/**
	 * Run a backup while makebigfile.php misbehaves with the misbehaving file in the requested mode.
	 *
	 * @param   string  $mode  One of 'grow', 'shrink', 'nuke'.
	 *
	 * @return  array{success: bool, error: string, warnings: string[], raw: string}
	 */
	private function runMisbehavingBackup(string $mode): array
	{
		$delay = $mode === 'nuke' ? self::$nukeDelay : self::$misbehaveDelay;

		// Start makebigfile.php in the background. It (re)creates the 15 MiB file, then misbehaves with it.
		$makeBig = $this->startBackground(
			[self::$makeBigFile, self::$misbehavingFile, $mode, (string) $delay]
		);

		try
		{
			// Wait until the misbehaving file is present and large enough to span many archive chunks, so the backup's
			// file enumeration is guaranteed to pick it up. We use a threshold below the initial 15 MiB because in the
			// shrink scenario the file starts shrinking the instant it has been created.
			if (!$this->waitForInitialSize(self::$misbehavingFile, 14 * 1024 * 1024, 5.0))
			{
				$this->markTestIncomplete(
					'makebigfile.php did not create the 15 MiB misbehaving file in time; cannot exercise the ' . $mode
					. ' scenario on this machine.'
				);
			}

			// Take the backup (foreground, captured), with the artificial per-chunk delay enabled.
			$output = self::runCli(
				['backup:take', 'Misbehaving file test (' . $mode . ')', '--profile=' . self::$profileId, '--jsonl'],
				['AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY' => (string) self::$multipartDelay]
			);
		}
		finally
		{
			$this->stopBackground($makeBig);
		}

		return $this->parseJsonl($output);
	}

	/**
	 * Parse the --jsonl output of `backup:take` into a flat result.
	 *
	 * @param   string  $output  Raw CLI stdout.
	 *
	 * @return  array{success: bool, error: string, warnings: string[], raw: string}
	 */
	private function parseJsonl(string $output): array
	{
		$success  = false;
		$error    = '';
		$warnings = [];

		foreach (explode("\n", $output) as $line)
		{
			$line = trim($line);

			if ($line === '' || $line[0] !== '{')
			{
				continue;
			}

			$data = json_decode($line, true);

			if (!is_array($data))
			{
				continue;
			}

			if (($data['event'] ?? '') === 'finished')
			{
				$success = (bool) ($data['success'] ?? false);
			}

			if (!empty($data['error']))
			{
				$error = (string) $data['error'];
			}

			foreach ((array) ($data['warnings'] ?? []) as $warning)
			{
				$warning = (string) $warning;

				if ($warning !== '' && !in_array($warning, $warnings, true))
				{
					$warnings[] = $warning;
				}
			}
		}

		return [
			'success'  => $success,
			'error'    => $error,
			'warnings' => $warnings,
			'raw'      => $output,
		];
	}

	/**
	 * Verify that an archive can be extracted, using Akeeba Kickstart's dry-run mode.
	 *
	 * If Kickstart cannot be located the check is reported as incomplete rather than failing the test, since it is an
	 * external tool whose location is environment-specific (configure KICKSTART_PATH in Test/.env).
	 *
	 * @param   string  $archive  Absolute path to the (first part of the) archive.
	 */
	private function assertArchiveExtractable(string $archive): void
	{
		$kickstart = $this->locateKickstart();

		if ($kickstart === null)
		{
			$this->markTestIncomplete(
				'Akeeba Kickstart was not found, so the archive could not be test-extracted. Set KICKSTART_PATH in '
				. 'Test/.env to point at kickstart.php (or the directory containing it). The backup itself succeeded '
				. 'and produced ' . $archive . '.'
			);
		}

		// Run Kickstart's CLI in dry-run mode: it reads and validates the whole archive without writing any files. We
		// still hand it a throw-away target directory because the CLI insists on one.
		$dryRunTarget = self::$outputDir . '/kickstart-dryrun';
		@mkdir($dryRunTarget, 0777, true);

		try
		{
			$result = $this->runKickstartDryRun($kickstart, $archive, $dryRunTarget);
		}
		finally
		{
			self::recursiveDelete($dryRunTarget);
		}

		if ($result === null)
		{
			$this->markTestIncomplete(
				'Could not determine the outcome of the Kickstart dry-run (unexpected exit code). Inspect the archive '
				. 'manually: ' . $archive
			);
		}

		$this->assertTrue($result, 'Akeeba Kickstart reported that the partial archive could not be extracted.');
	}

	/**
	 * Run Akeeba Kickstart's CLI in dry-run mode against an archive and report whether extraction would succeed.
	 *
	 * The Kickstart CLI signature is:
	 *     php kickstart.php <archive> [target_path] [--dry-run] [--silent] …
	 * It exits 0 when the archive extracts cleanly and 255 when an error occurs.
	 *
	 * @param   string  $kickstartPhp  Path to the kickstart.php (or kickstart_core.php) script.
	 * @param   string  $archive       Absolute path to the (first part of the) archive.
	 * @param   string  $targetDir     Throw-away extraction target (nothing is written in dry-run mode).
	 *
	 * @return  bool|null  TRUE on success, FALSE on a reported failure, NULL if the exit code was not understood.
	 */
	private function runKickstartDryRun(string $kickstartPhp, string $archive, string $targetDir): ?bool
	{
		$descriptors = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = @proc_open(
			[PHP_BINARY, $kickstartPhp, $archive, $targetDir, '--dry-run', '--silent'],
			$descriptors,
			$pipes,
			$targetDir,
			$this->childEnvironment()
		);

		if (!is_resource($process))
		{
			return null;
		}

		// Drain and discard Kickstart's output; we judge the result by the exit code.
		stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$exitCode = proc_close($process);

		switch ($exitCode)
		{
			case 0:
				return true;

			case 255:
				return false;

			default:
				return null;
		}
	}

	/**
	 * Locate the kickstart.php script from the KICKSTART_PATH environment variable.
	 *
	 * @return  string|null  Absolute path to kickstart.php, or NULL if it cannot be found.
	 */
	private function locateKickstart(): ?string
	{
		$configured = getenv('KICKSTART_PATH') ?: '~/Projects/kickstart/output';

		// Expand a leading ~ to the user's home directory.
		if (strncmp($configured, '~', 1) === 0)
		{
			$home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');

			if ($home !== '')
			{
				$configured = $home . substr($configured, 1);
			}
		}

		$candidates = [$configured];

		if (is_dir($configured))
		{
			$dir          = rtrim($configured, '/');
			$candidates[] = $dir . '/kickstart.php';
			$candidates[] = $dir . '/kickstart_core.php';
		}

		foreach ($candidates as $candidate)
		{
			if (is_file($candidate) && pathinfo($candidate, PATHINFO_EXTENSION) === 'php')
			{
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Collect the archive parts produced in the output directory, sorted so the first part comes first.
	 *
	 * @return  string[]
	 */
	private function collectArchives(): array
	{
		$files = glob(self::$outputDir . '/*.{jpa,jps,zip,j01,j02,j03,z01,z02,z03}', GLOB_BRACE) ?: [];

		sort($files);

		return $files;
	}

	/**
	 * Create a dedicated backup profile for this test run and return its ID.
	 */
	private static function createProfile(): int
	{
		$output = self::runCli(['profile:add', '--description=Misbehaving file integration test']);

		if (!preg_match('/Profile #(\d+) added/i', $output, $matches))
		{
			self::markTestSkipped("Could not create a dedicated backup profile. CLI output:\n" . $output);
		}

		return (int) $matches[1];
	}

	/**
	 * Configure the dedicated profile for a slow, files-only backup of dev_platform.
	 *
	 * @param   int  $profileId  The profile to configure.
	 */
	private static function configureProfile(int $profileId): void
	{
		$config = [
			// Files only, JPA archive, into our temporary output directory.
			'akeeba.basic.backup_type'             => 'fileonly',
			'akeeba.basic.archive_name'            => 'misbehaving-[DATE]-[TIME]',
			'akeeba.basic.output_directory'        => self::$outputDir,
			'akeeba.advanced.archiver_engine'      => 'jpa',
			'akeeba.advanced.scan_engine'          => 'smart',
			// Deliberately slow timing so the engine is still reading the big file while it misbehaves. Note min > max
			// and step breaks between domains/large files are kept enabled, exactly as makebigfile.php recommends.
			'akeeba.tuning.min_exec_time'          => '2',
			'akeeba.tuning.max_exec_time'          => '1',
			'akeeba.tuning.run_time_bias'          => '10',
			'akeeba.tuning.nobreak.beforelargefile' => '0',
			'akeeba.tuning.nobreak.afterlargefile' => '0',
			'akeeba.tuning.nobreak.proactive'      => '0',
			'akeeba.tuning.nobreak.domains'        => '0',
			'akeeba.tuning.nobreak.finalization'   => '0',
			'akeeba.tuning.settimelimit'           => '0',
			'akeeba.tuning.setmemlimit'            => '0',
		];

		$configFile = self::$outputDir . '/profile-config.json';
		file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		self::runCli(['config:import', $configFile, '--profile=' . $profileId]);
		@unlink($configFile);

		// Exclude the dev_platform/backup directory (it may hold large, unrelated archives from other runs).
		self::runCli(['filter:add', 'backup', '--type=directories', '--profile=' . $profileId]);
	}

	/**
	 * Run the dev_platform CLI synchronously and return its captured stdout (with stderr appended).
	 *
	 * @param   string[]                $args         CLI arguments (after the script name).
	 * @param   array<string, string>   $envOverride  Extra environment variables for the child process.
	 *
	 * @return  string
	 */
	private static function runCli(array $args, array $envOverride = []): string
	{
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$command = array_merge([PHP_BINARY, self::$cli], $args);

		$process = proc_open($command, $descriptors, $pipes, self::$devPlatform, self::childEnvironment($envOverride));

		if (!is_resource($process))
		{
			self::fail('Could not start the dev_platform CLI: ' . implode(' ', $args));
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		return rtrim($stdout . (trim($stderr) !== '' ? "\n" . $stderr : ''));
	}

	/**
	 * Start a PHP helper script in the background, discarding its output.
	 *
	 * @param   string[]  $args  Script path followed by its arguments.
	 *
	 * @return  resource  The process handle.
	 */
	private function startBackground(array $args)
	{
		$descriptors = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['file', '/dev/null', 'a'],
			2 => ['file', '/dev/null', 'a'],
		];

		$command = array_merge([PHP_BINARY], $args);

		$process = proc_open($command, $descriptors, $pipes, self::$devPlatform, self::childEnvironment());

		if (!is_resource($process))
		{
			$this->fail('Could not start background helper: ' . implode(' ', $args));
		}

		return $process;
	}

	/**
	 * Terminate and reap a background process started with startBackground().
	 *
	 * @param   resource  $process  The process handle.
	 */
	private function stopBackground($process): void
	{
		if (!is_resource($process))
		{
			return;
		}

		$status = proc_get_status($process);

		if ($status['running'] ?? false)
		{
			proc_terminate($process);
		}

		proc_close($process);
	}

	/**
	 * Wait until a file exists and has reached at least a given size.
	 *
	 * @param   string  $file         Path to watch.
	 * @param   int     $minSize      Minimum size in bytes.
	 * @param   float   $timeoutSecs  How long to wait, in seconds.
	 *
	 * @return  bool  TRUE if the file reached the size in time.
	 */
	private function waitForInitialSize(string $file, int $minSize, float $timeoutSecs): bool
	{
		$deadline = microtime(true) + $timeoutSecs;

		do
		{
			clearstatcache(true, $file);

			if (is_file($file) && filesize($file) >= $minSize)
			{
				return true;
			}

			usleep(20000);
		}
		while (microtime(true) < $deadline);

		return false;
	}

	/**
	 * Build the environment for a child process: the current environment plus optional overrides.
	 *
	 * @param   array<string, string>  $overrides  Variables to add or replace.
	 *
	 * @return  array<string, string>
	 */
	private static function childEnvironment(array $overrides = []): array
	{
		$env = getenv();

		// Never let the parent's switch leak into helper processes that should not enable the debug delay.
		unset($env['AKEEBA_DEBUG_BIG_FILE_MULTIPART_DELAY']);

		return array_merge($env, $overrides);
	}

	/**
	 * Whether an environment value should be treated as "on".
	 */
	private static function isTruthy($value): bool
	{
		if ($value === false || $value === null)
		{
			return false;
		}

		return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
	}

	/**
	 * Remove all files inside a directory (non-recursively into sub-directories of the output dir is enough here).
	 */
	private static function emptyDirectory(string $dir): void
	{
		foreach (glob(rtrim($dir, '/') . '/*') ?: [] as $entry)
		{
			if (is_file($entry))
			{
				@unlink($entry);
			}
			elseif (is_dir($entry))
			{
				self::recursiveDelete($entry);
			}
		}
	}

	/**
	 * Recursively delete a directory and its contents.
	 */
	private static function recursiveDelete(string $path): void
	{
		if (is_file($path) || is_link($path))
		{
			@unlink($path);

			return;
		}

		if (!is_dir($path))
		{
			return;
		}

		foreach (scandir($path) ?: [] as $entry)
		{
			if ($entry === '.' || $entry === '..')
			{
				continue;
			}

			self::recursiveDelete($path . '/' . $entry);
		}

		@rmdir($path);
	}
}
