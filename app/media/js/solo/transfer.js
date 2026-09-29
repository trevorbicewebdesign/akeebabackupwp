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

if (typeof akeeba.Transfer === "undefined")
{
    akeeba.Transfer = {
        lastUrl:      "",
        lastResult:   "",
        FtpTest:      {},
        SftpTest:     {},
        FtpModal:     null,
        URLs:         {},
        translations: {}
    }
}

/**
 * Check the URL field
 */
akeeba.Transfer.onUrlChange = function (force)
{
    if (force === undefined)
    {
        force = false;
    }

    var urlBox = document.getElementById("akeeba-transfer-url");
    var url    = urlBox.value;

    if (url == "")
    {
        document.getElementById("akeeba-transfer-lbl-url").style.display = "block";
    }

    if ((url.substring(0, 7) != "http://") && (url.substring(0, 8) != "https://"))
    {
        url = "http://" + url;
    }

    var lastUrl    = akeeba.Transfer.lastUrl ? akeeba.Transfer.lastUrl : akeeba.System.getOptions(
        "akeeba.Transfer.lastUrl", "");
    var lastResult = akeeba.Transfer.lastResult ? akeeba.Transfer.lastResult : akeeba.System.getOptions(
        "akeeba.Transfer.lastResult", "");

    if (!force && (url === lastUrl))
    {
        akeeba.Transfer.applyUrlCheck({
            "status": lastResult,
            "url":    lastUrl
        });

        return;
    }

    var divList = document.querySelectorAll("#akeeba-transfer-row-url > div");

    for (i = 0; i < divList.length; i++)
    {
        divList[i].style.display = "none";
    }

    urlBox.setAttribute("disabled", "disabled");
    document.getElementById("akeeba-transfer-btn-url").setAttribute("disabled", "disabled");
    document.getElementById("akeeba-transfer-loading").style.display = "inline-block";

    akeeba.System.doAjax({
            "task": "checkUrl",
            "url":  url
        },
        akeeba.Transfer.applyUrlCheck,
        function (msg)
        {
            urlBox.removeAttribute("disabled");
            document.getElementById("akeeba-transfer-btn-url").removeAttribute("disabled");
            document.getElementById("akeeba-transfer-loading").style.display = "none";
        }, false, 10000
    );

    return false;
};

akeeba.Transfer.applyUrlCheck = function (response)
{
    var urlBox = document.getElementById("akeeba-transfer-url");

    urlBox.removeAttribute("disabled");
    document.getElementById("akeeba-transfer-btn-url").removeAttribute("disabled");
    document.getElementById("akeeba-transfer-loading").style.display       = "none";
    document.getElementById("akeeba-transfer-ftp-container").style.display = "none";

    urlBox.value = response.url;

    akeeba.Transfer.lastResult = response.status;
    akeeba.Transfer.lastUrl    = response.url;

    switch (response.status)
    {
        case "ok":
            akeeba.Transfer.showConnectionDetails();
            break;

        case "same":
            document.getElementById("akeeba-transfer-err-url-same").style.display = "block";
            break;

        case "invalid":
            document.getElementById("akeeba-transfer-err-url-invalid").style.display = "block";
            break;

        case "notexists":
            document.getElementById("akeeba-transfer-err-url-notexists").style.display = "block";
            break;
    }
};

akeeba.Transfer.showConnectionDetails = function ()
{
    document.getElementById("akeeba-transfer-url").setAttribute("disabled", "disabled");
    document.getElementById("akeeba-transfer-btn-url").setAttribute("disabled", "disabled");

    document.getElementById("akeeba-transfer-err-url-notexists").style.display = "none";
    document.getElementById("akeeba-transfer-ftp-container").style.display     = "grid";
    akeeba.Transfer.onTransferMethodChange();

    return false;
};

