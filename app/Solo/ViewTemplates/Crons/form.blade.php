<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

/** @var \Solo\View\Crons\Html $this */
$router = $this->container->router;
$token = $this->container->session->getCsrfToken()->getValue();

$id = $this->getModel()->getId() ?: 0;


?>
<form action="@route('index.php?view=crons')" method="POST" name="adminForm" id="adminForm"
      class="akeeba-form--horizontal--with-hidden" role="form">

    <div class="akeeba-form-group">
        <label for="description">
            @lang('COM_AKEEBA_CRONS_LBL_DESCRIPTION')
        </label>

        <input type="text" name="description" id="description"
               class="form-control"
               value="{{{ $this->getModel()->description }}}"
               required>

        <p class="akeeba-help-text">
            @lang('COM_AKEEBA_CRONS_LBL_DESCRIPTION_TIP')
        </p>
    </div>

	<div class="akeeba-form-group">
		<label for="profile_id">
			@lang('COM_AKEEBA_CRONS_LBL_PROFILE')
		</label>

        @html('select.genericlist', $this->getModel('Main')->getProfileList(), 'profile_id', ['list.select' => $this->getModel()->profile_id])

        <p class="akeeba-help-text">
            @lang('COM_AKEEBA_CRONS_LBL_PROFILE_TIP')
        </p>
	</div>

    <div class="akeeba-form-group">
        <label for="cron_expression">
            @lang('COM_AKEEBA_CRONS_LBL_CRON_EXPRESSION')
        </label>

        <input type="text" name="cron_expression" id="cron_expression"
               class="form-control"
               value="{{{ $this->getModel()->cron_expression }}}"
               required>

        <div class="akeeba-help-text">
            <p>
                @lang('COM_AKEEBA_CRONS_LBL_CRON_EXPRESSION_TIP')
            </p>
            <p>
                @sprintf('COM_AKEEBA_CRONS_LBL_CRON_EXPRESSION_TIMEZONE', $this->getTimezoneLiteral(), $this->formatDateTime(new DateTime()))
                <br/>
                <a href="@route('index.php?view=sysconfig')#forced_backup_timezone" target="_blank">
                @lang('COM_AKEEBA_CRONS_LBL_CRON_EXPRESSION_CHANGETZ')
                </a>
            </p>
        </div>
    </div>

    <div class="akeeba-hidden-fields-container">
        <input type="hidden" name="boxchecked" id="boxchecked" value="0"/>
        <input type="hidden" name="task" id="task" value=""/>
        <input type="hidden" name="id" id="id" value="{{ (int) $id }}"/>
        <input type="hidden" name="token" value="@token()">
    </div>
</form>
