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

use Akeeba\Engine\Driver\Base;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Abstract integration test case for MySQL-compatible database drivers (MySQLi and PDO MySQL).
 *
 * Concrete subclasses implement createDriver() to return the specific driver under test.
 *
 * Connection details come from environment variables set by run-integration-tests.sh:
 *   INTEGRATION_DB_HOST, INTEGRATION_DB_PORT, INTEGRATION_DB_USER,
 *   INTEGRATION_DB_PASSWORD, INTEGRATION_DB_NAME
 *
 * All tests are automatically skipped when INTEGRATION_DB_HOST is not set.
 */
abstract class AbstractMysqlDriverTestCase extends TestCase
{
	/** @var Base|null The driver under test, shared across all tests in this class. */
	protected static $db = null;

	/** @var string|null Non-null when the test class should be skipped entirely. */
	private static $skipReason = null;

	/**
	 * Return a configured, connected driver instance for the test database.
	 *
	 * @param   array  $options  Connection options (host, port, socket, user, password, database, prefix, select).
	 *
	 * @return  Base
	 */
	abstract protected static function createDriver(array $options);

	public static function setUpBeforeClass(): void
	{
		static::$skipReason = null;
		static::$db         = null;

		if (getenv('INTEGRATION_DB_HOST') === false)
		{
			static::$skipReason = 'Set INTEGRATION_DB_HOST to run MySQL driver integration tests.';

			return;
		}

		$options = [
			'host'     => getenv('INTEGRATION_DB_HOST'),
			'port'     => getenv('INTEGRATION_DB_PORT') !== false ? getenv('INTEGRATION_DB_PORT') : '3306',
			'socket'   => '',
			'user'     => getenv('INTEGRATION_DB_USER') !== false ? getenv('INTEGRATION_DB_USER') : 'root',
			'password' => getenv('INTEGRATION_DB_PASSWORD') !== false ? getenv('INTEGRATION_DB_PASSWORD') : '',
			'database' => getenv('INTEGRATION_DB_NAME') !== false ? getenv('INTEGRATION_DB_NAME') : 'akeebatest',
			'prefix'   => 'test_',
			'select'   => true,
		];

		try
		{
			static::$db = static::createDriver($options);
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
			static::$db->setQuery('DROP TABLE IF EXISTS `test_enginetest`')->execute();
			static::$db->setQuery(
				'CREATE TABLE `test_enginetest` (' .
				'  `id` INT NOT NULL AUTO_INCREMENT,' .
				'  `title` VARCHAR(255) NOT NULL DEFAULT \'\',' .
				'  `body` TEXT NULL,' .
				'  PRIMARY KEY (`id`),' .
				'  KEY `idx_title` (`title`)' .
				') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
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
			static::$db->setQuery('DROP TABLE IF EXISTS `test_enginetest`')->execute();
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

		// Start each test with a clean, empty table.
		static::$db->setQuery('TRUNCATE TABLE `test_enginetest`')->execute();
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
		$this->assertSame('mysql', static::$db->getDriverType());
	}

	public function testGetVersion()
	{
		$version = static::$db->getVersion();
		$this->assertIsString($version);
		$this->assertNotEmpty($version);
		// Must look like a version number (possibly prefixed with MariaDB metadata).
		$this->assertMatchesRegularExpression('/\d+\.\d+/', $version);
	}

	// =========================================================================
	// Query result loading
	// =========================================================================

	public function testLoadResult()
	{
		static::$db->setQuery('SELECT 1');
		$this->assertSame('1', static::$db->loadResult());
	}

	public function testLoadAssoc()
	{
		$this->insertRows([['LoadAssoc Title', 'LoadAssoc Body']]);

		static::$db->setQuery("SELECT `id`, `title`, `body` FROM `#__enginetest` WHERE `title` = 'LoadAssoc Title'");
		$row = static::$db->loadAssoc();

		$this->assertIsArray($row);
		$this->assertSame('LoadAssoc Title', $row['title']);
		$this->assertSame('LoadAssoc Body', $row['body']);
	}

	public function testLoadAssocList()
	{
		$this->insertRows([
			['Alpha', 'Body Alpha'],
			['Beta', 'Body Beta'],
			['Gamma', 'Body Gamma'],
		]);

		static::$db->setQuery('SELECT `title` FROM `#__enginetest` ORDER BY `title` ASC');
		$rows = static::$db->loadAssocList();

		$this->assertCount(3, $rows);
		$this->assertSame('Alpha', $rows[0]['title']);
		$this->assertSame('Beta', $rows[1]['title']);
		$this->assertSame('Gamma', $rows[2]['title']);
	}

	public function testLoadAssocListWithKey()
	{
		$this->insertRows([
			['KeyOne', 'Body One'],
			['KeyTwo', 'Body Two'],
		]);

		static::$db->setQuery('SELECT `id`, `title`, `body` FROM `#__enginetest` ORDER BY `title` ASC');
		$rows = static::$db->loadAssocList('title');

		$this->assertArrayHasKey('KeyOne', $rows);
		$this->assertArrayHasKey('KeyTwo', $rows);
		$this->assertSame('Body One', $rows['KeyOne']['body']);
	}

	public function testLoadRow()
	{
		$this->insertRows([['RowTitle', 'RowBody']]);

		static::$db->setQuery('SELECT `title`, `body` FROM `#__enginetest`');
		$row = static::$db->loadRow();

		$this->assertIsArray($row);
		$this->assertSame('RowTitle', $row[0]);
		$this->assertSame('RowBody', $row[1]);
	}

	public function testLoadRowList()
	{
		$this->insertRows([['R1', 'B1'], ['R2', 'B2']]);

		static::$db->setQuery('SELECT `title` FROM `#__enginetest` ORDER BY `title` ASC');
		$rows = static::$db->loadRowList();

		$this->assertCount(2, $rows);
		$this->assertSame('R1', $rows[0][0]);
		$this->assertSame('R2', $rows[1][0]);
	}

	public function testLoadObject()
	{
		$this->insertRows([['ObjTitle', 'ObjBody']]);

		static::$db->setQuery('SELECT `title`, `body` FROM `#__enginetest`');
		$obj = static::$db->loadObject();

		$this->assertIsObject($obj);
		$this->assertSame('ObjTitle', $obj->title);
		$this->assertSame('ObjBody', $obj->body);
	}

	public function testLoadObjectList()
	{
		$this->insertRows([['ObjA', 'BodyA'], ['ObjB', 'BodyB']]);

		static::$db->setQuery('SELECT `title`, `body` FROM `#__enginetest` ORDER BY `title` ASC');
		$objs = static::$db->loadObjectList();

		$this->assertCount(2, $objs);
		$this->assertSame('ObjA', $objs[0]->title);
		$this->assertSame('ObjB', $objs[1]->title);
	}

	public function testLoadColumn()
	{
		$this->insertRows([['ColA', null], ['ColB', null], ['ColC', null]]);

		static::$db->setQuery('SELECT `title` FROM `#__enginetest` ORDER BY `title` ASC');
		$col = static::$db->loadColumn();

		$this->assertSame(['ColA', 'ColB', 'ColC'], $col);
	}

	// =========================================================================
	// Data manipulation
	// =========================================================================

	public function testInsertObject()
	{
		$obj        = new stdClass();
		$obj->title = 'Inserted Title';
		$obj->body  = 'Inserted Body';

		$result = static::$db->insertObject('test_enginetest', $obj, 'id');

		$this->assertTrue($result);
		$this->assertGreaterThan(0, (int) $obj->id, 'insertObject must populate the primary key on the object');
	}

	public function testInsertid()
	{
		static::$db->setQuery(
			"INSERT INTO `test_enginetest` (`title`, `body`) VALUES ('InsertId Test', 'body')"
		)->execute();

		$id = static::$db->insertid();

		$this->assertGreaterThan(0, (int) $id);
	}

	public function testGetAffectedRowsAfterInsert()
	{
		static::$db->setQuery(
			"INSERT INTO `test_enginetest` (`title`, `body`) VALUES ('AffectedA', 'b'), ('AffectedB', 'b')"
		)->execute();

		$this->assertSame(2, (int) static::$db->getAffectedRows());
	}

	public function testUpdateObject()
	{
		$this->insertRows([['Original Title', 'Original Body']]);

		static::$db->setQuery('SELECT `id`, `title`, `body` FROM `test_enginetest` LIMIT 1');
		$obj = static::$db->loadObject();

		$obj->title = 'Updated Title';
		$obj->body  = 'Updated Body';
		$result     = static::$db->updateObject('test_enginetest', $obj, 'id');

		$this->assertTrue((bool) $result);

		static::$db->setQuery("SELECT `title` FROM `test_enginetest` WHERE `id` = {$obj->id}");
		$this->assertSame('Updated Title', static::$db->loadResult());
	}

	// =========================================================================
	// Schema introspection
	// =========================================================================

	public function testGetTableList()
	{
		$tables = static::$db->getTableList();

		$this->assertIsArray($tables);
		$this->assertContains('test_enginetest', $tables);
	}

	public function testGetTableColumns()
	{
		$columns = static::$db->getTableColumns('test_enginetest', true);

		$this->assertIsArray($columns);
		$this->assertArrayHasKey('id', $columns);
		$this->assertArrayHasKey('title', $columns);
		$this->assertArrayHasKey('body', $columns);

		// Types are returned with size info stripped by getTableColumns (e.g. "varchar" not "varchar(255)")
		$this->assertStringContainsStringIgnoringCase('int', $columns['id']);
		$this->assertStringContainsStringIgnoringCase('varchar', $columns['title']);
		$this->assertStringContainsStringIgnoringCase('text', $columns['body']);
	}

	public function testGetTableCreate()
	{
		$result = static::$db->getTableCreate(['test_enginetest']);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('test_enginetest', $result);

		$create = $result['test_enginetest'];
		$this->assertStringContainsStringIgnoringCase('CREATE TABLE', $create);
		$this->assertStringContainsString('test_enginetest', $create);
	}

	public function testGetTableKeys()
	{
		$keys = static::$db->getTableKeys('test_enginetest');

		$this->assertIsArray($keys);
		$this->assertNotEmpty($keys);

		$keyNames = array_column($keys, 'Key_name');
		$this->assertContains('PRIMARY', $keyNames);
		$this->assertContains('idx_title', $keyNames);
	}

	// =========================================================================
	// Transactions
	// =========================================================================

	public function testTransactionCommit()
	{
		static::$db->transactionStart();
		static::$db->setQuery("INSERT INTO `test_enginetest` (`title`) VALUES ('Committed')")->execute();
		static::$db->transactionCommit();

		static::$db->setQuery("SELECT COUNT(*) FROM `test_enginetest` WHERE `title` = 'Committed'");
		$this->assertSame('1', static::$db->loadResult());
	}

	public function testTransactionRollback()
	{
		static::$db->transactionStart();
		static::$db->setQuery("INSERT INTO `test_enginetest` (`title`) VALUES ('RolledBack')")->execute();
		static::$db->transactionRollback();

		static::$db->setQuery("SELECT COUNT(*) FROM `test_enginetest` WHERE `title` = 'RolledBack'");
		$this->assertSame('0', static::$db->loadResult());
	}

	// =========================================================================
	// Escaping and quoting
	// =========================================================================

	public function testEscape()
	{
		$text    = "it's a test";
		$escaped = static::$db->escape($text);

		$this->assertIsString($escaped);
		// MySQL escaping is backslash-based: the apostrophe survives, but is neutered by a leading backslash.
		$this->assertSame("it\\'s a test", $escaped);
	}

	/**
	 * A DOUBLE must survive a round trip through the driver without losing precision.
	 *
	 * The dump engine turns each fetched value into an INSERT statement via quote(). If a driver hands back a native
	 * PHP float instead of a string, quote() casts it back using the `precision` ini setting — 14 significant digits by
	 * default — silently truncating a DOUBLE which needs up to 17. That corrupts the backup, so every driver must
	 * return the value as the string the server sent.
	 */
	public function testDoublePrecisionIsNotLost()
	{
		$literal = '0.12345678901234568';

		static::$db->setQuery('SELECT CAST(' . $literal . ' AS DOUBLE)');
		$value = static::$db->loadResult();

		$this->assertIsString($value, 'The driver must return a DOUBLE as a string, not a native float');
		$this->assertSame($literal, $value);
		$this->assertSame("'" . $literal . "'", static::$db->quote($value));
	}

	public function testEscapeNull()
	{
		$this->assertSame('NULL', static::$db->escape(null));
	}

	public function testEscapeExtra()
	{
		$escaped = static::$db->escape('50% off everything_item', true);

		$this->assertStringContainsString('\%', $escaped);
		$this->assertStringContainsString('\_', $escaped);
	}

	public function testQuote()
	{
		$this->assertSame("'hello'", static::$db->quote('hello'));
	}

	public function testQuoteRoundTrip()
	{
		// Insert a value containing SQL-special characters via quote() and verify it round-trips correctly.
		$original = "it's a \"test\" with 100% special_chars";
		$quoted   = static::$db->quote($original);

		static::$db->setQuery("INSERT INTO `test_enginetest` (`title`) VALUES ({$quoted})")->execute();
		static::$db->setQuery('SELECT `title` FROM `test_enginetest`');
		$retrieved = static::$db->loadResult();

		$this->assertSame($original, $retrieved);
	}

	public function testQuoteName()
	{
		$this->assertSame('`tablename`', static::$db->quoteName('tablename'));
	}

	public function testQuoteNameDotNotation()
	{
		$this->assertSame('`schema`.`tablename`', static::$db->quoteName('schema.tablename'));
	}

	// =========================================================================
	// Prefix replacement
	// =========================================================================

	public function testReplacePrefix()
	{
		$result = static::$db->replacePrefix('SELECT * FROM `#__sometable`');

		$this->assertSame('SELECT * FROM `test_sometable`', $result);
	}

	public function testReplacePrefixPreservesStringLiterals()
	{
		// #__ inside single-quoted literals must NOT be replaced.
		$result = static::$db->replacePrefix("SELECT '#__literal' FROM `#__realtable`");

		$this->assertStringContainsString('#__literal', $result);
		$this->assertStringContainsString('test_realtable', $result);
	}

	// =========================================================================
	// Helper
	// =========================================================================

	/**
	 * Insert fixture rows into the test table using direct (non-abstract) table name.
	 *
	 * @param   array  $rows  Each element is [title, body] where body may be null.
	 */
	private function insertRows(array $rows)
	{
		foreach ($rows as [$title, $body])
		{
			$escapedTitle = static::$db->escape($title);

			if ($body === null)
			{
				$bodyExpr = 'NULL';
			}
			else
			{
				$bodyExpr = "'" . static::$db->escape($body) . "'";
			}

			static::$db->setQuery(
				"INSERT INTO `test_enginetest` (`title`, `body`) VALUES ('{$escapedTitle}', {$bodyExpr})"
			)->execute();
		}
	}
}