akeeba.Transfer.onTransferMethodChange = function (e)
{
    var elFtpMethod = document.getElementById("akeeba-transfer-ftp-method");
    var method      = elFtpMethod.options[elFtpMethod.selectedIndex].value;

    document.getElementById("akeeba-transfer-ftp-host").parentNode.style.display       = "none";
    document.getElementById("akeeba-transfer-ftp-port").parentNode.style.display       = "none";
    document.getElementById("akeeba-transfer-ftp-username").parentNode.style.display   = "none";
    document.getElementById("akeeba-transfer-ftp-password").parentNode.style.display   = "none";
    document.getElementById("akeeba-transfer-ftp-pubkey").parentNode.style.display     = "none";
    document.getElementById("akeeba-transfer-ftp-privatekey").parentNode.style.display = "none";
    document.getElementById("akeeba-transfer-ftp-directory").parentNode.style.display  = "none";
    document.getElementById("akeeba-transfer-ftp-passive-container").style.display     = "none";
    document.getElementById("akeeba-transfer-ftp-passive-fix-container").style.display = "none";
    document.getElementById("akeeba-transfer-chunkmode").parentNode.style.display      = "none";
    document.getElementById("akeeba-transfer-chunksize").parentNode.style.display      = "none";
    document.getElementById("akeeba-transfer-apply-loading").style.display             = "none";

    if (method !== "manual")
    {
        document.getElementById("akeeba-transfer-ftp-host").parentNode.style.display      =
            "grid";
        document.getElementById("akeeba-transfer-ftp-port").parentNode.style.display      =
            "grid";
        document.getElementById("akeeba-transfer-ftp-username").parentNode.style.display  =
            "grid";
        document.getElementById("akeeba-transfer-ftp-password").parentNode.style.display  =
            "grid";
        document.getElementById("akeeba-transfer-ftp-directory").parentNode.style.display =
            "grid";
        document.getElementById("akeeba-transfer-chunkmode").parentNode.style.display     = "grid";
        document.getElementById("akeeba-transfer-chunksize").parentNode.style.display     = "grid";
        document.getElementById("akeeba-transfer-btn-apply").parentNode.style.display     = "block";
        document.getElementById("akeeba-transfer-manualtransfer").style.display           = "none";
    }

    if (method === "manual")
    {
        document.getElementById("akeeba-transfer-btn-apply").parentNode.style.display = "none";
        document.getElementById("akeeba-transfer-manualtransfer").style.display       = "grid";

        return;
    }

    if ((method === "ftp") || (method === "ftps") || (method === "ftpcurl") || (method === "ftpscurl"))
    {
        document.getElementById("akeeba-transfer-ftp-passive-container").style.display = "grid";
    }

    if ((method === "ftpcurl") || (method === "ftpscurl"))
    {
        document.getElementById("akeeba-transfer-ftp-passive-fix-container").style.display = "grid";
    }

    if ((method === "sftp") || (method === "sftpcurl"))
    {
        document.getElementById("akeeba-transfer-ftp-pubkey").parentNode.style.display     = "grid";
        document.getElementById("akeeba-transfer-ftp-privatekey").parentNode.style.display = "grid";
    }

};

