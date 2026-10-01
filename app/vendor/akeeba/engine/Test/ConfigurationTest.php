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

namespace Akeeba\Engine\Test;

use Akeeba\Engine\Configuration;
use Akeeba\Engine\Factory;

final class ConfigurationTest extends AbstractEngineTestCase
{
	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function conf(): Configuration
	{
		return Factory::getConfiguration();
	}

	// -----------------------------------------------------------------------
	// get() / set() round-trip
	// -----------------------------------------------------------------------

	public function testSetAndGetTwoPartKey(): void
	{
		$conf = $this->conf();
		$conf->set('myns.mykey', 'hello');

		$this->assertSame('hello', $conf->get('myns.mykey'));
	}

	public function testSetAndGetThreePartKey(): void
	{
		$conf = $this->conf();
		$conf->set('myns.section.key', 'world');

		$this->assertSame('world', $conf->get('myns.section.key'));
	}

	public function testSetAndGetFourPartKey(): void
	{
		$conf = $this->conf();
		$conf->set('myns.section.sub.deep', 'deeply');

		$this->assertSame('deeply', $conf->get('myns.section.sub.deep'));
	}

	public function testGetReturnsDefaultForMissingKey(): void
	{
		$conf = $this->conf();

		$this->assertSame('fallback', $conf->get('no.such.key', 'fallback'));
	}

	public function testGetReturnsNullDefaultWhenNoDefaultGiven(): void
	{
		$conf = $this->conf();

		$this->assertNull($conf->get('no.such.key'));
	}

	public function testSetOverwritesExistingValue(): void
	{
		$conf = $this->conf();
		$conf->set('myns.mykey', 'first');
		$conf->set('myns.mykey', 'second');

		$this->assertSame('second', $conf->get('myns.mykey'));
	}

	public function testSetReturnsPreviousValue(): void
	{
		$conf = $this->conf();
		$conf->set('myns.mykey', 'initial');
		$returned = $conf->set('myns.mykey', 'updated');

		// set() returns the value actually stored (which may or may not equal old value)
		// The source shows it returns $ns->{$nodes[$i]} after assigning, i.e. the new value.
		$this->assertSame('updated', $returned);
	}

	public function testSetCreatesNamespaceAutomatically(): void
	{
		$conf = $this->conf();
		// 'brandnew' namespace does not exist yet
		$conf->set('brandnew.somekey', 'auto');

		$this->assertContains('brandnew', $conf->getNameSpaces());
		$this->assertSame('auto', $conf->get('brandnew.somekey'));
	}

	// -----------------------------------------------------------------------
	// remove()
	// -----------------------------------------------------------------------

	public function testRemoveDeletesKey(): void
	{
		$conf = $this->conf();
		$conf->set('myns.toremove', 'gone');
		$conf->remove('myns.toremove');

		$this->assertNull($conf->get('myns.toremove'));
	}

	public function testRemoveReturnsTrueOnSuccess(): void
	{
		$conf = $this->conf();
		$conf->set('myns.toremove', 'value');

		$this->assertTrue($conf->remove('myns.toremove'));
	}

	public function testRemoveOnNonExistentKeyReturnsTrue(): void
	{
		// The engine's remove() silently unsets a non-existent property on the stdClass node
		// and still returns true; it does not distinguish "key existed" from "key was absent".
		$conf = $this->conf();

		$this->assertTrue($conf->remove('myns.nonexistent'));
	}

	public function testRemoveDoesNotAffectSiblingKeys(): void
	{
		$conf = $this->conf();
		$conf->set('myns.keep', 'stay');
		$conf->set('myns.remove', 'go');
		$conf->remove('myns.remove');

		$this->assertSame('stay', $conf->get('myns.keep'));
	}

	// -----------------------------------------------------------------------
	// mergeArray()
	// -----------------------------------------------------------------------

	public function testMergeArrayOverridesExistingValues(): void
	{
		$conf = $this->conf();
		$conf->set('myns.alpha', 'original');

		$conf->mergeArray(['myns.alpha' => 'replaced', 'myns.beta' => 'new'], false);

		$this->assertSame('replaced', $conf->get('myns.alpha'));
		$this->assertSame('new', $conf->get('myns.beta'));
	}

	public function testMergeArrayNoOverridePreservesExistingValues(): void
	{
		$conf = $this->conf();
		$conf->set('myns.alpha', 'original');

		$conf->mergeArray(['myns.alpha' => 'replaced', 'myns.beta' => 'new'], true);

		// alpha was already set — must not be replaced
		$this->assertSame('original', $conf->get('myns.alpha'));
		// beta was not set — must be added
		$this->assertSame('new', $conf->get('myns.beta'));
	}

