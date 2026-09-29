<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

/** @var \Solo\View\Dbfilters\Html $this */

?>
@include('CommonTemplates/ErrorModal')
@include('CommonTemplates/ProfileName')

<form class="akeeba-form--inline akeeba-panel--info">
    <div class="akeeba-form-group">
		<label>@lang('COM_AKEEBA_DBFILTER_LABEL_ROOTDIR')</label>
	    {{ $this->root_select }}
    </div>
	<div id="addnewfilter" class="akeeba-form-group--actions">
		<label>
			@lang('COM_AKEEBA_FILEFILTERS_LABEL_ADDNEWFILTER')
        </label>
		<button class="akeeba-btn--grey" id="comAkeebaDatabaseFiltersAddNewTables">
			@lang('COM_AKEEBA_DBFILTER_TYPE_TABLES')
		</button>
		<button class="akeeba-btn--grey" id="comAkeebaDatabaseFiltersAddNewTableData">
			@lang('COM_AKEEBA_DBFILTER_TYPE_TABLEDATA')
		</button>
	</div>
</form>

<div class="akeeba-panel--primary">
    <div id="ak_list_container">
        <table id="ak_list_table" class="akeeba-table--striped">
            <thead>
            <tr>
                <td width="250px">@lang('COM_AKEEBA_FILEFILTERS_LABEL_TYPE')</td>
                <td>@lang('COM_AKEEBA_FILEFILTERS_LABEL_FILTERITEM')</td>
            </tr>
            </thead>
            <tbody id="ak_list_contents">
            </tbody>
        </table>
    </div>
</div>
