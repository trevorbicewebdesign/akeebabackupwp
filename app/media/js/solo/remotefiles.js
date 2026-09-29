/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba == "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.RemoteFiles == "undefined")
{
    akeeba.RemoteFiles = {};
}

/**
 * Shows the modal "please wait" card.
 *
 * This informs the user that something is happening in the background instead of letting them wonder if the software is
 * broken. It also prevents impatient users from multiple-clicking an action button which could have unintended
 * consequences; none of the remote files actions are very good candidates for parallel execution.
 */
akeeba.RemoteFiles.showWaitModalFirst = function (e)
{
    document.getElementById("akeebaBackupRemoteFilesWorkInProgress").style.display = "block";
    document.getElementById("akeebaBackupRemoteFilesMainInterface").style.display  = "none";
};

akeeba.System.documentReady(function ()
{
    // Action button anchors: show the modal "please wait" card when clicked
    akeeba.System.iterateNodes(".akeebaRemoteFilesShowWait", function (el)
    {
        akeeba.System.addEventListener(el, "click", akeeba.RemoteFiles.showWaitModalFirst);
    });

    // Disabled button anchors: cancel the click event
    akeeba.System.iterateNodes(".akeebaBackupRemoteFilesMainInterface[disabled=\"disabled\"]", function (el)
    {
        akeeba.System.addEventListener(el, "click", function ()
        {
            return false;
        });
    });

    // dlprogress view: autosubmit form
    var adminForm = document.getElementById('adminForm');

    if (!adminForm)
    {
        return;
    }

    adminForm.submit();
});