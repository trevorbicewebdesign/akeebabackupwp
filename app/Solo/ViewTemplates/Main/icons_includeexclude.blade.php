<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Html;
use Awf\Text\Text;

defined('_AKEEBA') or die();

// Used for type hinting
/** @var \Solo\View\Main\Html $this */

$router   = $this->container->router;

?>
<section class="akeeba-panel--info">
	<header class="akeeba-block-header">
		<h3>
			<span class="akion-funnel"></span>
			@lang('COM_AKEEBA_CPANEL_HEADER_INCLUDEEXCLUDE')
		</h3>
	</header>

    <div class="akeeba-block--info small">
        @lang('COM_AKEEBA_CPANEL_LBL_INCLUDEEXCLUDE_CALLOUT')
    </div>

	<div class="akeeba-grid">
		@if(defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO)
			@if($this->canAccess('multidb', 'main'))
				<a class="akeeba-action--green" href="@route('index.php?view=multidb')">
					<span class="akion-arrow-swap"></span>
					@lang('COM_AKEEBA_MULTIDB')
				</a>
			@endif
			@if($this->canAccess('extradirs', 'main'))
				<a class="akeeba-action--green" href="@route('index.php?view=extradirs')">
					<span class="akion-folder"></span>
					@lang('COM_AKEEBA_INCLUDEFOLDER')
				</a>
			@endif
		@endif

		@if($this->canAccess('fsfilters', 'main'))
			<a class="akeeba-action--red" href="@route('index.php?view=fsfilters')">
				<span class="akion-filing"></span>
				@lang('COM_AKEEBA_FILEFILTERS')
			</a>
		@endif
		@if($this->canAccess('dbfilters', 'main'))
			<a class="akeeba-action--red" href="@route('index.php?view=dbfilters')">
				<span class="akion-ios-grid-view"></span>
				@lang('COM_AKEEBA_DBFILTER')
			</a>
		@endif
		@if(defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO)
			@if($this->canAccess('regexfsfilters', 'main'))
				<a class="akeeba-action--red" href="@route('index.php?view=regexfsfilters')">
					<span class="akion-ios-folder"></span>
					@lang('COM_AKEEBA_REGEXFSFILTERS')
				</a>
			@endif
			@if($this->canAccess('regexdbfilters', 'main'))
				<a class="akeeba-action--red" href="@route('index.php?view=regexdbfilters')">
					<span class="akion-ios-box"></span>
					@lang('COM_AKEEBA_REGEXDBFILTERS')
				</a>
			@endif
		@endif

	</div>
</section>
