<?php

namespace Akeeba\Engine\Test\Stub\Driver;

use Akeeba\Engine\FixMySQLHostname;

class FixMySQLHostnameStub
{
	use FixMySQLHostname;

	public function runFixCode(&$host, &$port, &$socket)
	{
		$this->fixHostnamePortSocket($host, $port, $socket);
	}
}