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

namespace Akeeba\Engine\Test\Platform;

defined('AKEEBAENGINE') || define('AKEEBAENGINE', 1);

use Akeeba\Engine\Driver\Sqlite;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform\Base as PlatformBase;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Security regression tests for the SQL built by the abstract Platform layer.
 *
 * These exercise the queries that consume caller-supplied data (backup record IDs, tags, profile IDs and, most
 * importantly, filter/operand/order values) which the consuming Joomla component reaches from its frontend Remote
 * JSON API and API application. The engine is the real SQL boundary, so it must neutralise hostile operands and
 * ORDER BY directions on its own instead of trusting the caller.
 *
 * The tests run against an in-memory SQLite database, so they need no external service. Where a mitigation cannot be
 * demonstrated through SQLite's forgiving semantics (LIKE wildcard escaping depends on the MySQL-family drivers), the
 * generated SQL string is asserted instead.
 */
final class StatisticsSqlSafetyTest extends TestCase
{
	/** @var array The in-memory SQLite connection options */
	private $dbOptions;

	/** @var Sqlite */
	private $db;

	/** @var SqliteAuditPlatform */
	private $platform;

	protected function setUp(): void
	{
		parent::setUp();

		if (!class_exists('\\PDO') || !in_array('sqlite', \PDO::getAvailableDrivers(), true))
		{
			$this->markTestSkipped('The pdo_sqlite extension is required for these tests.');
		}

		$this->dbOptions = [
			'driver'   => 'sqlite',
			'database' => ':memory:',
			'prefix'   => '',
			'host'     => '',
			'user'     => '',
			'password' => '',
		];

		Factory::nuke();

		$this->db = Factory::getDatabase($this->dbOptions);
		$this->createSchemaAndFixtures($this->db);

		$this->platform = new SqliteAuditPlatform($this->dbOptions);
	}

	protected function tearDown(): void
	{
		Factory::unsetDatabase($this->dbOptions);
		Factory::nuke();

		parent::tearDown();
	}

	// ----------------------------------------------------------------------------------------------------------------
	// F2 — filter operand allow-list
	// ----------------------------------------------------------------------------------------------------------------

