<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var \Solo\View\Sysconfig\Html $this */

$config = $this->getContainer()->appConfig;
$inCMS  = $this->container->segment->get('insideCMS', false);

global $wp_version;

/**
 * Remember to update wpcli/Command/Sysconfig.php in the WordPress application whenever this file changes.
 */
?>

@if($inCMS && version_compare($wp_version, '5.2', 'ge'))
    <div class="akeeba-form-group">
        <label for="options_backup_age_show">
            @lang('SOLO_SYSCONFIG_LBL_BACKUP_AGE_SHOW')
        </label>
        <div class="akeeba-toggle">
            @html('fefselect.booleanList', 'options[backup_age_show]', ['forToggle' => 1, 'colorBoolean' => 1], $config->get('options.backup_age_show', 1))

        </div>
        <p class="akeeba-help-text">
            @lang('SOLO_SYSCONFIG_LBL_BACKUP_AGE_SHOW_HELP')
        </p>
    </div>

    <div class="akeeba-form-group" {{ $this->showOn('options[backup_age_show]:1') }}>
        <label for="options_backup_age_failed">
            @lang('SOLO_SYSCONFIG_LBL_BACKUP_AGE_FAILED')
        </label>
        <div class="akeeba-toggle">
            @html('fefselect.booleanList', 'options[backup_age_failed]', ['forToggle' => 1, 'colorBoolean' => 1], $config->get('options.backup_age_failed', 0))

        </div>
        <p class="akeeba-help-text">
            @lang('SOLO_SYSCONFIG_LBL_BACKUP_AGE_FAILED_HELP')
        </p>
    </div>

    <div class="akeeba-form-group" {{ $this->showOn('options[backup_age_show]:1') }}>
        <label for="options_backup_age_max_hours">
            @lang('SOLO_SYSCONFIG_LBL_BACKUP_AGE_MAX_HOURS')
        </label>
        <input type="number"
               name="options[backup_age_max_hours]"
               id="options_backup_age_max_hours"
               value="{{ $config->get('options.backup_age_max_hours', 24) }}"
               min="1"
               max="8784"
        >
        <p class="akeeba-help-text">
            @lang('SOLO_SYSCONFIG_LBL_BACKUP_AGE_MAX_HOURS_HELP')
        </p>
    </div>

    <hr>
@endif

<div class="akeeba-form-group">
    <label for="failure_timeout">
        @lang('COM_AKEEBA_CONFIG_FAILURE_TIMEOUT_LABEL')
    </label>
    <input type="text" name="options[failure_timeout]" id="failure_timeout"
           placeholder="@lang('COM_AKEEBA_CONFIG_FAILURE_TIMEOUT_LABEL')"
           value="{{ $config->get('options.failure_timeout', 180) }}">
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_FAILURE_TIMEOUT_DESC')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="failure_email_address">
        @lang('COM_AKEEBA_CONFIG_FAILURE_EMAILADDRESS_LABEL')
    </label>
    <input type="text" name="options[failure_email_address]" id="failure_email_address"
           placeholder="@lang('COM_AKEEBA_CONFIG_FAILURE_EMAILADDRESS_LABEL')"
           value="{{ $config->get('options.failure_email_address') }}">
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_FAILURE_EMAILADDRESS_DESC')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="failure_email_subject">
        @lang('COM_AKEEBA_CONFIG_FAILURE_EMAILSUBJECT_LABEL')
    </label>
    <input type="text" name="options[failure_email_subject]" id="failure_email_subject"
           placeholder="@lang('COM_AKEEBA_CONFIG_FAILURE_EMAILSUBJECT_LABEL')"
           value="{{ $config->get('options.failure_email_subject') }}">
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_FAILURE_EMAILSUBJECT_DESC')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="failure_email_body">
        @lang('COM_AKEEBA_CONFIG_FAILURE_EMAILBODY_LABEL')
    </label>
    <textarea type="text" name="options[failure_email_body]" id="failure_email_body"
              placeholder="@lang('COM_AKEEBA_CONFIG_FAILURE_EMAILBODY_LABEL')"
              rows="15">{{ $config->get('options.failure_email_body') }}</textarea>
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_FAILURE_EMAILBODY_DESC')
    </p>
</div>
