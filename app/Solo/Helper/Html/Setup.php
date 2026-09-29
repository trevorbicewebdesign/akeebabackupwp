<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper\Html;

use Akeeba\Engine\Factory;
use Awf\Database\Driver;
use Awf\Html\AbstractHelper;
use Awf\Text\Text;

/**
 * Setup helper class
 */
class Setup extends AbstractHelper
{
	/**
	 * Get a dropdown list for database drivers
	 *
	 * @param   string  $selected  Selected value
	 * @param   string  $name      The name (also used for id) of the field, default: driver
	 *
	 * @return  string  HTML
	 */
	public function databaseTypesSelect($selected = '', $name = 'driver')
	{
		$connectors = Driver::getConnectors();

		$connectors = array_filter(
			$connectors, function (?string $driverName) {
			return !empty($driverName) && in_array(strtolower($driverName), ['mysql', 'mysqli', 'pdomysql']);
		}
		);

		$html = '<select name="' . $name . '" id="' . $name . '">' . "\n";

		foreach ($connectors as $connector)
		{
			// Unsupported driver types
			if (in_array(strtoupper($connector), ['PDO', 'NONE', 'SQLITE', 'PGSQL', 'POSTGRESQL']))
			{
				continue;
			}

			$checked = (strtoupper($selected) == strtoupper($connector)) ? 'selected="selected"' : '';

			$html .= "\t<option value=\"$connector\" $checked>" . Text::_(
					'SOLO_SETUP_LBL_DATABASE_DRIVER_' . $connector
				) . "</option>\n";
		}

		$html .= "</select>";

		return $html;
	}

	/**
	 * Get a dropdown list for script types
	 *
	 * @param   string  $selected  Selected value
	 * @param   string  $name      The name (also used for id) of the field, default: installer
	 *
	 * @return  string  HTML
	 */
	public function restorationScriptSelect($selected = '', $name = 'installer')
	{
		$installers = Factory::getEngineParamsProvider()->getInstallerList(true);

		$options = [];

		foreach ($installers as $key => $installer)
		{
			$options[] = $this->container->html->select->option($key, $installer['name']);
		}

		return $this->container->html->select->genericList(
			$options, $name, [], 'value', 'text', $selected, $name, false
		);
	}

	/**
	 * Get a dropdown list for restoration scripts
	 *
	 * @param   string  $selected  Selected value
	 * @param   string  $name      The name (also used for id) of the field, default: scripttype
	 *
	 * @return  string  HTML
	 */
	public function scriptTypesSelect($selected = '', $name = 'scripttype')
	{
		$scriptTypes = [
			'generic',
			'joomla',
			'wordpress',
		];

		$options = [];

		foreach ($scriptTypes as $scriptType)
		{
			$options[] = $this->container->html->select->option(
				$scriptType, Text::_('SOLO_CONFIG_PLATFORM_SCRIPTTYPE_' . $scriptType)
			);
		}

		return $this->container->html->select->genericList(
			$options, $name, [], 'value', 'text', $selected, $name, false
		);
	}

	/**
	 * Get a dropdown list for mailer engines
	 *
	 * @param   string  $selected  Selected value
	 * @param   string  $name      The name (also used for id) of the field, default: mailer
	 *
	 * @return  string  HTML
	 */
	public function mailerSelect($selected = '', $name = 'mailer')
	{

		$scriptTypes = ['mail', 'smtp', 'sendmail'];

		$options = [];

		foreach ($scriptTypes as $scriptType)
		{
			$options[] = $this->container->html->select->option(
				$scriptType, Text::_('SOLO_SYSCONFIG_EMAIL_MAILER_' . $scriptType)
			);
		}

		return $this->container->html->select->genericList(
			$options, $name, [], 'value', 'text', $selected, $name, false
		);
	}

	/**
	 * Get a dropdown list for SMTP security settings
	 *
	 * @param   string  $selected  Selected value
	 * @param   string  $name      The name (also used for id) of the field, default: smtpsecure
	 *
	 * @return  string  HTML
	 */
	public function smtpSecureSelect($selected = '', $name = 'smtpsecure')
	{
		$options   = [];
		$options[] = $this->container->html->select->option(0, Text::_('SOLO_SYSCONFIG_EMAIL_SMTPSECURE_NONE'));
		$options[] = $this->container->html->select->option(1, Text::_('SOLO_SYSCONFIG_EMAIL_SMTPSECURE_SSL'));
		$options[] = $this->container->html->select->option(2, Text::_('SOLO_SYSCONFIG_EMAIL_SMTPSECURE_TLS'));

		return $this->container->html->select->genericList(
			$options, $name, [], 'value', 'text', $selected, $name, false
		);
	}

