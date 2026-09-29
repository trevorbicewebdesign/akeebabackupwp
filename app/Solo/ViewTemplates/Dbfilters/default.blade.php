<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var \Solo\View\Dbfilters\Html $this */
?>
@include('CommonTemplates/ErrorModal')
@include('CommonTemplates/ProfileName')

<form class="akeeba-form--inline akeeba-panel--info">
    <div class="akeeba-form-group">
        <label>
		    @lang('COM_AKEEBA_FILEFILTERS_LABEL_ROOTDIR')
        </label>
        <span>{{ $this->root_select }}</span>
    </div>

    <div class="akeeba-form-group--actions">
		<button class="akeeba-btn--red" id="comAkeebaDatabaseFiltersNuke">
			<span class="akion-ios-loop-strong"></span>
			@lang('COM_AKEEBA_DBFILTER_LABEL_NUKEFILTERS')
		</button>
		<button class="akeeba-btn--green" id="comAkeebaDatabaseFiltersExcludeNonCMS">
			<span class="akion-ios-flag"></span>
			@lang('COM_AKEEBA_DBFILTER_LABEL_EXCLUDENONCORE')
		</button>
	</div>
</form>

<div class="akeeba-panel--info">
    <header class="akeeba-block-header">
        <h3>
			@lang('COM_AKEEBA_DBFILTER_LABEL_TABLES')
        </h3>
    </header>
    <div id="tables"></div>
</div>
