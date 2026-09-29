<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var   \Solo\View\Update\Html  $this */

$releaseNotes = $this->updateInfo->get('releaseNotes');
$infoUrl = $this->updateInfo->get('infoUrl');
$requirePlatformName = $this->getContainer()->segment->get('platformNameForUpdates', 'php');

?>

@if (!empty($releaseNotes))
<div class="modal fade" id="releaseNotesPopup" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none">
    <div class="akeeba-renderer-fef {{ ($this->getContainer()->appConfig->get('darkmode', -1) == 1) ? 'akeeba-renderer-fef--dark' : '' }}">
        <h4 class="modal-title" id="myModalLabel">
			@lang('SOLO_UPDATE_RELEASENOTES')
        </h4>
        <div>
            <p>
		        {{ $releaseNotes }}
            </p>
        </div>
    </div>
</div>
@endif

@if ($this->needsDownloadId)
<div id="solo-error-update-nodownloadid" class="akeeba-block--failure">
	<p>
		@lang('SOLO_UPDATE_ERROR_NEEDSAUTH')
	</p>
</div>
@endif

@if (!$this->updateInfo->get('loadedUpdate', 1))
	<div class="akeeba-block--failure" id="solo-error-update-noconnection">
		<h3>
			@lang('SOLO_UPDATE_NOCONNECTION_HEAD')
		</h3>
		<p>
			@sprintf('SOLO_UPDATE_NOCONNECTION_BODY', $this->getModel()->getUpdateStreamURL())
		</p>
	</div>
@elseif ($this->updateInfo->get('hasUpdate', 0))
	<div class="akeeba-block--warning" id="solo-warning-update-found">
		<h3>
			@lang('SOLO_UPDATE_HASUPDATES_HEAD')
		</h3>
	</div>
@elseif (!$this->updateInfo->get('minstabilityMatch', 0))
	<div class="akeeba-block--info" id="solo-error-update-minstability">
		<h3>
			@lang('SOLO_UPDATE_MINSTABILITY_HEAD')
		</h3>
	</div>
@elseif (!$this->updateInfo->get('platformMatch', 0))
	<div class="akeeba-block--failure" id="solo-error-update-platform-mismatch">
		<h3>
			@if (empty($requirePlatformName) || ($requirePlatformName == 'php'))
				@lang('SOLO_UPDATE_PLATFORM_HEAD')
			@elseif ($requirePlatformName == 'wordpress')
				@lang('SOLO_UPDATE_WORDPRESS_PLATFORM_HEAD')
			@elseif ($requirePlatformName == 'joomla')
				@lang('SOLO_UPDATE_JOOMLA_PLATFORM_HEAD')
			@endif
		</h3>
	</div>
@else
	<div class="akeeba-block--success" id="solo-success-update-uptodate">
		<h3>
			@lang('SOLO_UPDATE_NOUPDATES_HEAD')
		</h3>
	</div>
@endif

<table class="liveupdate-infotable akeeba-table--striped">
    <tr>
        <td>@lang('SOLO_UPDATE_CURRENTVERSION')</td>
        <td>
			<span class="akeeba-label--info">
				{{ AKEEBABACKUP_VERSION }}
			</span>
        </td>
    </tr>
    <tr>
        <td>@lang('SOLO_UPDATE_LATESTVERSION')</td>
        <td>
			<span class="akeeba-label--success">
				{{ $this->updateInfo->get('version') }}
			</span>
        </td>
    </tr>
    <tr>
        <td>@lang('SOLO_UPDATE_LATESTRELEASED')</td>
        <td>{{ $this->updateInfo->get('date') }}</td>
    </tr>
    <tr>
        <td>@lang('SOLO_UPDATE_DOWNLOADURL')</td>
        <td>
            <a href="{{ $this->updateInfo->get('download') }}">
				{{{ $this->updateInfo->get('download') }}}
            </a>
        </td>
    </tr>
	@if (!empty($releaseNotes) || !empty($infoUrl))
        <tr>
            <td>@lang('SOLO_UPDATE_RELEASEINFO')</td>
            <td>
				@if (!empty($releaseNotes))
                    <a href="#" id="btnLiveUpdateReleaseNotes">
						@lang('SOLO_UPDATE_RELEASENOTES')
                    </a>
				@endif

				@if (!empty($releaseNotes) && !empty($infoUrl))
                    &nbsp;&bull;&nbsp;
				@endif

				@if (!empty($infoUrl))
                    <a href="{{ $infoUrl }}" target="_blank" class="btn btn-link">
						@lang('SOLO_UPDATE_READMOREINFO')
                    </a>
				@endif
            </td>
        </tr>
	@endif
</table>

<p>
	@if ($this->updateInfo->get('hasUpdate', 0))
		@if ($this->needsDownloadId)
			<button disabled type="button"
			   class="akeeba-btn--large--primary">
				<span class="akion-chevron-right"></span>
				@lang('SOLO_UPDATE_DO_UPDATE')
			</button>
		@else
			<a href="@route('index.php?view=update&task=download')"
			   class="akeeba-btn--large--primary">
				<span class="akion-chevron-right"></span>
				@lang('SOLO_UPDATE_DO_UPDATE')
			</a>
		@endif

	@endif
	<a href="@route('index.php?view=update&force=1')"
		class="akeeba-btn--grey">
		<span class="akion-refresh"></span>
		@lang('SOLO_UPDATE_REFRESH_INFO')
	</a>
</p>
