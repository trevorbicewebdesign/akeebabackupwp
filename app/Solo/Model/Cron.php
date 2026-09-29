<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Model;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Awf\Container\Container;
use Awf\Mvc\DataModel;
use Awf\Text\Language;
use Awf\Text\Text;
use Solo\Dependencies\Cron\CronExpression;

/**
 * Model to handle CRON tasks
 *
 * @property int    $id              The ID of the task
 * @property string $description     The description of this task
 * @property int    $profile_id      The ID of the backup profile to take a backup with
 * @property string $cron_expression The CRON expression of when to take a backup
 * @property int    $last_exit       The last exit status
 * @property string $last_run_start  The last run start time
 * @property string $last_run_end    The last run completion time
 * @property string $storage         JSON store for resume-aware tasks
 *
 * Application configuration keys:
 * * `cron_stuck_threshold` Number of minutes before a running task is considered "stuck". Default: 3.
 *
 * @since  7.8.0
 */
class Cron extends DataModel
{
	/**
	 * A task has finished successfully, or has not been executed yet.
	 *
	 * @since  7.8.0
	 */
	public const TASK_OK = 0;

	/**
	 * A task is currently running.
	 *
	 * @since  7.8.0
	 */
	public const TASK_RUNNING = 10;

	/**
	 * A task has temporarily paused its execution and needs to resume.
	 *
	 * @since  7.8.0
	 */
	public const TASK_WILL_CONTINUE = 20;

	/**
	 * A task has timed out.
	 *
	 * @since  7.8.0
	 */
	public const TASK_TIMEOUT = 50;

	/**
	 * The task has been created but not yet executed.
	 *
	 * @since  7.8.0
	 */
	public const TASK_INITIAL_SCHEDULE = 100;

	/**
	 * A task has exited with an error.
	 *
	 * @since  7.8.0
	 */
	public const TASK_ERROR = 127;

	/**
	 * Public constructor
	 *
	 * @param   Container|null  $container  Configuration parameters
	 *
	 * @since   7.8.0
	 */
	public function __construct(?Container $container = null, ?Language $language = null)
	{
		$this->tableName   = '#__ak_schedules';
		$this->idFieldName = 'id';

		parent::__construct($container, $language);

		$this->addBehaviour('filters');
	}

	/**
	 * Runs the next scheduled tasks
	 *
	 * @return  void
	 * @since   7.8.0
	 */
	public function runNextTask(): void
	{
		$db = $this->getDbo();

		@ob_start();

		// Lock the table to avoid concurrency issues
		try
		{
			$query = 'LOCK TABLES ' . $db->quoteName('#__ak_schedules') . ' WRITE, '
				. $db->quoteName('#__ak_schedules', 's') . ' WRITE';
			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			ob_end_clean();

			return;
		}

		// Cancel any tasks which appear to be stuck
		try
		{
			$this->cleanUpStuckTasks();
		}
		catch (\Exception $e)
		{
			// If an error occurred it means that a past lock has not yet been freed; give up.
			$db->unlockTables();

			@ob_end_clean();

			return;
		}

		// Get the next pending task
		try
		{
			$pendingTask = $this->getNextTask();

			if (empty($pendingTask))
			{
				$db->unlockTables();

				return;
			}
		}
		catch (\Exception $e)
		{
			$db->unlockTables();

			@ob_end_clean();

			return;
		}

		// Mark the current task as running
		try
		{
			$willContinue = $pendingTask->last_exit == self::TASK_WILL_CONTINUE;

			$pendingTask->save([
				'last_exit'      => self::TASK_RUNNING,
				'last_run_start' => $this->container->dateFactory()->toSql(false, $this->container->db),
			]);
		}
		catch (\Exception $e)
		{
			// Failure to save the task means that the task execution has ultimately failed.
			@ob_end_clean();

			return;
		}

		try
		{
			$db->unlockTables();
		}
		catch (\Exception $e)
		{
			// This should not fail, but if it does we can survive it.
		}

		// The AJAX call to WP-CRON may time out; allow the execution to continue regardless
		if (function_exists('ignore_user_abort'))
		{
			ignore_user_abort(true);
		}

		// Install a timeout trap
		register_shutdown_function([$this, 'timeoutTrap'], $pendingTask);

		try
		{
			do
			{
				if ($willContinue)
				{
					$this->resumeBackup($pendingTask);
				}
				else
				{
					$this->startBackup($pendingTask);
				}

				$willContinue = $pendingTask->last_exit == self::TASK_WILL_CONTINUE;
			} while ($willContinue && Factory::getTimer()->getTimeLeft() > 0);
		}
		catch (\Exception $e)
		{
			$pendingTask->last_exit = self::TASK_ERROR;
			$pendingTask->storage   = json_encode([
				'error' => $e->getMessage(),
				'trace' => $e->getFile() . '::' . $e->getLine() . "\n" . $e->getTraceAsString(),
			]);
		}
		finally
		{
			$db->lockTable('#__ak_schedules');
			$pendingTask->save([
				'last_run_end' => $this->container->dateFactory()->toSql(false, $this->container->db),
			]);
			$db->unlockTables();

			@ob_end_clean();
		}
	}

