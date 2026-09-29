<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var \Solo\View\Manage\Html $this */

$router = $this->container->router;
?>
<form name="adminForm" id="adminForm" action="@route('index.php?view=manage')" method="post"
      class="akeeba-form--horizontal--with-hidden" role="form">


	<div class="form-group">
		<label class="control-label col-sm-3" for="description">
			@lang('COM_AKEEBA_BUADMIN_LABEL_DESCRIPTION')
		</label>
		<div class="col-sm-9">
			<input type="text" name="description" maxlength="255" size="50"
				   value="{{ $this->record['description'] }}"
				   class="form-control" />
		</div>
	</div>

	<div class="form-group">
		<label class="control-label col-sm-3" for="comment">
			@lang('COM_AKEEBA_BUADMIN_LABEL_COMMENT')
		</label>
		<div class="col-sm-9">
			<textarea id="comment" name="comment" rows="5" cols="73"
					  autocomplete="off">{{ $this->record['comment'] }}</textarea>
		</div>
	</div>

    <div class="akeeba-hidden-fields-container">
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="id" value="{{ $this->record['id'] }}" />
        <input type="hidden" name="token" value="@token()">
    </div>

</form>
