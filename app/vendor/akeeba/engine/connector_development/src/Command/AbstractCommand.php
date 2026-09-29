<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engine\Development\Command;

use Akeeba\Engine\Platform;
use Akeeba\Engine\Util\RandomValue;
use Composer\CaBundle\CaBundle;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Superclass used to create CLI scripts for developing Connector classes.
 */
abstract class AbstractCommand
{
	protected const SIZE_EMPTY = 0;

	protected const SIZE_INFINITESIMAL = 16 * 1024;

	protected const SIZE_MINISCULE = 128 * 1024;

	protected const SIZE_UNDERSIZE = 1024 * 1024;

	protected const SIZE_SMALL = 5 * 1024 * 1024;

	protected const SIZE_TYPICAL = 10 * 1024 * 1024;

	protected const SIZE_LARGE = 50 * 1024 * 1024;

	protected const SIZE_SIZEABLE = 100 * 1024 * 1024;

	protected const SIZE_GIGANTIC = 256 * 1024 * 1024;

	protected const SIZE_COLOSSAL = 1024 * 1024 * 1024;

	protected const SIZE_MONSTROUS = 2 * 1024 * 1024 * 1024;

	protected $commandDescription = '';

	private $filesRoot = '';

	/**
	 * @var   OutputInterface
	 */
	protected $output;

	/**
	 * Runs the inner loop. This is the only public method you should call.
	 *
	 * @return  void
	 */
	final public function __invoke(OutputInterface $output)
	{
		$this->output = $output;

		try
		{
			$this->innerLoop();
		}
		catch (Throwable $e)
		{
			$this->errorReporter($e);
		}
	}

	public function getFilesRoot(): string
	{
		if (empty($this->filesRoot))
		{
			$this->filesRoot = __DIR__ . '/../../tmp';

			if (!is_dir($this->filesRoot))
			{
				@mkdir($this->filesRoot, 0755, true);
			}
		}

		return $this->filesRoot;
	}

	public function setFilesRoot(string $filesRoot): void
	{
		if (is_dir($filesRoot) && is_writable($filesRoot) && is_readable($filesRoot))
		{
			$this->filesRoot = $filesRoot;

			return;
		}

		$this->filesRoot = '';
	}

	public function getDescription(): string
	{
		$bits = explode('\\', get_class($this));

		return $this->commandDescription
			?: sprintf('Run engine the development script for the %s engine', array_pop($bits));
	}

	/**
	 * Returns a temporary file name which will be removed automatically on script termination.
	 *
	 * @return  string
	 */
	protected function getTempFileName(): string
	{
		return stream_get_meta_data(tmpfile())['uri'];
	}

	/**
	 * Asserts that two files are equal
	 *
	 * @param   string  $referenceFile  Absolute path to the reference file (e.g. local data file)
	 * @param   string  $fileToCheck    Absolute path to the subject file (e.g. downloaded file)
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException
	 */
	protected function assertFilesEquals(string $referenceFile, string $fileToCheck): void
	{
		clearstatcache(true, $referenceFile);
		clearstatcache(true, $fileToCheck);

		if (!is_file($referenceFile))
		{
			throw new RuntimeException(sprintf('Reference file %s does not exist', $referenceFile));
		}

		if (!is_readable($referenceFile))
		{
			throw new RuntimeException(sprintf('Reference file %s is not readable', $referenceFile));
		}

		if (!is_file($fileToCheck))
		{
			throw new RuntimeException(sprintf('Subject file %s does not exist', $fileToCheck));
		}

		if (!is_readable($fileToCheck))
		{
			throw new RuntimeException(sprintf('Subject file %s is not readable', $fileToCheck));
		}

		$referenceSize = @filesize($referenceFile);
		$checkSize     = @filesize($fileToCheck);

		if ($referenceSize !== $checkSize)
		{
			throw new RuntimeException(
				sprintf(
					'Size mismatch. Reference file is %ub long, subject file is %ub long.', $referenceSize, $checkSize
				)
			);
		}

		$referenceSHA1 = hash_file('sha1', $referenceFile);
		$checkSHA1     = hash_file('sha1', $fileToCheck);

		if ($referenceSHA1 !== $checkSHA1)
		{
			throw new RuntimeException(
				sprintf('SHA-1 mismatch. Reference file SHA1 = %s, subject SHA1 = %s', $referenceSHA1, $checkSHA1)
			);
		}
	}

	/**
	 * Asserts that an object contains certain properties with specific values
	 *
	 * @param   array   $referenceItems  The properties and their values we want present (array of $property => $value)
	 * @param   object  $objectToCheck   The object whose properties we'll check
	 * @param   bool    $strict          True to also check the variable type of the property values
	 *
	 * @return  void
	 * @throws  RuntimeException
	 * @throws  ReflectionException
	 */
	protected function assertObjectContains(array $referenceItems, object $objectToCheck, bool $strict = false): void
	{
		$this->assertArrayContains($referenceItems, $this->objectToArray($objectToCheck), $strict);
	}

