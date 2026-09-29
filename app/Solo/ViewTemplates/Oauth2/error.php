<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * @var \Solo\View\Oauth2\Raw $this
 */

$doc = $this->getContainer()->application->getDocument();
$doc->addHTTPHeader('Pragma', 'public');
$doc->addHTTPHeader('Expires', '0');
$doc->addHTTPHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
$doc->addHTTPHeader('Cache-Control', 'public');
$doc->setMimeType('text/html');

$title = $this->getLanguage()->sprintf('COM_AKEEBA_OAUTH2_TITLE', $this->provider->getEngineNameForHumans());


?>
<html lang="<?= $this->getLanguage()->getLangCode() ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $this->getLanguage()->sprintf('COM_AKEEBA_OAUTH2_TITLE', $this->provider->getEngineNameForHumans()) ?></title>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
	      rel="stylesheet"
	      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
	      crossorigin="anonymous">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
	        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
	        defer
	        crossorigin="anonymous"></script>
</head>
<body>

<div class="card m-2 border-danger border-2">
	<div class="card-body">
		<h1>
			<?= $this->getLanguage()->sprintf('COM_AKEEBA_OAUTH2_AUTH_ERROR', $this->provider->getEngineNameForHumans()) ?>
		</h1>
		<p>
			<?= $this->escape($this->exception->getMessage()) ?>
		</p>
	</div>
</div>

</body>
</html>