	/**
	 * Mark stuck tasks as timed out
	 *
	 * @return  void
	 * @since   7.8.0
	 */
	public function cleanUpStuckTasks()
	{
		$db         = $this->getDbo();
		$threshold  = max(3, (int) $this->container->appConfig->get('cron_stuck_threshold', 3));
		$cutoffTime = $this->container->dateFactory()->sub(new \DateInterval('PT' . $threshold . 'M'));

		$query = $db->getQuery(true)
			->update($db->qn('#__ak_schedules'))
			->set([
				$db->qn('last_exit') . ' = ' . self::TASK_TIMEOUT,
				$db->qn('last_run_end') . ' = NOW()',
				$db->qn('storage') . ' = NULL',
			])
			->where([
				$db->qn('last_exit') . ' = ' . self::TASK_RUNNING,
				$db->qn('last_run_start') . ' <= ' . $db->quote($cutoffTime->toSql(false, $this->container->db)),
			]);

		$db->setQuery($query)->execute();
	}

	/**
	 * Set up a timeout trap.
	 *
	 * It updates the pending task as timed out and exits.
	 *
	 * @param   Cron  $pendingTask
	 *
	 * @return  void
	 * @since   7.8.0
	 */
	public function timeoutTrap(self $pendingTask): void
	{
		// The request has timed out. Whomp, whomp.
		if (in_array(connection_status(), [2, 3]))
		{
			$pendingTask->save([
				'last_exit' => self::TASK_TIMEOUT,
				'storage'   => null,
			]);

			exit(127);
		}
	}

	/**
	 * Check the table data for validity
	 *
	 * @return  $this
	 * @since   7.8.0
	 */
	public function check()
	{
		parent::check();

		if (empty($this->cron_expression) || !CronExpression::isValidExpression($this->cron_expression))
		{
			throw new \RuntimeException(Text::_('COM_AKEEBA_CRONS_ERR_CRON_EXPRESSION_INVALID'));
		}

		/**
		 * If this is a brand new CRON job set the last execution task to the previous run start relative to the current
		 * date and time. This will prevent the CRON job from starting immediately after scheduling it.
		 */
		$nullDate = $this->getDbo()->getNullDate();

		if ($this->last_run_start === null || $this->last_run_start === $nullDate)
		{
			$cronExpression = new CronExpression($this->cron_expression);

			try
			{
				$tz = $this->container->appConfig->get('forced_backup_timezone', 'UTC') ?: 'AKEEBA/DEFAULT';

				if ($tz === 'AKEEBA/DEFAULT')
				{
					$tz = function_exists('wp_timezone_string') ? (wp_timezone_string() ?: 'UTC') : 'UTC';
				}

				/**
				 * CRITICAL! Do not remove the dummy line.
				 *
				 * If an invalid timezone is configured, the following line will raise an exception which will be caught by
				 * the try/catch block, thus falling back to the safe default of `UTC`.
				 */
				new \DateTimeZone($tz);
			}
			catch (\Exception $e)
			{
				$tz = 'UTC';
			}

			try
			{
				$tz = new \DateTimeZone($tz);
			}
			catch (\Throwable $e)
			{
				$tz = new \DateTimeZone('UTC');
			}

			$now = new \DateTime('now');
			$now->setTimezone($tz);

			$previousRun          = $cronExpression->getPreviousRunDate($now, 0, false, $tz->getName())->format(DATE_W3C);
			$this->last_run_start = $this->container->dateFactory($previousRun)->toSql(false, $this->container->db);
			$this->last_run_end   = null;
			$this->last_exit      = self::TASK_INITIAL_SCHEDULE;
		}

		return $this;
	}

