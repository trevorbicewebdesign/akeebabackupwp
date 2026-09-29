<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Solo\View\Migreight\Html $this */

?>

@if (empty($this->affectedProfiles) && empty($this->migratedFolders))
    @include('Migreight/default_not_needed')
@else
    <div class="akeeba-panel--teal">
        <header class="akeeba-block-header">
            <h3>
                @lang('COM_AKEEBA_MIGREIGHT_NEEDED_TITLE')
            </h3>
        </header>

        <p>
            @lang('COM_AKEEBA_MIGREIGHT_NEEDED_WHY')
        </p>
        <p>
            @sprintf('COM_AKEEBA_MIGREIGHT_NEEDED_WHAT', rtrim(WP_CONTENT_DIR, '/\\') . '/backups')
        </p>

        @if (count($this->affectedProfiles))
            <p>
                @plural('COM_AKEEBA_MIGREIGHT_NEEDED_PROFILES_N', count($this->affectedProfiles))
            </p>
            <ul style="list-style: disc; padding-left: revert">
                @foreach($this->affectedProfiles as $id => $title)
                    <li>
                        #{{ (int)$id }}. {{{ $title }}}
                    </li>
                @endforeach
            </ul>
        @endif

        @if (count($this->migratedFolders))
            <p>
                @plural('COM_AKEEBA_MIGREIGHT_NEEDED_FOLDERS_N', count($this->migratedFolders))
            </p>
            <ul style="list-style: disc; padding-left: revert">
                @foreach($this->migratedFolders as $from => $to)
                    <li>
                        @sprintf('COM_AKEEBA_MIGREIGHT_NEEDED_FOLDER_MOVE', $from, rtrim(WP_CONTENT_DIR, '/\\') . '/backups' . (empty($to) ? '' : '/' . $to))
                    </li>
                @endforeach
            </ul>
        @endif

        <p style="margin-top: 2em">
            <a href="@route('index.php?view=migreight&task=start')"
               class="akeeba-btn--primary--big">
                <span class="akion-play" aria-hidden="true"></span>
                @lang('COM_AKEEBA_MIGREIGHT_NEEDED_START')
            </a>
            <a href="@route('index.php')"
               class="akeeba-btn--red--small">
                <span class="akion-load-a" aria-hidden="true"></span>
                @lang('COM_AKEEBA_MIGREIGHT_NEEDED_LATER')
            </a>
        </p>
    </div>
@endif