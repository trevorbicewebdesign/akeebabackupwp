<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var \Solo\View\Sysconfig\Html $this */

$config = $this->getContainer()->appConfig;

/**
 * Remember to update wpcli/Command/Sysconfig.php in the WordPress application whenever this file changes.
 */
?>
<div class="akeeba-form-group">
    <label for="desktop_notifications">
        @lang('COM_AKEEBA_CONFIG_DESKTOP_NOTIFICATIONS_LABEL')
    </label>
    <div class="akeeba-toggle">
        @html('fefselect.booleanList', 'options[desktop_notifications]', ['id' => 'desktop_notifications', 'forToggle' => 1, 'colorBoolean' => 1], $config->get('options.desktop_notifications', 0))
    </div>
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_DESKTOP_NOTIFICATIONS_DESC')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="push_preference">
        @lang('COM_AKEEBA_CONFIG_PUSH_PREFERENCE_LABEL')
    </label>
    <div class="akeeba-toggle">
        @html('fefselect.booleanList', 'options[push_preference]', ['id' => 'push_preference', 'forToggle' => 1, 'colorBoolean' => 1], $config->get('options.push_preference', 0))
    </div>
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_PUSH_PREFERENCE_DESC')
    </p>
</div>

<div class="akeeba-form-group" {{ $this->showOn('options[push_preference]:1') }}>
    <label for="push_apikey">
        @lang('COM_AKEEBA_CONFIG_PUSH_APIKEY_LABEL')
    </label>
    <input type="text" name="options[push_apikey]" id="push_apikey"
           placeholder="@lang('COM_AKEEBA_CONFIG_PUSH_APIKEY_LABEL')"
           value="{{ $config->get('options.push_apikey') }}">
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_PUSH_APIKEY_DESC')
    </p>
</div>
