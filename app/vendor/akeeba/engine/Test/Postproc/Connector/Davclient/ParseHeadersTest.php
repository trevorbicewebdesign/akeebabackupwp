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

namespace Akeeba\Engine\Test\Postproc\Connector\Davclient;

use Akeeba\Engine\Postproc\Connector\Davclient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for Davclient header parsing.
 *
 * The parser is exercised directly (via reflection) because driving it through the public request()/options() methods
 * would require a live cURL transport and a fully bootstrapped engine platform (for Factory::getLog()), neither of which
 * is appropriate for a unit test. parseHeaders() is the unit actually under repair, so we pin its behaviour here.
 */
final class ParseHeadersTest extends TestCase
{
	/**
	 * A response may legitimately repeat a header. Apache mod_dav, for instance, advertises its WebDAV capabilities
	 * across several `DAV:` headers. The parser used to keep only the last occurrence, so options() lost the DAV classes
	 * (`1, 2`) and reported only the final Apache propset header — breaking capability detection. Repeated headers must
	 * now be folded into one comma-joined value (RFC 7230 §3.2.2), preserving every advertised class.
	 */
	public function testRepeatedHeadersAreFoldedNotOverwritten(): void
	{
		$raw = "HTTP/1.1 200 OK\r\n"
			. "DAV: 1,2\r\n"
			. "DAV: <http://apache.org/dav/propset/fs/1>\r\n"
			. "Allow: OPTIONS,GET\r\n";

		$headers = $this->parse($raw);

		$this->assertSame(
			'1,2, <http://apache.org/dav/propset/fs/1>', $headers['dav'],
			'Repeated DAV headers must be comma-folded into a single value, not overwritten by the last one.'
		);

		// This is exactly how Davclient::options() splits the folded value: every advertised class must survive.
		$features = array_map('trim', explode(',', $headers['dav']));

		$this->assertContains('1', $features, 'DAV class 1 must survive header folding.');
		$this->assertContains('2', $features, 'DAV class 2 must survive header folding.');
	}

	/**
	 * Folding is case-insensitive on the header name: `DAV` and `Dav` are the same header and must combine under the one
	 * lower-cased key, never producing two separate entries.
	 */
	public function testRepeatedHeadersFoldRegardlessOfNameCase(): void
	{
		$raw = "HTTP/1.1 200 OK\r\n"
			. "DAV: 1\r\n"
			. "Dav: 2\r\n";

		$headers = $this->parse($raw);

		$this->assertArrayHasKey('dav', $headers);
		$this->assertSame('1, 2', $headers['dav']);
	}

	/**
	 * The common case — every header appears once — must keep parsing exactly as before: one lower-cased key per header,
	 * trimmed value. (Guards against the folding change regressing single-occurrence headers.)
	 */
	public function testSingleOccurrenceHeadersStillParseNormally(): void
	{
		$raw = "HTTP/1.1 200 OK\r\n"
			. "Content-Type: application/xml\r\n"
			. "Content-Length: 1234\r\n";

		$headers = $this->parse($raw);

		$this->assertSame('application/xml', $headers['content-type']);
		$this->assertSame('1234', $headers['content-length']);
	}

	/**
	 * When a redirect or 100-Continue produces several header blocks (separated by a blank line), only the last block is
	 * parsed — the parser's documented behaviour, which folding must not disturb.
	 */
	public function testOnlyTheLastHeaderBlockIsParsed(): void
	{
		$raw = "HTTP/1.1 100 Continue\r\n"
			. "DAV: should-be-ignored\r\n"
			. "\r\n"
			. "HTTP/1.1 200 OK\r\n"
			. "DAV: 1,2\r\n";

		$headers = $this->parse($raw);

		$this->assertSame('1,2', $headers['dav'], 'Only the final header block should be parsed.');
	}

	/**
	 * Drive Davclient's protected parseHeaders() over a raw header blob and return the resulting associative array.
	 *
	 * @param   string  $rawHeaders  The raw header blob, as the cURL header callback would have accumulated it.
	 *
	 * @return  array  The parsed, lower-cased header map.
	 */
	private function parse(string $rawHeaders): array
	{
		$client     = new Davclient(['baseUri' => 'http://example.com/']);
		$reflection = new ReflectionClass($client);

		$headersProp = $reflection->getProperty('headers');
		$parse       = $reflection->getMethod('parseHeaders');

		// setAccessible() is required on PHP < 8.1 to reach protected members, but is a deprecated no-op from 8.1 on.
		if (PHP_VERSION_ID < 80100)
		{
			$headersProp->setAccessible(true);
			$parse->setAccessible(true);
		}

		$headersProp->setValue($client, $rawHeaders);
		$parse->invoke($client);

		return $headersProp->getValue($client);
	}
}
