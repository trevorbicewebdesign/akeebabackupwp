<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var \Solo\View\Main\Html $this */

?>
<div class="akeeba-panel--orange" style="margin: 3em 1em 6em;">
    <header class="akeeba-block-header">
        <h3>
            @lang('COM_AKEEBA_MIGREIGHT_NEEDED_TITLE')
        </h3>
    </header>

    <div class="akeeba-block--warning">
        <p>
            @lang('COM_AKEEBA_MIGREIGHT_NEEDED_WHY')
        </p>
        <p>
            @sprintf('COM_AKEEBA_MIGREIGHT_NEEDED_WHAT', rtrim(WP_CONTENT_DIR, '/\\') . '/backups')
        </p>
    </div>

    <p>
        <a href="@route('index.php?view=migreight&task=start')"
           class="akeeba-btn--primary--big">
            <span class="akion-information-circled" aria-hidden="true"></span>
            @lang('COM_AKEEBA_MIGREIGHT_NEEDED_DETAILS')
        </a>
    </p>
</div>