	public function testKnownOperandStillFilters(): void
	{
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'profile_id', 'operand' => '=', 'value' => 1],
		]);

		$this->assertSame(2, $count, 'A plain equality filter must keep working.');
	}

	public function testMultiCharacterKnownOperandStillFilters(): void
	{
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'id', 'operand' => '>', 'value' => 1],
		]);

		$this->assertSame(2, $count, 'A legitimate multi-character operand must keep working.');
	}

	public function testHostileOperandIsNeutralised(): void
	{
		// Without the allow-list, the operand is concatenated raw and widens the result set to every record.
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'profile_id', 'operand' => '= 1 OR 1=1 -- ', 'value' => 2],
		]);

		$this->assertSame(
			1,
			$count,
			'A hostile operand must collapse to "=" and match only the single profile_id=2 record, not all rows.'
		);
	}

	public function testHostileOperandInListDoesNotWiden(): void
	{
		$list = $this->platform->get_statistics_list([
			'filters' => [
				['field' => 'profile_id', 'operand' => 'BETWEEN 0 AND 999 OR 1=1 -- ', 'value' => 2],
			],
		]);

		$this->assertCount(1, $list, 'The list variant must also neutralise a hostile operand.');
		$this->assertSame(3, (int) $list[0]['id']);
	}

	public function testNormaliseFilterOperandCoercesUnknownToEquals(): void
	{
		$method = new ReflectionMethod(PlatformBase::class, 'normaliseFilterOperand');
		$method->setAccessible(true);

		// Known operands survive (upper-cased, trimmed); everything else collapses to '='.
		$this->assertSame('LIKE', $method->invoke($this->platform, ' like '));
		$this->assertSame('NOT IN', $method->invoke($this->platform, 'not in'));
		$this->assertSame('=', $method->invoke($this->platform, '= 1 OR 1=1 -- '));
		$this->assertSame('=', $method->invoke($this->platform, ') UNION SELECT'));
		$this->assertSame('=', $method->invoke($this->platform, null));
	}

	// ----------------------------------------------------------------------------------------------------------------
	// Item 12 — BETWEEN with a missing value/value2 must not emit a malformed clause
	// ----------------------------------------------------------------------------------------------------------------

	public function testBetweenWithMissingValue2IsSkipped(): void
	{
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'id', 'operand' => 'BETWEEN', 'value' => 1],
		]);

		$this->assertSame(3, $count, 'A BETWEEN filter with no value2 must be skipped, leaving the count unfiltered.');
	}

	public function testBetweenWithMissingValueIsSkipped(): void
	{
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'id', 'operand' => 'BETWEEN', 'value2' => 3],
		]);

		$this->assertSame(3, $count, 'A BETWEEN filter with no value must be skipped, leaving the count unfiltered.');
	}

	// ----------------------------------------------------------------------------------------------------------------
	// Item 13 — a non-scalar value must not reach escape()/quote() and fatal
	// ----------------------------------------------------------------------------------------------------------------

	public function testNonScalarValueWithEqualsOperandIsSkipped(): void
	{
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'profile_id', 'operand' => '=', 'value' => [1, 2]],
		]);

		$this->assertSame(
			3,
			$count,
			'An array value with operand "=" must be skipped rather than passed to quote(), leaving the count unfiltered.'
		);
	}

	public function testNonScalarValueWithInOperandStillWorks(): void
	{
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'profile_id', 'operand' => 'IN', 'value' => [2]],
		]);

		$this->assertSame(1, $count, 'An array value with operand IN must keep working exactly as before.');
	}

	// ----------------------------------------------------------------------------------------------------------------
	// F5 — EMPTY operand builds valid SQL (missing break regression)
	// ----------------------------------------------------------------------------------------------------------------

	public function testEmptyOperandProducesValidSqlAndCorrectCount(): void
	{
		// id 1 has an empty backupid; ids 2 and 3 do not.
		$count = (int) $this->platform->get_statistics_count([
			['field' => 'backupid', 'operand' => 'EMPTY'],
		]);

		$this->assertSame(1, $count, 'EMPTY must build valid SQL and match only the blank/NULL record.');
	}

	// ----------------------------------------------------------------------------------------------------------------
	// F1 — ORDER BY direction whitelist
	// ----------------------------------------------------------------------------------------------------------------

	public function testOrderDirectionIsWhitelisted(): void
	{
		$list = $this->platform->get_statistics_list([
			'order' => ['by' => 'id', 'order' => 'ASC, (SELECT CASE WHEN 1=1 THEN 1 ELSE 1 END)'],
		]);

		$this->assertCount(3, $list, 'A hostile ORDER BY direction must not break the query.');
		$this->assertSame(3, (int) $list[0]['id'], 'An unrecognised direction must fall back to DESC.');
	}

	public function testLegitimateAscendingOrderStillWorks(): void
	{
		$list = $this->platform->get_statistics_list([
			'order' => ['by' => 'id', 'order' => 'asc'],
		]);

		$this->assertSame(1, (int) $list[0]['id'], 'A valid ASC direction must still order ascending.');
	}

	// ----------------------------------------------------------------------------------------------------------------
	// F3 — ORDER BY / filter column whitelist
	// ----------------------------------------------------------------------------------------------------------------

	public function testOrderColumnIsWhitelisted(): void
	{
		$list = $this->platform->get_statistics_list([
			'order' => ['by' => 'id); DROP TABLE ak_stats; --', 'order' => 'DESC'],
		]);

		$this->assertCount(3, $list, 'An unknown ORDER BY column must fall back to a safe default, not error.');

		// The table must still be intact.
		$this->db->setQuery('SELECT COUNT(*) FROM ' . $this->db->qn('ak_stats'));
		$this->assertSame(3, (int) $this->db->loadResult());
	}

	public function testGetValidBackupRecordsOrderingIsWhitelisted(): void
	{
		$ids = $this->platform->get_valid_backup_records(false, [], 'ASC; DROP TABLE ak_stats; --');

		$this->assertIsArray($ids);

		// The table must still be intact after a hostile ordering argument.
		$this->db->setQuery('SELECT COUNT(*) FROM ' . $this->db->qn('ak_stats'));
		$this->assertSame(3, (int) $this->db->loadResult());
	}

	// ----------------------------------------------------------------------------------------------------------------
	// F4 — LIKE wildcard / injection safety
	// ----------------------------------------------------------------------------------------------------------------

	public function testLikeValueSingleQuoteIsEscaped(): void
	{
		// SQLite's escape() does not honour the wildcard flag, but it does escape quotes: prove no breakout.
		$sql = $this->buildFilterSql($this->db, [
			['field' => 'tag', 'operand' => 'LIKE', 'value' => "x' OR '1'='1"],
		]);

		$this->assertStringContainsString("x'' OR ''1''=''1", $sql, 'Quotes in a LIKE value must be escaped.');
		$this->assertStringNotContainsString("x' OR '1'='1", $sql, 'The raw, unescaped payload must not appear.');
	}

	public function testLikeValueWildcardsAreEscapedOnMysqlStyleDriver(): void
	{
		// The MySQL-family drivers escape %/_ when escape($value, true) is used. This proves the Platform layer
		// asks for that escaping; a driver that honours the flag is used to make the effect observable.
		$mysqlLikeDb = new WildcardAwareSqlite($this->dbOptions);
		$this->createSchemaAndFixtures($mysqlLikeDb);

		$sql = $this->buildFilterSql($mysqlLikeDb, [
			['field' => 'tag', 'operand' => 'LIKE', 'value' => 'a_b%c'],
		]);

		$this->assertStringContainsString('a\_b\%c', $sql, 'Caller-supplied LIKE wildcards must be escaped.');

		$mysqlLikeDb->close();
	}

	// ----------------------------------------------------------------------------------------------------------------
	// F6 — record IDs are handled as integers
	// ----------------------------------------------------------------------------------------------------------------

	public function testGetStatisticsReturnsRequestedRecord(): void
	{
		$record = $this->platform->get_statistics(2);

		$this->assertIsArray($record);
		$this->assertSame(2, (int) $record['id']);
	}

	public function testDeleteStatisticsRemovesOnlyTheTargetRecord(): void
	{
		$this->assertTrue($this->platform->delete_statistics(3));

		$this->db->setQuery('SELECT COUNT(*) FROM ' . $this->db->qn('ak_stats'));
		$this->assertSame(2, (int) $this->db->loadResult(), 'Only the targeted record may be deleted.');
	}

	// ----------------------------------------------------------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------------------------------------------------------

	/**
	 * Runs the private applyStatisticsFilters() against a fresh query and returns the generated SQL string.
	 *
	 * @param   Sqlite  $db       The driver to build the query with
	 * @param   array   $filters  The filters to apply
	 *
	 * @return  string
	 */
	private function buildFilterSql($db, array $filters): string
	{
		$method = new ReflectionMethod(PlatformBase::class, 'applyStatisticsFilters');
		$method->setAccessible(true);

		$query = $db->createQuery();
		$query->select('*')->from($db->qn('ak_stats'));

		$method->invoke($this->platform, $db, $query, $filters);

		return (string) $query;
	}

	/**
	 * Creates the ak_stats table and inserts a small, deterministic fixture set.
	 *
	 * Records:
	 *   id 1 — profile_id 1, empty backupid
	 *   id 2 — profile_id 1
	 *   id 3 — profile_id 2
	 *
	 * @param   Sqlite  $db  The driver to create the schema on
	 *
	 * @return  void
	 */
	private function createSchemaAndFixtures($db): void
	{
		$db->setQuery('DROP TABLE IF EXISTS ak_stats');
		$db->query();

		$db->setQuery(
			'CREATE TABLE ak_stats (' .
			'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
			'tag TEXT, backupid TEXT, profile_id INTEGER, status TEXT, origin TEXT, ' .
			'archivename TEXT, absolute_path TEXT, remote_filename TEXT, ' .
			'filesexist INTEGER, multipart INTEGER)'
		);
		$db->query();

		$rows = [
			[1, 'backend', '', 1, 'complete', 'backend', 'a.jpa', '/backups/a.jpa', '', 1, 1],
			[2, 'frontend', 'B2', 1, 'complete', 'frontend', 'b.jpa', '/backups/b.jpa', '', 1, 1],
			[3, 'json', 'B3', 2, 'complete', 'json', 'c.jpa', '/backups/c.jpa', '', 1, 1],
		];

		foreach ($rows as $r)
		{
			$db->setQuery(
				'INSERT INTO ak_stats ' .
				'(id, tag, backupid, profile_id, status, origin, archivename, absolute_path, remote_filename, filesexist, multipart) ' .
				'VALUES (' .
				(int) $r[0] . ', ' . $db->q($r[1]) . ', ' . $db->q($r[2]) . ', ' . (int) $r[3] . ', ' .
				$db->q($r[4]) . ', ' . $db->q($r[5]) . ', ' . $db->q($r[6]) . ', ' . $db->q($r[7]) . ', ' .
				$db->q($r[8]) . ', ' . (int) $r[9] . ', ' . (int) $r[10] . ')'
			);
			$db->query();
		}
	}
}

