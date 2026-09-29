<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Engine\Factory;
use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var \Solo\View\Backup\Html $this */

$router = $this->getContainer()->router;
$config = Factory::getConfiguration();

$formstyle 	  = $this->hasErrors ? 'style="display: none"' : '';

?>
{{-- Configuration Wizard pop-up --}}
@if($this->promptForConfigurationWizard)
	@include('Configuration/confwiz_modal')
@endif

<div id="backup-setup" class="akeeba-panel--primary">
    <header class="akeeba-block-header">
        <h3>
	        @lang('COM_AKEEBA_BACKUP_HEADER_STARTNEW')
        </h3>
    </header>

	@if($this->hasWarnings && !$this->unwriteableOutput)
		<div id="quirks" class="akeeba-block--{{ $this->hasErrors ? 'failure' : 'warning' }}">
			<h3 class="alert-heading">
				@lang('COM_AKEEBA_BACKUP_LABEL_DETECTEDQUIRKS')
			</h3>
			<p>
				@lang('COM_AKEEBA_BACKUP_LABEL_QUIRKSLIST')
			</p>
			{{ $this->warningsCell }}

		</div>
	@endif

	@if($this->unwriteableOutput)
		<div id="akeeba-fatal-outputdirectory" class="akeeba-block--failure">
			<h3>
				@lang('COM_AKEEBA_BACKUP_ERROR_UNWRITABLEOUTPUT_' . ($this->autoStart ? 'AUTOBACKUP' : 'NORMALBACKUP'))
			</h3>
			<p>
				@sprintf('COM_AKEEBA_BACKUP_ERROR_UNWRITABLEOUTPUT_COMMON', $router->route('index.php?view=configuration'), 'https://www.akeeba.com/warnings/q001.html')
			</p>
		</div>
	@endif

    <form action="<?php echo $router->route('index.php?view=backup')?>" method="post" name="profileForm"
          id="profileForm" autocomplete="off" class="akeeba-formstyle-reset akeeba-panel--information akeeba-form--inline">

		<div class="akeeba-form-group">
			<label>
				@lang('COM_AKEEBA_CPANEL_PROFILE_TITLE'): #{{ $this->profileId }}

			</label>
			@html('select.genericlist', $this->profileList, 'profile', ['id' => 'profileId', 'list.select' => $this->profileId])
		</div>

		<div class="akeeba-form-group--actions">
			<button class="akeeba-btn--grey" id="comAkeebaBackupFlipProfile">
				<span class="akion-refresh"></span>
				@lang('COM_AKEEBA_CPANEL_PROFILE_BUTTON')
			</button>
		</div>

        <div class="akeeba-form-group--actions akeeba-hidden-fields-container">
            <input type="hidden" name="returnurl" value="{{{ $this->returnURL }}}" />
			<input type="hidden" name="description" id="flipDescription" value=""/>
			<input type="hidden" name="comment" id="flipComment" value=""/>
            <input type="hidden" name="token" value="@token(true)" />
        </div>
    </form>

    <div class="akeeba-block--info small">
	    @lang('COM_AKEEBA_BACKUP_LBL_EXPLAIN_PROFILES')
    </div>

    <form id="dummyForm" style="display: {{ $this->unwriteableOutput ? 'none' : 'block' }};" class="akeeba-form--horizontal" role="form">
		<div class="akeeba-form-group">
			<label for="description">
				@lang('COM_AKEEBA_BACKUP_LABEL_DESCRIPTION')
			</label>
            <input type="text" name="description" value="{{{ empty($this->description) ? $this->defaultDescription : $this->description }}}"
                   maxlength="255" size="80" id="backup-description"
                   autocomplete="off" />
            <span class="akeeba-help-text">@lang('COM_AKEEBA_BACKUP_LABEL_DESCRIPTION_HELP')</span>
		</div>

		<div class="akeeba-form-group">
			<label for="comment">
				@lang('COM_AKEEBA_BACKUP_LABEL_COMMENT')
			</label>
            <textarea name="comment" id="comment" rows="5" cols="73" autocomplete="off">{{ $this->comment }}</textarea>
            <span class="akeeba-help-text">@lang('COM_AKEEBA_BACKUP_LABEL_COMMENT_HELP')</span>
		</div>
		<div class="akeeba-form-group--pull-right">
			<div class="akeeba-form-group--actions">
				<button class="akeeba-btn--primary" id="backup-start">
					<span class="akion-play"></span>
					@lang('COM_AKEEBA_BACKUP_LABEL_START')
				</button>
				<span class="akeeba-btn--orange" id="backup-default">
					<span class="akion-refresh"></span>
					@lang('COM_AKEEBA_BACKUP_LABEL_RESTORE_DEFAULT')
				</span>
				<a class="akeeba-btn--red--small" id="backup-cancel" href="<?php echo $router->route('index.php?view=main') ?>">
					<span class="akion-chevron-left"></span>
					@lang('COM_AKEEBA_CONTROLPANEL')
				</a>
			</div>
		</div>
	</form>
