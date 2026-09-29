<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var \Solo\View\Profiles\Html $this */
$router = $this->container->router;
$token = $this->container->session->getCsrfToken()->getValue();

if (!$this->getModel()->getId())
{
	$id = 0;
	$description = '';
}
else
{
	$id = $this->getModel()->getId();
	$description = $this->getModel()->description;
}
?>
<form action="@route('index.php?view=profiles')" method="POST" name="adminForm" id="adminForm"
      class="akeeba-form--horizontal--with-hidden" role="form">
	<div class="akeeba-form-group">
		<label class="hasTooltip" for="description" title="@lang('COM_AKEEBA_PROFILES_LABEL_DESCRIPTION_TOOLTIP')">
			@lang('COM_AKEEBA_PROFILES_LABEL_DESCRIPTION')
		</label>
        <input type="text" name="description" class="form-control" id="description" value="{{{ $description }}}" required>
	</div>

    <div class="akeeba-hidden-fields-container">
        <input type="hidden" name="boxchecked" id="boxchecked" value="0"/>
        <input type="hidden" name="task" id="task" value=""/>
        <input type="hidden" name="id" id="id" value="{{ (int) $id }}"/>
        <input type="hidden" name="token" value="@token()">
    </div>
</form>
