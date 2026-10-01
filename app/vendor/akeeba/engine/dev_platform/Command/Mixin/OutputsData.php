<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see
 * <https://www.gnu.org/licenses/>.
 */

namespace Akeeba\Engine\DevPlatform\Command\Mixin;

use Symfony\Component\Console\Style\SymfonyStyle;

trait OutputsData
{
	/**
	 * Output a list of associative rows either as a table or as pretty JSON.
	 *
	 * @param   array         $rows     List of associative arrays (each row).
	 * @param   string[]      $headers  Column keys to show as table columns (in order). When empty,
	 *                                  uses the keys of the first row.
	 * @param   bool          $asJson   When true, print JSON and nothing else.
	 * @param   SymfonyStyle  $io
	 *
	 * @return  void
	 */
	protected function outputRows(array $rows, array $headers, bool $asJson, SymfonyStyle $io): void
	{
		if ($asJson)
		{
			$io->writeln(json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

			return;
		}

		if (empty($rows))
		{
			$io->text('No records found.');

			return;
		}

		if (empty($headers))
		{
			$headers = array_keys(reset($rows));
		}

		$table = $io->createTable();
		$table->setHeaders($headers);

		foreach ($rows as $row)
		{
			$table->addRow(
				array_map(
					function ($k) use ($row) {
						$value = $row[$k] ?? null;

						return (is_scalar($value) || is_null($value)) ? (string) ($value ?? '') : json_encode($value);
					},
					$headers
				)
			);
		}

		$table->render();
	}

	/**
	 * Output a single associative record as a key/value table or as pretty JSON.
	 *
	 * @param   array         $record  The record to display.
	 * @param   bool          $asJson  When true, print JSON and nothing else.
	 * @param   SymfonyStyle  $io
	 *
	 * @return  void
	 */
	protected function outputRecord(array $record, bool $asJson, SymfonyStyle $io): void
	{
		if ($asJson)
		{
			$io->writeln(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

			return;
		}

		$table = $io->createTable();
		$table->setHeaders(['Property', 'Value']);

		foreach ($record as $key => $value)
		{
			$table->addRow(
				[
					$key,
					(is_scalar($value) || is_null($value)) ? (string) ($value ?? '') : json_encode($value),
				]
			);
		}

		$table->render();
	}
}
