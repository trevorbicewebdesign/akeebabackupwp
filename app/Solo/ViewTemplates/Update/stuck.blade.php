<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var   \Solo\View\Update\Html  $this */

$product = Text::_('SOLO_APP_TITLE')
?>
<div class="akeeba-block--failure" id="solo-error-update-stuck">
	<h3>
		@lang('SOLO_UPDATE_STUCK_HEAD')
	</h3>

	<p>@lang('SOLO_UPDATE_STUCK_INFO')</p>

	<p>@sprintf('SOLO_UPDATE_NOTSUPPORTED_ALTMETHOD', $product)</p>

	<p class="liveupdate-buttons">
		<a href="@route('index.php?view=update&force=1')" class="akeeba-btn--primary">
			<span class="akion-refresh"></span>
			@lang('SOLO_UPDATE_REFRESH_INFO')
		</a>
	</p>
</div> 
