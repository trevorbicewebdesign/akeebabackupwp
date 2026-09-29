<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var  Solo\View\Phpinfo\Html $this */

$available = true;

$functions = ini_get('disable_functions') . ',';
$functions .= ini_get('suhosin.executor.func.blacklist');

if ($functions)
{
	$array = preg_split('/,\s*/', $functions);

	if (in_array('phpinfo', $array))
	{
		$available = false;
	}
}

$inCMS = $this->container->segment->get('insideCMS', false);
$height = $inCMS ? '550' : '80%';
?>

@if($available)

    <iframe width='100%' height="{{ $height }}" src="@route('index.php?view=phpinfo&task=phpinfo&format=raw')"></iframe>
@else
    <div>
        <p class="akeeba-block--warning">
			@lang('SOLO_PHPINFO_DISABLED')
        </p>

        <p>
            <strong>PHP Version: </strong> {{ phpversion() }}
        </p>
    </div>
@endif