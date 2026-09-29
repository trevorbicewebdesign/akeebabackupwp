<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Pythia\Oracle;

use Awf\Utils\Buffer;
use Solo\Pythia\AbstractOracle;

class Wordpress extends AbstractOracle
{
	/**
	 * The name of this oracle class
	 *
	 * @var   string
	 */
	protected $oracleName = 'wordpress';

	/**
	 * Should I try to load the wp-config.php file directly?
	 *
	 * @var   bool
	 * @since 7.3.0
	 */
	protected $loadWPConfig = true;

	/**
	 * Does this class recognises the CMS type as Wordpress?
	 *
	 * @return  boolean
	 */
	public function isRecognised(): bool
	{
		if (!@file_exists($this->path . '/wp-config.php') && !@file_exists($this->path . '/../wp-config.php'))
		{
			return false;
		}

		if (!@file_exists($this->path . '/wp-login.php'))
		{
			return false;
		}

		if (!@file_exists($this->path . '/xmlrpc.php'))
		{
			return false;
		}

		if (!@is_dir($this->path . '/wp-admin'))
		{
			return false;
		}

		return true;
	}

	public function setLoadWPConfig(bool $value): void
	{
		$this->loadWPConfig = ($value == true);
	}

	/**
	 * Return the database connection information for this CMS / script
	 *
	 * @return  array
	 */
	public function getDbInformation(): array
	{
		$ret = [
			'driver'     => 'mysqli',
			'host'       => '',
			'port'       => '',
			'username'   => '',
			'password'   => '',
			'name'       => '',
			'prefix'     => '',
			'proxy_host' => '',
			'proxy_port' => '',
			'proxy_user' => '',
			'proxy_pass' => '',
		];

		$filePath = $this->path . '/wp-config.php';

		if (!@file_exists($filePath))
		{
			$filePath = $this->path . '/../wp-config.php';
		}

		$hasTokenizer = function_exists('token_get_all');
		$fileContents = file_get_contents($filePath);

		if ($this->loadWPConfig)
		{
			$newValues = $this->parseByInclusion($fileContents);
		}
		elseif ($hasTokenizer)
		{
			$newValues = $this->parseWithTokenizer($fileContents);
		}
		else
		{
			$newValues = $this->parseWithoutTokenizer($fileContents);
		}

		return array_merge($ret, $newValues);
	}

	/**
	 * Parse the wp-config.php file using the PHP tokenizer extension. We use the tokenizer to remove all comments, then
	 * our regular code to parse the resulting file. Profit!
	 *
	 * @param   string  $fileContents  The contents of the file
	 *
	 * @return  array
	 */
	protected function parseWithTokenizer(?string $fileContents): array
	{
		$tokens = token_get_all($fileContents ?? '');

		$commentTokens = [T_COMMENT];

		if (defined('T_DOC_COMMENT'))
		{
			$commentTokens[] = T_DOC_COMMENT;
		}

		if (defined('T_ML_COMMENT'))
		{
			$commentTokens[] = T_ML_COMMENT;
		}

		$newStr = '';

		foreach ($tokens as $token)
		{
			if (is_array($token))
			{
				if (in_array($token[0], $commentTokens))
				{
					/**
					 * If the comment ended in a newline we need to output the newline. Otherwise we will have
					 * run-together lines which won't be parsed correctly by parseWithoutTokenizer.
					 */
					if (substr($token[1], -1) == "\n")
					{
						$newStr .= "\n";
					}

					continue;
				}

				$token = $token[1];
			}

			$newStr .= $token;
		}

		return $this->parseWithoutTokenizer($newStr);
	}

