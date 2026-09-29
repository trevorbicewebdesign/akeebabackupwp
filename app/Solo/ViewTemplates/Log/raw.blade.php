<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Text\Text;

defined('_AKEEBA') or die();

// Used for type hinting
/** @var  \Solo\View\Log\Html $this */

$router = $this->container->router;

// -- Get the log's file name
$tag     = $this->tag;
$logFile = \Akeeba\Engine\Factory::getLog()->getLogFilename($tag);

if (!@is_file($logFile) && @file_exists(substr($logFile, 0, -4)))
{
	/**
	 * Bad host: the log file akeeba.tag.log.php may not exist but the akeeba.tag.log does.
	 */
	$logFile = substr($logFile, 0, -4);
}

@ob_end_clean();

?>
<html>
<head>
    <title></title>
    <link href="https://fonts.googleapis.com/css?family=Roboto+Mono&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Source+Code+Pro&display=swap" rel="stylesheet">
    <style type="text/css">
        body {
            background-color: white;
            color: #333333;
            font-family: 'Roboto Mono', "Consolas", "Courier New", monospace;
            font-size: 10pt;
            white-space: pre;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .warning {
            color: #f0ad4e;
            font-weight: bold;
        }
        .info {
            color: black;
        }
        .debug {
            color: #5e5c5d;
        }

        {{ '@' }}media (prefers-color-scheme: dark)
        {
            body {
                background-color: #333333;
                color: #c0c0c0;
            }
            .debug {
                color: #a0a0a0;
            }
            .info {
                color: #e0e0e0;
            }
        }
    </style>
</head>
<body><?php

if (!@file_exists($logFile))
{
	// Oops! The log doesn't exist!
	echo '<p>' . Text::sprintf('SOLO_LOG_ERR_LOGFILENOTEXISTS', $logFile) . '</p>';
}
else
{
	// Allright, let's load and render it
	$fp = fopen($logFile, "r");

	if ($fp === FALSE)
	{
		// Oops! The log isn't readable?!
		echo '<p>' . Text::_('COM_AKEEBA_LOG_ERROR_UNREADABLE') . '</p>';
	}
	else while (!feof($fp))
	{
		$line = fgets($fp);
		if (!$line) return;
		$exploded = explode("|", $line, 3);
		unset($line);
		if (count($exploded) < 3) continue;
		switch (trim($exploded[0]))
		{
			case "ERROR":
				$fmtString = "<span class=\"error\">[";
				break;
			case "WARNING":
				$fmtString = "<span class=\"warning\">[";
				break;
			case "INFO":
				$fmtString = "<span class=\"info\">[";
				break;
			case "DEBUG":
				$fmtString = "<span class=\"debug\">[";
				break;
			default:
				$fmtString = "<span class=\"default\">[";
				break;
		}
		$fmtString .= $exploded[1] . "] " . htmlspecialchars($exploded[2]) . "</span>";
		unset($exploded);
		echo $fmtString;
		unset($fmtString);
	}
}
?>
</body>
</html>