/**
 * A concrete platform that inherits the real Platform\Base statistics methods under test, bound to an in-memory
 * SQLite database, with un-prefixed table names so SQLite's pragma-based schema introspection works.
 *
 * It deliberately extends Platform\Base (not the TestPlatform stub, which overrides these methods with no-ops).
 */
final class SqliteAuditPlatform extends PlatformBase
{
	/** @var array */
	private $dbOptions;

	public function __construct(array $dbOptions)
	{
		$this->platformName      = 'test';
		$this->dbOptions         = $dbOptions;
		$this->tableNameStats    = 'ak_stats';
		$this->tableNameProfiles = 'ak_profiles';
	}

	public function get_platform_database_options()
	{
		return $this->dbOptions;
	}

	public function get_active_profile()
	{
		return 1;
	}

	public function set_flash_variable($name, $value)
	{
	}

	public function get_flash_variable($name, $default = null)
	{
		return $default;
	}

	public function redirect($url)
	{
	}
}

/**
 * A SQLite driver whose escape() honours the wildcard-escaping flag, mirroring the MySQL-family drivers. Used to make
 * the Platform layer's LIKE wildcard escaping observable in a test (plain SQLite ignores the flag).
 */
final class WildcardAwareSqlite extends Sqlite
{
	public function escape($text, $extra = false)
	{
		$result = parent::escape($text, false);

		if ($extra && is_string($result))
		{
			$result = addcslashes($result, '%_');
		}

		return $result;
	}
}
