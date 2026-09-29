<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (!isset($exc))
{
	die();
}

if (defined('WPINC'))
{
	$network = is_multisite() ? 'network/' : '';
	$abwpURL = admin_url() . $network . 'admin.php?page=' . AkeebaBackupWP::$dirName . '/' . AkeebaBackupWP::$fileName;
}

switch ($exc->getCode())
{
	case 400:
		$header   = 'HTTP/1.1 400 Bad Request';
		$appError = 'Bad Request';
		break;
	case 401:
		$header   = 'HTTP/1.1 401 Unauthorized';
		$appError = 'Unauthorised';
		break;
	case 403:
		$header   = 'HTTP/1.1 403 Forbidden';
		$appError = 'Access Denied';
		break;
	case 404:
		$header   = 'HTTP/1.1 404 Not Found';
		$appError = 'Not Found';
		break;
	case 501:
		$header   = 'HTTP/1.1 501 Not Implemented';
		$appError = 'Not Implemented';
		break;
	case 503:
		$header   = 'HTTP/1.1 503 Service Unavailable';
		$appError = 'Service Unavailable';
		break;
	case 500:
	default:
		$header   = 'HTTP/1.1 500 Internal Server Error';
		$appError = 'Application Error';
		break;
}

// Avoid errors if headers were already sent
if (!headers_sent())
{
	header($header);
}

?>
<div class="akeeba-renderer-fef" id="error-wrap">
	<div class="akeeba-panel--danger">
		<header class="akeeba-block-header">
			<h2>
				<span class="akeeba-label--grey"><?php echo $exc->getCode() ?></span> <?php echo $appError ?>
			</h2>
		</header>

		<?php if (defined('AKEEBADEBUG')): ?>
			<p>&nbsp;</p>
			<p>
				Please submit the following error message and trace in its entirety when requesting support
			</p>
			<h4 class="text-info" id="error-message-text">
				<?= get_class($exc) ?> <?php echo $exc->getCode() . ' :: ' . $exc->getMessage(); ?>
				in
				<?php echo $exc->getFile() ?>
				<span class="label label-info">L <?php echo $exc->getLine(); ?></span>
			</h4>
			<p>Debug backtrace</p>
			<pre class="bg-info"><?php echo $exc->getTraceAsString(); ?></pre>
		<?php else: ?>
			<p id="error-message-text">
				<?= get_class($exc) ?>: <?php echo $exc->getMessage(); ?>
			</p>
		<?php endif; ?>
		<?php if (defined('WPINC')): ?>
		<hr />
		<div class="akeeba-container--50-50">
			<div>
				<a href="<?= $abwpURL ?>" class="akeeba-btn--default">
					<span class="akion-ios-arrow-back"></span>
					Akeeba Backup Control Panel
				</a>
			</div>
			<div>
				<a href="<?= $abwpURL ?>&_ak_reset_session=1" class="akeeba-btn--red">
					<span class="akion-nuclear"></span>
					Reset temporary storage
				</a>
				<p class="akeeba-help-text">
					Use this button if you get <var>Access Denied</var> or <var>Application Error</var> messages when trying to access the Akeeba Backup Control Panel despite being logged in as a WordPress administrator. It clears Akeeba Backup's temporary data storage and tries to reload the Control Panel page which fixes most of these issues.
				</p>
			</div>
		</div>
		<?php endif; ?>
	</div>
