<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Crons;

use Awf\Mvc\DataView\Html as BaseHtml;
use Solo\Dependencies\Cron\CronExpression;
use Solo\Model\Cron;

class Html extends BaseHtml
{
	public $profilesList;

	/**
	 * @return bool
	 * @since  7.8.0
	 */
	public function onBeforeBrowse()
	{
		$document = $this->container->application->getDocument();

		// Buttons (new, edit, delete)
		$buttons = [
			[
				'title'   => 'SOLO_BTN_ADD',
				'class'   => 'akeeba-btn--green',
				'onClick' => 'akeeba.System.submitForm(\'add\')',
				'icon'    => 'akion-plus-circled',
			],
			[
				'title'   => 'SOLO_BTN_EDIT',
				'class'   => 'akeeba-btn--grey',
				'onClick' => 'akeeba.System.submitForm(\'edit\')',
				'icon'    => 'akion-edit',
			],
			[
				'title'   => 'SOLO_BTN_DELETE',
				'class'   => 'akeeba-btn--red',
				'onClick' => 'akeeba.System.submitForm(\'remove\')',
				'icon'    => 'akion-trash-b',
			],
		];

		$toolbar = $document->getToolbar();

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		$temp = $this->getModel('Main')->getProfileList();
		$keys = array_map(
			function($x) {
				return $x->value;
			},
			$temp
		);
		$values = array_map(
			function($x) {
				return $x->text;
			},
			$temp
		);
		$this->profilesList = array_combine($keys, $values);

		return parent::onBeforeBrowse();
	}

	/**
	 * @return bool
	 * @since  7.8.0
	 */
	protected function onBeforeAdd()
	{
		$document = $this->container->application->getDocument();

		// Buttons (save, save and close, save and new, cancel)
		$buttons = [
			[
				'title'   => 'SOLO_BTN_SAVECLOSE',
				'class'   => 'akeeba-btn--green',
				'onClick' => 'akeeba.System.submitForm(\'save\')',
				'icon'    => 'akion-checkmark-circled',
			],
			[
				'title'   => 'SOLO_BTN_SAVE',
				'class'   => 'akeeba-btn--grey',
				'onClick' => 'akeeba.System.submitForm(\'apply\')',
				'icon'    => 'akion-checkmark',
			],
			[
				'title'   => 'SOLO_BTN_CANCEL',
				'class'   => 'akeeba-btn--orange',
				'onClick' => 'akeeba.System.submitForm(\'cancel\')',
				'icon'    => 'akion-close-circled',
			],
		];

		$toolbar = $document->getToolbar();

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		return parent::onBeforeAdd();
	}

	/**
	 * @return bool
	 * @since  7.8.0
	 */
	protected function onBeforeEdit()
	{
		$document = $this->container->application->getDocument();

		// Buttons (save, save and close, save and new, cancel)
		$buttons = [
			[
				'title'   => 'SOLO_BTN_SAVECLOSE',
				'class'   => 'akeeba-btn--green',
				'onClick' => 'akeeba.System.submitForm(\'save\')',
				'icon'    => 'akion-checkmark-circled',
			],
			[
				'title'   => 'SOLO_BTN_SAVE',
				'class'   => 'akeeba-btn--grey',
				'onClick' => 'akeeba.System.submitForm(\'apply\')',
				'icon'    => 'akion-checkmark',
			],
			[
				'title'   => 'SOLO_BTN_CANCEL',
				'class'   => 'akeeba-btn--orange',
				'onClick' => 'akeeba.System.submitForm(\'cancel\')',
				'icon'    => 'akion-close-circled',
			],
		];

		$toolbar = $document->getToolbar();

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		return parent::onBeforeEdit();
	}

	/**
	 * Format the date and time of a task run
	 *
	 * @param   \DateTime  $dateTime
	 *
	 * @return  string
	 * @since   7.8.0
	 */
	protected function formatDateTime(\DateTime $dateTime)
	{
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
			$tzObject = new \DateTimeZone($tz);
		}
		catch (\Exception $e)
		{
			$tz = 'UTC';
			$tzObject = new \DateTimeZone('UTC');
		}

		$dateTime->setTimezone($tzObject);

		return $dateTime->format('l, d F Y H:i:s T');
	}

	protected function getNextRun(Cron $task): \DateTime
	{
		$nullDate = $this->getModel()->getDbo()->getNullDate();
		$previousRunStamp = $task->last_run_start ?? '2000-01-01 00:00:00';
		$previousRunStamp = $previousRunStamp === $nullDate ? '2000-01-01 00:00:00' : $previousRunStamp;

		try
		{
			$relativeTime = new \DateTime($previousRunStamp);
		}
		catch (\Exception $e)
		{
			$relativeTime = new \DateTime('now');
		}

		$cronParser = new CronExpression($task->cron_expression);

		try
		{
			$tz = $this->container->appConfig->get('forced_backup_timezone', 'UTC');

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

		return $cronParser->getNextRunDate($relativeTime, 0, false, $tz);
	}

	protected function getTimezoneLiteral(): string
	{
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

		return $tz;
	}
}