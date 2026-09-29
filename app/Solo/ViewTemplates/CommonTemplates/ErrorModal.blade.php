<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

?>
{{--  Error modal  --}}
<div id="errorDialog" tabindex="-1" role="dialog" aria-labelledby="errorDialogLabel" aria-hidden="true"
     style="display:none;">
    <div class="akeeba-renderer-fef <?php echo ($this->getContainer()->appConfig->get('darkmode', -1) == 1) ? 'akeeba-renderer-fef--dark' : '' ?>">
        <h4 id="errorDialogLabel">
	        @lang('COM_AKEEBA_CONFIG_UI_AJAXERRORDLG_TITLE')
        </h4>

        <p>
	        @lang('COM_AKEEBA_CONFIG_UI_AJAXERRORDLG_TEXT')
        </p>
        <pre id="errorDialogPre"></pre>
    </div>
</div>
