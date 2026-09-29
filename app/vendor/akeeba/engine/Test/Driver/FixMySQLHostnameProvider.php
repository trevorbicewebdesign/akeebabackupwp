<?php

namespace Akeeba\Engine\Test\Driver;

final class FixMySQLHostnameProvider
{
	public static function brokenHostnamesProvider(): array
	{
		return [
			'Bare socket as hostname (*NIX)' => [
				'input'    => [
					'host'   => '/var/mysql/mysql.socket',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'Bare socket as hostname (*NIX), with port' => [
				'input'    => [
					'host'   => '/var/mysql/mysql.socket',
					'port'   => '1234',
					'socket' => '',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'Bare Windows named pipe as hostname' => [
				'input'    => [
					'host'   => '\\\\.\\MySQL',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '.',
					'port'   => null,
					'socket' => '(\\\\.\\MySQL)',
				],
			],
			'Windows named pipe in parentheses as hostname' => [
				'input'    => [
					'host'   => '(\\\\.\\MySQL)',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '.',
					'port'   => null,
					'socket' => '(\\\\.\\MySQL)',
				],
			],
			'Colon *NIX socket as hostname' => [
				'input'    => [
					'host'   => ':/var/mysql/mysql.sock',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.sock',
				],
			],
			'Unix socket as hostname' => [
				'input'    => [
					'host'   => 'unix:/var/mysql/mysql.socket',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'Unix socket as hostname, with port' => [
				'input'    => [
					'host'   => 'unix:/var/mysql/mysql.socket',
					'port'   => '1234',
					'socket' => '',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'Bare port as hostname' => [
				'input'    => [
					'host'   => ':1234',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '127.0.0.1',
					'port'   => 1234,
					'socket' => null,
				],
			],
			'IPv4 with inline port' => [
				'input'    => [
					'host'   => '1.2.3.4:1234',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '1.2.3.4',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'Square brackets IPv6 with inline port' => [
				'input'    => [
					'host'   => '[fe80:102::2%eth1]:1234',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '[fe80:102::2%eth1]',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'Named host with inline port' => [
				'input'    => [
					'host'   => 'mysql.example.com:1234',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => 'mysql.example.com',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'localhost with inline port' => [
				'input'    => [
					'host'   => 'localhost:1234',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '127.0.0.1',
					'port'   => '1234',
					'socket' => '',
				],
			],
		];
	}

	public static function oneTwentySevenHostnamesProvider(): array
	{
		return [
			'127.0.0.1 without port or socket' => [
				'input'    => [
					'host'   => '127.0.0.1',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '127.0.0.1',
					'port'   => '3306',
					'socket' => '',
				],
			],
			'127.0.0.1 with port' => [
				'input'    => [
					'host'   => '127.0.0.1',
					'port'   => '1234',
					'socket' => '',
				],
				'expected' => [
					'host'   => '127.0.0.1',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'Persistent 127.0.0.1 with port' => [
				'input'    => [
					'host'   => 'p:127.0.0.1',
					'port'   => '1234',
					'socket' => '',
				],
				'expected' => [
					'host'   => 'p:127.0.0.1',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'127.0.0.1 with socket' => [
				'input'    => [
					'host'   => '127.0.0.1',
					'port'   => '',
					'socket' => '/var/mysql/mysql.socket',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'127.0.0.1 with socket and port' => [
				'input'    => [
					'host'   => '127.0.0.1',
					'port'   => '1234',
					'socket' => '/var/mysql/mysql.socket',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'127.0.0.1 with inline socket' => [
				'input'    => [
					'host'   => '127.0.0.1:/var/mysql/mysql.socket',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'127.0.0.1 with inline port' => [
				'input'    => [
					'host'   => '127.0.0.1:1234',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '127.0.0.1',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'Persistent 127.0.0.1 with socket and port' => [
				'input'    => [
					'host'   => 'p:127.0.0.1',
					'port'   => '1234',
					'socket' => '/var/mysql/mysql.socket',
				],
				'expected' => [
					'host'   => null,
					'port'   => null,
					'socket' => '/var/mysql/mysql.socket',
				],
			],
			'Persistent 127.0.0.1 with inline port' => [
				'input'    => [
					'host'   => 'p:127.0.0.1:1234',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => 'p:127.0.0.1',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'Persistent 127.0.0.1 with inline socket' => [
				'input'    => [
					'host'   => 'p:127.0.0.1:/var/mysql/mysql.socket',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => null,
					'port'   => '',
					'socket' => '/var/mysql/mysql.socket',
				],
			],
		];
	}

	public static function happyPathProvider(): array
	{
		return [
			'localhost' => [
				'input'    => [
					'host'   => 'localhost',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => 'localhost',
					'port'   => '',
					'socket' => '',
				],
			],
			'IPv4 without port' => [
				'input'    => [
					'host'   => '1.2.3.4',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '1.2.3.4',
					'port'   => '3306',
					'socket' => '',
				],
			],
			'IPv4 with port' => [
				'input'    => [
					'host'   => '1.2.3.4',
					'port'   => '1234',
					'socket' => '',
				],
				'expected' => [
					'host'   => '1.2.3.4',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'IPv6 without port' => [
				'input'    => [
					'host'   => '2345:0425:2CA1:0000:0000:0567:5673:23B5',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '2345:0425:2CA1:0000:0000:0567:5673:23B5',
					'port'   => '3306',
					'socket' => '',
				],
			],
			'IPv6 with port' => [
				'input'    => [
					'host'   => '2345:0425:2CA1:0000:0000:0567:5673:23B5',
					'port'   => '1234',
					'socket' => '',
				],
				'expected' => [
					'host'   => '2345:0425:2CA1:0000:0000:0567:5673:23B5',
					'port'   => '1234',
					'socket' => '',
				],
			],
			'Square brackets IPv6 without port' => [
				'input'    => [
					'host'   => '[fe80:102::2%eth1]',
					'port'   => '',
					'socket' => '',
				],
				'expected' => [
					'host'   => '[fe80:102::2%eth1]',
					'port'   => '3306',
					'socket' => '',
				],
			],
			'Square brackets IPv6 with port' => [
				'input'    => [
					'host'   => '[fe80:102::2%eth1]',
					'port'   => '1234',
					'socket' => '',
				],
				'expected' => [
					'host'   => '[fe80:102::2%eth1]',
					'port'   => '1234',
					'socket' => '',
				],
			],
		];
	}
}