akeeba.Transfer.applyConnection = function ()
{
    document.getElementById("akeeba-transfer-ftp-error").style.display     = "none";
    document.getElementById("akeeba-transfer-apply-loading").style.display = "block";

    var button = document.getElementById("akeeba-transfer-btn-apply");
    button.setAttribute("disabled", "disabled");

    document.getElementById("akeeba-transfer-ftp-method").setAttribute("disabled", "disabled");
    document.getElementById("akeeba-transfer-ftp-host").parentNode.style.display       = "none";
    document.getElementById("akeeba-transfer-ftp-port").parentNode.style.display       = "none";
    document.getElementById("akeeba-transfer-ftp-username").parentNode.style.display   = "none";
    document.getElementById("akeeba-transfer-ftp-password").parentNode.style.display   = "none";
    document.getElementById("akeeba-transfer-ftp-pubkey").parentNode.style.display     = "none";
    document.getElementById("akeeba-transfer-ftp-privatekey").parentNode.style.display = "none";
    document.getElementById("akeeba-transfer-ftp-directory").parentNode.style.display  = "none";
    document.getElementById("akeeba-transfer-ftp-passive-container").style.display     = "none";
    document.getElementById("akeeba-transfer-ftp-passive-fix-container").style.display = "none";
    document.getElementById("akeeba-transfer-chunkmode").parentNode.style.display      = "none";
    document.getElementById("akeeba-transfer-chunksize").parentNode.style.display      = "none";

    var elFtpMethod = document.getElementById("akeeba-transfer-ftp-method");
    var method      = elFtpMethod.options[elFtpMethod.selectedIndex].value;

    if (method == "manual")
    {
        document.getElementById("akeeba-transfer-btn-apply").parentNode.style.display = "none";
        document.getElementById("akeeba-transfer-manualtransfer").style.display       = "grid";

        return;
    }

    var data = {
        "task":        "applyConnection",
        "method":      method,
        "host":        document.getElementById("akeeba-transfer-ftp-host").value,
        "port":        document.getElementById("akeeba-transfer-ftp-port").value,
        "username":    document.getElementById("akeeba-transfer-ftp-username").value,
        "password":    document.getElementById("akeeba-transfer-ftp-password").value,
        "directory":   document.getElementById("akeeba-transfer-ftp-directory").value,
        "passive":     document.getElementById("akeeba-transfer-ftp-passive_1").checked ? 1 : 0,
        "passive_fix": document.getElementById("akeeba-transfer-ftp-passive-fix_1").checked ? 1 : 0,
        "privateKey":  document.getElementById("akeeba-transfer-ftp-privatekey").value,
        "publicKey":   document.getElementById("akeeba-transfer-ftp-pubkey").value,
        "chunkMode":   document.getElementById("akeeba-transfer-chunkmode").value,
        "chunkSize":   document.getElementById("akeeba-transfer-chunksize").value
    };

    // Construct the query
    akeeba.System.doAjax(
        data,
        function (res)
        {
            document.getElementById("akeeba-transfer-apply-loading").style.display = "none";

            if (!res.status)
            {
                document.getElementById("akeeba-transfer-btn-apply").removeAttribute("disabled");
                document.getElementById("akeeba-transfer-ftp-method").removeAttribute("disabled");

                var akeebaTransferFTPError     = document.getElementById("akeeba-transfer-ftp-error");
                var akeebaTransferFTPErrorBody = document.getElementById("akeeba-transfer-ftp-error-body");
                var akeebaForceButton          = document.getElementById("akeeba-transfer-ftp-error-force");

                if (akeebaForceButton)
                {
                    akeebaForceButton.style.display = "none";

                    if (res.ignorable)
                    {
                        akeebaForceButton.style.display = "inline-block";
                    }
                }

                akeebaTransferFTPErrorBody.innerHTML = res.message;
                akeebaTransferFTPError.style.display = "block";
                akeeba.System.triggerEvent(akeebaTransferFTPError, "focus");

                akeeba.Transfer.onTransferMethodChange();

                return;
            }

            // Successful connection; perform preliminary checks and upload Kickstart
            akeeba.Transfer.uploadKickstart();

        },
        function (res)
        {
            document.getElementById("akeeba-transfer-apply-loading").style.display = "none";

            document.getElementById("akeeba-transfer-btn-apply").removeAttribute("disabled");
            document.getElementById("akeeba-transfer-ftp-method").removeAttribute("disabled");

            var elFtpError             = document.getElementById("akeeba-transfer-ftp-error");
            var elFtpErrorBody         = document.getElementById("akeeba-transfer-ftp-error-body");
            elFtpErrorBody.textContent = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTFTP_TEST_FAIL");
            elFtpError.style.display   = "block";
            akeeba.System.triggerEvent(elFtpError, "focus");

            akeeba.Transfer.onTransferMethodChange();
        }
        , false, 15000
    );
};

akeeba.Transfer.uploadKickstart = function ()
{
    var stepKickstart   = document.getElementById("akeeba-transfer-upload-lbl-kickstart");
    var stepArchive     = document.getElementById("akeeba-transfer-upload-lbl-archive");
    var uploadErrorBox  = document.getElementById("akeeba-transfer-upload-error");
    var uploadErrorBody = document.getElementById("akeeba-transfer-upload-error-body");
    var uploadForce     = document.getElementById("akeeba-transfer-upload-error-force");

    uploadErrorBox.style.display = "none";
    uploadForce.style.display    = "none";
    uploadErrorBody.innerHTML    = "";

    stepKickstart.className = "akeeba-label--orange";
    stepArchive.className   = "akeeba-label--grey";

    document.getElementById("akeeba-transfer-upload-area-kickstart").style.display = "none";
    document.getElementById("akeeba-transfer-upload-area-upload").style.display    = "block";
    document.getElementById("akeeba-transfer-upload").style.display                = "block";

    var data = {
        "task": "initialiseUpload"
    };

    // Construct the query
    akeeba.System.doAjax(data, function (res)
    {
        if (!res.status)
        {
            if (uploadForce)
            {
                uploadForce.style.display = "none";

                if (res.ignorable)
                {
                    uploadForce.style.display = "inline-block";
                }
            }

            stepKickstart.className = "akeeba-label--red";

            uploadErrorBody.innerHTML    = res.message;
            uploadErrorBox.style.display = "block";

            return;
        }

        // Success. Now let's upload the backup archive.
        akeeba.Transfer.uploadArchive(1);
    }, null, false, 150000);
};