	/**
	 * Get a dropdown of available timezones
	 *
	 * @param   string  $selected  Pre-selected value
	 * @param   string  $name      The name and id of the input element
	 *
	 * @return  string  HTML
	 */
	public function timezoneSelect($selected = '', $name = 'timezone', $includeDefaults = false, $disabled = false)
	{
		$groups      = [];
		$zoneHeaders = [
			'Africa',
			'America',
			'Antarctica',
			'Arctic',
			'Asia',
			'Atlantic',
			'Australia',
			'Europe',
			'Indian',
			'Pacific',
		];
		$zones       = \DateTimeZone::listIdentifiers();

		// Build the group lists.
		foreach ($zones as $zone)
		{
			// Time zones not in a group we will ignore.
			if (strpos($zone, '/') === false)
			{
				continue;
			}

			// Get the group/locale from the timezone.
			[$group, $locale] = explode('/', $zone, 2);

			// Only use known groups.
			if (true || in_array($group, $zoneHeaders))
			{
				// Initialize the group if necessary.
				if (!isset($groups[$group]))
				{
					$groups[$group] = [];
				}

				// Only add options where a locale exists.
				if (!empty($locale))
				{
					$groups[$group][$zone] = $this->container->html->select->option(
						$zone, str_replace('_', ' ', $locale)
					);
				}
			}
		}

		// Sort the group lists.
		ksort($groups);

		foreach ($groups as &$location)
		{
			sort($location);
		}

		if ($includeDefaults)
		{
			$defaultGroup = [
				$this->container->html->select->option('GMT', 'GMT'),
			];

			if (defined('WPINC'))
			{
				$defaultGroup[] = $this->container->html->select->option(
					'AKEEBA/DEFAULT', Text::_('COM_AKEEBA_CONFIG_FORCEDBACKUPTZ_DEFAULT_WORDPRESS')
				);
			}
			else
			{
				$defaultGroup[] = $this->container->html->select->option(
					'AKEEBA/DEFAULT', Text::_('COM_AKEEBA_CONFIG_FORCEDBACKUPTZ_DEFAULT')
				);
			}


			$groups[Text::_('COM_AKEEBA_CONFIG_FORCEDBACKUPTZ_DEFAULTGROUP')] = $defaultGroup;

			ksort($groups);
		}

		$options = [
			'id'          => $name,
			'list.select' => $selected,
			'group.items' => null,
		];

		if ($disabled)
		{
			$options['list.attr'] = ['disabled' => 'disabled'];
		}

		return $this->container->html->select->groupedList($groups, $name, $options);
	}

	/**
	 * Get a dropdown of available timezone formats
	 *
	 * @param   string  $selected  Pre-selected value
	 *
	 * @return  string  HTML
	 */
	public function timezoneFormatSelect($selected = '')
	{
		$rawOptions = [
			'COM_AKEEBA_CONFIG_BACKEND_TIMEZONETEXT_NONE'         => '',
			'COM_AKEEBA_CONFIG_BACKEND_TIMEZONETEXT_ABBREVIATION' => 'T',
			'COM_AKEEBA_CONFIG_BACKEND_TIMEZONETEXT_GMTOFFSET'    => '\\G\\M\\TP',
		];

		$html = '<select name="timezonetext" id="timezonetext">' . "\n";

		foreach ($rawOptions as $label => $value)
		{
			$checked = (strtoupper($selected) == strtoupper($value)) ? 'selected="selected"' : '';

			$label = Text::_($label);
			$html  .= "\t<option value=\"$value\" $checked>$label</option>\n";
		}

		$html .= "</select>";

		return $html;
	}

	/**
	 * Get a dropdown for the filesystem driver selection
	 *
	 * @param   string  $selected  The pre-selected value
	 *
	 * @return  string  HTML
	 */
	public function fsDriverSelect($selected = '', $showDirect = true)
	{
		$drivers = [];

		if ($showDirect)
		{
			$drivers[] = 'file';
		}

		if (function_exists('ftp_connect'))
		{
			$drivers[] = 'ftp';
		}

		if (extension_loaded('ssh2'))
		{
			$drivers[] = 'sftp';
		}

		$html = '<select name="fs_driver" id="fs_driver">' . "\n";

		foreach ($drivers as $driver)
		{
			$checked = (strtoupper($selected) == strtoupper($driver)) ? 'selected="selected"' : '';

			$html .= "\t<option value=\"$driver\" $checked>" . Text::_('SOLO_SETUP_LBL_FS_DRIVER_' . $driver)
			         . "</option>\n";
		}

		$html .= "</select>";

		return $html;
	}

	/**
	 * Get a dropdown for the minimum update stability
	 *
	 * @param   string  $selected  The pre-selected value
	 *
	 * @return  string  HTML
	 */
	public function minstabilitySelect($selected = '')
	{
		$levels = ['alpha', 'beta', 'rc', 'stable'];

		$html = '<select name="options[minstability]" id="minstability">' . "\n";

		foreach ($levels as $level)
		{
			$checked = (strtoupper($selected) == strtoupper($level)) ? 'selected="selected"' : '';

			$html .= "\t<option value=\"$level\" $checked>" . Text::_('SOLO_CONFIG_MINSTABILITY_' . $level)
			         . "</option>\n";
		}

		$html .= "</select>";

		return $html;
	}

	/**
	 * Get a dropdown for the two factor authentication methods
	 *
	 * @param   string  $name      The name of the field
	 * @param   string  $selected  The pre-selected value
	 *
	 * @return  string  HTML
	 */
	public function tfaMethods($name = 'tfamethod', $selected = 'none')
	{
		$methods = ['none', 'yubikey', 'google'];

		$html = '<select name="' . $name . '" id="' . $name . '">' . "\n";

		foreach ($methods as $method)
		{
			$checked = (strtoupper($selected) == strtoupper($method)) ? 'selected="selected"' : '';

			$html .= "\t<option value=\"$method\" $checked>" . Text::_('SOLO_USERS_TFA_' . $method) . "</option>\n";
		}

		$html .= "</select>";

		return $html;
	}
}
