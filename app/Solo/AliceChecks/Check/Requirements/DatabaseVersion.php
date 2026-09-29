<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Alice\Check\Requirements;

use Awf\Container\Container;
use Solo\Alice\Check\Base;
use Awf\Text\Text;

/**
 * Checks for supported DB type and version
 */
class DatabaseVersion extends Base
{
	public function __construct(Container $container, $logFile = null)
	{
		$this->priority         = 20;
		$this->checkLanguageKey = 'COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_DATABASE';

		parent::__construct($container, $logFile);
	}

	public function check()
	{
		// Instead of reading the log, I can simply take the JDatabase object and test it
		$db        = $this->container->db;
		$connector = strtolower($db->name);
		$version   = $db->getVersion();

		switch ($connector)
		{
			case 'mysql':
			case 'mysqli':
			case 'pdomysql':
				if (version_compare($version, '5.0.47', 'lt'))
				{
					$this->setResult(-1);
					$this->setErrorLanguageKey([
						'COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_DATABASE_VERSION_TOO_OLD', $version,
					]);
				}
				break;

			case 'pdo':
			case 'sqlite':
				$this->setResult(-1);
				$this->setErrorLanguageKey([
					'COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_DATABASE_UNSUPPORTED', $connector,
				]);
				break;

			default:
				$this->setResult(-1);
				$this->setErrorLanguageKey(['COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_DATABASE_UNKNOWN', $connector]);
				break;
		}
	}

	public function getSolution()
	{
		return Text::_('COM_AKEEBA_ALICE_ANALYZE_REQUIREMENTS_DATABASE_SOLUTION');
	}
}
