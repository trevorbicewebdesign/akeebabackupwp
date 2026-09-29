<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var \Solo\View\Configuration\Html $this */

// Make sure we only ever add this HTML and JS once per page
if (defined('AKEEBA_VIEW_JAVASCRIPT_CONFWIZ_MODAL'))
{
	return;
}

define('AKEEBA_VIEW_JAVASCRIPT_CONFWIZ_MODAL', 1);

$js = <<< JS

akeeba.System.documentReady(function(){

    akeeba.System.addEventListener('comAkeebaConwizModalClose', 'click', function () {
        akeeba.System.configurationWizardModal.close();
    });

	setTimeout(function() {
        akeeba.System.configurationWizardModal = akeeba.Modal.open({
            inherit: '#akeeba-config-confwiz-bubble',
            width: '80%'
        });		
	}, 500);
});

JS;

?>
@inlineJs($js)

<div id="akeeba-config-confwiz-bubble" class="modal fade" role="dialog"
     aria-labelledby="DialogLabel" aria-hidden="true" style="display: none;">
    <div class="akeeba-renderer-fef <?php echo ($this->getContainer()->appConfig->get('darkmode', -1) == 1) ? 'akeeba-renderer-fef--dark' : '' ?>">
        <h4>
            @lang('COM_AKEEBA_CONFIG_HEADER_CONFWIZ')
        </h4>
        <div>
            <p>
                @lang('COM_AKEEBA_CONFIG_LBL_CONFWIZ_INTRO')
            </p>
            <p>
                <a href="@route('index.php?view=wizard')"
                   class="akeeba-btn--green akeeba-btn--big">
                    <span class="akion-flash"></span>
                    @lang('COM_AKEEBA_CONFWIZ')
                </a>
            </p>
            <p>
                @lang('COM_AKEEBA_CONFIG_LBL_CONFWIZ_AFTER')
            </p>
        </div>
        <div>
            <a href="#" class="akeeba-btn--ghost akeeba-btn--small" id="comAkeebaConwizModalClose">
                <span class="akion-close"></span>
                @lang('SOLO_BTN_CANCEL')
            </a>
        </div>
    </div>
</div>
