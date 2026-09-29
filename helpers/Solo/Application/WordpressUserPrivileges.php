<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Application;

use Awf\User\Privilege;

class WordpressUserPrivileges extends Privilege
{
	public function __construct()
	{
		$this->name = 'akeeba';
		// Set up the privilege names and their default values
		$this->privileges = array(
			'backup'	=> false,
			'configure'	=> false,
			'download'	=> false,
		);
	}

	/**
	 * It's called before the user record we are attached to is loaded.
	 *
	 * @param   object  $data  The raw data we are going to bind to the user object
	 *
	 * @return  void
	 */
	public function onBeforeLoad(&$data)
	{
		// This only applies when running under WordPress
		if (!defined('WPINC'))
		{
			return;
		}

		$isMultisite  = is_multisite();
		$isSuperAdmin = is_super_admin();
		$user         = wp_get_current_user();
		$isAdmin      = $user->exists() && count($user->roles) && in_array('administrator', $user->roles);

		// Multi-site installations: only the Super Admin is allowed to access Akeeba Backup, period.
		if ($isMultisite)
		{
			$this->privileges['backup']    = $isSuperAdmin;
			$this->privileges['download']  = $isSuperAdmin;
			$this->privileges['configure'] = $isSuperAdmin;

			return;
		}

		// Single-site and Administrator role: forcibly grant all privileges.
		if ($isAdmin)
		{
			$this->privileges['backup']    = true;
			$this->privileges['download']  = true;
			$this->privileges['configure'] = true;

			return;
		}

		// Single-site, everyone else: depends on assigned capabilties
		$this->privileges['backup']    = current_user_can('akeebabackup_backup');
		$this->privileges['download']  = current_user_can('akeebabackup_download');
		$this->privileges['configure'] = current_user_can('akeebabackup_configure');
	}

	/**
	 * It's called after the user record we are attached to is loaded. We override it with a blank method to prevent
	 * the default privilege setup method from executing.
	 *
	 * @return  void
	 */
	public function onAfterLoad()
	{
		// Do nothing. DO NOT REMOVE THIS METHOD!!!
	}
}
