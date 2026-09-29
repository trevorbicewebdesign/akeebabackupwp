<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

// Used for type hinting
/** @var  Solo\View\Manage\Html $this */

$router = $this->container->router;
$token = $this->container->session->getCsrfToken()->getValue();
$proKey = (defined('AKEEBABACKUP_PRO') && AKEEBABACKUP_PRO) ? 'PRO' : 'CORE';
?>

@if ($this->promptForBackupRestoration)
    @include('Manage/howtorestore_modal')
@endif

<div class="akeeba-block--info">
    <h4>@lang('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_LEGEND')</h4>

    <p>
        @sprintf('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_TEXT_' . $proKey,
            'https://www.akeeba.com/videos/1214-akeeba-solo/1637-abts05-restoring-site-new-server.html',
            $router->route('index.php?view=Transfer'),
            'https://www.akeeba.com/latest-kickstart-core.zip'
        )
    </p>
    @if (!AKEEBABACKUP_PRO)
        <p>
            @if ($this->getContainer()->segment->get('insideCMS', false))
                @sprintf('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_TEXT_CORE_INFO_ABOUT_PRO',
                'https://www.akeeba.com/products/akeeba-backup-wordpress.html')
            @else
                @sprintf('COM_AKEEBA_BUADMIN_LABEL_HOWDOIRESTORE_TEXT_CORE_INFO_ABOUT_PRO',
                'https://www.akeeba.com/products/akeeba-solo.html')
            @endif
        </p>
    @endif
</div>