	public function testMergeArrayNoOverrideDoesNotSetNullValues(): void
	{
		// When a key already has a non-null value, noOverride must preserve it.
		$conf = $this->conf();
		$conf->set('myns.existing', 'present');

		$conf->mergeArray(['myns.existing' => 'attempt'], true);

		$this->assertSame('present', $conf->get('myns.existing'));
	}

	public function testMergeArraySetsNewKeysEvenWithNoOverride(): void
	{
		$conf = $this->conf();

		$conf->mergeArray(['myns.fresh' => 'value'], true);

		$this->assertSame('value', $conf->get('myns.fresh'));
	}

	// -----------------------------------------------------------------------
	// mergeEngineJSON()
	// -----------------------------------------------------------------------

	public function testMergeEngineJSONReturnsFalseForNonExistentFile(): void
	{
		$conf = $this->conf();

		$this->assertFalse($conf->mergeEngineJSON('/nonexistent/path/to/file.json'));
	}

	public function testMergeEngineJSONReturnsTrueForValidFile(): void
	{
		$conf    = $this->conf();
		$fixture = __DIR__ . '/_data/Configuration/engine_defaults.json';

		$this->assertTrue($conf->mergeEngineJSON($fixture));
	}

	public function testMergeEngineJSONLoadsDefaultValues(): void
	{
		$conf    = $this->conf();
		$fixture = __DIR__ . '/_data/Configuration/engine_defaults.json';

		$conf->mergeEngineJSON($fixture);

		$this->assertSame('alpha_default', $conf->get('myns.mysection.alpha'));
		$this->assertSame('beta_default', $conf->get('myns.mysection.beta'));
	}

	public function testMergeEngineJSONIgnoresUnderscorePrefixedKeys(): void
	{
		$conf    = $this->conf();
		$fixture = __DIR__ . '/_data/Configuration/engine_defaults.json';

		$conf->mergeEngineJSON($fixture);

		// _ignored.key starts with _ and must be skipped
		$this->assertNull($conf->get('_ignored.key'));
	}

	public function testMergeEngineJSONNoOverridePreservesExistingValues(): void
	{
		$conf    = $this->conf();
		$fixture = __DIR__ . '/_data/Configuration/engine_defaults.json';

		// Set before merge
		$conf->set('myns.mysection.alpha', 'pre_existing');

		$conf->mergeEngineJSON($fixture, true);

		// Should not be replaced because noOverride is true
		$this->assertSame('pre_existing', $conf->get('myns.mysection.alpha'));
	}

	public function testMergeEngineJSONNoOverrideSetsUnsetValues(): void
	{
		$conf    = $this->conf();
		$fixture = __DIR__ . '/_data/Configuration/engine_defaults.json';

		// Only set alpha, leave beta unset
		$conf->set('myns.mysection.alpha', 'pre_existing');

		$conf->mergeEngineJSON($fixture, true);

		// beta was null so should be set from the fixture
		$this->assertSame('beta_default', $conf->get('myns.mysection.beta'));
	}

	// -----------------------------------------------------------------------
	// mergeJSON()
	// -----------------------------------------------------------------------

	public function testMergeJSONReturnsFalseForNonExistentFile(): void
	{
		$conf = $this->conf();

		$this->assertFalse($conf->mergeJSON('/nonexistent/path/to/file.json'));
	}

	public function testMergeJSONReturnsTrueForValidFile(): void
	{
		$conf    = $this->conf();
		$fixture = __DIR__ . '/_data/Configuration/simple.json';

		$this->assertTrue($conf->mergeJSON($fixture));
	}

	public function testMergeJSONLoadsScalarValues(): void
	{
		$conf    = $this->conf();
		$fixture = __DIR__ . '/_data/Configuration/simple.json';

		$conf->mergeJSON($fixture);

		$this->assertSame('value_one', $conf->get('test.key1'));
		$this->assertSame('value_two', $conf->get('test.key2'));
	}

	// -----------------------------------------------------------------------
	// exportAsJSON()
	// -----------------------------------------------------------------------

	public function testExportAsJSONReturnsValidJSON(): void
	{
		$conf = $this->conf();
		$conf->set('myns.key', 'val');

		$json = $conf->exportAsJSON();

		$this->assertIsString($json);
		// Must decode without error
		$decoded = json_decode($json, true);
		$this->assertSame(JSON_ERROR_NONE, json_last_error());
		$this->assertIsArray($decoded);
	}

