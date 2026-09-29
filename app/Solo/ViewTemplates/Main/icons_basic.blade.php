<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

// Used for type hinting
/** @var \Solo\View\Main\Html $this */

$router = $this->container->router;

?>
<section class="akeeba-panel--info">
    <header class="akeeba-block-header">
        <h3>
            <span class="akion-home"></span>
            @lang('SOLO_MAIN_LBL_HEAD_BACKUPOPS')
        </h3>
    </header>

    <div class="akeeba-grid">
        @if ($this->canAccess('backup', 'main'))
            <a class="akeeba-action--green" href="@route('index.php?view=backup')">
                <span class="akion-play"></span>
                @lang('COM_AKEEBA_BACKUP')
            </a>
        @endif

        @if (AKEEBABACKUP_PRO && $this->canAccess('transfer', 'main'))
            <a class="akeeba-action--green" href="@route('index.php?view=transfer')">
                <span class="akion-android-open"></span>
                @lang('COM_AKEEBA_TRANSFER')
            </a>
        @endif

        @if ($this->canAccess('manage', 'main'))
            <a class="akeeba-action--teal" href="@route('index.php?view=manage')">
                <span class="akion-ios-list"></span>
                <span class="title">@lang('COM_AKEEBA_BUADMIN')</span>
            </a>
        @endif
        @if ($this->canAccess('configuration', 'main'))
            <a class="akeeba-action--teal" href="@route('index.php?view=configuration')">
                <span class="akion-ios-gear"></span>
                <span class="title">@lang('COM_AKEEBA_CONFIG')</span>
            </a>
        @endif
        @if ($this->canAccess('profiles', 'main'))
            <a class="akeeba-action--teal" href="@route('index.php?view=profiles')">
                <span class="akion-person-stalker"></span>
                <span class="title">@lang('COM_AKEEBA_PROFILES')</span>
            </a>
        @endif

        @if (!$this->needsDownloadId && $this->canAccess('update', 'main') && !$this->getContainer()->segment->get('insideCMS', false))
            <a class="akeeba-action--orange" href="@route('index.php?view=update')" id="soloUpdateContainer">
                <span class="akion-checkmark-circled" id="soloUpdateIcon"></span>
                <span id="soloUpdateAvailable" style="display: none">
                        @lang('SOLO_UPDATE_SUBTITLE_UPDATEAVAILABLE')
                </span>
                <span id="soloUpdateUpToDate" style="display: none">
                        @lang('SOLO_UPDATE_SUBTITLE_UPTODATE')
                </span>
            </a>
        @endif
    </div>
</section>
