<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Awf\Exception\App as AWFAppException;
use Awf\Text\Text;
use Solo\Exception\Update\ConnectionError;
use Solo\Exception\Update\UpdateError;

/**
 * Integration with WordPress' Plugins Updater.
 *
 * We convey the update information of our plugin to WordPress. The extent of WordPress' involvement in the update
 * installation is that of a glorified `wget` and `unzip` combo.
 *
 * WE CAN NOT AND DO NOT TRUST WORDPRESS TO RUN POST-UPGRADE CODE.
 *
 * Instead, the post-upgrade code runs “magically” whenever we call `\AkeebaBackupWP::loadAkeebaBackupContainer`.
 */
abstract class AkeebaBackupWPUpdater
{
	/**
	 * Private static variable keys that belong to our frozen state, stored in a site transient.
	 */
	const STATE_KEYS = [
		'connectionError',
	];

	const WP_OPTION_NAME = 'akeebabackupwp_config';

	/** @var bool Did I have a connection error while */
	protected static $connectionError = false;

	private static ?array $updateInformation = null;

	/**
	 * Force WordPress to reload update information
	 *
	 * @return  void
	 * @since   8.1.0
	 */
	public static function forceReload(): void
	{
		delete_transient('update_plugins');
		delete_transient('akeebabackupwp_pluginupdate_frozenstate');
	}

	/**
	 * Report update information to WordPress.
	 *
	 * Handles the `pre_set_site_transient_update_plugins` filter
	 *
	 * Retrieve the update information from Akeeba Backup for WordPress' update cache and report them back to WordPress
	 * in a format it understands.
	 *
	 * The returned information is cached by WordPress and used by checkinfo() to render the Akeeba Backup for WordPress
	 * update information in WordPress' Plugins page.
	 *
	 * DO NOT TYPE HINT!
	 *
	 * @param   stdClass  $value
	 * @param   string    $transientName
	 *
	 * @return  stdClass
	 * @throws  AWFAppException
	 * @see     https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/
	 */
	public static function getUpdateInformation($value = null, $transientName = null)
	{
		global $wp_version;

		// Make sure this is the right transient being updated.
		if (
			$transientName !== 'update_plugins' || empty($value) || !is_object($value)
			|| (is_multisite() && !is_network_admin())
		)
		{
			return $value;
		}

		// Reset the update state
		self::$connectionError = false;

		// On WordPress < 4.3 we can't use the integrated update system.
		if (version_compare($wp_version, '4.3', 'lt'))
		{
			return $value;
		}

		/**
		 * When the plugin is deleted, WordPress reloads the updates.
		 *
		 * Since this file was already loaded, the hook executes. However, trying to fetch updates when Akeeba Backup
		 * is not actually installed may lead to PHP errors.
		 *
		 * We try to detect this issue and quit early to avoid said PHP errors.
		 */
		if (!file_exists(__DIR__ . '/../helpers/integration.php'))
		{
			self::freezeState();

			return $value;
		}

		$updateInfo = false;

		try
		{
			$updateInfo = static::getUpdateInfo();
		}
		catch (Throwable $e)
		{
			static::$connectionError = true;
		}

		self::freezeState();

		// Error getting the update information? Return without giving any update information to WordPress.
		if (!$updateInfo)
		{
			return $value;
		}

		/**
		 * Third party plugins may have messed up the $value we are passed, or it may have been set to a WP_Error
		 * object.
		 *
		 * In the case of `WP_Error` we return it as-is. Not our monkey, not our circus. In any other case, we try to
		 * recover execution gracefully.
		 */
		if (!$value || !is_object($value) || !isset($value->response) || !is_array($value->response)
		    || !isset($value->no_update)
		    || !is_array($value->no_update))
		{
			$value = is_object($value) ? $value : new stdClass();

			if (is_wp_error($value))
			{
				return $value;
			}

			if (isset($value->response) && is_object($value->response))
			{
				$value->response = (array) ($value->response);
			}

			if (!isset($value->response) || !is_array($value->response))
			{
				$value->response = [];
			}

			if (isset($value->no_update) && is_object($value->no_update))
			{
				$value->no_update = (array) ($value->no_update);
			}

			if (!isset($value->no_update) || !is_array($value->no_update))
			{
				$value->no_update = [];
			}
		}

		$dirSlug = self::getPluginSlug();

		$obj              = new stdClass();
		$obj->slug        = $dirSlug;
		$obj->plugin      = $dirSlug . '/akeebabackupwp.php';
		$obj->new_version = $updateInfo['version'];
		$obj->url         = $updateInfo['infoUrl'];
		$obj->package     = $updateInfo['download'];
		$obj->icons       = [
			'2x' => WP_PLUGIN_URL . '/' . AkeebaBackupWP::$dirName . '/app/media/logo/abwp-256.png',
			'1x' => WP_PLUGIN_URL . '/' . AkeebaBackupWP::$dirName . '/app/media/logo/abwp-128.png',
		];

		if ($updateInfo['hasUpdate'] ?? false)
		{
			$value->response[$dirSlug . '/akeebabackupwp.php'] = $obj;
		}
		else
		{
			$value->no_update[$dirSlug . '/akeebabackupwp.php'] = $obj;
		}

		// Add the Download ID to Akeeba Backup Professional
		if (AKEEBABACKUP_PRO)
		{
			$dlid = self::getDownloadID();

			if (!empty($dlid))
			{
				$obj->package = add_query_arg(['dlid' => $dlid], $obj->package);
			}
		}

		return $value;
	}

