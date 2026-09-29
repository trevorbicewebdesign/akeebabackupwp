<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\Pythia;

interface OracleInterface
{
	/**
	 * Creates a new oracle objects
	 *
	 * @param   string  $path  The directory path to scan
	 */
	public function __construct(string $path);

	/**
	 * Does this class recognises the script / CMS type?
	 *
	 * @return  boolean
	 */
	public function isRecognised(): bool;

	/**
	 * Return the name of the CMS / script
	 *
	 * @return  string
	 */
	public function getName(): string;

	/**
	 * Return the default installer name for this CMS / script
	 *
	 * @return  string
	 */
	public function getInstaller(): string;

	/**
	 * Return the database connection information for this CMS / script
	 *
	 * @return  array
	 */
	public function getDbInformation(): array;

	/**
	 * Return extra directories required by the CMS / script
	 *
	 * @return array
	 */
	public function getExtradirs(): array;

    /**
     * Return extra databases required by the CMS / script (ie Drupal multi-site)
     *
     * @return array
     */
    public function getExtraDb(): array;
}
