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

namespace Akeeba\Engine\Test\Archiver;

use Akeeba\Engine\Archiver\Jpa;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Test\AbstractEngineTestCase;
use ReflectionObject;

/**
 * Regression tests for split JPA archives whose data ends exactly on a part boundary.
 *
 * BaseArchiver::putRawDataIntoArchive() rotated to a new part as soon as the current one filled up, without checking
 * whether it still had bytes to write. When a block of data *spans* a part boundary and ends exactly on the next one,
 * both counters hit zero on the same iteration and the surplus part never receives a byte. JPA finalisation renames
 * the last part to `.jpa`, so that empty part becomes the final archive — and post-processing engines (e.g. Amazon S3)
 * reject a zero byte upload, failing the whole backup.
 *
 * Note that a single write which fills a part exactly is *not* affected: it returns early, before the write loop.
 * That is why the trigger is far rarer than "the archive size is a multiple of the part size" suggests.
 *
 * @covers \Akeeba\Engine\Archiver\BaseArchiver
 * @covers \Akeeba\Engine\Archiver\Jpa
 */
class JpaSplitTest extends AbstractEngineTestCase
{
	/** @var string Absolute path to the throw-away output directory. */
	private $outputDirectory;

	/** @var int The part size the archiver under test was configured with. */
	private $partSize;

	protected function setUp(): void
	{
		parent::setUp();

		$this->outputDirectory = sys_get_temp_dir() . '/akeeba-jpa-split-' . uniqid();

		@mkdir($this->outputDirectory, 0777, true);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->outputDirectory . '/*') ?: [] as $file)
		{
			@unlink($file);
		}

		@rmdir($this->outputDirectory);

		parent::tearDown();
	}

	/**
	 * Data which fills the current part exactly must not spawn an extra, empty part.
	 */
	public function testDataEndingOnAPartBoundaryDoesNotCreateAnEmptyPart(): void
	{
		$archiver = $this->makeSplitArchiver(65536);

		$this->fillPartsExactly($archiver);

		clearstatcache();

		$this->assertFileExists($this->outputDirectory . '/test.j02');
		$this->assertFileDoesNotExist(
			$this->outputDirectory . '/test.j03',
			'Filling a part exactly must not create the next part before there is anything to write into it.'
		);
	}

	/**
	 * The final `.jpa` of a split archive must never be zero bytes long — that is what breaks the S3 upload.
	 */
	public function testFinalPartIsNotEmptyWhenDataEndsOnAPartBoundary(): void
	{
		$archiver = $this->makeSplitArchiver(65536);

		$this->fillPartsExactly($archiver);

		$archiver->finalize();

		clearstatcache();

		$finalArchive = $this->outputDirectory . '/test.jpa';

		$this->assertFileExists($finalArchive);
		$this->assertGreaterThan(
			0,
			filesize($finalArchive),
			'The final part of a split JPA must carry data; a zero byte .jpa is rejected by post-processing engines.'
		);
	}

	/**
	 * The part count in the archive header must match the parts actually written.
	 *
	 * The surplus part was counted by createNewPartFile(), so the header over-reported the number of parts as well.
	 */
	public function testArchiveHeaderRecordsTheRealNumberOfParts(): void
	{
		$archiver = $this->makeSplitArchiver(65536);

		$this->fillPartsExactly($archiver);

		$archiver->finalize();

		clearstatcache();

		$writtenParts = count(glob($this->outputDirectory . '/test.j*') ?: []);

		$this->assertEquals(2, $writtenParts, 'Expected exactly .j01 plus the final .jpa.');
		$this->assertEquals(
			$writtenParts,
			$this->getPartCountFromHeader($this->outputDirectory . '/test.j01'),
			'The Spanned Archive Extra Header must report the number of parts that really exist.'
		);
	}

	/**
	 * Rotation must still happen when there really are more bytes to write than the part can hold.
	 */
	public function testDataOverflowingThePartStillCreatesTheNextPart(): void
	{
		$partSize = 65536;
		$archiver = $this->makeSplitArchiver($partSize);

		$data = str_repeat('A', $this->getPartFreeSize($archiver) + 1024);

		$this->putRawDataIntoArchive($archiver, $data);

		clearstatcache();

		$this->assertFileExists($this->outputDirectory . '/test.j02');
		$this->assertEquals(1024, filesize($this->outputDirectory . '/test.j02'));
		$this->assertEquals($partSize, filesize($this->outputDirectory . '/test.j01'));
	}

	/**
	 * Deferring the rotation leaves the part exactly full. The next write must still roll over, without losing a byte.
	 */
	public function testWritingAgainAfterAPartWasLeftExactlyFullRollsOver(): void
	{
		$partSize = 65536;
		$archiver = $this->makeSplitArchiver($partSize);

		$this->fillPartsExactly($archiver);
		$this->putRawDataIntoArchive($archiver, str_repeat('B', 4096));

		clearstatcache();

		$this->assertEquals($partSize, filesize($this->outputDirectory . '/test.j01'));
		$this->assertEquals($partSize, filesize($this->outputDirectory . '/test.j02'));
		$this->assertEquals(4096, filesize($this->outputDirectory . '/test.j03'));
		$this->assertEquals(
			str_repeat('B', 4096),
			file_get_contents($this->outputDirectory . '/test.j03'),
			'The deferred rotation must not drop or duplicate any of the data written afterwards.'
		);
	}

	/**
	 * Create a JPA archiver writing split parts of the given size into the throw-away output directory.
	 */
	private function makeSplitArchiver(int $partSize): Jpa
	{
		$this->partSize = $partSize;

		Factory::getConfiguration()->set('engine.archiver.common.part_size', $partSize);

		$archiver = new Jpa();
		$archiver->initialize($this->outputDirectory . '/test.jpa');

		$openForOutput = (new ReflectionObject($archiver))->getMethod('openArchiveForOutput');
		$openForOutput->setAccessible(true);
		$openForOutput->invoke($archiver, true);

		return $archiver;
	}

	/**
	 * Write a blob which spills over into the next part and ends exactly on that part's boundary.
	 *
	 * This is the condition reported in the field: the write loop runs out of data and out of free part space on the
	 * very same iteration.
	 */
	private function fillPartsExactly(Jpa $archiver): void
	{
		$length = $this->getPartFreeSize($archiver) + $this->partSize;

		$this->putRawDataIntoArchive($archiver, str_repeat('A', $length));
	}

	/**
	 * Read the number of parts out of a split JPA's Spanned Archive Extra Header.
	 *
	 * Layout: 19 byte Standard Header, then a 4 byte signature and a 2 byte extra field length, then the part count.
	 */
	private function getPartCountFromHeader(string $firstPart): int
	{
		$header = file_get_contents($firstPart, false, null, 0, 27);

		$this->assertEquals('JPA', substr($header, 0, 3), 'Not a JPA archive.');
		$this->assertEquals("\x4A\x50\x01\x01", substr($header, 19, 4), 'Missing the Spanned Archive Extra Header.');

		return unpack('v', substr($header, 25, 2))[1];
	}

	private function getPartFreeSize(Jpa $archiver): int
	{
		$method = (new ReflectionObject($archiver))->getMethod('getPartFreeSize');
		$method->setAccessible(true);

		return (int) $method->invoke($archiver);
	}

	private function putRawDataIntoArchive(Jpa $archiver, string $data): void
	{
		$method = (new ReflectionObject($archiver))->getMethod('putRawDataIntoArchive');
		$method->setAccessible(true);

		$method->invokeArgs($archiver, [&$data]);
	}
}