	/**
	 * Used to render "View version x.x.x details" link from the plugins page.
	 *
	 * Handles the `plugins_api` filter.
	 *
	 * We hook to this event to redirect the connection from the WordPress directory to our site for updates
	 *
	 * DO NOT TYPE HINT!
	 *
	 * @param   false|object|array  $result  The result object or array. Default false.
	 * @param   string              $action  The type of information being requested from the Plugin Installation API.
	 * @param   object|array        $arg     Plugin API arguments.
	 *
	 * @return  false|object|array
	 *
	 * @see https://developer.wordpress.org/reference/hooks/plugins_api/
	 */
	public static function pluginInformationPage($result = false, $action = '', $arg = null)
	{
		if (!in_array($action ?: '', ['query_plugins', 'plugin_information']))
		{
			return $result;
		}

		if (!is_object($arg))
		{
			return $result;
		}

		$dirSlug = self::getPluginSlug();

		if (!isset($arg->slug))
		{
			return $result;
		}

		if ($arg->slug !== $dirSlug)
		{
			return $result;
		}

		try
		{
			$updateInfo = static::getUpdateInfo();
		}
		catch (Throwable $e)
		{
			$updateInfo = false;
		}

		/**
		 * Sanity check.
		 *
		 * This if-block should never be triggered. We only ever reach this code if we have already determined there is
		 * an update available.
		 */
		if (!$updateInfo)
		{
			return $result;
		}

		$platforms          = $updateInfo['platforms'] ?? [];
		$platformVersions   = $platforms['wordpress'] ?? [];
		$minPlatformVersion = array_reduce(
			$platformVersions,
			fn($carry, $version) => empty($carry)
				? str_replace('+', '', $version)
				: (
				version_compare($carry, str_replace('+', '', $version), 'gt')
					? str_replace('+', '', $version)
					: $carry
				),
			''
		);
		$minPlatformVersion = $minPlatformVersion ?: get_bloginfo('version');
		$minPhp             = $updateInfo['phpMin'] ?? '';
		$releaseNotes       = $updateInfo['releaseNotes'] ?? sprintf(
			"For downloads and release notes please visit the <a href='%s' target='_blank'>the download page of version %s</a> on the plugin's site.",
			$updateInfo['infoUrl'],
			$updateInfo['version']
		);

		/**
		 * This is the information WordPress is using to render the Akeeba Backup for WordPress row in its Plugins page.
		 */
		$information = [
			// We leave the "name" index empty, so WordPress won't display the ugly title on top of our banner
			'name'          => '',
			'slug'          => $dirSlug,
			'author'        => 'Akeeba Ltd.',
			'homepage'      => 'https://www.akeeba.com/products/akeeba-backup-wordpress.html',
			'last_updated'  => $updateInfo['date'],
			'version'       => $updateInfo['version'],
			'download_link' => $updateInfo['download'],
			'requires'      => $minPlatformVersion,
			'requires_php'  => $minPhp,
			//'tested'        => get_bloginfo('version'),
			'sections'      => [
				'release_notes' => $releaseNotes,
			],
			'banners'       => [
				'low'  => plugins_url() . '/' . $dirSlug . '/app/media/image/wordpressupdate_banner.jpg',
				'high' => false,
			],
		];

		return (object) $information;
	}