akeeba.Transfer.uploadArchive = function (start)
{
    if (start === undefined)
    {
        start = 0;
    }

    var stepKickstart   = document.getElementById("akeeba-transfer-upload-lbl-kickstart");
    var stepArchive     = document.getElementById("akeeba-transfer-upload-lbl-archive");
    var uploadErrorBox  = document.getElementById("akeeba-transfer-upload-error");
    var uploadErrorBody = document.getElementById("akeeba-transfer-upload-error-body");

    uploadErrorBody.innerHTML    = "";
    uploadErrorBox.style.display = "none";

    stepKickstart.className = "akeeba-label--green";
    stepArchive.className   = "akeeba-label--orange";

    var data = {
        "task": "upload", "start": start
    };

    // Construct the query
    akeeba.System.doAjax(data, function (res)
    {
        if (!res.result)
        {
            stepArchive.className = "akeeba-label--red";

            uploadErrorBody.innerHTML    = res.message;
            uploadErrorBox.style.display = "block";

            return;
        }

        // Success. Let's update the displayed information and step through the upload.
        if (res.done)
        {
            document.getElementById("akeeba-transfer-upload-percent").textContent = "100 %";
            document.getElementById("akeeba-transfer-upload-size").innerHTML      = "";

            document.getElementById("akeeba-transfer-upload-area-kickstart").style.display = "block";
            document.getElementById("akeeba-transfer-upload-area-upload").style.display    = "none";

            const kickstartName = akeeba.System.getOptions("akeebabackup.transfer")?.randomName ?? "kickstart.php";
            const urlBox = document.getElementById("akeeba-transfer-url");
            const url = urlBox.value.replace(/\/$/, "") + "/" + kickstartName + ".php";

            document.getElementById("akeeba-transfer-upload-btn-kickstart").setAttribute("href", url);

            return;
        }

        var donePercent = 0;
        var totalSize   = res.totalSize * 1.0;
        var doneSize    = res.doneSize * 1.0;

        if ((totalSize > 0) && (doneSize > 0))
        {
            donePercent = 100 * (doneSize / totalSize);
        }

        document.getElementById("akeeba-transfer-upload-percent").textContent = donePercent.toFixed(2) + "%";
        document.getElementById("akeeba-transfer-upload-size").textContent    = doneSize.toFixed(
            0) + " / " + totalSize.toFixed(0) + " bytes";

        // Using setTimeout prevents recursive call issues.
        window.setTimeout(function ()
        {
            akeeba.Transfer.uploadArchive(0);
        }, 50);
    }, null, false, 150000);
};

akeeba.Transfer.testFtpSftpConnection = function ()
{
    var elFtpMethod = document.getElementById("akeeba-transfer-ftp-method");
    var driver      = elFtpMethod.options[elFtpMethod.selectedIndex].value;

    if ((driver === "ftp") || (driver === "ftps") || (driver === "ftpcurl") || (driver === "ftpscurl"))
    {
        akeeba.Transfer.FtpTest.testConnection("akeeba-transfer-btn-testftp");
    }
    else if ((driver === "sftp") || (driver === "sftpcurl"))
    {
        akeeba.Transfer.SftpTest.testConnection("akeeba-transfer-btn-testftp");
    }

    return false;
};

//=============================================================================
// 							I N I T I A L I Z A T I O N
//=============================================================================
akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener(
        document.getElementById("akeeba-transfer-ftp-method"), "change", akeeba.Transfer.onTransferMethodChange);
    akeeba.System.addEventListener(
        document.getElementById("akeeba-transfer-btn-url"), "click", function (e)
        {
            e.preventDefault();

            akeeba.Transfer.onUrlChange(true);

            return false;
        });

    // Auto-process URL change event
    if (document.getElementById("akeeba-transfer-url").value)
    {
        akeeba.Transfer.onUrlChange();
    }

    // Remote connection hooks
    akeeba.System.addEventListener("akeeba-transfer-ftp-method", "change", akeeba.Transfer.onTransferMethodChange);
    // akeeba.System.addEventListener(
    //     "akeeba-transfer-ftp-directory-browse", "click", akeeba.Transfer.initFtpSftpBrowser);
    akeeba.System.addEventListener(
        "akeeba-transfer-btn-apply", "click", akeeba.Transfer.applyConnection);
    akeeba.System.addEventListener(
        "akeeba-transfer-err-url-notexists-btn-ignore", "click", akeeba.Transfer.showConnectionDetails
    );

});
