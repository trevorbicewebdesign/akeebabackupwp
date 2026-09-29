/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Update === "undefined")
{
    akeeba.Update = {
        errorCallback: null,
        statistics:    {
            inbytes: 0, outbytes: 0, files: 0
        },
        factory:       null,
        password:      null,
        ajaxURL:       null,
        mainURL:       null,
        translations:  {},
        nextStepUrl:   "",
        finaliseUrl:   ""
    };
}

akeeba.Update.downloadErrorCallback = function (msg)
{
    var elPBInfo = document.getElementById("downloadProgressInfo");
    var elPBFill = document.getElementById("downloadProgressBar");

    elPBInfo.style.display = "none";
    elPBFill.className     = "akeeba-progress-fill--failure";

    document.getElementById("downloadErrorText").innerHTML = msg;
    document.getElementById("downloadError").style.display = "block";
};

akeeba.Update.extractErrorCallback = function (msg)
{
    var elPBInfo = document.getElementById("extractProgressInfo");
    var elPBFill = document.getElementById("extractProgressBar");

    elPBInfo.style.display = "none";
    elPBFill.className     = "akeeba-progress-fill--failure";

    document.getElementById("extractErrorText").innerHTML = msg;
    document.getElementById("extractError").style.display = "block";
};

akeeba.Update.nextStep = function ()
{
    window.location = akeeba.System.getOptions("akeeba.Update.nextStepUrl");
};

/**
 * Generic error handler
 *
 * @param   msg  {string}  The error message to display
 */
akeeba.Update.onGenericError = function (msg)
{
    alert(msg);
};

/**
 * Update a progress bar
 *
 * @param   percent        {int}     The percent to set the progress bar to, must be 0 to 100
 * @param   progressBarId  {string}  The ID of the progress bar to set
 */
akeeba.Update.setProgressBar = function (percent, progressBarId)
{
    if (progressBarId === undefined)
    {
        progressBarId = "downloadProgressBar";
    }

    var newValue;

    if (percent <= 1)
    {
        newValue = 100 * percent;
    }
    else
    {
        newValue = percent;
    }

    if (newValue < 0)
    {
        newValue = 0;
    }

    if (newValue > 100)
    {
        newValue = 100;
    }

    var elPBFill = document.getElementById(progressBarId);
    var elPBINfo = document.getElementById(progressBarId + "Info");

    if (newValue < 100)
    {
        elPBFill.className = "akeeba-progress-fill";
    }
    else
    {
        elPBFill.className = "akeeba-progress-fill--success";
    }

    document.getElementById(progressBarId).style.width = newValue.toFixed(1) + "%";
    elPBINfo.innerText                                 = newValue.toFixed(1) + "%";
};

/**
 * Converts a bytes value to a human readable form (e.g. KiB, MiB etc)
 *
 * @param   bytes  {Number}   The number of bytes, e.g. 1124
 * @param   si     {Boolean}  Set to true to use base 10 for unit conversion, otherwise base 2 is used
 *
 * @returns  string The human readable size with maximum 1 decimal precision, e.g. 1.1 Kib
 */
akeeba.Update.humanFileSize = function (bytes, si)
{
    var thresh = si ? 1000 : 1024;

    if (bytes < thresh)
    {
        return bytes + " B";
    }

    var units = si ? [
        "kB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"
    ] : [
        "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB"
    ];
    var u     = -1;

    do
    {
        bytes /= thresh;
        ++u;
    }
    while (bytes >= thresh);

    return bytes.toFixed(1) + " " + units[u];
};

/**
 * Starts the download of the update file
 */
akeeba.Update.startDownload = function ()
{
    akeeba.Update.setProgressBar(0, "downloadProgressBar");

    document.getElementById("downloadProgressBarText").innerHTML = "";

    var jsonObject = {
        frag: -1, totalSize: -1
    };

    var data = {
        task: "downloader",
        json: JSON.stringify(jsonObject)
    };

    akeeba.System.doAjax(data, function (ret)
    {
        akeeba.Update.stepDownload(ret);
    }, null, false);
};

/**
 * Steps through the download of the update file
 *
 * @param   data  {Object} The return data from the server
 */
akeeba.Update.stepDownload = function (data)
{
    // Look for errors
    if (!data.status)
    {
        if ((data.errorCode >= 300) && (data.errorCode < 500))
        {
            akeeba.Update.downloadErrorCallback(akeeba.System.Text._("SOLO_UPDATE_ERR_INVALIDDOWNLOADID"));
            return;
        }

        akeeba.Update.downloadErrorCallback(data.error);
        return;
    }

    var totalSize = 0;
    var doneSize  = 0;
    var percent   = 0;
    var frag      = -1;

    // get running stats
    if (data.totalSize !== undefined)
    {
        totalSize = data.totalSize;
    }

    if (data.doneSize !== undefined)
    {
        doneSize = data.doneSize;
    }

    if (data.percent !== undefined)
    {
        percent = data.percent;
    }

    if (data.frag !== undefined)
    {
        frag = data.frag;
    }

    // Update GUI
    akeeba.Update.setProgressBar(percent, "downloadProgressBar");

    document.getElementById("downloadProgressBarText").innerText = percent.toFixed(1) + "%";

    var jsonObject = {
        frag: frag, totalSize: totalSize, doneSize: doneSize
    };

    var post = {
        "task": "downloader", "json": JSON.stringify(jsonObject)
    };

    if (percent < 100)
    {
        // More work to do
        akeeba.System.doAjax(post, function (ret)
        {
            akeeba.Update.stepDownload(ret);
        }, null, false);

        return;
    }

    // Done!
    akeeba.Update.setProgressBar(100, "downloadProgressBar");

    akeeba.Update.nextStep();
};

