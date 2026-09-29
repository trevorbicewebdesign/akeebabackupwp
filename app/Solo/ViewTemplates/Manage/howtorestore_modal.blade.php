<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var $this \Solo\View\Configuration\Html */

$router = $this->container->router;

$proKey = (defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO) ? 'PRO' : 'CORE';

$js = <<< JS

akeeba.System.documentReady(function(){
	setTimeout(function() {
        akeeba.System.howToRestoreModal = akeeba.Modal.open({
            inherit: '#akeeba-config-howtorestore-bubble',
            width: '80%'
        });		
	}, 500);
});

JS;
?>
@inlineJs($js)

<div id="akeeba-config-howtorestore-bubble" style="display: none;">
    <div class="akeeba-renderer-fef {{ ($this->getContainer()->appConfig->get('darkmode', -1) == 1) ? 'akeeba-renderer-fef--dark' : '' }}">
        <h4>
		    @lang('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_LEGEND')
        </h4>

        <p>
            @sprintf('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_TEXT_' . $proKey,
            'https://www.akeeba.com/videos/1214-akeeba-solo/1637-abts05-restoring-site-new-server.html',
            $router->route('index.php?view=Transfer'),
            'https://www.akeeba.com/latest-kickstart-core.zip'
            )
        </p>
        @if (!AKEEBABACKUP_PRO)
            <p>
                @if ($this->getContainer()->segment->get('insideCMS', false))
                    @sprintf('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_TEXT_CORE_INFO_ABOUT_PRO',
                    'https://www.akeeba.com/products/akeeba-backup-wordpress.html')
                @else
                    @sprintf('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_TEXT_CORE_INFO_ABOUT_PRO',
                    'https://www.akeeba.com/products/akeeba-solo.html')
                @endif
            </p>
        @endif

        <div>
            <a href="#" onclick="akeeba.System.howToRestoreModal.close(); document.getElementById('akeeba-config-howtorestore-bubble').style.display = 'none'" class="akeeba-btn--primary">
                <span class="akion-close"></span>
		        @lang('COM_AKEEBA_BUADMIN_BTN_REMINDME')
            </a>
            <a href="@route('index.php?view=Manage&task=hideModal')" class="akeeba-btn--green">
                <span class="akion-checkmark-circled"></span>
		        @lang('COM_AKEEBA_BUADMIN_BTN_DONTSHOWTHISAGAIN')
            </a>
        </div>
    </div>
</div>
