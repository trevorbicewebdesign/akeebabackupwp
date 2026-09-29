/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof (akeeba) == "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Manage == "undefined")
{
    akeeba.Manage = {
        remoteManagementModal: null,
        uploadModal:           null,
        downloadModal:         null,
        infoModal:             null
    }
}

akeeba.Manage.onRemoteManagementClick = function (managementUrl, reloadUrl)
{
    akeeba.Modal.remoteManagementModal = akeeba.Modal.open({
        iframe:        managementUrl,
        width:         "500",
        height:        "450",
        closeCallback: function ()
                       {
                           akeeba.Modal.remoteManagementModal = null;
                           window.location                    = reloadUrl;
                       }
    });
};

akeeba.Manage.onUploadClick = function (uploadURL, reloadUrl)
{
    akeeba.Modal.uploadModal = akeeba.Modal.open({
        iframe:        uploadURL,
        width:         "450",
        height:        "280",
        closeCallback: function ()
                       {
                           akeeba.Modal.remoteManagementModal = null;
                           window.location                    = reloadUrl;
                       }
    });
};

akeeba.Manage.onDownloadClick = function (inheritFrom)
{
    akeeba.Modal.downloadModal = akeeba.Modal.open({
        inherit: inheritFrom,
        width:   "450",
        height:  "280"
    });
};

akeeba.Manage.onShowInfoClick = function (inheritFrom)
{
    akeeba.Modal.infoModal = akeeba.Modal.open({
        inherit: inheritFrom,
        width:   "450",
        height:  "280"
    });
};

akeeba.Manage.confirmDownload = function (e)
{
    var answer = confirm(akeeba.System.Text._("COM_AKEEBA_BUADMIN_LOG_DOWNLOAD_CONFIRM"));

    if (!answer)
    {
        return;
    }

    var clickedElement = null;

    if (typeof e.target === "object")
    {
        clickedElement = e.target;
    }
    else if (typeof e.srcElement === "object")
    {
        clickedElement = e.srcElement;
    }

    if (clickedElement === null)
    {
        return;
    }

    var id   = akeeba.System.data.get(clickedElement, "id", "id1");
    var part = akeeba.System.data.get(clickedElement, "part", "");

    if (id < 0)
    {
        return;
    }

    var newURL = akeeba.System.getOptions("akeeba.Manage.downloadURL") +
        "&id=" + id;

    if ((typeof part === "undefined") || (part !== ""))
    {
        newURL += "&part=" + part
    }

    window.location = newURL;
};

akeeba.System.documentReady(function ()
{
    // Enable tooltips
    akeeba.Tooltip.enableFor(document.querySelectorAll(".akeebaCommentPopover"), false);

    // Add click event handlers to download buttons
    akeeba.System.iterateNodes(".comAkeebaManageDownloadButton", function (el)
    {
        akeeba.System.addEventListener(el, "click", akeeba.Manage.confirmDownload);
    });

    akeeba.System.iterateNodes(".akeeba_remote_management_link", function (el)
    {
        akeeba.System.addEventListener(el, "click", function (e)
        {
            e.preventDefault();

            var managementUrl = akeeba.System.data.get(el, "management", "");
            var reloadUrl     = akeeba.System.data.get(el, "reload", "");

            if ((managementUrl === "") || (reloadUrl === ""))
            {
                return false;
            }

            akeeba.Manage.onRemoteManagementClick(managementUrl, reloadUrl);

            return false;
        });
    });

    akeeba.System.iterateNodes(".akeeba_upload", function (el)
    {
        akeeba.System.addEventListener(el, "click", function (e)
        {
            e.preventDefault();

            var uploadUrl = akeeba.System.data.get(el, "upload", "");
            var reloadUrl = akeeba.System.data.get(el, "reload", "");

            if ((uploadUrl === "") || (reloadUrl === ""))
            {
                return false;
            }

            akeeba.Manage.onUploadClick(uploadUrl, reloadUrl);

            return false;
        });
    });

    akeeba.System.iterateNodes(".akeeba_download_button", function (el)
    {
        akeeba.System.addEventListener(el, "click", function (e)
        {
            e.preventDefault();

            var dlTarget = akeeba.System.data.get(el, "dltarget", "");

            if (dlTarget === "")
            {
                return false;
            }

            akeeba.Manage.onDownloadClick(dlTarget);

            return false;
        });
    });

    akeeba.System.iterateNodes(".akeeba_showinfo_link", function (el)
    {
        akeeba.System.addEventListener(el, "click", function (e)
        {
            e.preventDefault();

            var infoTarget = akeeba.System.data.get(el, "infotarget", "");

            if (infoTarget === "")
            {
                return false;
            }

            akeeba.Manage.onShowInfoClick(infoTarget);

            return false;
        });
    });

    // Show the how to restore modal if necessary
    if (akeeba.System.getOptions("akeeba.Manage.ShowHowToRestoreModal", 0))
    {
        setTimeout(function ()
        {
            akeeba.System.howToRestoreModal = akeeba.Modal.open({
                inherit: "#akeeba-config-howtorestore-bubble",
                width:   "80%"
            });
        }, 500);

        akeeba.System.addEventListener("comAkeebaManageCloseHowToRestoreModal", "click", function ()
        {
            akeeba.System.howToRestoreModal.close();

            document.getElementById("akeeba-config-howtorestore-bubble").style.display = "none";
        })
    }

    // Profile filter event handler
    akeeba.System.addEventListener("comAkeebaManageProfileSelector", "change", function ()
    {
        document.forms.adminForm.submit();
    });
});