</div>

{{-- Warning for having set an ANGIE password --}}
<div id="angie-password-warning" class="akeeba-block--warning" style="display: none">
	<h3>@lang('COM_AKEEBA_BACKUP_ANGIE_PASSWORD_WARNING_HEADER')</h3>

	<p>@lang('COM_AKEEBA_BACKUP_ANGIE_PASSWORD_WARNING_1')</p>
	<p>@lang('COM_AKEEBA_BACKUP_ANGIE_PASSWORD_WARNING_2')</p>
</div>

{{-- Backup in progress --}}
<div id="backup-progress-pane" style="display: none">
@if($this->backupOnUpdate)
    <div class="akeeba-block--info">
        @lang('SOLO_BACKUP_BACKUPONUPDATE_INFO')
    </div>
@endif
	<div class="akeeba-block--info">
		@lang('COM_AKEEBA_BACKUP_TEXT_BACKINGUP')
	</div>

    <div class="akeeba-panel--primary">
        <header class="akeeba-block-header">
            <h3>
	            @lang('COM_AKEEBA_BACKUP_LABEL_PROGRESS')
            </h3>
        </header>

		<div id="backup-progress-content">
			<div id="backup-steps">
			</div>
			<div id="backup-status" class="backup-steps-container">
				<div id="backup-step"></div>
				<div id="backup-substep"></div>
			</div>
			<div id="backup-percentage" class="akeeba-progress">
				<div class="bar akeeba-progress-fill" role="progressbar" style="width: 0"></div>
			</div>
			<div id="response-timer">
				<div class="color-overlay"></div>
				<div class="text"></div>
			</div>
		</div>
		<span id="ajax-worker"></span>
	</div>

	@if (!AKEEBABACKUP_PRO)
	<div>
		<p>
			<em>@lang('COM_AKEEBA_BACKUP_LBL_UPGRADENAG')</em>
		</p>
	</div>
	@endif
</div>

{{-- Backup complete --}}
<div id="backup-complete" style="display: none">
	<div class="akeeba-panel--success">
        <header class="akeeba-block-header">
            <h3>
				@if(empty($this->returnURL))
					@lang('COM_AKEEBA_BACKUP_HEADER_BACKUPFINISHED')
				@else
					@lang('COM_AKEEBA_BACKUP_HEADER_BACKUPWITHRETURNURLFINISHED')
				@endif
            </h3>
        </header>

		<div id="finishedframe">
			<p>
				@if(empty($this->returnURL))
					@lang('COM_AKEEBA_BACKUP_TEXT_CONGRATS')
				@else
					@lang('COM_AKEEBA_BACKUP_TEXT_PLEASEWAITFORREDIRECTION')
				@endif
			</p>

			@if(empty($this->returnURL))
				<a class="akeeba-btn--primary--big" href="@route('index.php?view=manage')">
					<span class="akion-ios-list"></span>
					@lang('COM_AKEEBA_BUADMIN')
				</a>
				<a class="akeeba-btn--grey" id="ab-viewlog-success" href="@route('index.php?view=log&latest=1')">
					<span class="akion-ios-search-strong"></span>
					@lang('COM_AKEEBA_LOG')
				</a>
            @elseif(!empty($this->returnURL) && !empty($this->returnForm))
                <form id="returnForm" action="<?php echo html_entity_decode($this->returnURL)?>" method="post">
                    <?php
                    $fields = json_decode(base64_decode($this->returnForm));
					foreach ($fields as $field_id => $field_value)
					{
                        echo '<input type="hidden" name="'.$field_id.'" id="'.$field_id.'" value="'.$field_value.'" />';
                    }
                    ?>
                </form>
			@endif
		</div>
	</div>
</div>

{{-- Backup warnings --}}
<div id="backup-warnings-panel" style="display:none">
	<div class="akeeba-panel--warning">
        <header class="akeeba-block-header">
            <h3>@lang('COM_AKEEBA_BACKUP_LABEL_WARNINGS')</h3>
        </header>
		<div id="warnings-list">
		</div>
	</div>
