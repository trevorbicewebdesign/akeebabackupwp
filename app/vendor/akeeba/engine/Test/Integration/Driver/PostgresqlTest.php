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

namespace Akeeba\Engine\Test\Integration\Driver;

use Akeeba\Engine\Driver\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the PostgreSQL database driver.
 *
 * Unlike the MySQL drivers, PDO's PostgreSQL driver hands back native PHP types (int, float, bool) and there is no way
 * to ask it for the server's original wire text — ATTR_STRINGIFY_FETCHES parses the value into a double first and only
 * then stringifies it, so it truncates rather than preserves. The driver therefore keeps the native types and takes
 * care of rendering them losslessly in escape(); testDoublePrecisionIsNotLost() is what pins that down.
 *
 * Run via ./run-integration-tests.sh, or manually against a server you manage yourself:
 *   INTEGRATION_PGSQL_HOST=127.0.0.1 INTEGRATION_PGSQL_PORT=15432 \
 *   INTEGRATION_PGSQL_USER=postgres INTEGRATION_PGSQL_PASSWORD=akeebatest \
 *   INTEGRATION_PGSQL_NAME=akeebatest \
 *   vendor/bin/phpunit Test/Integration/Driver/PostgresqlTest.php
 */
final class PostgresqlTest extends TestCase
{
	/** @var Postgresql|null The driver under test, shared across all tests in this class. */
	protected static $db = null;

	/** @var string|null Non-null when the test class should be skipped entirely. */
	private static $skipReason = null;

