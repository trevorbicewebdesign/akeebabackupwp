/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Object initialisation
if (typeof akeeba === 'undefined')
{
	var akeeba = {};
}

if (typeof akeeba.Users === 'undefined')
{
	akeeba.Users = {}
}

akeeba.Users.onTFAMethodChange = function()
{
	var tfaDivs = document.querySelectorAll('#tfa_containers>div');

	for (var i = 0; i < tfaDivs.length; i++)
	{
		var container = tfaDivs[i];

		container.style.display = 'none';
	}

	var elTFAMethod = document.getElementById('tfa[method]');
	var containerName = 'tfa_' + elTFAMethod.value;
	document.getElementById(containerName).style.display = 'block';
};

akeeba.System.documentReady(function() {
	akeeba.System.addEventListener(document.getElementById('tfa[method]'), 'change', akeeba.Users.onTFAMethodChange);

	akeeba.Users.onTFAMethodChange();
});