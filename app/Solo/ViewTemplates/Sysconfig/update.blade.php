<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var \Solo\View\Sysconfig\Html $this */

$config = $this->getContainer()->appConfig;
$inCMS  = $this->getContainer()->segment->get('insideCMS', false);

?>
<div id="sysconfigUpdate" class="tab-pane">
    @if (defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO)
        <div class="akeeba-form-group">
            <label for="update_dlid">
                @lang('COM_AKEEBA_CONFIG_DOWNLOADID_LABEL')
            </label>
            <input type="text" name="options[update_dlid]" id="update_dlid"
                   placeholder="@lang('COM_AKEEBA_CONFIG_DOWNLOADID_LABEL')"
                   value="<?php echo $config->get('options.update_dlid')?>">
            <p class="akeeba-help-text">
                @lang('COM_AKEEBA_CONFIG_DOWNLOADID_DESC')
            </p>
        </div>
    @else
        <input type="hidden" name="options[update_dlid]" id="update_dlid"
               value="">
    @endif

    <div class="akeeba-form-group">
        <label for="minstability">
            @lang('SOLO_CONFIG_MINSTABILITY_LABEL')
        </label>
        {{ $this->getContainer()->html->setup->minstabilitySelect( $config->get('options.minstability', 'stable')) }}
        <p class="akeeba-help-text">
            @lang('SOLO_CONFIG_MINSTABILITY_DESC')
        </p>
    </div>

    @if ($inCMS && false)
        <div class="akeeba-form-group">
            <label for="options_integratedupdate">
                @lang('SOLO_CONFIG_UPDATE_INTEGRATED_WP')
            </label>
            <div class="akeeba-toggle">
                @html('fefselect.booleanList', 'options[integratedupdate]', ['id' => 'options_integratedupdate', 'forToggle' => 1, 'colorBoolean' => 1], $config->get('options.integratedupdate', 1))
            </div>
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_UPDATE_INTEGRATED_WP_DESC')
            </p>
        </div>
    @endif

    @unless ($inCMS)
        <div class="akeeba-form-group">
            <label for="options_no_php_version_check">
                @lang('SOLO_CONFIG_UPDATE_NO_PHP_VERSION_CHECK_WP')
            </label>
            <div class="akeeba-toggle">
                @html('fefselect.booleanList', 'options[no_php_version_check]', ['id' => 'options_no_php_version_check', 'forToggle' => 1, 'colorBoolean' => 1], $config->get('options.no_php_version_check', 1))
            </div>
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_UPDATE_NO_PHP_VERSION_CHECK_DESC')
            </p>
        </div>
    @endif

</div>
