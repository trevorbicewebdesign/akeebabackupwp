<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

// Used for type hinting
/** @var \Solo\View\Profiles\Html $this */

$router = $this->container->router;
$configURL = base64_encode($router->route('index.php?view=configuration'));
$token = $this->container->session->getCsrfToken()->getValue();

/** @var \Solo\Model\Profiles $model */
$model = $this->getModel();
?>

@include('CommonTemplates/ProfileName')

<form action="@route('index.php?view=profiles')" method="post" name="adminForm" id="adminForm"
      class="akeeba-form--with-hidden" role="form">

    <div class="akeeba-block--info small">
        @lang('COM_AKEEBA_PROFILES_LBL_EXPLAIN_PROFILES')
    </div>

	<table class="akeeba-table--striped" id="adminList">
		<thead>
			<tr>
				<th width="30">
					&nbsp;
				</th>
				<th width="50">
					@html('grid.sort', '#', 'id', $this->lists->order_Dir, $this->lists->order, 'browse')
				</th>
				<th width="20%">
				</th>
				<th>
					@html('grid.sort', 'COM_AKEEBA_PROFILES_COLLABEL_DESCRIPTION', 'description', $this->lists->order_Dir, $this->lists->order, 'browse')
				</th>
				<th>
					@lang('COM_AKEEBA_CONFIG_QUICKICON_LABEL')
				</th>
			</tr>
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td>
					<input type="text" name="description" value="{{ $model->getState('description', '', 'string') }}"
						   class="akeebaGridViewAutoSubmitOnChange"
						   placeholder="@lang('COM_AKEEBA_PROFILES_COLLABEL_DESCRIPTION')" />
				</td>
                <td></td>
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
		<?php $i = 0; ?>
			@foreach ($this->items as $profile)
			<?php $exportBaseName = \Awf\Utils\StringHandling::toSlug($profile->description); ?>
		<tr>
			<td>
				@html('grid.id', ++$i, $profile->id)
			</td>
			<td>
				{{ (int) $profile->id }}
			</td>
			<td>
				<a class="akeeba-btn--teal--small" href="@route('index.php?view=main&task=switchProfile&profile=' . $profile->id . '&returnurl=' . $configURL . '&token=' . $token)">
					<span class="akion-ios-cog"></span>
					@lang('COM_AKEEBA_CONFIG_UI_CONFIG')
				</a>
				&nbsp;
				<a class="akeeba-btn--dark--small" href="@route('index.php?view=profiles&task=read&id=' . $profile->id . '&basename=' . urlencode($exportBaseName) . '&format=json&' . $token . '=1')">
					<span class="akion-android-download"></span>
					@lang('COM_AKEEBA_PROFILES_BTN_EXPORT')
				</a>
			</td>
			<td>
				<a href="@route('index.php?&view=profiles&task=edit&id=' . $profile->id)">
					{{{ $this->escape($profile->description) }}}
				</a>
			</td>
            <td>
                <?php $action = $profile->quickicon ? 'unpublish' : 'publish' ?>
                <a href="@route('index.php?view=Profiles&task='.$action.'&id='.$profile->id.'&'.$token.'=1')"
                   class="akeeba-btn--{{ $profile->quickicon ? 'green' : 'red' }}"
                >
                    <span class="akion-{{ $profile->quickicon ? 'checkmark' : 'close-circled' }}"></span>
                </a>

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

<form action="@route('index.php?view=profiles&task=import')" method="POST" name="importForm" enctype="multipart/form-data" id="importForm" class="akeeba-form--inline--with-hidden--no-margins akeeba-panel--info">
	<div class="akeeba-form-group">
        <input type="file" name="importfile" class="form-control" />
    </div>

    <div class="akeeba-form-group--actions">
        <button class="akeeba-btn--green">
            <span class="akion-upload"></span>
		    @lang('COM_AKEEBA_PROFILES_HEADER_IMPORT')
        </button>
        <span class="akeeba-help-text">
	    	@lang('COM_AKEEBA_PROFILES_LBL_IMPORT_HELP')
    	</span>
    </div>

    <div class="akeeba-hidden-fields-container">
        <input type="hidden" name="boxchecked" id="boxchecked" value="0" />
        <input type="hidden" name="task" id="task" value="import" />
        <input type="hidden" name="@token()" value="1" />
    </div>

</form>

<p></p>
