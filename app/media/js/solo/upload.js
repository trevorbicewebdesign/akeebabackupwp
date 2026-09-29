/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Object initialisation
if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Upload === "undefined")
{
    akeeba.Upload = {}
}

akeeba.Upload.autoSubmit = function ()
{
    var akeebaform = document.forms["akeebaform"];

    if (!akeebaform)
    {
        return;
    }

    akeebaform.submit();
};

akeeba.Upload.autoClose = function ()
{
    var elMessage = document.getElementById("comAkeebaUploadDone");

    // Only applies on the "done" layout
    if (elMessage === null)
    {
        return;
    }

    window.setTimeout(function ()
    {
        parent.akeeba.System.modalDialog.modal("hide");
    }, 3000);
};

akeeba.System.documentReady(function ()
{
    // Auto-submit the form "akeebaform" in the default and uploading layouts
    akeeba.Upload.autoSubmit();

    // Auto-close the window in the "done" layout
    akeeba.Upload.autoClose();
});