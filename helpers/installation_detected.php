<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('AKEEBASOLO') || die;

use Awf\Text\Text; ?>

<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<script type="text/javascript"
			src="<?= plugins_url('app/media/js/fef/menu.min.js', self::$absoluteFileName) ?>"></script>
	<script type="text/javascript"
			src="<?= plugins_url('app/media/js/fef/tabs.min.js', self::$absoluteFileName) ?>"></script>

	<link rel="stylesheet" type="text/css"
		  href="<?= plugins_url('app/media/css/fef-wp.min.css', self::$absoluteFileName) ?>">
	<link rel="stylesheet" type="text/css"
		  href="<?= plugins_url('app/media/css/theme.min.css', self::$absoluteFileName) ?>">
	<link rel="stylesheet" type="text/css"
		  href="<?= plugins_url('app/media/css/dark.min.css', self::$absoluteFileName) ?>">
	<link rel="stylesheet" type="text/css"
		  href="<?= plugins_url('app/media/css/theme_dark.min.css', self::$absoluteFileName) ?>">

	<title>
		<?= Text::_('SOLO_NOTYETRESTORED_PAGE_TITLE') ?>
	</title>
</head>
<body>
<div class="akeeba-renderer-fef akeeba-wp">
	<h1>
		<span class="aklogo-backup-wp"></span>
		<?= Text::_('SOLO_NOTYETRESTORED_PAGE_TITLE') ?>
	</h1>
	<h2>
		<a class="akeeba-btn--block" href="installation/index.php">
			<span class="aklogo-kickstart"></span>
			<?= Text::_('SOLO_NOTYETRESTORED_BTN_FINISH_RESTORING') ?>
		</a>
	</h2>

	<div class="akeeba-panel--info">

		<h4>
			<?= Text::_('SOLO_NOTYETRESTORED_HEAD_WHYSEETHIS') ?>
		</h4>

		<p>
			<?= Text::_('SOLO_NOTYETRESTORED_WHYSEETHIS_LBL_P1') ?>
		</p>

		<p>
			<?= Text::_('SOLO_NOTYETRESTORED_WHYSEETHIS_LBL_P2') ?>
		</p>
		<p>
			<?= Text::_('SOLO_NOTYETRESTORED_WHYSEETHIS_LBL_P3') ?>
		</p>

		<p>
			<a class="akeeba-btn--teal" href="installation/index.php">
				<span class="aklogo-kickstart"></span>
				<?= Text::_('SOLO_NOTYETRESTORED_BTN_FINISH_RESTORING') ?>
			</a>
		</p>

		<p>
			<?= Text::_('SOLO_NOTYETRESTORED_WHYSEETHIS_LBL_P4') ?>
		</p>

		<h4>
			<?= Text::_('SOLO_NOTYETRESTORED_HEAD_STILLSEETHIS') ?>
		</h4>

		<p>
			<?= Text::_('SOLO_NOTYETRESTORED_STILLSEETHIS_LBL_P1') ?>
		</p>

		<p>
			<?= Text::_('SOLO_NOTYETRESTORED_STILLSEETHIS_LBL_P2') ?>
		</p>
	</div>
</div>
</body>
</html>
