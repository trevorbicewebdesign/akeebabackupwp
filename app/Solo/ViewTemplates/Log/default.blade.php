<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_AKEEBA') or die();

// Used for type hinting
/** @var  \Solo\View\Log\Html $this */

?>
@if(isset($this->logs) && count($this->logs))
    <form name="adminForm" id="adminForm" action="@route('index.php?view=Log')" method="POST"
          class="akeeba-form--inline">
        <div class="akeeba-form-group">
            <label for="tag">
                @lang('COM_AKEEBA_LOG_CHOOSE_FILE_TITLE')
            </label>
            @html('select.genericList', $this->logs, 'tag', ['list.attr' => ['class' => 'akeebaGridViewAutoSubmitOnChange'], 'list.select' => $this->tag, 'id' => 'tag'])
        </div>

        @if(!empty($this->tag))
            <div class="akeeba-form-group--actions">
                <a class="akeeba-btn--primary"
                   href="@route('index.php?view=Log&task=download&format=raw&tag=' . urlencode($this->tag))">
                    <span class="akion-ios-download"></span>
                    @lang('COM_AKEEBA_LOG_LABEL_DOWNLOAD')
                </a>

            </div>
        @endif

        <input type="hidden" name="token" value="@token()">
    </form>
@endif

@if(!empty($this->tag))
    @if ($this->logTooBig)
        <div class="akeeba-block--warning">
            <p>
                @sprintf('COM_AKEEBA_LOG_SIZE_WARNING', number_format($this->logSize / (1024 * 1024), 2))
            </p>
            <button class="akeeba-btn--dark" id="showlog">
                @lang('COM_AKEEBA_LOG_SHOW_LOG')
            </button>
        </div>
    @endif

    <div id="iframe-holder" class="akeeba-panel--primary" style="display: {{ $this->logTooBig ? 'none' : 'block' }};">
        @if (!$this->logTooBig)
            <iframe
                    src="@route('index.php?view=Log&task=iframe&format=raw&tag=' . urlencode($this->tag))"
                    width="99%" height="400px">
            </iframe>
        @endif
    </div>
@endif

@if( ! (isset($this->logs) && count($this->logs)))
    <div class="alert alert-danger">
        @lang('COM_AKEEBA_LOG_NONE_FOUND')
    </div>
@endif
