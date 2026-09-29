<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Solo\View\Migreight\Html $this */

defined('_AKEEBA') || die();

?>
<div class="akeeba-panel--success">
    <header class="akeeba-block-header">
        <h3>
            <span class="akion-checkmark-circled" aria-hidden="true"></span>
            @lang('COM_AKEEBA_MIGREIGHT_NOT_NEEDED_TITLE')
        </h3>
    </header>
    <p>
        @lang('COM_AKEEBA_MIGREIGHT_NOT_NEEDED_INFO')
    </p>
    <p>
        @lang('COM_AKEEBA_MIGREIGHT_GO_BACK_PROMPT')
    </p>
    <p>
        <a href="@route('index.php')" class="akeeba-btn--primary">
            <span class="akion-chevron-left" aria-hidden="true"></span>
            @lang('COM_AKEEBA_CONTROLPANEL')
        </a>
    </p>
</div>