	/**
	 * Generates an array of property names to property values from an object using Reflection recursively
	 *
	 * @param   object  $object        The object to extract
	 * @param   int     $nestingDepth  Nesting depth. Used in recursion to apply a recustion limit of 20 levels deep.
	 *
	 * @return  array
	 *
	 * @throws  ReflectionException
	 */
	protected function objectToArray(object $object, int $nestingDepth = 0): array
	{
		if ($nestingDepth > 20)
		{
			return [];
		}

		$refObj = new ReflectionClass($object);
		$array  = [];

		/** @var ReflectionProperty $refProp */
		foreach ($refObj->getProperties() as $refProp)
		{
			if (version_compare(PHP_VERSION, '8.1.0', 'lt'))
			{
				$refProp->setAccessible(true);
			}

			$value = $refProp->getValue($object);

			if (is_object($value))
			{
				$value = $this->objectToArray($value, $nestingDepth + 1);
			}

			$array[$refProp->getName()] = $value;
		}

		return $array;
	}

	/**
	 * Asserts that an array contains a subarray in any order.
	 *
	 * @param   array  $referenceItems  These items must exist in $arrayToCheck (array of key => value)
	 * @param   array  $arrayToCheck    The array whose contents we'll check
	 * @param   bool   $strict          True to also check the variable type of the values
	 *
	 * @return  void
	 * @throws  RuntimeException
	 */
	protected function assertArrayContains(array $referenceItems, array $arrayToCheck, bool $strict = false): void
	{
		if (empty($referenceItems))
		{
			// WTF, dude?
			return;
		}

		foreach ($referenceItems as $key => $value)
		{
			if (!array_key_exists($key, $arrayToCheck))
			{
				throw new RuntimeException(sprintf('Key ‘%s’ does not exist.', $key));
			}

			if ($arrayToCheck[$key] != $value)
			{
				throw new RuntimeException(
					sprintf(
						'Value of key ‘%s’ does not match. Expected ‘%s’, got ‘%s’', $key, print_r($value, true),
						print_r($arrayToCheck[$key], true)
					)
				);
			}

			if ($strict && ($arrayToCheck[$key] !== $value))
			{
				throw new RuntimeException(
					sprintf(
						'Variable type of key ‘%s’ does not match. Expected ‘%s’, got ‘%s’', $key, gettype($value),
						gettype($arrayToCheck[$key])
					)
				);
			}
		}
	}

	/**
	 * Returns the full filesystem path to a file of the requested size (and optional basename).
	 *
	 * If the basename is empty or omitted an appropriate English adjective will be used to generate a filename.
	 *
	 * @param   int     $size      The required size in bytes
	 * @param   string  $baseName  The basename to use (leave empty to figure out an appropriate adjective)
	 *
	 * @return  string The absolute filesystem path of the generated file
	 */
	protected function getTestFile(int $size = 5242880, string $baseName = ''): string
	{
		if (empty($baseName))
		{
			$baseName = 'engine_' . $this->getSizeAdjective($size) . '.bin';
		}

		$filePath = rtrim(realpath($this->getFilesRoot()), DIRECTORY_SEPARATOR) .
		            DIRECTORY_SEPARATOR . basename($baseName);

		$this->createFile($filePath, $size);

		return $filePath;
	}

	/**
	 * Get an English adjective best describing the given file size.
	 *
	 * @param   int  $size  Size in bytes
	 *
	 * @return string
	 */
	private function getSizeAdjective(int $size = 0): string
	{
		if ($size <= self::SIZE_EMPTY)
		{
			return 'empty';
		}

		if ($size < self::SIZE_INFINITESIMAL)
		{
			return 'infinitesimal';
		}

		if ($size < self::SIZE_MINISCULE)
		{
			return 'miniscule';
		}

		if ($size < self::SIZE_UNDERSIZE)
		{
			return 'undersize';
		}

		if ($size < self::SIZE_SMALL)
		{
			return 'small';
		}

		if ($size <= self::SIZE_TYPICAL)
		{
			return 'typical';
		}

		if ($size <= self::SIZE_LARGE)
		{
			return 'large';
		}

		if ($size <= self::SIZE_SIZEABLE)
		{
			return 'sizeable';
		}

		if ($size <= self::SIZE_GIGANTIC)
		{
			return 'gigantic';
		}

		if ($size <= self::SIZE_COLOSSAL)
		{
			return 'colossal';
		}

		if ($size <= self::SIZE_MONSTROUS)
		{
			return 'monstrous';
		}

		return 'cosmic';
	}

