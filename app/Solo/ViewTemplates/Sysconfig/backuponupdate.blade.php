<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var \Solo\View\Sysconfig\Html $this */

$config = $this->getContainer()->appConfig;

?>
<div class="akeeba-form-group">
    <label for="backup-core-update">
        @lang('SOLO_SETUP_LBL_BACKUPONUPDATE_CORE')
    </label>
    <div class="akeeba-toggle">
        @html('fefselect.booleanList', 'options[backuponupdate_core_manual]', ['forToggle' => 1, 'colorBoolean' => 1], $config->get('options.backuponupdate_core_manual', 1))
    </div>
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_BACKUPONUPDATE_CORE_DESC')
    </p>
</div>
<div class="akeeba-form-group">
    <label for="backup-core-update">
        @lang('SOLO_SETUP_LBL_BACKUPONUPDATE_CORE_PROFILE')
    </label>
    <div class="akeeba-toggle">
        @html('select.genericlist', $this->profileList, 'options[backuponupdate_core_manual_profile]', [], 'value', 'text', $config->get('options.backuponupdate_core_manual_profile', 1))
    </div>
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_BACKUPONUPDATE_CORE_PROFILE_DESC')
    </p>
</div>
