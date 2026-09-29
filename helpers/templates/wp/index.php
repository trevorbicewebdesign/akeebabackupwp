<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Document\Document;
use Awf\Text\Text;
use Awf\Uri\Uri;

/** @var Document $this */

include __DIR__ . '/php/menu.php';

$this->outputHTTPHeaders();
$darkMode = $this->getContainer()->appConfig->get('darkmode', -1);
$langParts    = explode('-', $this->getContainer()->language->getLangCode(), 2);

if (defined('AKEEBA_SOLOWP_OBFLAG') || defined('AKEEBABACKUPWP_OBFLAG'))
{ ?>
<html lang="<?= $langParts[0] ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<link rel="shortcut icon" href="<?php echo Uri::base() ?>media/logo/favicon.ico">
	<link rel="apple-touch-icon-precomposed" href="<?php echo Uri::base() ?>media/logo/abwp-152.png">
	<meta name="msapplication-TileColor" content="#FFFFFF">
	<meta name="msapplication-TileImage" content="<?php echo Uri::base() ?>media/logo/abwp-144.png">
	<link rel="apple-touch-icon-precomposed" sizes="152x152" href="<?php echo Uri::base() ?>media/logo/abwp-152.png">
	<link rel="apple-touch-icon-precomposed" sizes="144x144" href="<?php echo Uri::base() ?>media/logo/abwp-144.png">
	<link rel="apple-touch-icon-precomposed" sizes="120x120" href="<?php echo Uri::base() ?>media/logo/abwp-120.png">
	<link rel="apple-touch-icon-precomposed" sizes="114x114" href="<?php echo Uri::base() ?>media/logo/abwp-114.png">
	<link rel="apple-touch-icon-precomposed" sizes="72x72" href="<?php echo Uri::base() ?>media/logo/abwp-72.png">
	<link rel="apple-touch-icon-precomposed" href="<?php echo Uri::base() ?>media/logo/abwp-57.png">
	<link rel="icon" href="<?php echo Uri::base() ?>media/logo/abwp-32.png" sizes="32x32">

	<title><?php echo Text::_('SOLO_APP_TITLE') ?></title>
	<?php include __DIR__ . '/php/head.php'; ?>
</head>
<body style="padding: 0; margin: 0">
<?php
}
else
{
	include __DIR__ . '/php/head_wp.php';
}

?>
<div class="akeeba-renderer-fef <?php echo ($darkMode == 1) ? 'akeeba-renderer-fef--dark' : 'akeeba-wp' ?>">
	<?php if ($this->getContainer()->input->getCmd('tmpl', '') != 'component'): ?>
	<header class="akeeba-navbar">
		<div class="akeeba-maxwidth akeeba-flex">
			<!-- Branding -->
			<div class="akeeba-nav-logo">
				<a href="<?php echo $this->getContainer()->router->route('index.php') ?>">
					<span class="aklogo-backup-wp"></span>
					<span class="akeeba-product-name">
                        <?php echo Text::_('SOLO_APP_TITLE') ?>
                    </span>
					<span class="akeeba-product-<?php echo AKEEBABACKUP_PRO ? 'pro' : 'core' ?>">
                        <?php echo AKEEBABACKUP_PRO ? 'Professional' : 'Core' ?>
                    </span>

					<?php if ((substr(AKEEBABACKUP_VERSION, 0, 3) == 'rev') || (strpos(AKEEBABACKUP_VERSION, '.a') !== false)): ?>
						<span class="akeeba-label--red--small">Alpha</span>
					<?php elseif (strpos(AKEEBABACKUP_VERSION, '.b') !== false): ?>
						<span class="akeeba-label--orange--small">Beta</span>
					<?php elseif (strpos(AKEEBABACKUP_VERSION, '.rc') !== false): ?>
						<span class="akeeba-label--grey--small">RC</span>
					<?php endif; ?>
				</a>
				<a href="#" class="akeeba-menu-button akeeba-hidden-desktop akeeba-hidden-tablet"
				   title="<?php echo Text::_('SOLO_COMMON_TOGGLENAV') ?>"><span class="akion-navicon-round"></span></a>
			</div>
			<!-- Navigation -->

			<nav>
				<?php _solo_template_renderSubmenu($this, $this->getMenu()->getMenuItems('main')); ?>
			</nav>
		</div>
	</header>

<?php include __DIR__ . '/php/toolbar.php' ?>

	<div class="akeeba-maxwidth">
		<?php endif; ?>

		<?php include __DIR__ . '/php/messages.php' ?>
		<?php echo $this->getBuffer() ?>

		<?php if ($this->getContainer()->input->getCmd('tmpl', '') != 'component'): ?>
	</div>
	<footer id="akeeba-footer">
		<div class="akeeba-maxwidth">
			<p class="muted credit">
				Copyright &copy;2013 &ndash; <?php echo date('Y') ?> <a href="https://www.akeeba.com">Akeeba Ltd</a>.
				All legal rights reserved.
			</p>
			<p>
				<?php echo Text::_('SOLO_APP_TITLE') ?> is Free Software distributed under the
				<a href="http://www.gnu.org/licenses/gpl.html">GNU GPL version 3</a> or any later version published by
				the FSF.
			</p>
			<?php if (defined('AKEEBADEBUG')): ?>
				<p class="small">
					Page creation <?php echo sprintf('%0.3f', $this->getApplication()->getTimeElapsed()) ?> sec
					&bull;
					Peak memory usage <?php echo sprintf('%0.1f', memory_get_peak_usage() / 1048576) ?> MB
				</p>
			<?php endif; ?>
		</div>
	</footer>
<?php endif; ?>
</div>
<?php if (defined('AKEEBA_SOLOWP_OBFLAG') || defined('AKEEBABACKUPWP_OBFLAG')): ?>
</body>
</html>
<?php endif; ?>
