<?php
namespace Akeeba\Engine\Test\Driver;

use Akeeba\Engine\Test\Stub\Driver\FixMySQLHostnameStub;
use \PHPUnit\Framework\TestCase;

final class FixMySQLHostnameTest extends TestCase
{
	/**
	 * @var FixMySQLHostnameStub
	 */
	private static $testObject;

	public static function setUpBeforeClass(): void
	{
		self::$testObject = new FixMySQLHostnameStub();
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Driver\FixMySQLHostnameProvider::brokenHostnamesProvider()
	 * @dataProvider \Akeeba\Engine\Test\Driver\FixMySQLHostnameProvider::oneTwentySevenHostnamesProvider()
	 * @dataProvider \Akeeba\Engine\Test\Driver\FixMySQLHostnameProvider::happyPathProvider()
	 * @return void
	 */
	public function testCanFixDefinitions(array $input, array $expected): void
	{
		[$host, $port, $socket] = array_values($input);
		self::$testObject->runFixCode($host, $port, $socket);

		$this->assertEquals($expected['host'], $host, 'Hostname mismatch');
		$this->assertEquals($expected['port'], $port, 'Port mismatch');
		$this->assertEquals($expected['socket'], $socket, 'Socket mismatch');
	}
}