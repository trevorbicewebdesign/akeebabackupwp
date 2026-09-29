<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Html;
use Awf\Text\Text;

defined('_AKEEBA') or die();

// Used for type hinting
/** @var \Solo\View\Main\Html $this */

?>

<div class="akeeba-panel--info">
	<form action="@route('index.php?view=main')" method="post" name="profileForm" class="akeeba-form--inline">
		<div class="akeeba-form-group">
			<label>
				@lang('COM_AKEEBA_CPANEL_PROFILE_TITLE'): # {{ $this->profile }}
			</label>
			@html('select.genericlist', $this->profileList, 'profile', ['list.select' => $this->profile, 'id' => 'comAkeebaControlPanelProfileSwitch'])
		</div>
		<div class="akeeba-form-group--actions">
			<button class="akeeba-btn--small--grey" type="submit">
				<span class="akion-android-share"></span>
				@lang('COM_AKEEBA_CPANEL_PROFILE_BUTTON')
			</button>
		</div>
		<div class="akeeba-form-group--actions">
			<input type="hidden" name="token" value="@token()">
			<input type="hidden" name="task" value="switchProfile" />
		</div>
	</form>
</div>