</div>

{{-- Backup retry after error --}}
<div id="retry-panel" style="display: none">
    <div class="akeeba-panel--warning">
        <header class="akeeba-block-header">
            <h3>
		        @lang('COM_AKEEBA_BACKUP_HEADER_BACKUPRETRY')
            </h3>
        </header>
        <div id="retryframe">
            <p>@lang('COM_AKEEBA_BACKUP_TEXT_BACKUPFAILEDRETRY')</p>
            <p>
                <strong>
					@lang('COM_AKEEBA_BACKUP_TEXT_WILLRETRY')
                    <span id="akeeba-retry-timeout">0</span>
					@lang('COM_AKEEBA_BACKUP_TEXT_WILLRETRYSECONDS')
                </strong>
                <br/>
                <button class="akeeba-btn--red--small" id="comAkeebaBackupResumeCancel">
                    <span class="akion-android-cancel"></span>
					@lang('COM_AKEEBA_MULTIDB_GUI_LBL_CANCEL')
                </button>
                <button class="akeeba-btn--green--small" id="comAkeebaBackupResume">
                    <span class="akion-ios-redo"></span>
					@lang('COM_AKEEBA_BACKUP_TEXT_BTNRESUME')
                </button>
            </p>

            <p>@lang('COM_AKEEBA_BACKUP_TEXT_LASTERRORMESSAGEWAS')</p>
            <p id="backup-error-message-retry">
            </p>
        </div>
    </div>
</div>

{{-- Backup error (halt) --}}
<div id="error-panel" style="display: none">
	<div class="akeeba-panel--red">
        <header class="akeeba-block-header">
            <h3>
		        @lang('COM_AKEEBA_BACKUP_HEADER_BACKUPFAILED')
            </h3>
        </header>

        <div id="errorframe">
			<p>
				@lang('COM_AKEEBA_BACKUP_TEXT_BACKUPFAILED')
			</p>
			<p id="backup-error-message">
			</p>

			<p>
				@lang('COM_AKEEBA_BACKUP_TEXT_READLOGFAILPRO')
			</p>

            <div class="akeeba-block--info" id="error-panel-troubleshooting">
				<p>
					@if(AKEEBABACKUP_PRO)
					    @lang('COM_AKEEBA_BACKUP_TEXT_RTFMTOSOLVEPRO')
                    @endif

					@sprintf('COM_AKEEBA_BACKUP_TEXT_RTFMTOSOLVE', 'https://www.akeeba.com/documentation/troubleshooter/abwpbackup.html?utm_source=akeeba_backup&utm_campaign=backuperrorlink')
				</p>
				<p>
					@if(AKEEBABACKUP_PRO)
						@sprintf('COM_AKEEBA_BACKUP_TEXT_SOLVEISSUE_PRO', 'https://www.akeeba.com/support.html?utm_source=akeeba_backup&utm_campaign=backuperrorpro')
					@else
						@sprintf('COM_AKEEBA_BACKUP_TEXT_SOLVEISSUE_CORE', 'https://www.akeeba.com/subscribe.html?utm_source=akeeba_backup&utm_campaign=backuperrorcore','https://www.akeeba.com/support.html?utm_source=akeeba_backup&utm_campaign=backuperrorcore')
					@endif

					@sprintf('COM_AKEEBA_BACKUP_TEXT_SOLVEISSUE_LOG', 'index.php?view=log&latest=1')
				</p>
			</div>

			@if (AKEEBABACKUP_PRO)
			<a id="ab-alice-error" class="akeeba-btn--green" href="@route('index.php?view=alice')">
				<span class="akion-medkit"></span>
				@lang('COM_AKEEBA_BACKUP_ANALYSELOG')
			</a>
			@endif

            <a class="akeeba-btn--primary" href="https://www.akeeba.com/documentation/troubleshooter/abwpbackup.html?utm_source=akeeba_backup&utm_campaign=backuperrorbutton">
				<span class="akion-ios-book"></span>
				@lang('COM_AKEEBA_BACKUP_TROUBLESHOOTINGDOCS')
			</a>

            <a id="ab-viewlog-error" class="akeeba-btn-grey" href="@route('index.php?view=log&latest=1')">
				<span class="akion-ios-search-strong"></span>
				@lang('COM_AKEEBA_LOG')
			</a>
		</div>
	</div>
</div>
