<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Application;

use Awf\Inflector\Inflector;
use Awf\Text\Text;

/**
 * Automatic, map-driven access control.
 *
 * This is the single source of truth for which privilege each view and task requires. Controllers use aclCheck() to
 * enforce it; views use canAccess() to decide whether to render an affordance, so that the UI and the enforcement can
 * never disagree.
 *
 * The map used to be duplicated between ControllerDefault, DataControllerDefault and View\Main\Html. The copies
 * drifted, which is how the `crons` view ended up enforcing nothing at all. Do not copy this map anywhere — use the
 * trait instead.
 *
 * Task keys MUST be lowercase. The lookup lowercases the requested task, so a camelCase key can never match and the
 * view silently falls through to its '*' entry.
 */
trait AclChecks
{
	/**
	 * Privileges required per view and task.
	 *
	 * The privilege names are relative to the `akeeba.` prefix. ALL of the listed privileges are required, not any of
	 * them. An empty array means "no privilege required, but you still have to be logged in". A view with no entry at
	 * all ALSO requires a logged in user — it just requires no particular privilege, which is almost never what you
	 * want: add an entry, even an empty one, so that the omission is deliberate and visible.
	 *
	 * There is exactly one way to be reachable without logging in, and it is not this map: $aclPublicViews.
	 *
	 * @var array
	 */
	protected $aclChecks = [
		'alice'          => ['*' => ['configure']],
		'backup'         => ['*' => ['backup']],
		'browser'        => ['*' => ['configure']],
		'configuration'  => ['*' => ['configure']],
		'crons'          => ['*' => ['configure']],
		'dbfilters'      => ['*' => ['configure']],
		'discover'       => ['*' => ['configure']],
		'errortest'      => ['*' => ['configure']],
		'extradirs'      => ['*' => ['configure']],
		'fsfilters'      => ['*' => ['configure']],
		'log'            => ['*' => ['configure']],
		'main'           => [
			// Everything else on the Control Panel is available to any logged in user.
			'addrandomtofilename' => ['configure'],
			'applydownloadid'     => ['configure'],
			'fixoutputdirectory'  => ['configure'],
			'forceupdatedb'       => ['configure'],
			'resetsecretword'     => ['configure'],
			'*'                   => [],
		],
		'manage'         => [
			'main'        => [],
			'cancel'      => ['backup'],
			'deletefiles' => ['backup'],
			'freeze'      => ['backup'],
			'remove'      => ['backup'],
			'save'        => ['backup'],
			'showcomment' => ['backup'],
			'unfreeze'    => ['backup'],
			'download'    => ['download'],
			/**
			 * By design. This writes options.show_howtorestoremodal, but the modal it dismisses is a nag about
			 * downloading backups, aimed squarely at the people holding the `download` privilege. Requiring
			 * `configure` to dismiss it would leave them nagged relentlessly with no way out — and the same
			 * information is on the page anyway once the modal is closed.
			 */
			'hidemodal'   => ['download'],
			'restore'     => ['configure'],
			'*'           => ['download'],
		],
		'multidb'        => ['*' => ['configure']],
		'phpinfo'        => ['*' => ['configure', 'backup', 'download']],
		'profiles'       => ['*' => ['configure']],
		'profile'        => ['*' => ['configure']],
		'regexdbfilters' => ['*' => ['configure']],
		'regexfsfilters' => ['*' => ['configure']],
		'remotefiles'    => ['*' => ['download']],
		'restore'        => ['*' => ['configure']],
		's3import'       => ['*' => ['configure']],
		'schedule'       => ['*' => ['configure']],
		'sysconfig'      => ['*' => ['configure', 'backup', 'download']],
		'transfer'       => ['*' => ['download']],
		'update'         => ['*' => ['configure', 'backup', 'download']],
		'upload'         => ['*' => ['backup']],
		'users'          => ['*' => ['configure', 'backup', 'download']],
		'wizard'         => ['*' => ['configure']],
	];

