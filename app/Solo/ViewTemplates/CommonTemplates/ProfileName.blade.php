<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_AKEEBA') or die();
?>
<div class="akeeba-block--info">
	<strong>@lang('COM_AKEEBA_CPANEL_PROFILE_TITLE')</strong>:
	#{{{ (int)($this->profileid) }}} {{{ $this->profilename }}}
</div>
