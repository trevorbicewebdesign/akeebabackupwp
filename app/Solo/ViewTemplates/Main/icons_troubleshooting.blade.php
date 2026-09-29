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
			<span class="akion-help-buoy"></span>
			@lang('COM_AKEEBA_CPANEL_HEADER_TROUBLESHOOTING')
		</h3>
	</header>

	<div class="akeeba-grid">
		@if ($this->canAccess('log', 'main'))
			<a class="akeeba-action--teal" href="@route('index.php?view=log')">
				<span class="akion-ios-search-strong"></span>
				@lang('COM_AKEEBA_LOG')
			</a>
		@endif
		@if (AKEEBABACKUP_PRO && $this->canAccess('alice', 'main'))
			<a class="akeeba-action--teal" href="@route('index.php?view=alice')">
				<span class="akion-medkit"></span>
				@lang('COM_AKEEBA_ALICE')
			</a>
		@endif
	</div>
</section>