	public static function setUpBeforeClass(): void
	{
		static::$skipReason = null;
		static::$db         = null;

		if (getenv('INTEGRATION_PGSQL_HOST') === false)
		{
			static::$skipReason = 'Set INTEGRATION_PGSQL_HOST to run the PostgreSQL driver integration tests.';

			return;
		}

		if (!Postgresql::isSupported())
		{
			static::$skipReason = 'PDO with the PostgreSQL driver is not available.';

			return;
		}

		$options = [
			'host'     => getenv('INTEGRATION_PGSQL_HOST'),
			'port'     => getenv('INTEGRATION_PGSQL_PORT') !== false ? getenv('INTEGRATION_PGSQL_PORT') : '5432',
			'user'     => getenv('INTEGRATION_PGSQL_USER') !== false ? getenv('INTEGRATION_PGSQL_USER') : 'postgres',
			'password' => getenv('INTEGRATION_PGSQL_PASSWORD') !== false ? getenv('INTEGRATION_PGSQL_PASSWORD') : '',
			'database' => getenv('INTEGRATION_PGSQL_NAME') !== false ? getenv('INTEGRATION_PGSQL_NAME') : 'akeebatest',
			'prefix'   => 'test_',
			'select'   => true,
		];

		try
		{
			static::$db = new Postgresql($options);
		}
		catch (\Throwable $e)
		{
			static::$skipReason = 'Cannot instantiate driver: ' . $e->getMessage();

			return;
		}

		if (!static::$db->connected())
		{
			static::$skipReason = 'Cannot connect to test database: ' . static::$db->getErrorMsg();
			static::$db         = null;

			return;
		}

		try
		{
			static::$db->setQuery('DROP TABLE IF EXISTS test_enginetest')->execute();
			static::$db->setQuery(
				'CREATE TABLE test_enginetest (' .
				'  id SERIAL PRIMARY KEY,' .
				'  title VARCHAR(255) NOT NULL DEFAULT \'\',' .
				'  body TEXT NULL' .
				')'
			)->execute();
		}
		catch (\Throwable $e)
		{
			static::$skipReason = 'Cannot create test table: ' . $e->getMessage();
			static::$db         = null;
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (static::$db === null)
		{
			return;
		}

		try
		{
			static::$db->setQuery('DROP TABLE IF EXISTS test_enginetest')->execute();
		}
		catch (\Throwable $e)
		{
		}

		static::$db->close();
		static::$db = null;
	}

	protected function setUp(): void
	{
		if (static::$skipReason !== null)
		{
			$this->markTestSkipped(static::$skipReason);
		}

		// Start each test with a clean, empty table and the id sequence rewound.
		static::$db->setQuery('TRUNCATE TABLE test_enginetest RESTART IDENTITY')->execute();
	}

	/**
	 * Insert the given [title, body] pairs into the test table.
	 *
	 * @param   array  $rows  Array of [title, body] pairs.
	 */
	protected function insertRows(array $rows)
	{
		foreach ($rows as $row)
		{
			static::$db->setQuery(
				'INSERT INTO test_enginetest (title, body) VALUES (' .
				static::$db->quote($row[0]) . ', ' . static::$db->quote($row[1]) . ')'
			)->execute();
		}
	}

	// =========================================================================
	// Connection
	// =========================================================================

	public function testConnected()
	{
		$this->assertTrue(static::$db->connected());
	}

	public function testDriverType()
	{
		$this->assertSame('postgresql', static::$db->getDriverType());
	}

	public function testGetVersion()
	{
		$version = static::$db->getVersion();

		$this->assertIsString($version);
		$this->assertMatchesRegularExpression('/\d+\.\d+/', $version);
	}

	// =========================================================================
	// Query result loading
	// =========================================================================

	public function testLoadResult()
	{
		static::$db->setQuery('SELECT 1');

		// PDO PostgreSQL returns native types; escape() is what makes them safe for the dump.
		$this->assertEquals(1, static::$db->loadResult());
	}

	public function testLoadAssoc()
	{
		$this->insertRows([['LoadAssoc Title', 'LoadAssoc Body']]);

		static::$db->setQuery("SELECT id, title, body FROM #__enginetest WHERE title = 'LoadAssoc Title'");
		$row = static::$db->loadAssoc();

		$this->assertIsArray($row);
		$this->assertSame('LoadAssoc Title', $row['title']);
		$this->assertSame('LoadAssoc Body', $row['body']);
	}

	public function testLoadAssocList()
	{
		$this->insertRows([['Alpha', 'A'], ['Beta', 'B']]);

		static::$db->setQuery('SELECT title, body FROM #__enginetest ORDER BY title');
		$rows = static::$db->loadAssocList();

		$this->assertCount(2, $rows);
		$this->assertSame('Alpha', $rows[0]['title']);
		$this->assertSame('Beta', $rows[1]['title']);
	}

	public function testLoadRow()
	{
		$this->insertRows([['LoadRow Title', 'LoadRow Body']]);

		static::$db->setQuery('SELECT title, body FROM #__enginetest');
		$row = static::$db->loadRow();

		$this->assertSame(['LoadRow Title', 'LoadRow Body'], $row);
	}

	public function testLoadObject()
	{
		$this->insertRows([['LoadObject Title', 'LoadObject Body']]);

		static::$db->setQuery('SELECT title, body FROM #__enginetest');
		$row = static::$db->loadObject();

		$this->assertIsObject($row);
		$this->assertSame('LoadObject Title', $row->title);
	}

	public function testLoadObjectList()
	{
		$this->insertRows([['Alpha', 'A'], ['Beta', 'B']]);

		static::$db->setQuery('SELECT title FROM #__enginetest ORDER BY title');
		$rows = static::$db->loadObjectList();

		$this->assertCount(2, $rows);
		$this->assertSame('Alpha', $rows[0]->title);
	}

	public function testLoadColumn()
	{
		$this->insertRows([['Alpha', 'A'], ['Beta', 'B']]);

		static::$db->setQuery('SELECT title FROM #__enginetest ORDER BY title');

		$this->assertSame(['Alpha', 'Beta'], static::$db->loadColumn());
	}

	// =========================================================================
	// Writing
	// =========================================================================

	public function testInsertid()
	{
		$this->insertRows([['Insertid', 'Body']]);

		$this->assertEquals(1, static::$db->insertid());
	}

	public function testGetAffectedRowsAfterInsert()
	{
		$this->insertRows([['Affected', 'Body']]);

		$this->assertSame(1, static::$db->getAffectedRows());
	}

	// =========================================================================
	// Schema introspection
	// =========================================================================

	public function testGetTableList()
	{
		$this->assertContains('test_enginetest', static::$db->getTableList());
	}

	public function testGetTableColumns()
	{
		$columns = static::$db->getTableColumns('#__enginetest');

		$this->assertIsArray($columns);
		$this->assertArrayHasKey('id', $columns);
		$this->assertArrayHasKey('title', $columns);
		$this->assertArrayHasKey('body', $columns);
	}

	// =========================================================================
	// Transactions
	// =========================================================================

	public function testTransactionCommit()
	{
		static::$db->transactionStart();
		static::$db->setQuery("INSERT INTO test_enginetest (title) VALUES ('Committed')")->execute();
		static::$db->transactionCommit();

		static::$db->setQuery("SELECT COUNT(*) FROM test_enginetest WHERE title = 'Committed'");

		$this->assertEquals(1, static::$db->loadResult());
	}

	public function testTransactionRollback()
	{
		static::$db->transactionStart();
		static::$db->setQuery("INSERT INTO test_enginetest (title) VALUES ('RolledBack')")->execute();
		static::$db->transactionRollback();

		static::$db->setQuery("SELECT COUNT(*) FROM test_enginetest WHERE title = 'RolledBack'");

		$this->assertEquals(0, static::$db->loadResult());
	}

	// =========================================================================
	// Escaping and quoting
	// =========================================================================

	public function testEscape()
	{
		// PostgreSQL escaping is SQL-standard: the apostrophe is doubled, not backslash-prefixed as in MySQL.
		$this->assertSame("it''s a test", static::$db->escape("it's a test"));
	}

	public function testQuoteName()
	{
		$this->assertSame('"foo"', static::$db->quoteName('foo'));
	}

	public function testReplacePrefix()
	{
		$this->assertSame(
			'SELECT * FROM test_enginetest',
			static::$db->replacePrefix('SELECT * FROM #__enginetest')
		);
	}

	/**
	 * A DOUBLE PRECISION must survive a round trip through the driver without losing precision.
	 *
	 * The dump engine turns each fetched value into an INSERT statement via quote(). PDO PostgreSQL hands back a native
	 * PHP float, and quoting it with an ordinary string cast would run it through the `precision` ini setting — 14
	 * significant digits by default — silently truncating a double which needs up to 17. That corrupts the backup.
	 */
	public function testDoublePrecisionIsNotLost()
	{
		$literal = '0.12345678901234568';

		static::$db->setQuery('SELECT CAST(' . $literal . ' AS DOUBLE PRECISION)');
		$value = static::$db->loadResult();

		$this->assertSame($literal, (string) static::$db->escape($value));
		$this->assertSame("'" . $literal . "'", static::$db->quote($value));
	}

	/**
	 * The same, end to end: a DOUBLE PRECISION written out as an INSERT statement and read back must be bit-identical.
	 */
	public function testDoubleSurvivesDumpRoundTrip()
	{
		static::$db->setQuery('DROP TABLE IF EXISTS test_doubles')->execute();
		static::$db->setQuery('CREATE TABLE test_doubles (d DOUBLE PRECISION)')->execute();

		try
		{
			static::$db->setQuery('INSERT INTO test_doubles (d) VALUES (0.12345678901234568)')->execute();

			// Read the value back the way the dump engine does, and rebuild the INSERT statement from it.
			static::$db->setQuery('SELECT d FROM test_doubles');
			$original = static::$db->loadResult();

			static::$db->setQuery('DELETE FROM test_doubles')->execute();
			static::$db->setQuery('INSERT INTO test_doubles (d) VALUES (' . static::$db->quote($original) . ')')
				->execute();

			static::$db->setQuery('SELECT d FROM test_doubles');
			$restored = static::$db->loadResult();

			$this->assertSame(
				(float) $original, (float) $restored,
				'The restored DOUBLE PRECISION differs from the value the dump read'
			);
		}
		finally
		{
			static::$db->setQuery('DROP TABLE IF EXISTS test_doubles')->execute();
		}
	}
}
