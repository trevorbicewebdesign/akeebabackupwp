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
$token    = $this->container->session->getCsrfToken()->getValue();

?>

@if ($this->needsMigreight)
	@include('Main/migreight')
@endif


{{-- Display various possible warnings about issues which directly affect the user's experience --}}
@include('Main/warnings')

{{-- Update notification container --}}
<div id="soloUpdateNotification"></div>

<div class="akeeba-container--66-33">
	<div>
        {{-- Active profile switch --}}
        @include('Main/profile')

        {{-- One Click Backup icons --}}
		@if(!empty($this->quickIconProfiles) && $this->canAccess('backup', 'main'))
			@include('Main/oneclick')
		@endif

        {{-- Basic operations --}}
		@include('Main/icons_basic')

		@include('Main/paypal')

        {{-- Troubleshooting --}}
        @include('Main/icons_troubleshooting')

        {{-- Advanced operations --}}
		@include('Main/icons_advanced')

        {{-- Include / Exclude data --}}
        @if ($this->container->userManager->getUser()->getPrivilege('akeeba.configure'))
	        @include('Main/icons_includeexclude')
        @endif


		@if ($this->container->userManager->getUser()->getPrivilege('akeeba.configure'))
			@include('Main/icons_system')
        @endif
	</div>

	<div>
		@include('Main/status')

		@include('Main/latest_backup')
	</div>
</div>

@if ($this->statsIframe)
    <div style="display: none">
        <?= $this->statsIframe ?>
    </div>
@endif