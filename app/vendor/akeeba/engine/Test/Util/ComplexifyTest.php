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

use Akeeba\Engine\Util\Complexify;
use PHPUnit\Framework\TestCase;

final class ComplexifyTest extends TestCase
{
	// -------------------------------------------------------------------------
	// Return shape
	// -------------------------------------------------------------------------

	public function testEvaluateSecurityReturnsObject(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('SomePassword1!');

		$this->assertIsObject($res);
	}

	public function testEvaluateSecurityHasValidProperty(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('SomePassword1!');

		$this->assertObjectHasProperty('valid', $res);
		$this->assertIsBool($res->valid);
	}

	public function testEvaluateSecurityHasComplexityProperty(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('SomePassword1!');

		$this->assertObjectHasProperty('complexity', $res);
		$this->assertIsFloat($res->complexity);
	}

	public function testEvaluateSecurityHasErrorsProperty(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('SomePassword1!');

		$this->assertObjectHasProperty('errors', $res);
		$this->assertIsArray($res->errors);
	}

	// -------------------------------------------------------------------------
	// Empty string
	// -------------------------------------------------------------------------

	public function testEmptyStringHandledWithoutError(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('');

		$this->assertIsObject($res);
		$this->assertFalse($res->valid, 'Empty string must not be considered valid');
	}

	public function testEmptyStringComplexityIsAtFloor(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('');

		// complexity is scaled to a percentage; empty string should be very low (≤ 0 due to log(0^0))
		$this->assertLessThanOrEqual(0, $res->complexity);
	}

	public function testEmptyStringHasTooshortError(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('');

		$this->assertContains('tooshort', $res->errors);
	}

	// -------------------------------------------------------------------------
	// Weak passwords
	// -------------------------------------------------------------------------

	public function testWeakPasswordIsInvalid(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('aaa');

		$this->assertFalse($res->valid);
	}

	public function testWeakPasswordHasLowComplexity(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('aaa');

		$this->assertLessThan(50, $res->complexity);
	}

	// -------------------------------------------------------------------------
	// Strong password
	// -------------------------------------------------------------------------

	public function testStrongPasswordIsValid(): void
	{
		$c   = new Complexify();
		// Long, mixed-case, digits, symbols — should be clearly strong
		$res = $c->evaluateSecurity('T#5gX!aQmP2@rZsW');

		$this->assertTrue($res->valid);
		$this->assertEmpty($res->errors);
	}

	public function testStrongPasswordComplexityIsHigh(): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity('T#5gX!aQmP2@rZsW');

