<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Helper;

use Akeeba\Engine\Factory;
use Awf\Application\Application;
use Awf\Container\Container;

abstract class SecretWord
{
	/**
	 * Enforce (reversible) encryption for the component setting $settingsKey
	 *
	 * @param   string  $settingsKey  The key for the setting containing the secret word
	 *
	 * @return  void
	 *
	 * @throws  \Awf\Exception\App
	 *
	 * @since   5.5.2
	 */
	public static function enforceEncryption($settingsKey, Container $container)
	{
		$params = $container->appConfig;

		// If encryption is not enabled in the Engine we can't encrypt the Secret Word
		if ($params->get('useencryption', -1) == 0)
		{
			return;
		}

		// If encryption is not supported on this server we can't encrypt the Secret Word
		if (!Factory::getSecureSettings()->supportsEncryption())
		{
			return;
		}

		/**
		 * Never save a configuration which has no database settings. Such a configuration is what a request gets when it
		 * reads config.php while another request is writing it (an empty or half-written file loads as an empty
		 * configuration); saving it would replace the real config.php with a stub and the site would lose its database
		 * credentials and every other setting. ABWP keeps its configuration in the WordPress database instead.
		 */
		if (!defined('WPINC') && empty($params->get('dbname', '')))
		{
			return;
		}

		// Get the raw version of frontend_secret_word and check if it has a valid encryption signature
		$raw             = $params->get('options.' . $settingsKey, '');
		$signature       = substr($raw, 0, 12);
		$validSignatures = array('###AES128###', '###CTR128###');

		// If the setting is already encrypted I have nothing to do here
		if (in_array($signature, $validSignatures))
		{
			return;
		}

		// The setting was NOT encrypted. I need to encrypt it.
		$secureSettings = Factory::getSecureSettings();
		$encrypted      = $secureSettings->encryptSettings($raw);

		// Without an encryption key file (yet) the Engine hands back the raw value. Nothing changed, so do not save: this
		// code runs on every request and would rewrite config.php each time.
		if ($encrypted === $raw)
		{
			return;
		}

		// Finally, I need to save it back to the database
		$params->set('options.' . $settingsKey, $encrypted);
		$params->saveConfiguration();
	}

	/**
	 * Forcibly store the Secret Word settings $settingsKey unencrypted in the database. This is meant to be called when
	 * the user disables settings encryption. Since the encryption key will be deleted we need to decrypt the Secret
	 * Word at the same time as the Engine settings. Otherwise we will never be able to access it again.
	 *
	 * @param   string       $settingsKey    The key of the Secret Word parameter
	 * @param   string|null  $encryptionKey  (Optional) The AES key with which to decrypt the parameter
	 *
	 * @return  void
	 *
	 * @throws  \Awf\Exception\App
	 *
	 * @since   5.5.2
	 */
	public static function enforceDecrypted($settingsKey, $encryptionKey = null, ?Container $container = null)
	{
		// Get the raw version of frontend_secret_word and check if it has a valid encryption signature
		$params          = $container->appConfig;
		$raw             = $params->get('options.' . $settingsKey, '');
		$signature       = substr($raw, 0, 12);
		$validSignatures = array('###AES128###', '###CTR128###');

		// If the setting is not already encrypted I have nothing to decrypt
		if (!in_array($signature, $validSignatures))
		{
			return;
		}

		// The setting was encrypted. I need to decrypt it.
		$secureSettings = Factory::getSecureSettings();
		$decrypted      = $secureSettings->decryptSettings($raw, $encryptionKey);

		// Finally, I need to save it back to the database
		$params->set('options.' . $settingsKey, $decrypted);
		$params->saveConfiguration();
	}
}
