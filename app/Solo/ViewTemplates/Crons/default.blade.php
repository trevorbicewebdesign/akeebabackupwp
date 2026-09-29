<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

// Used for type hinting
/** @var \Solo\View\Crons\Html $this */

$token = $this->container->session->getCsrfToken()->getValue();

/** @var \Solo\Model\Cron $model */
$model    = $this->getModel();
$nullDate = $this->getModel()->getDbo()->getNullDate();
$i = 0;
?>

@if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON)
	<div class="akeeba-block--warning">
		<details style="margin: 0; padding: 0">
			<summary style="font-size: larger; font-weight: bold; margin: 0">
				@lang('COM_AKEEBA_CRONS_DEFAULT_SETUP_WARNING_HEAD')
			</summary>
			<p style="padding: .5em 0">
				@lang('COM_AKEEBA_CRONS_DEFAULT_SETUP_WARNING_P1')
			</p>
			<p style="padding-bottom: .5em">
				@lang('COM_AKEEBA_CRONS_DEFAULT_SETUP_WARNING_P2')
			</p>
		</details>
	</div>
@endif

<form action="@route('index.php?view=crons')" method="post" name="adminForm" id="adminForm"
	  class="akeeba-form--with-hidden" role="form">

	<table class="akeeba-table--striped" id="adminList">
		<thead>
		<tr>
			<th width="30">
			</th>
			<th width="4em">
				@lang('AWF_COMMON_LBL_DISPLAY_NUM')
			</th>
			<th>
				@lang('COM_AKEEBA_CRONS_LBL_DESCRIPTION')
			</th>
			<th>
				@lang('COM_AKEEBA_CRONS_LBL_PROFILE')
			</th>
			<th>
				@lang('COM_AKEEBA_CRONS_LBL_LAST_RUN_START')
			</th>
			<th>
				@lang('COM_AKEEBA_CRONS_LBL_NEXT_RUN')
			</th>
		</tr>
		</thead>
		<tfoot>
		<tr>
			<td colspan="20" class="center">
				{{ $this->pagination->getListFooter() }}
			</td>
		</tr>
		</tfoot>
		<tbody>
		<?php /** @var \Solo\Model\Cron $task */ ?>
		@foreach ($this->items as $task)
				<?php
				try {
					$storage = @json_decode($task->storage ?? '{}') ?? new stdClass;
				} catch(Exception $e) {
					$storage = new stdClass;
				}
				?>
			<tr>
				<td>
					@html('grid.id', ++$i, $task->id)
				</td>
				<td>
					{{ (int) $task->id }}
				</td>
				<td>
					<a href="@route('index.php?view=cron&id=' . $task->id)">
						{{{ $task->description }}}
					</a>
					<br />
					<small>
						<strong>@lang('COM_AKEEBA_CRONS_LBL_CRON_EXPRESSION')</strong>
						<code>{{{ $task->cron_expression }}}</code>
					</small>
				</td>
				<td>
					{{{ $this->profilesList[$task->profile_id] ?? '—' }}}
				</td>
				<td style="max-width: 25vw">

					@if(empty($task->last_run_start) || $task->last_run_start === $nullDate || $task->last_exit == \Solo\Model\Cron::TASK_INITIAL_SCHEDULE)
						<span class="akeeba-label--grey">
							<span class="akion-android-alarm-clock" aria-hidden="true"></span>
							@lang('COM_AKEEBA_CRONS_STATUS_SCHEDULED')
						</span>
					@elseif ($task->last_exit == \Solo\Model\Cron::TASK_OK)
						<span class="akeeba-label--success">
							@lang('COM_AKEEBA_CRONS_STATUS_OK')
						</span>
					@elseif ($task->last_exit == \Solo\Model\Cron::TASK_RUNNING)
						<span class="akeeba-label--orange">
							<span class="akion-play" aria-hidden="true"></span>
							@lang('COM_AKEEBA_CRONS_STATUS_RUNNING')
						</span>
					@elseif ($task->last_exit == \Solo\Model\Cron::TASK_WILL_CONTINUE)
						<span class="akeeba-label--orange">
							<span class="akion-pause" aria-hidden="true"></span>
							@lang('COM_AKEEBA_CRONS_STATUS_WILL_CONTINUE')
						</span>
					@elseif ($task->last_exit == \Solo\Model\Cron::TASK_TIMEOUT)
						<span class="akeeba-label--failure">
							@lang('COM_AKEEBA_CRONS_STATUS_TIMEOUT')
						</span>
					@elseif ($task->last_exit == \Solo\Model\Cron::TASK_ERROR)
						<span class="akeeba-label--failure">
							@lang('COM_AKEEBA_CRONS_STATUS_ERROR')
						</span>
					@endif

					@if(!empty($task->last_run_start) && $task->last_run_start !== $nullDate && $task->last_exit != \Solo\Model\Cron::TASK_INITIAL_SCHEDULE)
						<br/>
						<span class="akion-ios-time-outline" aria-hidden="true"></span>
						{{{$this->formatDateTime(new DateTime($task->last_run_start))}}}
						@if(!empty($task->last_run_end) && $task->last_run_end !== $nullDate)
						<br/>
						<span class="akion-clock" aria-hidden="true"></span>
						{{{$this->formatDateTime(new DateTime($task->last_run_end))}}}
					    @endif
					@endif

					@if ($task->last_exit == \Solo\Model\Cron::TASK_ERROR && !empty($storage->error ?? ''))
						<details>
							<summary>@lang('COM_AKEEBA_CRONS_LBL_ERROR_INFO')</summary>
							<p>
								{{{ $storage->error ?? '' }}}
							</p>
							@if ($storage->trace ?? '')
							<pre>
							{{{ $storage->trace ?? '' }}}
							</pre>
							@endif
						</details>
					@endif
				</td>
				<td style="max-width: 15vw">
					<span class="akion-android-alarm-clock" aria-hidden="true"></span>
					{{{ $this->formatDateTime($this->getNextRun($task)) }}}
				</td>
			</tr>
		@endforeach
		</tbody>
	</table>

	<div class="akeeba-hidden-fields-container">
		<input type="hidden" name="boxchecked" id="boxchecked" value="0">
		<input type="hidden" name="task" id="task" value="browse">
		<input type="hidden" name="filter_order" id="filter_order" value="{{ $this->lists->order }}">
		<input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="{{ $this->lists->order_Dir }}">
		<input type="hidden" name="token" value="@token()">
	</div>
</form>

<p></p>

<p style="font-size: small; padding: 1em; background-color: rgba(0,0,0,.05); color: rgba(0,0,0,0.4)">
	@lang('COM_AKEEBA_CRONS_THIS_IS_A_BAD_IDEA')
</p>

<p></p>