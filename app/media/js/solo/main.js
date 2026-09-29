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

if (typeof akeeba.ControlPanel === "undefined")
{
    akeeba.ControlPanel = {
        outputDirUnderSiteRoot: false,
        hasSecurityFiles:       false
    }
}

/* Warn about CloudFlare Rocket Loader */
akeeba.ControlPanel.displayCloudFlareWarning = function (testfile)
{
    if (!localStorage.getItem(testfile))
    {
        return;
    }

    document.getElementById("cloudFlareWarn").style.display = "block";
};

akeeba.ControlPanel.isReadableFile = function (myURL, callback)
{
    if (!myURL)
    {
        return;
    }

    akeeba.Ajax.ajax(myURL, {
        type:    "GET",
        success: function (responseText, statusText, xhr)
                 {
                     if (responseText.length > 0)
                     {
                         callback.apply();
                     }
                 }
    });
};

akeeba.ControlPanel.getUpdateInformation = function (updateInformationUrl)
{
    var elUpdateContainer = document.getElementById("soloUpdateContainer");

    if (!elUpdateContainer)
    {
        return;
    }

    akeeba.Ajax.ajax(updateInformationUrl, {
        type:    "GET",
        success: function (msg)
                 {
                     var extracted = akeeba.System.extractResponse(msg);

                     if (!extracted.isValid)
                     {
                         return;
                     }

                     var data              = extracted.data;
                     var elUpdateContainer = document.getElementById("soloUpdateContainer");
                     var elUpdateIcon      = document.getElementById("soloUpdateIcon");

                     if ((elUpdateContainer === null) || (elUpdateIcon === null))
                     {
                         return;
                     }

                     if (data.hasUpdate)
                     {
                         elUpdateContainer.className                                  = "akeeba-action--orange";
                         elUpdateIcon.className                                       = "akion-android-warning";
                         document.getElementById("soloUpdateAvailable").style.display = "inline-block";
                         document.getElementById("soloUpdateUpToDate").style.display  = "none";

                         document.getElementById("soloUpdateNotification").innerHTML = data.noticeHTML;
                     }
                     else
                     {
                         elUpdateContainer.className                                  = "akeeba-action--green";
                         elUpdateIcon.className                                       = "akion-checkmark-circled";
                         document.getElementById("soloUpdateAvailable").style.display = "none";
                         document.getElementById("soloUpdateUpToDate").style.display  = "inline-block";
                     }
                 }
    });

};

/**
 * Displays the changelog in a popup box
 */
akeeba.ControlPanel.showChangelog = function ()
{
    akeeba.Modal.open({
        inherit: "#akeeba-changelog",
        width:   "80%"
    });
};

akeeba.ControlPanel.checkOutputFolderSecurity = function ()
{
    if (!akeeba.System.getOptions("akeeba.ControlPanel.outputDirUnderSiteRoot", false))
    {
        return;
    }

    akeeba.System.doAjax({
            ajaxURL: akeeba.System.getOptions("akeeba.ControlPanel.checkOutDirUrl")
        }, function (data)
        {
            var readFile   = data.hasOwnProperty("readFile") ? data.readFile : false;
            var listFolder = data.hasOwnProperty("listFolder") ? data.listFolder : false;
            var isSystem   = data.hasOwnProperty("isSystem") ? data.isSystem : false;
            var hasRandom  = data.hasOwnProperty("hasRandom") ? data.hasRandom : true;

            if (listFolder && isSystem)
            {
                document.getElementById("outDirSystem").style.display = "block";
            }
            else if (listFolder)
            {
                document.getElementById("insecureOutputDirectory").style.display = "block";
            }
            else if (readFile && !listFolder && !hasRandom)
            {
                if (!akeeba.System.getOptions("akeeba.ControlPanel.hasSecurityFiles", true))
                {
                    document.getElementById("insecureOutputDirectory").style.display = "block";

                    return;
                }

                if (!hasRandom)
                {
                    document.getElementById("missingRandomFromFilename").style.display = "block";
                }
            }
        }, function (message)
        {
            // I can ignore errors for this AJAX requesy
        }, false
    );
};

akeeba.ControlPanel.warnAboutAdBlocker = function ()
{
    var elAdBlockBanner = document.getElementById("adblock-warning");

    if (typeof elAdBlockBanner !== "object")
    {
        return;
    }

    var testElement       = document.createElement("div");
    testElement.innerHTML = "&nbsp;";
    testElement.className = "adsbox";

    document.body.appendChild(testElement);

    window.setTimeout(function ()
    {
        if (testElement.offsetHeight === 0)
        {
            document.getElementById("adblock-warning").style.display = "block";
        }
        testElement.remove();
    }, 250);
};

// Initialization
akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener("comAkeebaControlPanelProfileSwitch", "change", function ()
    {
        document.forms.profileForm.submit();
    });

    document.querySelectorAll(".oneclick").forEach(function(item){
        akeeba.System.addEventListener(item, "click", function(){
            item.parentElement.submit();

            return false;
        })
    })

    akeeba.System.addEventListener("btnchangelog", "click", akeeba.ControlPanel.showChangelog);

    akeeba.ControlPanel.displayCloudFlareWarning(akeeba.System.getOptions("akeeba.ControlPanel.cloudFlareURN"));
    akeeba.ControlPanel.getUpdateInformation(akeeba.System.getOptions("akeeba.ControlPanel.updateInfoURL"));
    akeeba.ControlPanel.checkOutputFolderSecurity();
    akeeba.ControlPanel.warnAboutAdBlocker();

    if (akeeba.System.getOptions("akeeba.System.notification.hasDesktopNotification", false))
    {
        akeeba.System.notification.askPermission();
    }
});