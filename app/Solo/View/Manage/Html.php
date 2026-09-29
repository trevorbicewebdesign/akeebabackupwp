<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Manage;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Awf\Html\Select;
use Awf\Mvc\View;
use Awf\Pagination\Pagination;
use Awf\Text\Text;
use Awf\Utils\Template;
use DateTimeZone;
use Solo\Helper\Escape;
use Solo\Model\Profiles;
use Solo\Model\Transfers;

class Html extends View
{
	/**
	 * Should I use the user's local time zone for display?
	 *
	 * @var  boolean
	 */
	public $useLocalTime;

	/**
	 * Time format string to use for the time zone suffix
	 *
	 * @var  string
	 */
	public $timeZoneFormat;

	/**
	 * The backup record for the showcomment view
	 *
	 * @var  array
	 */
	public $record = [];

	/**
	 * The backup record ID for the showcomment view
	 *
	 * @var  int
	 */
	public $record_id = 0;

	/**
	 * List of Profiles objects
	 *
	 * @var  array
	 */
	public $profiles = [];

	/**
	 * List of profiles for JHtmlSelect
	 *
	 * @var  array
	 */
	public $profilesList = [];

	/**
	 * List of frozen options for JHtmlSelect
	 *
	 * @var  array
	 */
	public $frozenList = [];

	/**
	 * List of records to display
	 *
	 * @var  array
	 */
	public $items = [];

	/**
	 * Pagination object
	 *
	 * @var Pagination
	 */
	public $pagination = null;

	/**
	 * Date format for the backup start time
	 *
	 * @var  string
	 */
	public $dateFormat = '';

	/**
	 * Should I prompt the user ot run the configuration wizard?
	 *
	 * @var  bool
	 */
	public $promptForBackupRestoration = false;

	/**
	 * The record lists of this view
	 *
	 * @var   \stdClass
	 */
	public $lists = null;

	/**
	 * Cache the user privileges
	 *
	 * @var  array
	 */
	public $privileges = [];

	/**
	 * Post-processing engines per backup profile in the format profile id => post-processing enging
	 *
	 * @var   array
	 */
	public $enginesPerProfile = [];

	/**
	 * Should I show the browser download buttons in WordPress?
	 *
	 * @var   bool
	 * @since 7.6.1
	 */
	public $showBrowserDownload;

	/**
	 * Is PHP set up to output error to the browser?
	 *
	 * -1: Don't know; 0: no; 1 yes
	 *
	 * @var   int
	 * @since 7.6.1
	 */
	public $phpErrorDisplay = -1;

	public function __construct($container = null)
	{
		parent::__construct($container);

		$this->lists = new \stdClass();

		Template::addJs('media://js/solo/manage.js', $this->container->application);
	}

