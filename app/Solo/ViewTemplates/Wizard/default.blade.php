<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Engine\Factory;

defined('_AKEEBA') or die();

/** @var \Solo\View\Wizard\Html $this */

$config = Factory::getConfiguration();
?>

@include('CommonTemplates/FolderBrowser')

<div class="akeeba-block--info">
    @lang('SOLO_WIZARD_LBL_INTRO')
</div>

<form action="@route('index.php?view=wizard&task=applySiteSettings')" method="post"
      role="form" class="akeeba-form--horizontal--with-hidden" id="adminForm">

    <div class="akeeba-panel--info">
        <header class="akeeba-block-header">
            <h3>
                @lang('SOLO_WIZARD_LBL_SITEROOT_TITLE')
            </h3>
        </header>

        <p>@lang('SOLO_WIZARD_LBL_SITEROOT_INTRO')</p>

        <div class="akeeba-form-group">
            <label for="var[akeeba.platform.site_url]">
                @lang('SOLO_CONFIG_PLATFORM_SITEURL_TITLE')
            </label>
            <input type="text" id="var[akeeba.platform.site_url]"
                   name="var[akeeba.platform.site_url]" size="30"
                   value="{{ $this->siteInfo->url }}">
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_SITEURL_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group">
            <label for="var[akeeba.platform.newroot]">
                @lang('SOLO_CONFIG_PLATFORM_NEWROOT_TITLE')
            </label>
            <div class="akeeba-input-group">
                <input type="text" id="var[akeeba.platform.newroot]"
                       name="var[akeeba.platform.newroot]" size="30"
                       value="{{ $this->siteInfo->root }}">
                <span class="akeeba-input-group-btn">
                    <button title="@lang('COM_AKEEBA_CONFIG_UI_BROWSE')" class="akeeba-btn--teal" id="btnBrowse">
                        <span class="akion-android-folder-open"></span>
                    </button>
                </span>
            </div>
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_NEWROOT_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group--pull-right">
            <div class="akeeba-form-group--actions">
                <button class="akeeba-btn--green--big" id="btnPythia">
                    <span class="akion-wand"></span>
                    @lang('SOLO_WIZARD_BTN_PYTHIA')
                </button>
            </div>
            <p class="akeeba-help-text">
                @lang('SOLO_WIZARD_BTN_PYTHIA_HELP')
            </p>
        </div>
    </div>

    <div class="akeeba-panel--info">
        <header class="akeeba-block-header">
            <h3>
                @lang('SOLO_WIZARD_LBL_DBINFO_TITLE')
            </h3>
        </header>


        <p>@lang('SOLO_WIZARD_LBL_DBINFO_INTRO')</p>

        <div class="akeeba-form-group">
            <label for="var[akeeba.platform.dbdriver]">
                @lang('SOLO_CONFIG_PLATFORM_DBDRIVER_TITLE')
            </label>
            {{ \Solo\Helper\Utils::engineDatabaseTypesSelect($config->get('akeeba.platform.dbdriver', 'mysqli'), 'var[akeeba.platform.dbdriver]') }}
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_DBDRIVER_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group" id="host-wrapper">
            <label for="var[akeeba.platform.dbhost]">
                @lang('SOLO_CONFIG_PLATFORM_DBHOST_TITLE')
            </label>
            <input type="text" id="var[akeeba.platform.dbhost]"
                   name="var[akeeba.platform.dbhost]" size="30"
                   value="{{ $config->get('akeeba.platform.dbhost', 'localhost') }}">
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_DBHOST_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group" id="port-wrapper">
            <label for="var[akeeba.platform.dbport]">
                @lang('SOLO_CONFIG_PLATFORM_DBPORT_TITLE')
            </label>
            <input type="text" id="var[akeeba.platform.dbport]"
                   name="var[akeeba.platform.dbport]" size="30"
                   value="{{ $config->get('akeeba.platform.dbport', '') }}">
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_DBPORT_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group" id="user-wrapper">
            <label for="var[akeeba.platform.dbusername]">
                @lang('SOLO_CONFIG_PLATFORM_DBUSERNAME_TITLE')
            </label>
            <input type="text" id="var[akeeba.platform.dbusername]"
                   name="var[akeeba.platform.dbusername]" size="30"
                   value="{{ $config->get('akeeba.platform.dbusername', '') }}">
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_DBUSERNAME_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group" id="pass-wrapper">
            <label for="var[akeeba.platform.dbpassword]">
                @lang('SOLO_CONFIG_PLATFORM_DBPASSWORD_TITLE')
            </label>
            <input type="password" id="var[akeeba.platform.dbpassword]"
                   name="var[akeeba.platform.dbpassword]" size="30"
                   value="{{ $config->get('akeeba.platform.dbpassword', '') }}">
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_DBPASSWORD_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group" id="name-wrapper">
            <label for="var[akeeba.platform.dbname]">
                @lang('SOLO_CONFIG_PLATFORM_DBDATABASE_TITLE')
            </label>
            <input type="text" id="var[akeeba.platform.dbname]"
                   name="var[akeeba.platform.dbname]" size="30"
                   value="{{ $config->get('akeeba.platform.dbname', '') }}">
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_DBDATABASE_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group" id="prefix-wrapper">
            <label for="var[akeeba.platform.dbprefix]">
                @lang('SOLO_CONFIG_PLATFORM_DBPREFIX_TITLE')
            </label>
            <input type="text" id="var[akeeba.platform.dbprefix]"
                   name="var[akeeba.platform.dbprefix]" size="30"
                   value="{{ $config->get('akeeba.platform.dbprefix', '') }}">
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_DBPREFIX_DESCRIPTION')
            </p>
        </div>
    </div>

    <div class="akeeba-panel--info">
        <header class="akeeba-block-header">
            <h3>
                @lang('SOLO_WIZARD_LBL_SITEINFO_TITLE')
            </h3>
        </header>

        <p>@lang('SOLO_WIZARD_LBL_SITEINFO_INTRO')</p>

        <div class="akeeba-form-group">
            <label for="var[akeeba.platform.scripttype]">
                @lang('SOLO_CONFIG_PLATFORM_SCRIPTTYPE_TITLE')
            </label>
            {{ $this->getContainer()->html->setup->scriptTypesSelect($config->get('akeeba.platform.scripttype', 'generic'), 'var[akeeba.platform.scripttype]') }}
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_SCRIPTTYPE_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group">
            <label for="extradirs">
                @lang('SOLO_CONFIG_PLATFORM_EXTRADIRS_TITLE')
            </label>
            <span id="pythiaExtradirs">&nbsp;</span>
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_EXTRADIRS_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group">
            <label for="extradirs">
                @lang('SOLO_CONFIG_PLATFORM_EXTRADB_TITLE')
            </label>
            <span id="pythiaExtradb">&nbsp;</span>
            <p class="akeeba-help-text">
                @lang('SOLO_CONFIG_PLATFORM_EXTRADB_DESCRIPTION')
            </p>
        </div>

        <div class="akeeba-form-group">
            <label for="var[akeeba.advanced.embedded_installer]">
                @lang('COM_AKEEBA_CONFIG_INSTALLER_TITLE')
            </label>
            {{ $this->getContainer()->html->setup->restorationScriptSelect($config->get('akeeba.advanced.embedded_installer', 'generic'), 'var[akeeba.advanced.embedded_installer]') }}
            <p class="akeeba-help-text">
                @lang('COM_AKEEBA_CONFIG_INSTALLER_DESCRIPTION')
            </p>
        </div>
    </div>

    <div class="akeeba-form-group--actions">
        <button id="btnWizardSiteConfigSubmit" type="submit" class="akeeba-btn--primary--big">
            @lang('SOLO_BTN_SUBMIT')
        </button>
    </div>

    <div class="akeeba-hidden-fields-container">
        <input type="hidden" name="token" value="@token()" />
    </div>

</form>
