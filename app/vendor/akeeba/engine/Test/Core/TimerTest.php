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

namespace Akeeba\Engine\Test\Core;

use Akeeba\Engine\Core\Timer;
use Akeeba\Engine\Factory;
use Akeeba\Engine\Test\AbstractEngineTestCase;

final class TimerTest extends AbstractEngineTestCase
{
	/**
	 * getRunningTime() should be approximately zero immediately after construction.
	 */
	public function testGetRunningTimeIsNearZeroAtConstruction(): void
	{
		$timer = new Timer(30, 100);

		$runningTime = $timer->getRunningTime();

		// Should be very close to zero — less than 1 second
		$this->assertGreaterThanOrEqual(0.0, $runningTime);
		$this->assertLessThan(1.0, $runningTime);
	}

	/**
	 * getRunningTime() should increase after a short sleep.
	 */
	public function testGetRunningTimeIncreasesAfterSleep(): void
	{
		$timer = new Timer(30, 100);

		usleep(20000); // 20ms

		$runningTime = $timer->getRunningTime();

		// Should be at least ~10ms (half the sleep, generous tolerance)
		$this->assertGreaterThan(0.005, $runningTime, 'Running time should increase after sleep');
	}

	/**
	 * getTimeLeft() should be positive with a large max exec time immediately after construction.
	 */
	public function testGetTimeLeftIsPositiveWithLargeMaxExecTime(): void
	{
		// 30 seconds max exec time, 100% bias = 30 seconds left
		$timer = new Timer(30, 100);

		$timeLeft = $timer->getTimeLeft();

		$this->assertGreaterThan(0.0, $timeLeft, 'Time left should be positive immediately after construction');
	}

	/**
	 * getTimeLeft() should equal max_exec_time - getRunningTime().
	 */
	public function testGetTimeLeftEqualsMaxExecMinusRunningTime(): void
	{
		// 10 seconds max, 100% bias = 10 seconds effective max
		$timer = new Timer(10, 100);

		$runningTime = $timer->getRunningTime();
		$timeLeft    = $timer->getTimeLeft();

		// timeLeft + runningTime should be approximately 10 (the effective max_exec_time)
		$total = $timeLeft + $runningTime;

		// Allow ±0.1 second tolerance for measurement imprecision
		$this->assertGreaterThan(9.9, $total, 'timeLeft + runningTime should approximate max_exec_time');
		$this->assertLessThan(10.1, $total, 'timeLeft + runningTime should approximate max_exec_time');
	}

	/**
	 * getTimeLeft() should decrease as time passes.
	 */
	public function testGetTimeLeftDecreasesAfterSleep(): void
	{
		$timer = new Timer(30, 100);

		$timeLeftBefore = $timer->getTimeLeft();

		usleep(20000); // 20ms

		$timeLeftAfter = $timer->getTimeLeft();

		$this->assertLessThan($timeLeftBefore, $timeLeftAfter, 'Time left should decrease as time passes');
	}

	/**
	 * Constructor with explicit bias of 50% should halve the effective max exec time.
	 */
	public function testConstructorBiasAffectsEffectiveMaxExecTime(): void
	{
		// 10 seconds, 50% bias = 5 seconds effective max
		$timer50  = new Timer(10, 50);
		// 10 seconds, 100% bias = 10 seconds effective max
		$timer100 = new Timer(10, 100);

		$timeLeft50  = $timer50->getTimeLeft();
		$timeLeft100 = $timer100->getTimeLeft();

		// timeLeft at 50% bias should be roughly half of 100% bias
		$this->assertLessThan($timeLeft100, $timeLeft50, '50% bias timer should have less time left than 100% bias');

		// More precisely: ~5 vs ~10, so 50% timer should be less than 7
		$this->assertLessThan(7.0, $timeLeft50, '50% bias should give ~5s effective max');
		$this->assertGreaterThan(3.0, $timeLeft50, '50% bias should give ~5s effective max');
	}

	/**
	 * Constructor should enforce minimum bias of 10.
	 */
	public function testConstructorEnforcesMinimumBias(): void
	{
		// bias of 1 should be clamped to 10
		$timer = new Timer(10, 1);

		$timeLeft = $timer->getTimeLeft();

		// Effective max = 10 * 10 / 100 = 1 second
		$this->assertGreaterThan(0.0, $timeLeft, 'Even with minimum bias, time left should be positive');
		$this->assertLessThan(1.5, $timeLeft, 'Minimum bias of 10% on 10s = 1s effective max');
	}

	/**
	 * Constructor should enforce minimum max exec time of 1 second.
	 */
	public function testConstructorEnforcesMinimumMaxExecTime(): void
	{
		// maxExecTime of 0 should be clamped to 1
		$timer = new Timer(0, 100);

		$timeLeft = $timer->getTimeLeft();

		// Effective max = 1 * 100 / 100 = 1 second
		$this->assertGreaterThan(0.0, $timeLeft, 'Minimum maxExecTime of 1s should yield positive time left');
		$this->assertLessThan(1.5, $timeLeft, 'maxExecTime clamped to 1 with 100% bias = 1s effective max');
	}

