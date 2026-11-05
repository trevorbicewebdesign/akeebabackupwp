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
<div class="akeeba-block--warning">
    @lang('SOLO_SYSCONFIG_WARNDB')
</div>

<div class="akeeba-form-group">
    <label for="dbdriver">
        @lang('SOLO_SETUP_LBL_DATABASE_DRIVER')
    </label>
    {{ $this->getContainer()->html->setup->databaseTypesSelect($config->get('dbdriver'), 'dbdriver') }}
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_DATABASE_DRIVER_HELP')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="dbhost">
        @lang('SOLO_SETUP_LBL_DATABASE_HOST')
    </label>
    <input type="text" id="dbhost" name="dbhost" placeholder="@lang('SOLO_SETUP_LBL_DATABASE_HOST')"
           value="{{{ $config->get('dbhost') }}}">
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_DATABASE_HOST_HELP')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="dbuser">
        @lang('SOLO_SETUP_LBL_DATABASE_USER')
    </label>
    <input type="text" id="dbuser" name="dbuser" placeholder="@lang('SOLO_SETUP_LBL_DATABASE_USER')"
           value="{{{ $config->get('dbuser') }}}">
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_DATABASE_USER_HELP')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="dbpass">
        @lang('SOLO_SETUP_LBL_DATABASE_PASS')
    </label>
    <input type="password" id="dbpass" name="dbpass" placeholder="@lang('SOLO_SETUP_LBL_DATABASE_PASS')"
           value="{{{ $config->get('dbpass') }}}">
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_DATABASE_PASS_HELP')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="dbname">
        @lang('SOLO_SETUP_LBL_DATABASE_NAME')
    </label>
    <input type="text" id="dbname" name="dbname" placeholder="@lang('SOLO_SETUP_LBL_DATABASE_NAME')"
           value="{{{ $config->get('dbname') }}}">
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_DATABASE_NAME_HELP')
    </p>
</div>

<div class="akeeba-form-group">
    <label for="prefix">
        @lang('SOLO_SETUP_LBL_DATABASE_PREFIX')
    </label>
    <input type="text" id="prefix" name="prefix" placeholder="@lang('SOLO_SETUP_LBL_DATABASE_PREFIX')"
           value="{{{ $config->get('prefix') }}}">
    <p class="akeeba-help-text">
        @lang('SOLO_SETUP_LBL_DATABASE_PREFIX_HELP')
    </p>
</div>