	public function onBeforeMain()
	{
		$user                          = $this->container->userManager->getUser();
		$this->privileges['backup']    = $user->getPrivilege('akeeba.backup');
		$this->privileges['download']  = $user->getPrivilege('akeeba.download');
		$this->privileges['configure'] = $user->getPrivilege('akeeba.configure');

		$document = $this->container->application->getDocument();
		$router   = $this->container->router;

		$task = $this->container->segment->get('solo_manage_task', 'main');

		/** @var \Solo\Model\Manage $model */
		$model = $this->getModel();

		$this->lists->order          = $model->getState('filter_order', 'backupstart');
		$this->lists->order_Dir      = $model->getState('filter_order_Dir', 'DESC');
		$this->lists->fltDescription = $model->getState('filter_description', null);
		$this->lists->fltFrom        = $model->getState('filter_from', null);
		$this->lists->fltTo          = $model->getState('filter_to', null);
		$this->lists->fltOrigin      = $model->getState('filter_origin', null);
		$this->lists->fltProfile     = $model->getState('filter_profile', null);
		$this->lists->fltFrozen      = $model->getState('filter_frozen', null);

		$filters  = $this->_getFilters();
		$ordering = $this->_getOrdering();

		$start = $model->getState('limitstart');
		$limit = $model->getState('limit', 10);
		$total = (int) Platform::getInstance()->get_statistics_count($filters);

		if ($start >= $total)
		{
			$pages = ceil($total / $limit);
			$start = max(0, $limit * ($pages - 1));

			$model->setState('limitstart', $start);
		}

		$this->items = $model->getStatisticsListWithMeta(false, $filters, $ordering);

		/** @var Profiles $profileModel */
		$profileModel         = $this->container->mvcFactory->makeTempModel('Profiles');
		$this->profiles       = $profileModel->get(true);
		$this->profilesList   = [];
		$this->profilesList[] = $this->getContainer()->html->select->option( '', '&mdash;');

		if (!empty($this->profiles))
		{
			foreach ($this->profiles as $profile)
			{
				$this->profilesList[] = $this->getContainer()->html->select->option( $profile->id, $profile->description);
			}
		}

		$this->frozenList = [
			$this->getContainer()->html->select->option( '', '–' . Text::_('COM_AKEEBA_BUADMIN_LABEL_FROZEN_SELECT') . '–'),
			$this->getContainer()->html->select->option( '1', Text::_('COM_AKEEBA_BUADMIN_LABEL_FROZEN_FROZEN')),
			$this->getContainer()->html->select->option( '2', Text::_('COM_AKEEBA_BUADMIN_LABEL_FROZEN_UNFROZEN')),
		];

		$this->pagination = $model->getPagination($filters);

		// Date format
		$dateFormat       = $this->container->appConfig->get('dateformat', '');
		$dateFormat       = trim($dateFormat);
		$this->dateFormat = !empty($dateFormat) ? $dateFormat : Text::_('DATE_FORMAT_LC4');

		// Time zone options
		$this->useLocalTime   = $this->container->appConfig->get('localtime', '1') == 1;
		$this->timeZoneFormat = $this->container->appConfig->get('timezonetext', 'T');

		$this->enginesPerProfile = $model->getPostProcessingEnginePerProfile();

		$this->showBrowserDownload = $this->container->appConfig->get('showBrowserDownload', 0) == 1;

		if (function_exists('ini_get'))
		{
			$this->phpErrorDisplay = (ini_get('display_errors') ?: 0) ? 1 : 0;
		}

		// "Show warning first" download button.
		$doc = $this->container->application->getDocument();
		$doc->lang('COM_AKEEBA_BUADMIN_LOG_DOWNLOAD_CONFIRM', false);
		$document->addScriptOptions('akeeba.Manage.downloadURL', $router->route('index.php?option=com_akeeba&view=Manage&task=download&format=raw'));

		$buttons = [
			'view'        => [
				'task'    => 'main',
				'title'   => 'COM_AKEEBA_BUADMIN_LOG_EDITCOMMENT',
				'class'   => 'akeeba-btn--grey',
				'onClick' => 'akeeba.System.submitForm(\'showComment\')',
				'icon'    => 'akion-edit',
			],
			'discover'    => [
				'task'  => 'main',
				'title' => 'COM_AKEEBA_DISCOVER',
				'class' => 'akeeba-btn--grey',
				'url'   => $router->route('index.php?view=discover'),
				'icon'  => 'akion-search',
			],
			's3import'    => [
				'task'  => 'main',
				'title' => 'COM_AKEEBA_S3IMPORT',
				'class' => 'akeeba-btn--grey',
				'url'   => $router->route('index.php?view=s3import'),
				'icon'  => 'akion-ios-cloud-download',
			],
			'restore'     => [
				'task'    => '',
				'title'   => 'COM_AKEEBA_BUADMIN_LABEL_RESTORE',
				'class'   => 'akeeba-btn--teal',
				'onClick' => 'akeeba.System.submitForm(\'restore\')',
				'icon'    => 'akion-android-open',
			],
			'deletefiles' => [
				'task'    => 'main',
				'title'   => 'COM_AKEEBA_BUADMIN_LABEL_DELETEFILES',
				'class'   => 'akeeba-btn--orange',
				'onClick' => 'akeeba.System.submitForm(\'deleteFiles\')',
				'icon'    => 'akion-trash-a',
			],
			'delete'      => [
				'task'    => '',
				'title'   => 'SOLO_MANAGE_BTN_DELETE',
				'class'   => 'akeeba-btn--red',
				'onClick' => 'akeeba.System.submitForm(\'remove\')',
				'icon'    => 'akion-trash-b',
			],
		];

		if (!$this->privileges['configure'])
		{
			unset($buttons['discover']);
			unset($buttons['s3import']);
			unset($buttons['restore']);
		}
		elseif (!AKEEBABACKUP_PRO)
		{
			unset($buttons['restore']);
			unset($buttons['discover']);
			unset($buttons['s3import']);
		}

		if (!$this->privileges['backup'])
		{
			unset($buttons['delete']);
			unset($buttons['deletefiles']);
		}

		$toolbar = $document->getToolbar();

		foreach ($buttons as $button)
		{
			if (empty($button['task']) || ($button['task'] == $task))
			{
				$toolbar->addButtonFromDefinition($button);
			}
		}

		// Should I show the prompt for the configuration wizard?
		$this->promptForBackupRestoration = Platform::getInstance()->get_platform_configuration_option('show_howtorestoremodal', 1);

		// "Show warning first" download button.
		$confirmationText = Escape::escapeJS(Text::_('COM_AKEEBA_BUADMIN_LOG_DOWNLOAD_CONFIRM'));
		$confirmationText = str_replace('\\\\n', '\\n', $confirmationText);
		$newURL           = Escape::escapeJS($router->route('index.php?view=manage&task=download&format=raw'));
		$js               = <<<JS

;// This comment is intentionally put here to prevent badly written plugins from causing a Javascript error
// due to missing trailing semicolon and/or newline in their code.
function confirmDownloadButton()
{
	var answer = confirm(akeeba.System.Text._("COM_AKEEBA_BUADMIN_LOG_DOWNLOAD_CONFIRM"));
	
	if (answer)
	{
		akeeba.System.submitForm('adminForm', 'download');
	}
}

function confirmDownload(id, part)
{
	var answer = confirm(akeeba.System.Text._("COM_AKEEBA_BUADMIN_LOG_DOWNLOAD_CONFIRM"));
	var newURL = '$newURL';
	if (answer)
	{
		var query = 'id=' + id;

		if (part != '')
		{
			query += '&part=' + part
		}

		window.location = newURL + (newURL.indexOf('?') != -1 ? '&' : '?') + query;
	}
}

akeeba.System.documentReady(function() {
	setTimeout(function() {
		akeeba.Tooltip.enableFor(document.querySelectorAll('.akeebaCommentPopover'), false);
	}, 500);
});

JS;

		$this->getContainer()->application->getDocument()->addScriptDeclaration($js);

		// All done, show the page!
		return true;
	}