/**
 * Pings the update script (making sure its executable)
 */
akeeba.Update.pingExtract = function ()
{
    // Reset variables
    akeeba.Update.statistics.files    = 0;
    akeeba.Update.statistics.inbytes  = 0;
    akeeba.Update.statistics.outbytes = 0;

    // Do AJAX post
    var post = {task: "ping"};

    akeeba.System.doEncryptedAjax(post, function (data)
    {
        akeeba.Update.startExtract(data);
    }, function (msg)
    {
        document.getElementById("extractProgress").style.display  = "none";
        document.getElementById("extractPingError").style.display = "block";
    },
        true, 60000, true);
};

akeeba.Update.startExtract = function ()
{
    // Reset variables
    akeeba.Update.statistics.files    = 0;
    akeeba.Update.statistics.inbytes  = 0;
    akeeba.Update.statistics.outbytes = 0;

    var post = {task: "startRestore"};

    akeeba.System.doEncryptedAjax(post, function (data)
    {
        akeeba.Update.stepExtract(data);
    },
        null,
        true,
        60000,
        true
    );
};

akeeba.Update.stepExtract = function (data)
{
    if (data.status == false)
    {
        // handle failure
        akeeba.Update.extractErrorCallback(data.message);

        return;
    }

    // Parse total size, if exists
    if (data.totalsize !== undefined)
    {
        if (is_array(data.filelist))
        {
            akeeba.Update.statistics.totalSize = 0;

            for (var i = 0; i < data.filelist.length; i++)
            {
                var item = data.filelist[i];

                akeeba.Update.statistics.totalSize += item[1];
            }
        }

        akeeba.Update.statistics.files    = 0;
        akeeba.Update.statistics.inbytes  = 0;
        akeeba.Update.statistics.outbytes = 0;
    }

    // Update GUI
    akeeba.Update.statistics.files += data.files;
    akeeba.Update.statistics.inbytes += data.bytesIn;
    akeeba.Update.statistics.outbytes += data.bytesOut;

    var percentage = 0;

    if (akeeba.Update.statistics.totalSize > 0)
    {
        percentage = 100 * akeeba.Update.statistics.inbytes / akeeba.Update.statistics.totalSize;

        if (percentage < 0)
        {
            percentage = 0;
        }
        else if (percentage > 100)
        {
            percentage = 100;
        }
    }

    if (data.done)
    {
        percentage = 100;
    }

    akeeba.Update.setProgressBar(percentage, "extractProgressBar");
    document.getElementById("extractProgressBarTextPercent").innerText = percentage.toFixed(1);
    document.getElementById("extractProgressBarTextIn").innerText      = akeeba.Update.humanFileSize(
        akeeba.Update.statistics.inbytes, 0) + " / " + akeeba.Update.humanFileSize(
        akeeba.Update.statistics.totalSize, 0);
    document.getElementById("extractProgressBarTextOut").innerText     = akeeba.Update.humanFileSize(
        akeeba.Update.outbytes, 0);
    document.getElementById("extractProgressBarTextFile").innerText    = data.lastfile;

    if (!empty(data.factory))
    {
        akeeba.Update.factory = data.factory;
    }

    if (data.done)
    {
        window.setTimeout(akeeba.Update.finalizeUpdate, 500);
    }
    else
    {
        // Do AJAX post
        post = {
            task: "stepRestore", factory: data.factory
        };

        akeeba.System.doEncryptedAjax(post, function (data)
        {
            akeeba.Update.stepExtract(data);
        },
            null,
            true,
            60000,
            true
        );
    }
};

akeeba.Update.finalizeUpdate = function ()
{
    // Do AJAX post
    var post = {task: "finalizeRestore", factory: akeeba.Update.factory};
    akeeba.System.doEncryptedAjax(post, function (data)
    {
        window.location = akeeba.System.getOptions("akeeba.Update.finaliseUrl");
    },
        null,
        true,
        60000,
        true
    );
};


akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener("btnLiveUpdateReleaseNotes", "click", function ()
    {
        akeeba.Modal.open({
            inherit: "#releaseNotesPopup",
            width:   "80%"
        });

        return false;
    });

    akeeba.System.params.errorCallback = akeeba.Update.downloadErrorCallback;

    var autoAction = akeeba.System.getOptions("akeeba.Update.autoAction", "none");

    switch (autoAction)
    {
        case "startDownload":
            akeeba.Update.startDownload();
            break;

        case "pingExtract":
            akeeba.Update.pingExtract();
            break;
    }
});