<form action="@route('index.php?view=manage')" method="post" name="adminForm" id="adminForm"
      role="form" class="akeeba-form">

    <table class="akeeba-table--striped" id="itemsList">
        <thead>
        <tr>
            <th width="20">
                <input type="checkbox" name="toggle" value="" onclick="akeeba.System.checkAll(this);" />
            </th>
            <th width="20" class="akeeba-hidden-phone">
                @html('grid.sort', 'COM_AKEEBA_BUADMIN_LABEL_ID', 'id', $this->lists->order_Dir, $this->lists->order,
                'default')
            </th>
            <th width="80" class="akeeba-hidden-phone">
                @html('grid.sort', 'COM_AKEEBA_BUADMIN_LABEL_FROZEN', 'frozen', $this->lists->order_Dir, $this->lists->order,
                'default')
            </th>
            <th width="25%">
                @html('grid.sort', 'COM_AKEEBA_BUADMIN_LABEL_DESCRIPTION', 'description', $this->lists->order_Dir,
                $this->lists->order, 'default')
            </th>
            <th width="25%" class="akeeba-hidden-phone">
                @html('grid.sort', 'COM_AKEEBA_BUADMIN_LABEL_PROFILEID', 'profile_id', $this->lists->order_Dir,
                $this->lists->order, 'default')
            </th>
            <th width="80">
                @html('grid.sort', 'COM_AKEEBA_BUADMIN_LABEL_DURATION', 'backupstart', $this->lists->order_Dir,
                $this->lists->order, 'default')
            </th>
            <th width="80">
                @lang('COM_AKEEBA_BUADMIN_LABEL_STATUS')
            </th>
            <th width="110" class="akeeba-hidden-phone">
                @lang('COM_AKEEBA_BUADMIN_LABEL_SIZE')
            </th>
            <th class="akeeba-hidden-phone">
                @lang('COM_AKEEBA_BUADMIN_LABEL_MANAGEANDDL')
            </th>
        </tr>
        <tr>
            <td></td>
            <td class="akeeba-hidden-phone"></td>
            <td>
                @html('select.genericlist', $this->frozenList, 'filter_frozen', ['list.attr' => ['class' => 'akeebaGridViewAutoSubmitOnChange'], 'list.select' => $this->lists->fltFrozen])
            </td>
            <td>
                <input type="text" name="filter_description" id="description"
                       class="akeebaGridViewAutoSubmitOnChange" style="width: 100%;"
                       value="{{ $this->lists->fltDescription }}"
                       placeholder="@lang('SOLO_MANAGE_FIELD_DESCRIPTION')">
            </td>
            <td class="akeeba-hidden-phone">
                @html('select.genericlist', $this->profilesList, 'filter_profile', ['list.attr' => ['class' => 'akeebaGridViewAutoSubmitOnChange', 'style' => 'max-width: 12vw'], 'list.select' => $this->lists->fltProfile])
            </td>
            <td></td>
            <td></td>
            <td colspan="2" class="akeeba-hidden-phone"></td>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <td colspan="11" class="center">
                {{ $this->pagination->getListFooter() }}
            </td>
        </tr>
        </tfoot>
        <tbody>
        @if(empty($this->items))
            <tr>
                <td colspan="11">
                    @lang('SOLO_LBL_NO_RECORDS')
                </td>
            </tr>
        @endif

        @if (!empty($this->items))
			<?php $i = 0; ?>
            @foreach ($this->items as $record)
				<?php
				list($originDescription, $originIcon) = $this->getOriginInformation($record);
				list($startTime, $duration, $timeZoneText) = $this->getTimeInformation($record);
				list($statusClass, $statusIcon) = $this->getStatusInformation($record);
				$profileName = $this->getProfileName($record);

				$frozenIcon  = 'akion-waterdrop';
				$frozenTask  = 'freeze';
				$frozenTitle = Text::_('COM_AKEEBA_BUADMIN_LABEL_ACTION_FREEZE');

				if ($record['frozen'])
				{
					$frozenIcon  = 'akion-ios-snowy';
					$frozenTask  = 'unfreeze';
					$frozenTitle = Text::_('COM_AKEEBA_BUADMIN_LABEL_ACTION_UNFREEZE');
				}

				?>
                <tr>
                    <td>@html('grid.id', ++$i, $record['id'])</td>
                    <td class="akeeba-hidden-phone">
                        {{{ $record['id'] }}}
                    </td>

                    <td style="text-align: center">
                        <a href="@route('index.php?view=Manage&id=' . $record['id'] . '&task=' . $frozenTask . '&token=' . $token)" title="{{$frozenTitle}}">
                            <span class="{{ $frozenIcon }}"></span>
                        </a>
                    </td>

                    <td>
						<span class="{{ $originIcon }} akeebaCommentPopover" rel="popover"
                              title="@lang('COM_AKEEBA_BUADMIN_LABEL_ORIGIN')"
                              data-content="{{{ $originDescription }}}"></span>
                        @if( ! (empty($record['comment'])))
                            <span class="akion-help-circled akeebaCommentPopover" rel="popover"
                                  data-content="{{{ $record['comment'] }}}"></span>
                        @endif
                        <a href="@route('index.php?view=manage&task=showComment&id=' . $record['id'] . '&token=' . $token)">
                            {{{ empty($record['description']) ? Text::_('COM_AKEEBA_BUADMIN_LABEL_NODESCRIPTION') : $record['description'] }}}

                        </a>
                        <br />
                        <div class="akeeba-buadmin-startdate" title="@lang('COM_AKEEBA_BUADMIN_LABEL_START')">
                            <small>
                                <span class="akion-calendar"></span>
                                {{{ $startTime }}} {{{ $timeZoneText }}}
                            </small>
                        </div>
                    </td>
                    <td class="akeeba-hidden-phone">
                        #{{{ (int)$record['profile_id'] }}}. {{{ $profileName }}}
                        <br />
                        <small>
                            <em>{{{ $this->translateBackupType($record['type']) }}}</em>
                        </small>
                    </td>
                    <td>
                        {{{ $duration }}}
                    </td>
                    <td>
                        <span class="{{ $statusClass }} akeebaCommentPopover" rel="popover"
                              data-original-title="@lang('COM_AKEEBA_BUADMIN_LABEL_STATUS')"
                              data-content="@lang('COM_AKEEBA_BUADMIN_LABEL_STATUS_' . $record['meta'])"
                              style="padding: 0.4em 0.6em;"
                        >
                            <span class="{{ $statusIcon }}"></span>
                        </span>
                    </td>
                    <td class="akeeba-hidden-phone">
                        @if($record['meta'] == 'ok')
                            {{{ \Solo\Helper\Format::fileSize($record['size']) }}}

                        @elseif($record['total_size'] > 0)
                            <i>{{ \Solo\Helper\Format::fileSize($record['total_size']) }}</i>
                            @else
                            &mdash;
                        @endif
                    </td>
                    <td class="akeeba-hidden-phone">
                        @include('Manage/manage_column', ['record' => &$record])
                    </td>
                </tr>
            @endforeach
        @endif
        </tbody>
    </table>

    <div class="akeeba-hidden-fields-container">
        <input type="hidden" name="boxchecked" id="boxchecked" value="0">
        <input type="hidden" name="task" id="task" value="default">
        <input type="hidden" name="filter_order" id="filter_order" value="{{ $this->lists->order }}">
        <input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="{{ $this->lists->order_Dir }}">
        <input type="hidden" name="token" value="@token()">
    </div>
</form>
