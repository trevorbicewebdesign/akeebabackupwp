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

use PHPUnit\Framework\TestCase;

final class BufferTest extends TestCase
{
	/**
	 * Open handles to close in tearDown.
	 *
	 * @var resource[]
	 */
	private $handles = [];

	protected function setUp(): void
	{
		// Buffer class self-registers on inclusion; ensure it is loaded.
		// The stream wrapper is registered in Buffer.php's global scope.
		// We just make sure the class is loaded by referencing it.
		class_exists(\Akeeba\Engine\Util\Buffer::class);
	}

	protected function tearDown(): void
	{
		foreach ($this->handles as $handle)
		{
			if (is_resource($handle))
			{
				fclose($handle);
			}
		}

		$this->handles = [];
	}

	/**
	 * Helper: open a buffer:// handle and track it for cleanup.
	 */
	private function openBuffer(string $name, string $mode = 'w+')
	{
		$handle          = fopen('buffer://' . $name, $mode);
		$this->handles[] = $handle;

		return $handle;
	}

	// -----------------------------------------------------------------------
	// Open + write + rewind + read round-trip
	// -----------------------------------------------------------------------

	public function testWriteThenReadReturnsOriginalBytes(): void
	{
		$data   = 'Hello, World!';
		$handle = $this->openBuffer('rw_roundtrip');

		fwrite($handle, $data);
		rewind($handle);
		$result = fread($handle, strlen($data) + 1);

		$this->assertSame($data, $result);
	}

	public function testWriteThenFreadReturnsOriginalBytes(): void
	{
		$data   = 'Hello, World!';
		$handle = $this->openBuffer('rw_fread');

		fwrite($handle, $data);
		rewind($handle);
		$result = fread($handle, strlen($data));

		$this->assertSame($data, $result);
	}

	// -----------------------------------------------------------------------
	// stream_tell() position tracking
	// -----------------------------------------------------------------------

	public function testTellAfterWriteReflectsPosition(): void
	{
		$data   = 'Hello';
		$handle = $this->openBuffer('tell_write');

		fwrite($handle, $data);

		$this->assertSame(strlen($data), ftell($handle));
	}

	public function testTellAfterRewindIsZero(): void
	{
		$handle = $this->openBuffer('tell_rewind');

		fwrite($handle, 'ABCDE');
		rewind($handle);

		$this->assertSame(0, ftell($handle));
	}

	public function testTellAdvancesAfterRead(): void
	{
		$data   = 'Hello, World!';
		$handle = $this->openBuffer('tell_read');

		fwrite($handle, $data);
		rewind($handle);
		fread($handle, 5);

		$this->assertSame(5, ftell($handle));
	}

	// -----------------------------------------------------------------------
	// stream_seek() with SEEK_SET, SEEK_CUR, SEEK_END
	// -----------------------------------------------------------------------

	public function testSeekSetPositionsCorrectly(): void
	{
		$data   = 'Hello, World!';
		$handle = $this->openBuffer('seek_set');

		fwrite($handle, $data);
		fseek($handle, 7, SEEK_SET);

		$this->assertSame(7, ftell($handle));
		$this->assertSame('World!', fread($handle, 6));
	}

	public function testSeekCurAdvancesPosition(): void
	{
		$data   = 'Hello, World!';
		$handle = $this->openBuffer('seek_cur');

		fwrite($handle, $data);
		rewind($handle);
		fread($handle, 5);      // position = 5
		fseek($handle, 2, SEEK_CUR); // position = 7

		$this->assertSame(7, ftell($handle));
		$this->assertSame('World!', fread($handle, 6));
	}

	public function testSeekEndPositionsFromEnd(): void
	{
		$data   = 'Hello, World!';
		$handle = $this->openBuffer('seek_end');

		fwrite($handle, $data);
		fseek($handle, -6, SEEK_END); // position = strlen($data) - 6 = 7

		$this->assertSame('World!', fread($handle, 6));
	}

	// -----------------------------------------------------------------------
	// stream_eof()
	// -----------------------------------------------------------------------

	public function testEofIsFalseMidStream(): void
	{
		$data   = 'Hello, World!';
		$handle = $this->openBuffer('eof_mid');

		fwrite($handle, $data);
		rewind($handle);
		fread($handle, 5); // read only part of it

		$this->assertFalse(feof($handle));
	}

	public function testEofIsTrueAfterReadingAllContent(): void
	{
		$data   = 'Hello';
		$handle = $this->openBuffer('eof_end');

		fwrite($handle, $data);
		rewind($handle);
		fread($handle, strlen($data) + 1); // read everything

		$this->assertTrue(feof($handle));
	}

	public function testEmptyBufferIsImmediatelyEof(): void
	{
		$handle = $this->openBuffer('eof_empty');

		// Nothing written; position = 0 and buffer is null (length 0)
		$result = fread($handle, 1);

		$this->assertSame('', $result);
		$this->assertTrue(feof($handle));
	}

	// -----------------------------------------------------------------------
	// Partial reads
	// -----------------------------------------------------------------------

	public function testPartialReadAdvancesPointerCorrectly(): void
	{
		$data   = 'ABCDEFGHIJ';
		$handle = $this->openBuffer('partial');

		fwrite($handle, $data);
		rewind($handle);

		$first  = fread($handle, 3);
		$second = fread($handle, 3);
		$third  = fread($handle, 4);

		$this->assertSame('ABC', $first);
		$this->assertSame('DEF', $second);
		$this->assertSame('GHIJ', $third);
		$this->assertSame(10, ftell($handle));
	}

	// -----------------------------------------------------------------------
	// Distinct buffer names are independent
	// -----------------------------------------------------------------------

	public function testDistinctBufferNamesAreIndependent(): void
	{
		$handleA = $this->openBuffer('indep_a');
		$handleB = $this->openBuffer('indep_b');

		fwrite($handleA, 'DataForA');
		fwrite($handleB, 'DataForB');

		rewind($handleA);
		rewind($handleB);

		$readA = fread($handleA, 20);
		$readB = fread($handleB, 20);

		$this->assertSame('DataForA', $readA);
		$this->assertSame('DataForB', $readB);
	}

	// -----------------------------------------------------------------------
	// Overwrite behaviour (write at offset overwrites, not inserts)
	// -----------------------------------------------------------------------

	public function testWriteAtMiddleOverwritesBytes(): void
	{
		$handle = $this->openBuffer('overwrite');

		fwrite($handle, 'Hello, World!');
		fseek($handle, 7, SEEK_SET);
		fwrite($handle, 'PHP!!');

		rewind($handle);
		$result = fread($handle, 20);

		$this->assertSame('Hello, PHP!!!', $result);
	}
}
