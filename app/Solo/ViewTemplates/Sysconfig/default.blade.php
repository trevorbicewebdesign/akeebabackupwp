<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var \Solo\View\Sysconfig\Html $this */

$inCMS = $this->getContainer()->segment->get('insideCMS', false);
?>

<form action="@route('index.php?view=sysconfig')" method="POST" id="adminForm"
      class="akeeba-form--horizontal" role="form">
    <div class="akeeba-tabs">
        <label for="sysconfigAppSetup" class="active">
            <span class="akion-ios-cog"></span>
	        @lang('SOLO_SETUP_LBL_APPSETUP')
        </label>
        <section id="sysconfigAppSetup">
	        @include('Sysconfig/appsetup')
        </section>

    @if ($inCMS && AKEEBABACKUP_PRO)
        <label for="sysconfigBackupOnUpdate">
            <span class="akion-refresh"></span>
			@lang('SOLO_SETUP_LBL_BACKUPONUPDATE')
        </label>
        <section id="sysconfigBackupOnUpdate">
			@include('Sysconfig/backuponupdate')
        </section>
    @endif

        <label for="sysconfigBackupChecks">
            <span class="akion-android-list"></span>
	        @lang('SOLO_SYSCONFIG_BACKUP_CHECKS')
        </label>
        <section id="sysconfigBackupChecks">
	        @include('Sysconfig/backupchecks')
        </section>

        <label for="sysconfigPublicAPI">
            <span class="akion-android-globe"></span>
	        @lang('SOLO_SYSCONFIG_FRONTEND')
        </label>
        <section id="sysconfigPublicAPI">
	        @include('Sysconfig/publicapi')
        </section>

        <label for="sysconfigPushNotifications">
            <span class="akion-chatbubble"></span>
	        @lang('SOLO_SYSCONFIG_PUSH')
        </label>
        <section id="sysconfigPushNotifications">
	        @include('Sysconfig/push')
        </section>

        <label for="sysconfigUpdate">
            <span class="akion-refresh"></span>
	        @lang('SOLO_SYSCONFIG_UPDATE')
        </label>
        <section id="sysconfigUpdate">
	        @include('Sysconfig/update')
        </section>

        <label for="sysconfigEmail">
            <span class="akion-email"></span>
	        @lang('SOLO_SYSCONFIG_EMAIL')
        </label>
        <section id="sysconfigEmail">
	        @include('Sysconfig/email')
        </section>

	    @if (!$inCMS)
        <label for="sysconfigDatabase">
            <span class="akion-ios-box"></span>
	        @lang('SOLO_SETUP_SUBTITLE_DATABASE')
        </label>
        <section id="sysconfigDatabase">
	        @include('Sysconfig/database')
        </section>
	    @endif

        @if (AKEEBABACKUP_PRO)
        <label for="sysconfigOauth2">
            <span class="akion-ios-locked"></span>
            @lang('COM_AKEEBA_CONFIG_OAUTH2_HEADER_LABEL')
        </label>
        <section id="sysconfigOauth2">
            @include('Sysconfig/oauth2')
        </section>
        @endif
    </div>

    <div class="akeeba-hidden-fields-container">
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="token" value="@token()">
    </div>
</form>

