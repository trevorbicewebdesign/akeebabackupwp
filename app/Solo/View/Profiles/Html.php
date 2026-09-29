<?php
/**
 * @package   solo
 * @copyright Copyright (c)2014-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Solo\View\Profiles;

use Awf\Mvc\DataView\Html as BaseHtml;
use Solo\View\ViewTraits\ProfileIdAndName;

class Html extends BaseHtml
{
	use ProfileIdAndName;

	public function onBeforeBrowse()
	{
		$document = $this->container->application->getDocument();

		// Buttons (new, edit, copy, delete)
		$buttons = array(
			array(
				'title' 	=> 'SOLO_BTN_ADD',
				'class' 	=> 'akeeba-btn--green',
				'onClick'	=> 'akeeba.System.submitForm(\'add\')',
				'icon' 		=> 'akion-plus-circled'
			),
			array(
				'title' 	=> 'SOLO_BTN_EDIT',
				'class' 	=> 'akeeba-btn--teal',
				'onClick'	=> 'akeeba.System.submitForm(\'edit\')',
				'icon' 		=> 'akion-edit'
			),
			array(
				'title' 	=> 'SOLO_BTN_COPY',
				'class' 	=> 'akeeba-btn--grey',
				'onClick'	=> 'akeeba.System.submitForm(\'copy\')',
				'icon' 		=> 'akion-ios-copy'
			),
			array(
				'title' 	=> 'COM_AKEEBA_PROFILES_BTN_RESET',
				'class' 	=> 'akeeba-btn--orange',
				'onClick'	=> 'akeeba.System.submitForm(\'reset\')',
				'icon' 		=> 'akion-refresh'
			),
			array(
				'title' 	=> 'SOLO_BTN_DELETE',
				'class' 	=> 'akeeba-btn--red',
				'onClick' 	=> 'akeeba.System.submitForm(\'remove\')',
				'icon' 		=> 'akion-trash-b'
			),
		);

		$toolbar = $document->getToolbar();

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		$this->getProfileIdAndName();

		return parent::onBeforeBrowse();
	}

	protected function onBeforeAdd()
	{
		$document = $this->container->application->getDocument();

		// Buttons (save, save and close, save and new, cancel)
		$buttons = array(
			array(
				'title' 	=> 'SOLO_BTN_SAVECLOSE',
				'class' 	=> 'akeeba-btn--green',
				'onClick'	=> 'akeeba.System.submitForm(\'save\')',
				'icon' 		=> 'akion-checkmark-circled'
			),
			array(
				'title' 	=> 'SOLO_BTN_SAVE',
				'class' 	=> 'akeeba-btn--grey',
				'onClick'	=> 'akeeba.System.submitForm(\'apply\')',
				'icon' 		=> 'akion-checkmark'
			),
			array(
				'title' 	=> 'SOLO_BTN_SAVENEW',
				'class' 	=> 'akeeba-btn--grey',
				'onClick'	=> 'akeeba.System.submitForm(\'savenew\')',
				'icon' 		=> 'akion-ios-copy'
			),
			array(
				'title' 	=> 'SOLO_BTN_CANCEL',
				'class' 	=> 'akeeba-btn--orange',
				'onClick' 	=> 'akeeba.System.submitForm(\'cancel\')',
				'icon' 		=> 'akion-close-circled'
			),
		);

		$toolbar = $document->getToolbar();

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		return parent::onBeforeAdd();
	}

	protected function onBeforeEdit()
	{
		$document = $this->container->application->getDocument();

		// Buttons (save, save and close, save and new, cancel)
		$buttons = array(
			array(
				'title' => 'SOLO_BTN_SAVECLOSE',
				'class' => 'akeeba-btn--green',
				'onClick' => 'akeeba.System.submitForm(\'save\')',
				'icon' => 'akion-checkmark-circled'
			),
			array(
				'title' => 'SOLO_BTN_SAVE',
				'class' => 'akeeba-btn--grey',
				'onClick' => 'akeeba.System.submitForm(\'apply\')',
				'icon' => 'akion-checkmark'
			),
			array(
				'title' => 'SOLO_BTN_SAVENEW',
				'class' => 'akeeba-btn--grey',
				'onClick' => 'akeeba.System.submitForm(\'savenew\')',
				'icon' => 'akion-ios-copy'
			),
			array(
				'title' => 'SOLO_BTN_CANCEL',
				'class' => 'akeeba-btn--orange',
				'onClick' => 'akeeba.System.submitForm(\'cancel\')',
				'icon' => 'akion-close-circled'
			),
		);

		$toolbar = $document->getToolbar();

		foreach ($buttons as $button)
		{
			$toolbar->addButtonFromDefinition($button);
		}

		return parent::onBeforeEdit();
	}
} 
