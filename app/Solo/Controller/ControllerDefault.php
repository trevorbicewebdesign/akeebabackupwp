<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Controller;

use Solo\Application\AclChecks;

/**
 * Common controller superclass. Reserved for future use.
 */
abstract class ControllerDefault extends \Awf\Mvc\Controller
{
	use AclChecks;

	/**
	 * Executes a given controller task. The onBefore<task> and onAfter<task>
	 * methods are called automatically if they exist.
	 *
	 * @param   string  $task The task to execute, e.g. "browse"
	 *
	 * @return  null|bool  False on execution failure
	 *
	 * @throws  \Exception  When the task is not found
	 */
	public function execute($task)
	{
		$view = $this->input->getCmd('view', 'main');

		$this->aclCheck($view, $task);

		return parent::execute($task);
	}

	/**
	 * Tell every cache in the chain not to store this response.
	 *
	 * AJAX endpoints need to always hit the server, or the backups quite simply never runs. Even though we use POST
	 * requests which will not be cached in an RFC-compliant cache, and we do use cache-busting random data in the
	 * URL query parameters, it may not be enough. I have seen with my own eyes some really broken environments deciding
	 * to ignore RFCs and common sense. Hence the need to send cache-busting HTTP headers, which is what this helper
	 * does.
	 *
	 * @return  void
	 */
	protected function sendNoCacheHeaders(): void
	{
		@header('Expires: Wed, 17 Aug 2005 00:00:00 GMT', true);
		@header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0', true);
		@header('Pragma: no-cache', true);
	}

	/**
	 * Refuse an unauthenticated front-end endpoint request, cleanly.
	 *
	 * The front-end endpoints (remote, check, uploadcheck) speak a plain text protocol where the HTTP-like status is
	 * the first token of the body: "200 OK", "500 ERROR -- …", "301 More work required …". This helper emits the
	 * refusal in the same dialect, which is what the native CLI clients already expect — see the
	 * `strpos($result, '403 ')` branches in Solo\Cli\AltCheckFailedCli and Solo\Cli\AltCheckFailedUploadCli.
	 *
	 * The message is deliberately generic: an anonymous caller must not be told which gate stopped them, i.e. whether
	 * the legacy front-end API is disabled or their secret key was wrong.
	 *
	 * IMPORTANT: this method TERMINATES the request. Awf\Application\Application::close() ends in exit(); nothing
	 * after the call to this method will ever run.
	 *
	 * @param   string  $message  The refusal message, appended to the "403 " status token.
	 *
	 * @return  void
	 */
	protected function refuseFrontendEndpoint(string $message = 'Operation not permitted'): void
	{
		@ob_end_clean();

		$this->sendNoCacheHeaders();

		@header('Content-type: text/plain', true);
		@header('Connection: close', true);

		echo '403 ' . $message;

		if (!$this->container->appConfig->get('no_flush', 0))
		{
			flush();
		}

		$this->container->application->close();
	}
}