	public function onBeforeShowComment()
	{
		$model = $this->getModel();

		$this->record_id = $model->getState('id', -1, 'int');
		$this->record    = Platform::getInstance()->get_statistics($this->record_id);

		$buttons = [
			[
				'title'   => 'SOLO_BTN_SAVECLOSE',
				'class'   => 'akeeba-btn--green',
				'onClick' => 'akeeba.System.submitForm(\'save\')',
				'icon'    => 'akion-checkmark-circled',
			],
			[
				'title'   => 'SOLO_BTN_CANCEL',
				'class'   => 'akeeba-btn--orange',
				'onClick' => 'akeeba.System.submitForm(\'cancel\')',
				'icon'    => 'akion-close-circled',
			],
		];

		$toolbar = $this->container->application->getDocument()->getToolbar();

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		return true;
	}

	/**
	 * Translates the internal backup type (e.g. cli) to a human readable string
	 *
	 * @param   string  $recordType  The internal backup type
	 *
	 * @return  string
	 */
	public function translateBackupType($recordType)
	{
		static $backup_types = null;

		if (!is_array($backup_types))
		{
			// Load a mapping of backup types to textual representation
			$scripting    = Factory::getEngineParamsProvider()->loadScripting();
			$backup_types = [];
			foreach ($scripting['scripts'] as $key => $data)
			{
				$backup_types[$key] = Text::_($data['text']);
			}
		}

		if (array_key_exists($recordType, $backup_types))
		{
			return $backup_types[$recordType];
		}

		return '&ndash;';
	}

	/**
	 * Get the start time and duration of a backup record
	 *
	 * @param   array  $record  A backup record
	 *
	 * @return  array  array(startTimeAsString, durationAsString)
	 */
	protected function getTimeInformation($record)
	{
		$utcTimeZone = new DateTimeZone('UTC');
		try
		{
			$startTime = $this->container->dateFactory($record['backupstart'], $utcTimeZone);
		}
		catch (\Throwable $e)
		{
			$startTime = null;
		}

		try
		{
			$endTime = $this->container->dateFactory($record['backupend'], $utcTimeZone);
		}
		catch (\Throwable $e)
		{
			$endTime = null;
		}

		$duration = (is_null($startTime) || is_null($endTime)) ? 0 : $endTime->toUnix() - $startTime->toUnix();

		if ($duration > 0)
		{
			$seconds  = $duration % 60;
			$duration = $duration - $seconds;

			$minutes  = ($duration % 3600) / 60;
			$duration = $duration - $minutes * 60;

			$hours    = $duration / 3600;
			$duration = sprintf('%02d', $hours) . ':' . sprintf('%02d', $minutes) . ':' . sprintf('%02d', $seconds);
		}
		else
		{
			$duration = '';
		}

		$tz      = $this->container->appConfig->get('timezone', 'UTC');
		$user    = $this->container->userManager->getUser();
		$user_tz = $user->getParameters()->get('timezone', null);

		if (!empty($user_tz))
		{
			$tz = $user_tz;
		}

		$tzObject = new DateTimeZone($tz);

		if ($startTime !== null)
		{
			$startTime->setTimezone($tzObject);
		}

		$timeZoneSuffix = '';

		if (!empty($this->timeZoneFormat))
		{
			$timeZoneSuffix = $startTime->format($this->timeZoneFormat, $this->useLocalTime);
		}

		return [
			is_null($startTime) ? '&nbsp;' : $startTime->format($this->dateFormat, $this->useLocalTime),
			$duration,
			$timeZoneSuffix,
		];
	}

