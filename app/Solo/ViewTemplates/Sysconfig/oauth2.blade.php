<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Engine\Platform;
use Awf\Uri\Uri;

defined('_AKEEBA') or die();

/** @var \Solo\View\Sysconfig\Html $this */

$router = $this->getContainer()->router;
$inCMS  = $this->getContainer()->segment->get('insideCMS', false);
?>

@repeatable('oauth2Block', $engine)
<?php
$config = $this->getContainer()->appConfig;
$uri    = new Uri(
	defined('WPINC')
		? admin_url('admin-ajax.php?action=akeebabackup_oauth2&engine=' . $engine . '&task=noop')
		: (rtrim(Platform::getInstance()->get_platform_configuration_option('siteurl', ''), '/') . '/index.php?view=oauth2&task=noop&format=raw&engine=' . $engine)
);

?>
<div class="akeeba-form-group">
    <label for="oauth2_client_{{ $engine }}">
        @lang('COM_AKEEBA_CONFIG_OAUTH2_CLIENT_' . $engine . '_LABEL')
    </label>
    <div class="akeeba-toggle" id="oauth2_client_{{ $engine }}">
        @html('fefselect.booleanList', 'oauth2_client_' . $engine, ['forToggle' => 1, 'colorBoolean' => 1], $config->get('oauth2_client_' . $engine, 0))
    </div>
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_OAUTH2_CLIENT_' . $engine . '_DESC')
    </p>
</div>

<div class="akeeba-block--info" {{ $this->showOn('oauth2_client_' . $engine . ':1') }}>
    <p>@lang('COM_AKEEBA_CONFIG_OAUTH2URLFIELD_YOU_WILL_NEED')</p>
    <p>
        <strong>@lang('COM_AKEEBA_CONFIG_OAUTH2URLFIELD_CALLBACK_URL')</strong>:
        <br />
        <?php $uri->setVar('task', 'step2') ?>
        <code><?= $uri->toString() ?></code>
    </p>
    <p>
        <strong>@lang('COM_AKEEBA_CONFIG_OAUTH2URLFIELD_HELPER_URL')</strong>:
        <br />
	    <?php $uri->setVar('task', 'step1') ?>
        <code><?= $uri->toString() ?></code>
    </p>
    <p>
        <strong>@lang('COM_AKEEBA_CONFIG_OAUTH2URLFIELD_REFRESH_URL')</strong>:
        <br />
	    <?php $uri->setVar('task', 'refresh') ?>
        <code><?= $uri->toString() ?></code>
    </p>
</div>

<div class="akeeba-form-group" {{ $this->showOn('oauth2_client_' . $engine . ':1') }}>
    <label for="{{ $engine }}_client_id">
        @lang('COM_AKEEBA_CONFIG_' . $engine . '_CLIENT_ID_LABEL')
    </label>
    <input type="text" name="{{ $engine }}_client_id" id="{{ $engine }}_client_id"
           value="{{ $config->get($engine . '_client_id') }}">
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_' . $engine . '_CLIENT_ID_DESC')
    </p>
</div>

<div class="akeeba-form-group" {{ $this->showOn('oauth2_client_' . $engine . ':1') }}>
    <label for="{{ $engine }}_client_secret">
        @lang('COM_AKEEBA_CONFIG_' . $engine . '_CLIENT_SECRET_LABEL')
    </label>
    <input type="password" name="{{  $engine }}_client_secret" id="{{ $engine }}_client_secret"
           value="{{ $config->get($engine . '_client_secret') }}">
    <p class="akeeba-help-text">
        @lang('COM_AKEEBA_CONFIG_' . $engine . '_CLIENT_SECRET_DESC')
    </p>
</div>
@endrepeatable

<div class="akeeba-block--info">
    @lang('COM_AKEEBA_CONFIG_OAUTH2_HEADER_DESC')
</div>

@yieldRepeatable('oauth2Block', 'box')
<hr />
@yieldRepeatable('oauth2Block', 'dropbox')
<hr />
@yieldRepeatable('oauth2Block', 'googledrive')
<hr />
@yieldRepeatable('oauth2Block', 'onedrivebusiness')