	/**
	 * Returns the next task to execute, NULL if there is none.
	 *
	 * It prioritises tasks which have returned a TASK_WILL_CONTINUE status.
	 *
	 * If none of these paused tasks exist, it will go through all non-running tasks, evaluating their CRON expressions
	 * to find which one is the next task which should be executed.
	 *
	 * @return $this|null
	 * @throws \Exception
	 * @since  7.8.0
	 */
	private function getNextTask(): ?self
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ak_schedules'))
			->where($db->qn('last_exit') . ' = ' . self::TASK_WILL_CONTINUE)
			->order($db->qn('last_run_end') . ' DESC')
			->union(
				$db->getQuery(true)
					->select('*')
					->from($db->qn('#__ak_schedules', 's'))
					->where([
						$db->qn('last_exit') . ' != ' . self::TASK_WILL_CONTINUE,
						$db->qn('last_exit') . ' != ' . self::TASK_RUNNING,
					])
					->order($db->qn('id') . ' DESC')
			);

		$tasks = $db->setQuery($query)->loadObjectList();

		if (empty($tasks))
		{
			return null;
		}

		$now      = new \DateTimeImmutable();
		$nullDate = $this->getDbo()->getNullDate();

		foreach ($tasks as $task)
		{
			if ($task->last_exit == self::TASK_WILL_CONTINUE)
			{
				return $this->getClone()->bind($task);
			}

			$previousRunStamp = $task->last_run_start ?? '2000-01-01 00:00:00';
			$previousRunStamp = $previousRunStamp === $nullDate ? '2000-01-01 00:00:00' : $previousRunStamp;
			try
			{
				$previousRun  = new \DateTime($previousRunStamp);
				$relativeTime = $previousRun;
			}
			catch (\Exception $e)
			{
				$previousRun  = new \DateTime('2000-01-01 00:00:00');
				$relativeTime = new \DateTime('now');
			}

			try
			{
				$tz = $this->container->appConfig->get('forced_backup_timezone', 'UTC') ?: 'AKEEBA/DEFAULT';

				if ($tz === 'AKEEBA/DEFAULT')
				{
					$tz = function_exists('wp_timezone_string') ? (wp_timezone_string() ?: 'UTC') : 'UTC';
				}

				/**
				 * CRITICAL! Do not remove the dummy line.
				 *
				 * If an invalid timezone is configured, the following line will raise an exception which will be caught by
				 * the try/catch block, thus falling back to the safe default of `UTC`.
				 */
				new \DateTimeZone($tz);
			}
			catch (\Exception $e)
			{
				$tz = 'UTC';
			}

			try
			{
				$tz = new \DateTimeZone($tz);
			}
			catch (\Throwable $e)
			{
				$tz = new \DateTimeZone('UTC');
			}

			$relativeTime->setTimezone($tz);

			$cronParser = new CronExpression($task->cron_expression);
			$nextRun    = $cronParser->getNextRunDate($relativeTime, 0, false, $tz->getName());

			// A task is pending if its next run is after its last run but before the current date and time
			if ($nextRun > $previousRun && $nextRun <= $now)
			{
				return $this->getClone()->bind($task);
			}
		}