	/**
	 * Get the class and icon for the backup status indicator
	 *
	 * @param   array  $record  A backup record
	 *
	 * @return  array  array(class, icon)
	 */
	protected function getStatusInformation($record)
	{
		$statusClass = '';

		switch ($record['meta'])
		{
			case 'ok':
				$statusIcon  = 'akion-checkmark';
				$statusClass = 'akeeba-label--green';
				break;
			case 'pending':
				$statusIcon  = 'akion-play';
				$statusClass = 'akeeba-label--orange';
				break;
			case 'fail':
				$statusIcon  = 'akion-android-cancel';
				$statusClass = 'akeeba-label--red';
				break;
			case 'remote':
				$statusIcon  = 'akion-cloud';
				$statusClass = 'akeeba-label--teal';
				break;
			default:
				$statusIcon  = 'akion-trash-a';
				$statusClass = 'akeeba-label--grey';
				break;
		}

		return [$statusClass, $statusIcon];
	}

	/**
	 * Get the profile name for the backup record (or "–" if the profile no longer exists)
	 *
	 * @param   array  $record  A backup record
	 *
	 * @return  string
	 */
	protected function getProfileName($record)
	{
		$profileName = '&mdash;';

		if (isset($this->profiles[$record['profile_id']]))
		{
			$profileName = $this->escape($this->profiles[$record['profile_id']]->description);

			return $profileName;
		}

		return $profileName;
	}

	/**
	 * Returns the origin's translated name and the appropriate icon class
	 *
	 * @param   array  $record  A backup record
	 *
	 * @return  array  array(originTranslation, iconClass)
	 */
	protected function getOriginInformation($record)
	{
		$originLanguageKey = 'COM_AKEEBA_BUADMIN_LABEL_ORIGIN_' . $record['origin'];
		$originDescription = Text::_($originLanguageKey);

		switch (strtolower($record['origin']))
		{
			case 'backend':
				$originIcon = 'akion-android-desktop';
				break;

			case 'frontend':
				$originIcon = 'akion-ios-world';
				break;

			case 'json':
				$originIcon = 'akion-android-cloud';
				break;

			case 'cli':
				$originIcon = 'akion-ios-paper-outline';
				break;

			case 'wpcron':
				$originIcon = 'akion-ios-alarm';
				break;

			default:
				$originIcon = 'akion-help';
				break;
		}

		if (empty($originLanguageKey) || ($originDescription == $originLanguageKey))
		{
			$originDescription = '&ndash;';
			$originIcon        = 'akion-help';

			return [$originDescription, $originIcon];
		}

		return [$originDescription, $originIcon];
	}

	private function _getFilters()
	{
		$filters = [];
		$task    = $this->container->segment->get('solo_manage_task', 'main');

		if ($this->lists->fltDescription)
		{
			$filters[] = [
				'field'   => 'description',
				'operand' => 'LIKE',
				'value'   => $this->lists->fltDescription,
			];
		}

		if ($this->lists->fltFrom && $this->lists->fltTo)
		{
			$filters[] = [
				'field'   => 'backupstart',
				'operand' => 'BETWEEN',
				'value'   => $this->lists->fltFrom,
				'value2'  => $this->lists->fltTo,
			];
		}
		elseif ($this->lists->fltFrom)
		{
			$filters[] = [
				'field'   => 'backupstart',
				'operand' => '>=',
				'value'   => $this->lists->fltFrom,
			];
		}
		elseif ($this->lists->fltTo)
		{
			$filters[] = [
				'field'   => 'backupstart',
				'operand' => '<=',
				'value'   => $this->lists->fltTo,
			];
		}

		if ($this->lists->fltOrigin)
		{
			$filters[] = [
				'field'   => 'origin',
				'operand' => '=',
				'value'   => $this->lists->fltOrigin,
			];
		}

		if ($this->lists->fltProfile)
		{
			$filters[] = [
				'field'   => 'profile_id',
				'operand' => '=',
				'value'   => (int) $this->lists->fltProfile,
			];
		}

		if ($this->lists->fltFrozen == 1)
		{
			$filters[] = [
				'field'   => 'frozen',
				'operand' => '=',
				'value'   => 1,
			];
		}
		elseif ($this->lists->fltFrozen == 2)
		{
			$filters[] = [
				'field'   => 'frozen',
				'operand' => '=',
				'value'   => 0,
			];
		}

		if (empty($filters))
		{
			$filters = null;
		}

		return $filters;
	}

	private function _getOrdering()
	{
		$order = [
			'by'    => $this->lists->order,
			'order' => strtoupper($this->lists->order_Dir),
		];

		return $order;
	}

} 