	/**
	 * resetTime() should reset the running time back to near-zero.
	 */
	public function testResetTimeResetsRunningTime(): void
	{
		$timer = new Timer(30, 100);

		usleep(20000); // 20ms

		$runningTimeBefore = $timer->getRunningTime();
		$this->assertGreaterThan(0.005, $runningTimeBefore, 'Running time should be non-zero before reset');

		$timer->resetTime();

		$runningTimeAfter = $timer->getRunningTime();

		// After reset, running time should be less than what it was before
		$this->assertLessThan($runningTimeBefore, $runningTimeAfter, 'Running time should be reset to near-zero');

		// Should be very close to 0 again
		$this->assertLessThan(0.1, $runningTimeAfter, 'Running time after reset should be near-zero');
	}

	/**
	 * resetTime() should also make getTimeLeft() increase back to near max.
	 */
	public function testResetTimeRestoresTimeLeft(): void
	{
		$timer = new Timer(30, 100);

		usleep(20000); // 20ms

		$timeLeftBefore = $timer->getTimeLeft();

		$timer->resetTime();

		$timeLeftAfter = $timer->getTimeLeft();

		// After reset, time left should be greater than before (we "got back" the elapsed time)
		$this->assertGreaterThan($timeLeftBefore, $timeLeftAfter, 'Time left should increase after reset');
	}

	/**
	 * Config-driven path: when maxExecTime and bias are null, they come from configuration.
	 */
	public function testConfigDrivenConstruction(): void
	{
		$config = Factory::getConfiguration();
		$config->set('akeeba.tuning.max_exec_time', 20);
		$config->set('akeeba.tuning.run_time_bias', 50);

		// Construct with no explicit args — should use config values (20 * 50% = 10s effective)
		$timer = new Timer(null, null);

		$timeLeft = $timer->getTimeLeft();

		// Effective max = 20 * 50 / 100 = 10 seconds
		$this->assertGreaterThan(0.0, $timeLeft, 'Config-driven timer should have positive time left');
		$this->assertLessThan(10.5, $timeLeft, 'Config-driven timer should have ~10s left');
		$this->assertGreaterThan(9.0, $timeLeft, 'Config-driven timer should have ~10s left');
	}

	/**
	 * enforce_min_exec_time() with zero minimum should return 0 and not sleep.
	 */
	public function testEnforceMinExecTimeWithZeroMinimumReturnsZero(): void
	{
		$config = Factory::getConfiguration();
		$config->set('akeeba.tuning.min_exec_time', 0);

		$timer = new Timer(30, 100);

		$startTime = microtime(true);
		$result    = $timer->enforce_min_exec_time(false, true);
		$elapsed   = microtime(true) - $startTime;

		$this->assertSame(0, $result, 'With zero min exec time, result should be 0');
		// Should complete almost instantly
		$this->assertLessThan(0.1, $elapsed, 'With zero min exec time, should not sleep');
	}

	/**
	 * enforce_min_exec_time() with client-side sleep should return positive value
	 * when already running and min exec time has not been reached.
	 */
	public function testEnforceMinExecTimeClientSideReturnsWaitTime(): void
	{
		$config = Factory::getConfiguration();
		// Set a minimum exec time of 50ms (0.05 seconds -> 50ms)
		// The timer just started so elapsed_time should be well under 50ms
		$config->set('akeeba.tuning.min_exec_time', 50);

		$timer = new Timer(30, 100);

		// Do a tiny sleep to ensure elapsed_time > 0 (required by the code)
		usleep(5000); // 5ms

		// Client-side sleep: should return the msec to wait without sleeping
		$startTime = microtime(true);
		$result    = $timer->enforce_min_exec_time(false, false);
		$elapsed   = microtime(true) - $startTime;

		// Should return a positive number of msec (remaining time to reach 50ms)
		$this->assertGreaterThan(0, $result, 'Client-side sleep should return positive msec to wait');

		// Should complete quickly since no actual sleep happens
		$this->assertLessThan(0.1, $elapsed, 'Client-side call should return quickly without sleeping');
	}

	/**
	 * enforce_min_exec_time() should not sleep when running time already exceeds min_exec_time.
	 */
	public function testEnforceMinExecTimeNoSleepWhenAlreadyPastMinimum(): void
	{
		$config = Factory::getConfiguration();
		// Set a tiny minimum exec time (1ms)
		$config->set('akeeba.tuning.min_exec_time', 1);

		$timer = new Timer(30, 100);

		// Sleep for longer than the minimum
		usleep(10000); // 10ms (well past 1ms minimum)

		$startTime = microtime(true);
		$result    = $timer->enforce_min_exec_time(false, true);
		$elapsed   = microtime(true) - $startTime;

		// Since we've already exceeded min exec time, no sleep should occur
		$this->assertSame(0, $result, 'No client sleep needed when past minimum');
		$this->assertLessThan(0.05, $elapsed, 'Should return quickly when past minimum exec time');
	}

	/**
	 * __wakeup() should reset the start time (tested via serialize/unserialize).
	 */
	public function testWakeupResetsStartTime(): void
	{
		$timer = new Timer(30, 100);

		usleep(20000); // 20ms

		$runningTimeBefore = $timer->getRunningTime();

		// Serialize and unserialize — __wakeup() should reset the start time
		$serialized   = serialize($timer);
		$restoredTimer = unserialize($serialized);

		$runningTimeAfter = $restoredTimer->getRunningTime();

		// After unserialize, the running time should be reset to near-zero
		$this->assertLessThan($runningTimeBefore, $runningTimeAfter, '__wakeup() should reset running time');
		$this->assertLessThan(0.1, $runningTimeAfter, '__wakeup() should reset running time to near-zero');
	}
}