		return null;
	}

	/**
	 * Start a new backup
	 *
	 * @param   Cron  $pendingTask  The task against which the backup is running
	 *
	 * @return  void
	 * @since   7.8.0
	 */
	private function startBackup(self $pendingTask): void
	{
		$this->container->application->initialise();

		// Set the backup profile
		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', $pendingTask->profile_id);
		}

		if (!defined('AKEEBA_BACKUP_ORIGIN'))
		{
			define('AKEEBA_BACKUP_ORIGIN', 'wpcron');
		}

		/** @var Backup $model */
		$model = $this->container->mvcFactory->makeTempModel('Backup');

		/**
		 * DO NOT REMOVE!
		 *
		 * The Model will only try to load the configuration after nuking the factory. This causes Profile 1 to be
		 * loaded first. Then it figures out it needs to load a different profile and it does – but the protected keys
		 * are NOT replaced, meaning that certain configuration parameters are not replaced. Most notably, the chain.
		 * This causes backups to behave weirdly. So, DON'T REMOVE THIS UNLESS WE REFACTOR THE MODEL.
		 */
		Platform::getInstance()->load_configuration(AKEEBA_PROFILE);

		$model->setState('tag', AKEEBA_BACKUP_ORIGIN);
		$model->setState('backupid', null);
		$model->setState('comment', '');

		$array = $model->startBackup($this->getOverrides());

		$this->updateRunningTaskFromReturnArray($pendingTask, $array, $model->getState('backupid', null, 'cmd'));
	}

	/**
	 * Resume a backup task
	 *
	 * @param   Cron  $runningTask  The task which owns the backup we are resuming
	 *
	 * @return  void
	 * @since   7.8.0
	 */
	private function resumeBackup(self $runningTask): void
	{
		$this->container->application->initialise();

		// Set the backup profile
		if (!defined('AKEEBA_PROFILE'))
		{
			define('AKEEBA_PROFILE', $runningTask->profile_id);
		}

		if (!defined('AKEEBA_BACKUP_ORIGIN'))
		{
			define('AKEEBA_BACKUP_ORIGIN', 'wpcron');
		}

		/** @var Backup $model */
		$model = $this->container->mvcFactory->makeTempModel('Backup');

		Platform::getInstance()->load_configuration(AKEEBA_PROFILE);

		try
		{
			$storage = @json_decode($runningTask->storage ?: '{}', false);
		}
		catch (\Exception $e)
		{
			$storage = new \stdClass();
		}

		$model->setState('backupid', $storage->backupid ?? null);
		$model->setState('tag', AKEEBA_BACKUP_ORIGIN);
		$model->setState('profile', AKEEBA_PROFILE);

		$array = $model->stepBackup(true);

		$this->updateRunningTaskFromReturnArray($runningTask, $array, $model->getState('backupid', null, 'cmd'));
	}

	/**
	 * Update the status of the running task given an Engine return array
	 *
	 * @param   Cron         $runningTask  The running task
	 * @param   array        $array        The Engine return array
	 * @param   string|null  $backupId     The `backupid` which identifies the backup to resume
	 *
	 * @return  void
	 * @since   7.8.0
	 */
	private function updateRunningTaskFromReturnArray(self $runningTask, array $array, ?string $backupId)
	{
		if ($array['Error'] != '')
		{
			$runningTask->bind([
				'last_exit' => self::TASK_ERROR,
				'storage'   => json_encode([
					'error' => $array['Error'],
				]),
			]);
		}
		elseif ($array['HasRun'] == 1)
		{
			$runningTask->bind([
				'last_exit' => self::TASK_OK,
				'storage'   => null,
			]);
		}
		else
		{
			$runningTask->bind([
				'last_exit' => self::TASK_WILL_CONTINUE,
				'storage'   => json_encode([
					'backupid' => $backupId,
				]),
			]);
		}
	}

	/**
	 * Get configuration overrides for starting the backup
	 *
	 * @return  array
	 * @since   7.8.0
	 */
	private function getOverrides(): array
	{
		$overrides = [
			'akeeba.tuning.settimelimit' => 1,
			'akeeba.tuning.setmemlimit'  => 1,
			'akeeba.advanced.autoresume' => 0,
		];

		$overrideTimePolicy = $this->container->appConfig->get('wp_cron_override_time', 1);

		if ($overrideTimePolicy != 0)
		{
			$bestMaxExec = $this->getBestMaxExecTime();

			if ($bestMaxExec !== null)
			{
				if ($overrideTimePolicy == 2)
				{
					$bestMaxExec = max(3, $bestMaxExec / 2);
				}

				$overrides = $overrides + [
						'akeeba.tuning.min_exec_time' => 0,
						'akeeba.tuning.max_exec_time' => $bestMaxExec,
						'akeeba.tuning.run_time_bias' => 75,
					];
			}
		}

		return $overrides;
	}

	/**
	 * Get the best maximum execution time.
	 *
	 * It takes into consideration WordPress' lock timeout, the PHP maximum execution time, and the max CPU usage limit
	 * (ulimit -t).
	 *
	 * If we cannot get the PHP maximum execution time, or the max CPU usage limit, we assume them to be 30 seconds. If
	 * the platform is Windows we ignore the CPU usage limit (since ulimit -t is not available on Windows).
	 *
	 * @return  int|null
	 * @since   7.8.0
	 */
	private function getBestMaxExecTime(): ?int
	{
		$lockTimeout = defined('WP_CRON_LOCK_TIMEOUT') ? WP_CRON_LOCK_TIMEOUT : 60;

		$maxExec = function_exists('ini_get') ? ini_get('max_execution_time') : null;
		$maxExec = is_int($maxExec) ? $maxExec : 30;

		try
		{
			$systemLimit = null;
			$isWindows   = substr(PHP_OS, 0, 3) == 'WIN';

			if (!$isWindows && function_exists('exec') && exec('ulimit -t', $output) !== false)
			{
				$output = array_shift($output);

				if ($output === 'unlimited')
				{
					$systemLimit = 60;
				}
				elseif (is_numeric($output))
				{
					$systemLimit = (int) $output;
				}

				$systemLimit = $systemLimit > 0 ? $systemLimit : 30;
			}
		}
		catch (\Exception $e)
		{
			$systemLimit = 30;
		}

		$bestTimeout = min($lockTimeout, 60);

		if ($maxExec !== null)
		{
			$bestTimeout = min($bestTimeout, $maxExec);
		}

		if ($systemLimit !== null)
		{
			$bestTimeout = min($bestTimeout, $systemLimit);
		}

		return $bestTimeout;
	}
}