	/**
	 * Parse the wp-config.php file without using the PHP tokenizer extension
	 *
	 * @param   string  $fileContents  The contents of the wp-config.php file
	 *
	 * @return  array
	 */
	protected function parseWithoutTokenizer(?string $fileContents): array
	{
		$fileContents = explode("\n", $fileContents ?? '');
		$fileContents = array_map('trim', $fileContents);
		$ret          = [];

		foreach ($fileContents as $line)
		{
			$line  = trim($line);
			$key   = null;
			$value = null;

			if (strpos($line, 'define') !== false)
			{
				[$key, $value] = $this->parseDefine($line);
			}
			elseif (strpos($line, 'const') !== false)
			{
				[$key, $value] = $this->parseConst($line);
			}

			if (is_string($key) && !empty($key))
			{
				switch (strtoupper($key))
				{
					case 'DB_NAME':
						$ret['name'] = $value;
						break;

					case 'DB_USER':
						$ret['username'] = $value;
						break;

					case 'DB_PASSWORD':
						$ret['password'] = $value;
						break;

					case 'DB_HOST':
						$ret['host'] = $value;
						break;

					case 'DB_CHARSET':
						$ret['charset'] = $value;
						break;

					case 'DB_COLLATE':
						$ret['collate'] = $value;
						break;

					case 'WP_PROXY_HOST':
						$ret['proxy_host'] = $value;
						break;

					case 'WP_PROXY_PORT':
						$ret['proxy_port'] = $value;
						break;

					case 'WP_PROXY_USERNAME':
						$ret['proxy_user'] = $value;
						break;

					case 'WP_PROXY_PASSWORD':
						$ret['proxy_pass'] = $value;
						break;
				}
			}
			elseif (strpos($line, '$table_prefix') === 0)
			{
				$parts         = explode('=', $line, 2);
				$prefixData    = trim($parts[1]);
				$ret['prefix'] = $this->parseStringDefinition($prefixData);
			}
		}

		return $ret;
	}

	/**
	 * Parses the wp-config.php file by evaluating it as raw PHP code, just like WP-CLI does.
	 *
	 * The only difference is that we use a stream buffer instead of evil-with-an-a-instead-of-i (can't type it without
	 * triggering false positives from security scanners). You can disable it by passing --no-wp-config in CLI scripts,
	 * setting no-wp-config=1 in web scripts or creating a text file without any contents in
	 * wp-content/plugins/akeebabackupwp/helpers/no-wp-config.txt
	 *
	 * @param  $fileContents
	 *
	 * @return array
	 */
	protected function parseByInclusion(?string $fileContents): array
	{
		if (!Buffer::canRegisterWrapper())
		{
			$hasTokenizer = function_exists('token_get_all');

			if ($hasTokenizer)
			{
				$newValues = $this->parseWithTokenizer($fileContents);
			}
			else
			{
				$newValues = $this->parseWithoutTokenizer($fileContents);
			}
		}

		// Convert the file into individual lines
		$lines = explode("\n", $fileContents ?? '');

		// Replace __DIR__ and __FILE__ with their equivalents
		$absPath = defined('ABSPATH') ? constant('ABSPATH') : $this->path;

		$replacements = [
			'__DIR__'  => "'" . $absPath . "'",
			'__FILE__' => "'" . $absPath . "/wp-config.php'",
		];
		$lines        = array_map(function ($line) use ($replacements) {
			return str_replace(array_keys($replacements), array_values($replacements), $line);
		}, $lines);

		// Remove the require_once(ABSPATH . 'wp-settings.php'); line
		$lines = array_filter($lines, function ($line) {
			return !preg_match('#\s?require_once.*wp-settings\.php#i', $line);
		});

		$fileContents = implode("\n", $lines);
		file_put_contents('awf://abwp/wp-config.php', $fileContents);
		include_once 'awf://abwp/wp-config.php';

		return [
			'driver'     => 'mysqli',
			'host'       => defined('DB_HOST') ? DB_HOST : '',
			'port'       => '',
			'username'   => defined('DB_USER') ? DB_USER : '',
			'password'   => defined('DB_PASSWORD') ? DB_PASSWORD : '',
			'name'       => defined('DB_NAME') ? DB_NAME : '',
			'charset'    => defined('DB_CHARSET') ? DB_CHARSET : 'utf8',
			'collate'    => defined('DB_COLLATE') ? DB_COLLATE : '',
			'prefix'     => isset($table_prefix) ? $table_prefix : 'wp_',
			'proxy_host' => defined('WP_PROXY_HOST') ? WP_PROXY_HOST : '',
			'proxy_port' => defined('WP_PROXY_PORT') ? WP_PROXY_PORT : '',
			'proxy_user' => defined('WP_PROXY_USERNAME') ? WP_PROXY_USERNAME : '',
			'proxy_pass' => defined('WP_PROXY_PASSWORD') ? WP_PROXY_PASSWORD : '',
		];
	}
}
