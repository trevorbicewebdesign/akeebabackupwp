/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba == "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.S3Import == "undefined")
{
    akeeba.S3Import = {};
}

akeeba.S3Import.delayedApplicationOfS3KeysInGUI = function ()
{
    var elAccessKey = document.getElementById("s3access");
    var elSecretKey = document.getElementById("s3secret");

    if ((elAccessKey === null) || (elSecretKey === null))
    {
        return;
    }

    /**
     * This makes sure that the S3 access and secret keys are not replaced by the browser or a password manager browser
     * extensions with some arbitrary username and password combination.
     */
    setTimeout(function ()
    {
        document.getElementById("s3access").value = akeeba.System.getOptions("akeeba.S3Import.accessKey");
        document.getElementById("s3secret").value = akeeba.System.getOptions("akeeba.S3Import.secretKey");
    }, 500);
};

//=============================================================================
// Akeeba Backup Pro - Import arbitrary archives from S3
//=============================================================================

akeeba.S3Import.resetRoot = function ()
{
    document.getElementById("ak_s3import_folder").value = "";

    return true;
};

akeeba.S3Import.changeDirectory = function (e)
{
    var elTarget      = e.target;
    var encodedPrefix = akeeba.System.data.get(elTarget, "s3prefix", "");

    document.getElementById("ak_s3import_folder").value = atob(encodedPrefix);
    document.forms.adminForm.submit();
};

/**
 *
 * @param {Event} e
 */
akeeba.S3Import.importFile = function (e)
{
    var elTarget    = e.target;
    var encodedName = akeeba.System.data.get(elTarget, "s3object", "");
    var objectName  = atob(encodedName);

    if (objectName === "")
    {
        return false;
    }

    window.location = akeeba.System.getOptions("akeeba.S3Import.importURL") + "&file=" + encodeURIComponent(objectName);
};

akeeba.System.documentReady(function ()
{
    akeeba.S3Import.delayedApplicationOfS3KeysInGUI();

    akeeba.System.addEventListener("akeebaS3ImportResetRoot", "click", akeeba.S3Import.resetRoot);

    akeeba.System.iterateNodes(".akeebaS3ImportChangeDirectory", function (el)
    {
        akeeba.System.addEventListener(el, "click", akeeba.S3Import.changeDirectory);
    });

    akeeba.System.iterateNodes(".akeebaS3ImportObjectDownload", function (el)
    {
        akeeba.System.addEventListener(el, "click", akeeba.S3Import.importFile);
    });

    var redirectionURL = akeeba.System.getOptions("akeeba.S3Import.autoRedirectURL", "");

    if (redirectionURL !== '')
    {
        window.location = redirectionURL;
    }
});