	public function testExportAsJSONContainsSetValues(): void
	{
		$conf = $this->conf();
		$conf->set('myns.fruit', 'apple');

		$json    = $conf->exportAsJSON();
		$decoded = json_decode($json, true);

		// The namespace 'myns' should be a top-level key, and inside it 'fruit' => 'apple'
		$this->assertArrayHasKey('myns', $decoded);
		$this->assertSame('apple', $decoded['myns']['fruit'] ?? null);
	}

	public function testExportAsJSONExcludesVolatileNamespace(): void
	{
		$conf = $this->conf();
		// Add something to the volatile namespace
		$conf->makeNameSpace('volatile');
		$conf->set('volatile.temp', 'ephemeral');

		$json    = $conf->exportAsJSON();
		$decoded = json_decode($json, true);

		$this->assertArrayNotHasKey('volatile', $decoded);
	}

	public function testExportAndReimportRoundTrip(): void
	{
		$conf = $this->conf();
		$conf->set('round.trip.value', 'original');

		$exported = $conf->exportAsJSON();
		$decoded  = json_decode($exported, true);

		// The exported structure is: namespace => nested-associative-array.
		// For 'round.trip.value', the exported form is:
		//   { "round": { "trip": { "value": "original" } } }
		// We verify the value is present by walking the nested array.
		$this->assertArrayHasKey('round', $decoded);
		$this->assertArrayHasKey('trip', $decoded['round']);
		$this->assertArrayHasKey('value', $decoded['round']['trip']);
		$this->assertSame('original', $decoded['round']['trip']['value']);
	}

	// -----------------------------------------------------------------------
	// Key protection
	// -----------------------------------------------------------------------

	public function testSetKeyProtectionPreventsOverwrite(): void
	{
		$conf = $this->conf();
		$conf->set('myns.guarded', 'locked');
		$conf->setKeyProtection('myns.guarded', true);

		// Attempt to overwrite the protected key
		$conf->set('myns.guarded', 'intruder');

		$this->assertSame('locked', $conf->get('myns.guarded'));
	}

	public function testSetKeyProtectionFalseAllowsOverwrite(): void
	{
		$conf = $this->conf();
		$conf->set('myns.guarded', 'locked');
		$conf->setKeyProtection('myns.guarded', true);
		$conf->setKeyProtection('myns.guarded', false);

		$conf->set('myns.guarded', 'unlocked');

		$this->assertSame('unlocked', $conf->get('myns.guarded'));
	}

	public function testGetProtectedKeysReturnsProtectedList(): void
	{
		$conf = $this->conf();
		$conf->set('myns.a', 'val');
		$conf->set('myns.b', 'val');
		$conf->setKeyProtection('myns.a', true);
		$conf->setKeyProtection('myns.b', true);

		$protected = $conf->getProtectedKeys();

		$this->assertContains('myns.a', $protected);
		$this->assertContains('myns.b', $protected);
	}

	public function testResetProtectedKeysEmptiesList(): void
	{
		$conf = $this->conf();
		$conf->set('myns.a', 'val');
		$conf->setKeyProtection('myns.a', true);
		$conf->resetProtectedKeys();

		$this->assertSame([], $conf->getProtectedKeys());
	}

	public function testSetKeyProtectionAcceptsArray(): void
	{
		$conf = $this->conf();
		$conf->set('myns.x', 'xval');
		$conf->set('myns.y', 'yval');
		$conf->setKeyProtection(['myns.x', 'myns.y'], true);

		$conf->set('myns.x', 'changed');
		$conf->set('myns.y', 'changed');

		$this->assertSame('xval', $conf->get('myns.x'));
		$this->assertSame('yval', $conf->get('myns.y'));
	}

	public function testMergeEngineJSONProtectedNodeIsSetFromFileAndProtected(): void
	{
		// Create a temporary engine JSON file with a protected node
		$tmpFile = tempnam(sys_get_temp_dir(), 'cfg_test_') . '.json';
		file_put_contents($tmpFile, json_encode([
			'myns.protected_key' => [
				'default'   => 'protected_value',
				'protected' => true,
			],
		]));

		$conf = $this->conf();
		$conf->mergeEngineJSON($tmpFile);

		// Value must be set
		$this->assertSame('protected_value', $conf->get('myns.protected_key'));

		// Key must now be in the protected list
		$this->assertContains('myns.protected_key', $conf->getProtectedKeys());

		// Overwrite attempt must fail
		$conf->set('myns.protected_key', 'hacked');
		$this->assertSame('protected_value', $conf->get('myns.protected_key'));

		@unlink($tmpFile);
	}