	/**
	 * Throws an error if the Download ID is missing when the user tries to install an update.
	 *
	 * Handles the `upgrader_pre_download` filter.
	 *
	 * DO NOT TYPE HINT!
	 *
	 * @param   bool              $bailout
	 * @param   null|string       $package
	 * @param   null|WP_Upgrader  $upgrader
	 *
	 * @return WP_Error|false    An error if anything goes wrong or is missing, either case FALSE to keep the update
	 *                           process going
	 * @see https://developer.wordpress.org/reference/hooks/upgrader_pre_download/
	 */
	public static function ensureDownloadID($bailout = false, $package = null, $upgrader = null)
	{
		// TODO
		if (!is_string($package))
		{
			return false;
		}

		// The Download ID check only applies to the Professional version
		self::ensureVersionInfo();

		if (!AKEEBABACKUP_PRO)
		{
			return false;
		}

		// Ensure the download URL is one coming from our domain name.
		$parsedUrl = wp_parse_url($package);

		if (!$parsedUrl || !isset($parsedUrl['host']))
		{
			return false;
		}

		$isAllowedURL = in_array(
			$parsedUrl['host'],
			[
				'akeeba.com',
				'www.akeeba.com',
				'cdn.akeeba.com',
			]
		);

		// Process only our download links
		if (!$isAllowedURL || !isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https']))
		{
			return false;
		}

		// Check if we are downloading an Akeeba Backup package, not another Akeeba plugin.
		if (strpos($package, 'akeebabackupwp') === false)
		{
			return false;
		}

		// Is the Download ID missing?
		if (static::needsDownloadID())
		{
			return new WP_Error(
				403,
				'Please visit Akeeba Backup\'s main page and follow the instructions on the screen to enter your Download ID. Updating Akeeba Backup is not possible until you do that.'
			);
		}

		// Nothing to do here
		return false;
	}

	/**
	 * Helper function to display some custom text AFTER the row regarding our update.
	 *
	 * Handles the `after_plugin_row_akeebaebackupwp/akeebabackupwp.php` filter
	 *
	 * This is typically used to communicate problems preventing the update information from being retrieved or used,
	 * meaning updates for our plugin are essentially broken.
	 *
	 * DO NOT TYPEHINT!
	 *
	 * @param   string  $plugin_file  Path to the plugin file relative to the plugins directory.
	 * @param   array   $plugin_data  An array of plugin data.
	 * @param   string  $status       Status filter currently applied to the plugin list.
	 *
	 * @see   https://developer.wordpress.org/reference/hooks/after_plugin_row_plugin_file/
	 */
	public static function updateMessage($plugin_file = '', $plugin_data = [], $status = '')
	{
		global $wp_version;

		self::thawState();

		$html     = '';
		$warnings = [];

		if (version_compare($wp_version, '4.3', 'lt'))
		{
			$warnings[] = <<< HTML
<p>
	You are using an unsupported version of WordPress. Please update WordPress and install the latest version of Akeeba
	Backup for WordPress manually.
</p>
HTML;
		}

		if (static::needsDownloadID())
		{
			$warnings[] = <<< HTML
<p>
	Please go to Akeeba Backup for WordPress' main page and follow the instructions on your screen to enter your
	Download ID. You will not be able to see the update availability, or install updates if they are available, until
	you do so.
</p>
HTML;
		}

		if (static::$connectionError)
		{
			$warnings[] = <<< HTML
<p>
	Could not retrieve the update information for Akeeba Backup for WordPress. If this message does not go away after a
	few hours please check whether your host blocks the request to Akeeba Backup's update server (cdn.akeeba.com).
</p>
HTML;
		}

		if ($warnings)
		{
			$warnings = implode('', $warnings);
			$msg      = Text::_('SOLO_UPDATE_WORDPRESS_WARNING');

			$html = <<<HTML
<tr class="">
	<th></th>
	<td></td>
	<td>
		<div style="border: 1px solid #F0AD4E;border-radius: 3px;background: #fdf5e9;padding:10px">
			<strong>$msg</strong><br/>
			$warnings		
		</div>
	</td>
</tr>
HTML;
		}

		if ($html)
		{
			echo $html;
		}
	}


	/**
	 * Fetches the update information from the remote server
	 *
	 * @return array|bool
	 * @throws AWFAppException
	 * @throws UpdateError
	 */
	private static function getUpdateInfo(bool $force = false)
	{
		// If the information is already loaded within this request, return it.
		if (self::$updateInformation && !$force)
		{
			return self::$updateInformation;
		}

		// Make sure that the Akeeba Backup version information is loaded, and get the update source URL.
		self::ensureVersionInfo();

		$url = self::getUpdateSourceURL();

		// Prime the update information
		self::$updateInformation = [
			'version'   => AKEEBABACKUP_VERSION,
			'infoUrl'   => 'https://www.akeeba.com/download',
			'download'  => 'https://www.akeeba.com/download',
			'hasUpdate' => false,
			'link'      => 'https://www.akeeba.com/download',
		];

		// Try to load the raw update source JSON data.
		try
		{
			$response = wp_remote_get($url);

			if (is_wp_error($response))
			{
				throw new ConnectionError(
					sprintf(
						"There was an error fetching the Akeeba Backup update information: %s",
						$response->get_error_message()
					)
				);
			}

			$body = wp_remote_retrieve_body($response);

			if (empty($body) || is_wp_error($body))
			{
				throw new UpdateError('There was an error fetching the Akeeba Backup update information: empty body');
			}
		}
		catch (Throwable $e)
		{
			throw new UpdateError(
				sprintf(
					"There was an error fetching the Akeeba Backup update information: %s — [%s:%d]",
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);
		}

		// Try to parse the JSON data.
		try
		{
			$rawData = @json_decode($body, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			throw new UpdateError(
				'There was an error fetching the Akeeba Backup update information: body is not JSON data'
			);
		}

		if (!is_array($rawData) || empty($rawData))
		{
			throw new UpdateError('There was an error fetching the Akeeba Backup update information: invalid format');
		}

		// Filter the updates
		$rawData = array_filter(
			$rawData,
			function ($item) use (&$updateWithUnmetPHPLimits, &$updateWithUnmetWordPressLimits) {
				// Weed out invalid data
				$isValid = is_array($item) && isset($item['version']) && isset($item['download']);

				if (!$isValid)
				{
					return false;
				}

				// Make sure the version is equal to or greater than the current version
				if (version_compare($item['version'], AKEEBABACKUP_VERSION, '<'))
				{
					return false;
				}

				// Make sure the date is newer than or equal to AKEEBABACKUP_DATE
				try
				{
					if (defined('AKEEBABACKUP_DATE') && strtotime($item['date']) < strtotime(AKEEBABACKUP_DATE))
					{
						return false;
					}
				}
				catch (Throwable $e)
				{
					// Malformed dates will just be ignored.
				}

				// Accepted update
				return true;
			}
		);

		if (empty($rawData))
		{
			// There are no updates. You have the latest version
			return self::$updateInformation;
		}

		// Cool. Let's find the maximum version we can update to and return that.
		$myUpdate = array_reduce(
			$rawData,
			function ($carry, $item) {
				if (!is_array($carry))
				{
					return is_array($item) ? $item : null;
				}

				if (!is_array($item))
				{
					return $carry;
				}

				return version_compare($carry['version'], $item['version'], 'lt')
					? $item
					: $carry;
			},
			null
		);

		if (!is_array($myUpdate))
		{
			return self::$updateInformation;
		}

		// It is an update if it's newer than the currently installed version
		$myUpdate['hasUpdate'] = version_compare($myUpdate['version'], AKEEBABACKUP_VERSION, '>');

		self::$updateInformation = $myUpdate;

		return $myUpdate;
	}

	/**
	 * Does the user need to enter a new Download ID?
	 *
	 * @return bool
	 */
	private static function needsDownloadID(): bool
	{
		self::ensureVersionInfo();

		// With the core version we're always good to go
		if (!AKEEBABACKUP_PRO)
		{
			return false;
		}

		$dlid = self::getDownloadID();

		if (empty($dlid) || !preg_match('/^([0-9]{1,}:)?[0-9a-f]{32}$/i', $dlid))
		{
			return true;
		}

		return false;
	}

	private static function getDownloadID(): ?string
	{
		self::ensureVersionInfo();

		// With the core version we're always good to go
		if (!AKEEBABACKUP_PRO)
		{
			return null;
		}

		$jsonData = get_option(self::WP_OPTION_NAME, null);

		if (empty($jsonData))
		{
			return null;
		}

		try
		{
			$config = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			return null;
		}

		$options = $config['options'] ?? [];

		if (empty($config['options']))
		{
			return null;
		}

		$options = is_array($options) ? $options : (array) $options;

		return $options['update_dlid'] ?? null;
	}

	/**
	 * Freeze the update warnings state.
	 *
	 * We create an array with the update warnings flags and save it as a site transient.
	 */
	private static function freezeState(): void
	{
		$frozenState = [
			'updateIntegrationVersion' => '2.0',
		];

		foreach (self::STATE_KEYS as $key)
		{
			if (isset(self::${$key}))
			{
				$frozenState[$key] = self::${$key};
			}
		}

		set_site_transient('akeebabackupwp_pluginupdate_frozenstate', $frozenState);
	}

	/**
	 * Unfreeze the update warnings state
	 *
	 * We read the site transient and restore the update warnings flags from it, if it's set.
	 */
	private static function thawState(): void
	{
		// Retrieve the frozen state.
		$frozenState = get_site_transient('akeebabackupwp_pluginupdate_frozenstate');

		if (empty($frozenState) || !is_array($frozenState))
		{
			return;
		}

		// If the integration version of the frozen state is too old, return so we can fetch it afresh.
		$updateIntegrationVersion = $frozenState['updateIntegrationVersion'] ?? '1.0';

		if (version_compare($updateIntegrationVersion, '2.0', '<'))
		{
			return;
		}

		foreach (self::STATE_KEYS as $key)
		{
			if (isset(self::${$key}) && isset($frozenState[$key]))
			{
				self::${$key} = $frozenState[$key];
			}
		}
	}

	/**
	 * Returns the subdirectory of the main WP_CONTENT_DIR/plugins folder where our plugin is installed.
	 *
	 * @return string
	 */
	private static function getPluginSlug(): string
	{
		$pluginsUrl   = plugins_url('', realpath(__DIR__ . '/../akeebabackupwp.php'))
			?: realpath(__DIR__ . '/..');
		$baseUrlParts = explode('/', $pluginsUrl);
		$dirSlug      = end($baseUrlParts);

		if (!empty($dirSlug) && ($dirSlug != '..'))
		{
			return $dirSlug;
		}

		$fullDir  = __DIR__;
		$dirParts = explode(DIRECTORY_SEPARATOR, $fullDir, 3);
		$dirSlug  = $dirParts[1] ?? 'akeebabackupwp';

		return $dirSlug;
	}

	private static function ensureVersionInfo(): void
	{
		// Make sure the constants are defined. If not, we will try to load version.php.
		if (defined('AKEEBABACKUP_VERSION') && defined('AKEEBABACKUP_PRO'))
		{
			return;
		}

		try
		{
			require_once APATH_BASE . '/version.php';
		}
		catch (\Throwable $e)
		{
			// We will ignore any errors and fall back to the next if-block.
		}

		// Make sure all the constants we rely on are defined.
		if (!defined('AKEEBABACKUP_VERSION'))
		{
			define('AKEEBABACKUP_VERSION', '0.0.0-dev');
		}

		if (!defined('AKEEBABACKUP_DATE'))
		{
			define('AKEEBABACKUP_DATE', date('Y-m-d'));
		}

		if (!defined('AKEEBABACKUP_PRO'))
		{
			define('AKEEBABACKUP_PRO', @is_file(__DIR__ . '/../app/Solo/Controller/Alice.php'));
		}
	}

	private static function getUpdateSourceURL(): string
	{
		$suffix    = (AKEEBABACKUP_PRO ? 'pro' : 'core') . '.json';
		$customUrl = defined('AKEEBABACKUP_UPDATE_BASEURL') ? AKEEBABACKUP_UPDATE_BASEURL : null;

		if ($customUrl)
		{
			return $customUrl . $suffix;
		}

		return 'https://cdn.akeeba.com/updates/akeebabackupwp_' . $suffix;
	}
}