		$this->assertGreaterThan(50, $res->complexity);
	}

	// -------------------------------------------------------------------------
	// Monotonic ordering: weak < strong
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ComplexifyProvider::weakVsStrongProvider()
	 */
	public function testWeakScoresLowerThanStrong(string $weak, string $strong): void
	{
		$c       = new Complexify();
		$weakRes = $c->evaluateSecurity($weak);
		$strongRes = $c->evaluateSecurity($strong);

		$this->assertLessThan(
			$strongRes->complexity,
			$weakRes->complexity,
			"Expected '$weak' to score lower than '$strong'"
		);
	}

	// -------------------------------------------------------------------------
	// Longer passwords of the same charset score higher
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ComplexifyProvider::longerScoreshigherProvider()
	 */
	public function testLongerPasswordScoreshigher(string $shorter, string $longer): void
	{
		$c          = new Complexify();
		$shortRes   = $c->evaluateSecurity($shorter);
		$longRes    = $c->evaluateSecurity($longer);

		$this->assertGreaterThan(
			$shortRes->complexity,
			$longRes->complexity,
			"Expected '$longer' to score higher than '$shorter'"
		);
	}

	// -------------------------------------------------------------------------
	// Adding a new character class increases score
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ComplexifyProvider::charsetExpansionProvider()
	 */
	public function testAddingCharsetIncreasesScore(string $simpler, string $richer): void
	{
		$c          = new Complexify();
		$simplerRes = $c->evaluateSecurity($simpler);
		$richerRes  = $c->evaluateSecurity($richer);

		$this->assertGreaterThan(
			$simplerRes->complexity,
			$richerRes->complexity,
			"Expected '$richer' (more charsets) to score higher than '$simpler'"
		);
	}

	// -------------------------------------------------------------------------
	// Banned passwords
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ComplexifyProvider::bannedPasswordProvider()
	 */
	public function testBannedPasswordIsInvalid(string $password): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity($password);

		$this->assertFalse($res->valid, "'$password' is a banned password and must not be valid");
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ComplexifyProvider::bannedPasswordProvider()
	 */
	public function testBannedPasswordHasBannedError(string $password): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity($password);

		$this->assertContains(
			'banned',
			$res->errors,
			"'$password' must produce a 'banned' error"
		);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\ComplexifyProvider::bannedPasswordProvider()
	 */
	public function testBannedPasswordComplexityIsAtFloor(string $password): void
	{
		$c   = new Complexify();
		$res = $c->evaluateSecurity($password);

		// Banned passwords get complexity=1 before the log step, so resulting
		// percentage must be very small (well below 50%).
		$this->assertLessThan(
			10,
			$res->complexity,
			"'$password' is banned and must have near-floor complexity"
		);
	}

	// -------------------------------------------------------------------------
	// Ban mode: strict vs loose
	// -------------------------------------------------------------------------

	public function testStrictBanModeBlocksSubstringOfBannedPassword(): void
	{
		// 'pass' is a substring of several banned passwords (e.g. 'password').
		// In strict mode (default) this should be banned.
		$c   = new Complexify(['banMode' => 'strict']);
		$res = $c->evaluateSecurity('pass');

		$this->assertContains('banned', $res->errors, "'pass' should be caught by strict ban mode as substring of 'password'");
	}

	public function testLooseBanModeAllowsSubstringOfBannedPassword(): void
	{
		// 'qwer' is a substring of the banned 'qwert' but is NOT itself in the banned list.
		// In loose mode (exact matches only), 'qwer' must NOT be reported as banned.
		$c   = new Complexify(['banMode' => 'loose']);
		$res = $c->evaluateSecurity('qwer');

		$this->assertNotContains('banned', $res->errors, "'qwer' should NOT be caught by loose ban mode (it is not itself banned)");
	}

	public function testLooseBanModeBlocksExactBannedPassword(): void
	{
		$c   = new Complexify(['banMode' => 'loose']);
		$res = $c->evaluateSecurity('password');

		$this->assertContains('banned', $res->errors, "'password' is exactly banned and must be caught in loose mode too");
	}

	// -------------------------------------------------------------------------
	// tooshort error
	// -------------------------------------------------------------------------

	public function testPasswordShorterThanMinimumHasTooshortError(): void
	{
		// Default minimumChars is 8; use 4 chars
		$c   = new Complexify();
		$res = $c->evaluateSecurity('aBc1');

		$this->assertContains('tooshort', $res->errors);
	}

	public function testPasswordAtMinimumLengthDoesNotHaveTooshortError(): void
	{
		// Default minimumChars is 8
		$c   = new Complexify();
		// Use a non-banned 8-char password with decent complexity
		$res = $c->evaluateSecurity('aB3!xY7@');

		$this->assertNotContains('tooshort', $res->errors);
	}

	public function testCustomMinimumCharsIsRespected(): void
	{
		$c   = new Complexify(['minimumChars' => 4]);
		$res = $c->evaluateSecurity('aB3!');

		$this->assertNotContains('tooshort', $res->errors);
	}

	// -------------------------------------------------------------------------
	// Complexity capped at 100%
	// -------------------------------------------------------------------------

	public function testComplexityDoesNotExceed100(): void
	{
		$c   = new Complexify();
		// Very long, diverse password — complexity must be capped at 100
		$res = $c->evaluateSecurity('T#5gX!aQmP2@rZsWvNbH8$kLpO3^yUjD9&cIeF6*');

		$this->assertLessThanOrEqual(100, $res->complexity);
	}

	// -------------------------------------------------------------------------
	// strengthScaleFactor option
	// -------------------------------------------------------------------------

	public function testHigherStrengthScaleFactorReducesScore(): void
	{
		$pwd       = 'T#5gX!aQmP2@rZsW';
		$c1        = new Complexify(['strengthScaleFactor' => 1]);
		$c2        = new Complexify(['strengthScaleFactor' => 2]);
		$res1      = $c1->evaluateSecurity($pwd);
		$res2      = $c2->evaluateSecurity($pwd);

		$this->assertGreaterThan(
			$res2->complexity,
			$res1->complexity,
			'A higher strengthScaleFactor must reduce the reported complexity'
		);
	}

	// -------------------------------------------------------------------------
	// Custom bannedPasswords option
	// -------------------------------------------------------------------------

	public function testCustomBannedPasswordListIsUsed(): void
	{
		$c   = new Complexify(['bannedPasswords' => ['mysecretword']]);
		$res = $c->evaluateSecurity('mysecretword');

		$this->assertContains('banned', $res->errors);
	}

	public function testDefaultBannedListIsReplacedByCustomList(): void
	{
		// With a custom list that does NOT contain 'password', 'password' should no longer be banned.
		$c   = new Complexify(['bannedPasswords' => ['totally_different_banned_word']]);
		$res = $c->evaluateSecurity('password');

		$this->assertNotContains('banned', $res->errors);
	}

	// -------------------------------------------------------------------------
	// Deprecated banmode option
	// -------------------------------------------------------------------------

	public function testDeprecatedBanmodeOptionTriggersDeprecation(): void
	{
		$triggered = false;
		set_error_handler(
			function ($errno, $errstr) use (&$triggered) {
				if (($errno === E_USER_DEPRECATED) && strpos($errstr, 'banmode') !== false) {
					$triggered = true;
				}
				return true;
			},
			E_USER_DEPRECATED
		);

		new Complexify(['banmode' => 'loose']);

		restore_error_handler();

		$this->assertTrue($triggered, 'Expected E_USER_DEPRECATED for the deprecated lowercase banmode option');
	}
}