	/**
	 * Views which do NOT require a logged in user.
	 *
	 * These authenticate by their own means, per request, instead of relying on a session:
	 *
	 * - `api`, `check`, `uploadcheck`, `remote` and `json` are the front-end endpoints. They are authenticated by the
	 *   Secret Word, in their own controllers (see e.g. Solo\Controller\Api::verifyKey(),
	 *   Solo\Controller\Check::checkPermissions()) or, for the legacy `json` view, inside Solo\Model\Json itself.
	 * - `oauth2` is the OAuth2 callback landing point, reached by the remote provider, not by the site's user.
	 * - `login` and `setup` have to be reachable before there is a user to be logged in as. Setup guards itself: it
	 *   refuses once assets/private/config.php exists, and refuses outright inside a CMS.
	 * - `ftpbrowser` and `sftpbrowser` mirror Solo\Application::redirectToLogin()'s own list. Listing them is inert:
	 *   both controllers throw a 403 at the top of execute(), before ever reaching aclCheck(), because the legacy
	 *   directory browsers are hard-disabled as an SSRF / port-scanning oracle. They stay here so that re-enabling
	 *   one does not silently depend on this list having quietly dropped it.
	 *
	 * This list must exist. canAccess() requires a logged in user for everything else — including views with no entry
	 * in $aclChecks — so without it every one of these endpoints answers 403, which under WordPress's admin-ajax.php
	 * is an uncaught exception and therefore a fatal error page. Do not "simplify" this away.
	 *
	 * Names are matched lowercase and exactly, deliberately: an inflected alias (`checks`, `apis`) is NOT exempt, so a
	 * near-miss fails closed into the logged-in check rather than open.
	 *
	 * @var array
	 */
	protected $aclPublicViews = [
		'api', 'check', 'uploadcheck', 'remote', 'oauth2', 'json',
		'login', 'setup',
		'ftpbrowser', 'sftpbrowser',
	];

	/**
	 * Enforces the access control checks, throwing a 403 when the user does not have the required privileges.
	 *
	 * @param   string  $view  The view being accessed
	 * @param   string  $task  The task being accessed
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException
	 */
	protected function aclCheck($view, $task)
	{
		if ($this->canAccess($view, $task))
		{
			return;
		}

		throw new \RuntimeException(Text::_('SOLO_ERR_ACLDENIED'), 403);
	}

	/**
	 * Performs the access control checks without throwing. Use this to show or hide an affordance in a view.
	 *
	 * @param   string  $view  The view being considered
	 * @param   string  $task  The task being considered
	 *
	 * @return  bool  True if access is allowed
	 */
	public function canAccess($view, $task)
	{
		// Views which authenticate per request rather than through a session. See $aclPublicViews.
		if (in_array(strtolower($view), $this->aclPublicViews, true))
		{
			return true;
		}

		$user = $this->container->userManager->getUser();

		if (empty($user) || !$user->getId())
		{
			return false;
		}

		foreach ($this->getRequiredPrivileges($view, $task) as $privilege)
		{
			if (!$user->getPrivilege('akeeba.' . $privilege))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns the privileges required to access a view and task, per the ACL map.
	 *
	 * @param   string  $view  The view being accessed
	 * @param   string  $task  The task being accessed
	 *
	 * @return  array  Privilege names, relative to the `akeeba.` prefix. Empty if the view is not access controlled.
	 */
	protected function getRequiredPrivileges($view, $task)
	{
		$view = $this->canonicaliseViewName(strtolower($view));
		$task = strtolower($task);

		if (!isset($this->aclChecks[$view]))
		{
			return [];
		}

		if (isset($this->aclChecks[$view][$task]))
		{
			return $this->aclChecks[$view][$task];
		}

		return $this->aclChecks[$view]['*'] ?? [];
	}

	/**
	 * Canonicalise the view name against the ACL map.
	 *
	 * The MVC factory resolves controllers using the exact, singularised and pluralised form of the view. Without
	 * normalising here, an inflected alias (e.g. "user" for "users" or "configurations" for "configuration") would
	 * resolve to the real controller yet miss its ACL map entry, bypassing the privilege check entirely.
	 *
	 * @param   string  $view  The lowercase view name, as requested
	 *
	 * @return  string  The view name to look up in the ACL map
	 */
	private function canonicaliseViewName($view)
	{
		if (isset($this->aclChecks[$view]))
		{
			return $view;
		}

		foreach ([Inflector::singularize($view), Inflector::pluralize($view)] as $candidate)
		{
			$candidate = strtolower($candidate);

			if (isset($this->aclChecks[$candidate]))
			{
				return $candidate;
			}
		}

		return $view;
	}
}
