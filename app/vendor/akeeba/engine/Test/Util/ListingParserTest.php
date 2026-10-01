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

use Akeeba\Engine\Util\ListingParser;
use PHPUnit\Framework\TestCase;

final class ListingParserTest extends TestCase
{
	private ListingParser $parser;

	protected function setUp(): void
	{
		$this->parser = new ListingParser();
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ListingParserProvider::parseUnixListingFullProvider()
	 */
	public function testParseListingUnixFull(string $listing, bool $quick, array $expected): void
	{
		$result = $this->parser->parseListing($listing, $quick);

		$this->assertSame($expected, $result);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ListingParserProvider::parseUnixListingQuickProvider()
	 */
	public function testParseListingUnixQuick(string $listing, bool $quick, array $expected): void
	{
		$result = $this->parser->parseListing($listing, $quick);

		$this->assertSame($expected, $result);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ListingParserProvider::parseMSDOSListingFullProvider()
	 */
	public function testParseListingMSDOSFull(string $listing, bool $quick, array $expected): void
	{
		$result = $this->parser->parseListing($listing, $quick);

		$this->assertSame($expected, $result);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ListingParserProvider::parseMSDOSListingQuickProvider()
	 */
	public function testParseListingMSDOSQuick(string $listing, bool $quick, array $expected): void
	{
		$result = $this->parser->parseListing($listing, $quick);

		$this->assertSame($expected, $result);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ListingParserProvider::emptyOrMalformedProvider()
	 */
	public function testEmptyOrMalformedListingReturnsEmptyArray(string $listing): void
	{
		$result = $this->parser->parseListing($listing);

		$this->assertSame([], $result);
	}

	/**
	 * Test that a UNIX listing with a dot entry does NOT filter it out.
	 * The engine does not explicitly skip . and .. entries; they are parsed as regular entries.
	 */
	public function testDotEntriesAreIncludedInUnixListing(): void
	{
		$listing = "drwxr-xr-x 2 user group 4096 Jan  2 2023 .";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('.', $result[0]['name']);
		$this->assertSame('dir', $result[0]['type']);
	}

	/**
	 * Test that a UNIX listing with a dot-dot entry does NOT filter it out.
	 */
	public function testDotDotEntriesAreIncludedInUnixListing(): void
	{
		$listing = "drwxr-xr-x 2 user group 4096 Jan  2 2023 ..";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('..', $result[0]['name']);
		$this->assertSame('dir', $result[0]['type']);
	}

	/**
	 * Test that a UNIX symlink with spaces around the arrow is parsed correctly.
	 */
	public function testUnixSymlinkWithSpacesAroundArrow(): void
	{
		$listing = "lrwxrwxrwx 1 user group 7 Jan  2 2023 link -> /some/target";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('link', $result[0]['name']);
		$this->assertSame('link', $result[0]['type']);
		$this->assertSame('/some/target', $result[0]['target']);
	}

	/**
	 * Test that an Ubuntu-style executable with trailing asterisk has the asterisk stripped.
	 */
	public function testUnixExecutableWithTrailingAsteriskIsStripped(): void
	{
		$listing = "-rwxr-xr-x 1 user group 1234 Jan  2 2023 script.sh*";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('script.sh', $result[0]['name']);
	}

	/**
	 * Test CRLF line endings are normalized.
	 */
	public function testCRLFLineEndingsAreNormalized(): void
	{
		$listing = "-rw-r--r-- 1 user group 12345 Jan  2 2023 file.txt\r\ndrwxr-xr-x 2 user group 4096 Jan  2 2023 dirname";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(2, $result);
		$this->assertSame('file.txt', $result[0]['name']);
		$this->assertSame('dirname', $result[1]['name']);
	}

	/**
	 * Test MS-DOS SYMLINKD type is parsed as link type.
	 */
	public function testMSDOSSymlinkDIsLink(): void
	{
		$listing = "01-01-2024 15:30 <SYMLINKD> dirname";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('link', $result[0]['type']);
		$this->assertSame('dirname', $result[0]['name']);
	}

	/**
	 * Test MS-DOS SYMLINK type is parsed as link type.
	 */
	public function testMSDOSSymlinkIsLink(): void
	{
		$listing = "01-01-2024 15:30 <SYMLINK> file.txt";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('link', $result[0]['type']);
		$this->assertSame('file.txt', $result[0]['name']);
	}

	/**
	 * Test MS-DOS JUNCTION type is parsed as link type.
	 */
	public function testMSDOSJunctionIsLink(): void
	{
		$listing = "01-01-2024 15:30 <JUNCTION> dirname";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('link', $result[0]['type']);
	}

	/**
	 * Test MS-DOS listing with link target in brackets (AM/PM format).
	 *
	 * Note: The link target parsing in parseMSDOSListing only works when the date uses
	 * an AM/PM suffix. In that case the initial 5-field split groups the name and target
	 * together into the last field, and after AM/PM removal vInfo[3] becomes
	 * 'linkname [target]' which the regex can parse. Without AM/PM the name and target
	 * are split into separate fields (3 and 4) and the target cannot be captured.
	 */
	public function testMSDOSLinkWithTargetInAMPMFormat(): void
	{
		// AM/PM format: initial split gives 5 fields, after AM removal vInfo[3]='linkname [/some/target]'
		$listing = "01-01-2024 12:00 AM <SYMLINKD> linkname [/some/target]";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('link', $result[0]['type']);
		$this->assertSame('linkname', $result[0]['name']);
		$this->assertSame('/some/target', $result[0]['target']);
	}

	/**
	 * Test MS-DOS listing with link target in brackets (24h format) — target is captured.
	 *
	 * Without AM/PM the initial 5-field split places 'linkname' in vInfo[3] and
	 * '[/some/target]' in vInfo[4]. The code checks vInfo[4] when no target was found
	 * in vInfo[3], so the target is correctly extracted.
	 */
	public function testMSDOSLinkWithTargetIn24hFormatTargetIsEmpty(): void
	{
		$listing = "01-01-2024 15:30 <SYMLINKD> linkname [/some/target]";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		$this->assertSame('link', $result[0]['type']);
		$this->assertSame('linkname', $result[0]['name']);
		$this->assertSame('/some/target', $result[0]['target']);
	}

	/**
	 * Test that parseListing uses UNIX parser first, and only falls back to MSDOS if UNIX returns empty.
	 */
	public function testParseListingPrefersUnixOverMSDOS(): void
	{
		// A valid UNIX listing line should return a UNIX-parsed result
		$listing = "-rw-r--r-- 1 user group 12345 Jan  2 2023 file.txt";
		$result  = $this->parser->parseListing($listing);

		$this->assertCount(1, $result);
		// UNIX results have 'user' and 'group' keys; MSDOS also has them but set to '0'
		// The distinguishing feature is the size is a string from UNIX parsing
		$this->assertSame('12345', $result[0]['size']);
		$this->assertSame('user', $result[0]['user']);
		$this->assertSame('group', $result[0]['group']);
	}

	/**
	 * Test ordering preservation with three-entry UNIX listing.
	 */
	public function testMultipleUnixEntriesPreserveOrder(): void
	{
		$listing = implode("\n", [
			"-rw-r--r-- 1 user group 100 Jan  2 2023 aaa.txt",
			"drwxr-xr-x 2 user group 4096 Jan  2 2023 bbb",
			"lrwxrwxrwx 1 user group 7 Jan  2 2023 ccc->target",
		]);
		$result = $this->parser->parseListing($listing);

		$this->assertCount(3, $result);
		$this->assertSame('aaa.txt', $result[0]['name']);
		$this->assertSame('bbb', $result[1]['name']);
		$this->assertSame('ccc', $result[2]['name']);
	}

	/**
	 * Test ordering preservation with three-entry MS-DOS listing.
	 */
	public function testMultipleMSDOSEntriesPreserveOrder(): void
	{
		$listing = implode("\n", [
			"01-01-2024 15:30 100 aaa.txt",
			"01-01-2024 15:30 <DIR> bbb",
			"01-01-2024 15:30 200 ccc.txt",
		]);
		$result = $this->parser->parseListing($listing);

		$this->assertCount(3, $result);
		$this->assertSame('aaa.txt', $result[0]['name']);
		$this->assertSame('bbb', $result[1]['name']);
		$this->assertSame('ccc.txt', $result[2]['name']);
	}
}