	/**
	 * Creates a random file, if it does not already exist at the same size.
	 *
	 * If the file exists and is of the same size as the one requested nothing happens; the file remains as it is.
	 *
	 * If we are on Windows or neither shell_exec nor exec are available we repeat a 16KB block of random data.
	 *
	 * In any other case we use dd to read /dev/urandom, creating a file $size big. If the size if over 5MB we will have
	 * dd read 16KB to 5MB blocks.
	 *
	 * @param   string  $fileName  Absolute path to the file to create
	 * @param   int     $size      Size in bytes for the file to be created
	 */
	private function createFile(string $fileName, int $size = 5242880): void
	{
		clearstatcache();
		$existingSize = is_file($fileName) ? filesize($fileName) : 0;

		if ($existingSize == $size)
		{
			return;
		}

		$blockSize = $size;
		$count     = 1;

		// For files over 5MB we will use a 512K, 1M, 2.5M, 5M or 10M block size that minimizes the size discrepancy
		if ($size > 5242880)
		{
			$possibilities = [];

			foreach ([524288, 1048576, 2621440, 5242880, 10485760] as $blockSize)
			{
				$count    = intdiv($size, $blockSize);
				$newSize  = $blockSize * $count;
				$distance = abs($newSize - $size);

				$possibilities[] = [
					$distance,
					$blockSize,
					$count,
				];
			}

			uasort(
				$possibilities, function ($a, $b) {
				// In case of items with the same size difference we prefer the larger block size
				if ($a[0] == $b[0])
				{
					return ($a[1] > $b[1]) ? -1 : 1;
				}

				return ($a[0] < $b[0]) ? -1 : 1;
			}
			);

			[$distance, $blockSize, $count] = array_shift($possibilities);
		}

		// If we are on Linux or macOS and we have access to exec or shell_exec we will do it the easy way
		$cmd          = sprintf(
			'dd if=/dev/urandom of=%s bs=%u count=%u 2>&1', escapeshellarg($fileName), (int) $blockSize, (int) $count
		);
		$hasShellExec = function_exists('shell_exec');
		$hasExec      = function_exists('exec');

		// If we're on Windows or in a restricted environment we'll do it the hard way.
		if (IS_WIN || (!$hasExec && !$hasShellExec))
		{
			$this->createFileTheHardWay($fileName, $size);

			return;
		}

		if ($hasShellExec)
		{
			$ignore = shell_exec($cmd);
		}

		exec($cmd, $ignore);
	}

	/**
	 * Creates a random file the hard way
	 *
	 * @param   string  $fileName  Absolute path to the file to create
	 * @param   int     $size      Size in bytes for the file to be created
	 */
	private function createFileTheHardWay(string $fileName, int $size = 5242880): void
	{
		// We will create a 16KB buffer of random data and repeat it over and over.
		$randVal = new RandomValue();
		$buffer  = $randVal->generate(16384);

		$fp = fopen($fileName, 'w');

		if ($fp === false)
		{
			throw new RuntimeException(sprintf("Cannot create temporary file %s", $fileName));
		}

		while ($size > 0)
		{
			$written = fwrite($fp, $buffer, $size);

			if (!$written)
			{
				break;
			}

			$size -= $written;
		}

		fclose($fp);
	}

	/**
	 * Loads Akeeba Engine
	 *
	 * @return  void
	 */
	private function loadEngine()
	{
		// Assign the correct platform
		Platform::addPlatform('Dummy', __DIR__ . '/../../Platform/Dummy');

		if (!defined('AKEEBA_CACERT_PEM'))
		{
			define('AKEEBA_CACERT_PEM', CaBundle::getBundledCaBundlePath());
		}
	}

	private function errorReporter(Throwable $e, bool $isNested = false)
	{
		$errorType = 'Exception';

		if (!($e instanceof Exception))
		{
			$errorType = 'Throwable';
		}

		$out = $this->output->getFormatter();

		if (!$isNested)
		{
			$this->output->writeln("");
			$this->output->writeln("");
			$this->output->writeln(str_repeat('=', 80));
			$this->output->writeln(sprintf("Unhandled %s (%s)", $errorType, $e->getCode()));
			$this->output->writeln(str_repeat('=', 80));
		}
		else
		{
			$this->output->writeln('');
			$this->output->writeln(sprintf("<<< Nested %s (%s)", $errorType, $e->getCode()));
			$this->output->writeln(str_repeat('~', 80));
		}

		$this->output->writeln("");
		$this->output->writeln($e->getMessage());
		$this->output->writeln(sprintf('%s(%u):', $e->getFile(), $e->getLine()));
		$this->output->writeln('');
		$this->output->writeln($e->getTraceAsString());

		if ($e->getPrevious())
		{
			$this->errorReporter($e->getPrevious());
		}
	}

	/**
	 * The inner loop of the execute method. We extract it to a method to allow us to catch both Exceptions and
	 * Throwables, depending on the PHP version.
	 *
	 * @return  void
	 */
	private function innerLoop()
	{
		// Load the Engine and its nifty autoloader
		$this->output->writeln("Loading the backup engine");
		$this->loadEngine();

		$this->doExecute();
	}

	/**
	 * Formats a number of bytes in human-readable format
	 *
	 * @param   int|float  $size  The size in bytes to format, e.g. 8254862
	 *
	 * @return  string  The human-readable representation of the byte size, e.g. "7.87 Mb"
	 * @since   9.3.1
	 */
	protected function formatByteSize($size): string
	{
		$unit = ['b', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

		return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $unit[$i];
	}


	abstract protected function doExecute(): void;
}