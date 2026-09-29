/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Browser === "undefined")
{
    akeeba.Browser = {}
}

akeeba.Browser.useThis = function ()
{
    var rawFolder = document.forms.adminForm.folderraw.value;

    if (rawFolder === "[SITEROOT]")
    {
        alert(akeeba.System.Text._("COM_AKEEBA_CONFIG_UI_ROOTDIR"));

        rawFolder = "[SITETMP]";
    }

    window.parent.akeeba.Configuration.onBrowserCallback(rawFolder);
};

akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener("comAkeebaBrowserUseThis", "click", function (e)
    {
        e.preventDefault();
        akeeba.Browser.useThis();

        return false;
    })
});