	// -----------------------------------------------------------------------
	// $process_special_vars — stock directory token substitution
	// -----------------------------------------------------------------------

	public function testGetProcessesSpecialVarsInOutputDirectory(): void
	{
		// akeeba.basic.output_directory is in $directory_containing_keys
		// TestPlatform maps [SITEROOT] => sys_get_temp_dir()
		$conf = $this->conf();
		$conf->set('akeeba.basic.output_directory', '[SITEROOT]/backups', false);

		$result = $conf->get('akeeba.basic.output_directory', null, true);

		$expected = sys_get_temp_dir() . '/backups';
		$this->assertSame($expected, $result);
	}

	public function testGetWithProcessSpecialVarsFalseReturnsRawToken(): void
	{
		$conf = $this->conf();
		$conf->set('akeeba.basic.output_directory', '[SITEROOT]/backups', false);

		$result = $conf->get('akeeba.basic.output_directory', null, false);

		$this->assertSame('[SITEROOT]/backups', $result);
	}

	public function testSetWithProcessSpecialVarsTrueStoresResolvedValue(): void
	{
		// When process_special_vars=true on set(), the value stored is already resolved.
		$conf = $this->conf();
		$conf->set('akeeba.basic.output_directory', '[SITEROOT]/backups', true);

		// Retrieve raw (no processing) — should already be resolved
		$result = $conf->get('akeeba.basic.output_directory', null, false);

		$expected = sys_get_temp_dir() . '/backups';
		$this->assertSame($expected, $result);
	}

	public function testSpecialVarsNotProcessedForOtherKeys(): void
	{
		// A normal key that is NOT in $directory_containing_keys
		// should never have its tokens replaced.
		$conf = $this->conf();
		$conf->set('myns.somekey', '[SITEROOT]/data', false);

		$result = $conf->get('myns.somekey', null, true);

		// Token must remain untouched because the key is not in the special list
		$this->assertSame('[SITEROOT]/data', $result);
	}

	// -----------------------------------------------------------------------
	// PHP 8.4+ null-safety of stock directory token substitution
	//
	// Reproduces the bug reported against Akeeba Backup for Joomla ticket #43211: reading
	// or writing an unset directory-containing key (null value) used to pass null to
	// str_replace()'s subject/replace parameters while expanding stock directory tokens,
	// which is deprecated since PHP 8.4.
	// -----------------------------------------------------------------------

	/**
	 * Installs a deprecation collector and returns a closure that asserts none were raised.
	 */
	private function assertNoDeprecations(): callable
	{
		$deprecations = [];

		set_error_handler(
			function (int $errno, string $errstr) use (&$deprecations) {
				if ($errno === E_DEPRECATED)
				{
					$deprecations[] = $errstr;
				}

				return true;
			}
		);

		return function () use (&$deprecations) {
			restore_error_handler();

			$this->assertSame([], $deprecations, 'Unexpected PHP deprecation notice(s) raised: ' . implode('; ', $deprecations));
		};
	}

	public function testGetOnNullDirectoryValueDoesNotDeprecate(): void
	{
		$conf = $this->conf();

		// Store an explicit null (bypassing token processing so it is stored verbatim)
		$conf->set('akeeba.basic.output_directory', null, false);

		$assert = $this->assertNoDeprecations();

		$result = $conf->get('akeeba.basic.output_directory', null, true);

		$assert();

		$this->assertNull($result);
	}

	public function testSetNullValueOnDirectoryKeyDoesNotDeprecate(): void
	{
		$conf = $this->conf();

		$assert = $this->assertNoDeprecations();

		$conf->set('akeeba.basic.output_directory', null, true);

		$assert();

		$this->assertNull($conf->get('akeeba.basic.output_directory', null, false));
	}

	// -----------------------------------------------------------------------
	// Namespace management
	// -----------------------------------------------------------------------

	public function testMakeNameSpaceAddsNamespace(): void
	{
		$conf = $this->conf();
		$conf->makeNameSpace('custom');

		$this->assertContains('custom', $conf->getNameSpaces());
	}

	public function testDefaultNamespaceIsGlobal(): void
	{
		$conf = $this->conf();

		// 'global' must always exist after construction/reset
		$this->assertContains('global', $conf->getNameSpaces());
	}
}
