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
@if (AKEEBABACKUP_PRO)
    <section class="akeeba-panel--info">
        <header class="akeeba-block-header">
            <h3>
                <span class="akion-wand"></span>
                @lang('COM_AKEEBA_CPANEL_HEADER_ADVANCED')
            </h3>
        </header>

        <div class="akeeba-grid">
            @if($this->canAccess('schedule', 'main'))
                <a class="akeeba-action--teal" href="@route('index.php?view=schedule')">
                    <span class="akion-calendar"></span>
                    @lang('COM_AKEEBA_SCHEDULE')
                </a>
            @endif
            @if($this->canAccess('discover', 'main'))
                <a class="akeeba-action--orange" href="@route('index.php?view=discover')">
                    <span class="akion-ios-download"></span>
                    @lang('COM_AKEEBA_DISCOVER')
                </a>
            @endif
            @if($this->canAccess('s3import', 'main'))
                <a class="akeeba-action--orange" href="@route('index.php?view=s3import')">
                    <span class="akion-ios-cloud-download"></span>
                    @lang('COM_AKEEBA_S3IMPORT')
                </a>
            @endif
        </div>
    </section>
@endif