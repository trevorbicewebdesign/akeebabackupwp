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
$inCMS    = $this->container->segment->get('insideCMS', false);

?>
<section class="akeeba-panel--info">
	<header class="akeeba-block-header">
		<h3>
			<span class="akion-ios-cog"></span>
			@lang('SOLO_MAIN_LBL_SYSMANAGEMENT')
		</h3>
	</header>
	<div class="akeeba-grid">
		@if(!$inCMS)
			@if($this->canAccess('users', 'main'))
				<a class="akeeba-action--teal" href="@route('index.php?view=users')">
					<span class="akion-ios-people"></span>
					@lang('SOLO_MAIN_LBL_USERS')
				</a>
			@endif
		@endif
		@if($this->canAccess('sysconfig', 'main'))
			<a class="akeeba-action--teal" href="@route('index.php?view=sysconfig')">
				<span class="akion-ios-settings-strong"></span>
				@lang('SOLO_MAIN_LBL_SYSCONFIG')
			</a>
		@endif
	</div>
</section>
