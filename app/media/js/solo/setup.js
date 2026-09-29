/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

if (typeof akeeba === "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Setup === "undefined")
{
    akeeba.Setup             = {};
    akeeba.Setup.URLs        = {};
    akeeba.Setup.FtpBrowser  = {
        params: {}
    };
    akeeba.Setup.SftpBrowser = {
        params: {}
    };
    akeeba.Setup.FtpTest     = {};
    akeeba.Setup.SftpTest    = {};
}

akeeba.Setup.onFsDriverClick = function (e)
{
    var driver       = document.getElementById("fs_driver").value;
    var elFtpOptions = document.getElementById("ftp_options");

    if ((driver === "ftp") || (driver === "sftp"))
    {
        elFtpOptions.style.display = "block";
    }
    else
    {
        elFtpOptions.style.display = "none";
    }
};

/**
 * FTP browser callback, used to set the FTP root directory in an element
 *
 * @param  path  The path returned by the browser
 */
akeeba.Setup.FtpBrowser.callback = function (path)
{
    var charlist = ("/").replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, "$1");
    var re       = new RegExp("^[" + charlist + "]+", "g");
    path         = "/" + (path + "").replace(re, "");

    document.getElementById(akeeba.Setup.FtpBrowser.params.key).value = path;
};

/**
 * Initialises an FTP folder browser
 *
 * @param  key        The id of the field holding the FTP directory we're outputting
 * @param  paramsKey  The key prefix of the fields holding FTP connection information
 */
akeeba.Setup.FtpBrowser.initialise = function (key, paramsKey)
{
    akeeba.Setup.FtpBrowser.params.host      = document.getElementById(paramsKey + "_host").value;
    akeeba.Setup.FtpBrowser.params.port      = document.getElementById(paramsKey + "_port").value;
    akeeba.Setup.FtpBrowser.params.username  = document.getElementById(paramsKey + "_username").value;
    akeeba.Setup.FtpBrowser.params.password  = document.getElementById(paramsKey + "_password").value;
    akeeba.Setup.FtpBrowser.params.passive   = true;
    akeeba.Setup.FtpBrowser.params.ssl       = false;
    akeeba.Setup.FtpBrowser.params.directory = document.getElementById(paramsKey + "_directory").value;

    akeeba.Setup.FtpBrowser.params.key = key;

    akeeba.Setup.FtpBrowser.open();
};

/**
 * Opens the FTP directory browser
 */
akeeba.Setup.FtpBrowser.open = function ()
{
    var ftp_dialog_element = document.getElementById("ftpdialog");

    ftp_dialog_element.style.display = "block";

    akeeba.System.addEventListener("ftpdialogOkButton", "click", function (e)
    {
        akeeba.Setup.FtpBrowser.callback(akeeba.Setup.FtpBrowser.params.directory);

        if ((typeof akeeba.Setup.FtpBrowser.Modal === "object") && akeeba.Setup.FtpBrowser.Modal.close)
        {
            akeeba.Setup.FtpBrowser.Modal.close();
        }
    });

    akeeba.System.addEventListener(document.getElementById("ftpdialogCancelButton"), "click", function (e)
    {
        if ((typeof akeeba.Setup.FtpBrowser.Modal === "object") && akeeba.Setup.FtpBrowser.Modal.close)
        {
            akeeba.Setup.FtpBrowser.Modal.close();
        }
    });

    akeeba.Setup.FtpBrowser.Modal = akeeba.Modal.open({
        inherit: ftp_dialog_element,
        width:   "80%"
    });

    document.getElementById("ftpBrowserErrorContainer").style.display = "none";
    document.getElementById("ftpBrowserFolderList").innerHTML         = "";
    document.getElementById("ftpBrowserCrumbs").innerHTML             = "";

    if (empty(akeeba.Setup.FtpBrowser.params.directory))
    {
        akeeba.Setup.FtpBrowser.params.directory = "";
    }

    var data = {
        "host":      akeeba.Setup.FtpBrowser.params.host,
        "port":      akeeba.Setup.FtpBrowser.params.port,
        "username":  akeeba.Setup.FtpBrowser.params.username,
        "password":  akeeba.Setup.FtpBrowser.params.password,
        "passive":   (akeeba.Setup.FtpBrowser.params.passive ? 1 : 0),
        "ssl":       (akeeba.Setup.FtpBrowser.params.ssl ? 1 : 0),
        "directory": akeeba.Setup.FtpBrowser.params.directory
    };

    // URL to load the browser
    data.ajaxURL = akeeba.Setup.URLs.ftpBrowser;

    // Do AJAX call & Render results
    akeeba.System.doAjax(
        data,
        function (data)
        {
            var elBreadCrumbs          = document.getElementById("ak_crumbs2");
            var elFTPBrowserFolderList = document.getElementById("ftpBrowserFolderList");

            elFTPBrowserFolderList.style.display = "none";
            elBreadCrumbs.style.display          = "none";

            if (data.error !== false)
            {
                // An error occured
                document.getElementById("ftpBrowserError").innerHTML              = data.error;
                document.getElementById("ftpBrowserErrorContainer").style.display = "block";

                return;
            }

            // Create the interface
            document.getElementById("ftpBrowserErrorContainer").style.display = "none";

            // Display the crumbs
            if (!empty(data.breadcrumbs))
            {
                elBreadCrumbs.style.display = "block";
                elBreadCrumbs.innerHTML     = "";
                var relativePath            = "/";

                akeeba.Setup.FtpBrowser.addCrumb(akeeba.System.Text._("SOLO_COMMON_LBL_ROOT"), "/", elBreadCrumbs);

                for (i = 0; i < data.breadcrumbs.length; i++)
                {
                    var crumb = data.breadcrumbs[i];

                    relativePath += "/" + crumb;

                    akeeba.Setup.FtpBrowser.addCrumb(crumb, relativePath, elBreadCrumbs);
                }
            }

            // Display the list of directories
            if (!empty(data.list))
            {
                elFTPBrowserFolderList.style.display = "block";

                /**
                 * If the directory in the browser is empty, let's inject it with the parent dir, otherwise if the
                 * user immediately clicks on "Use" gets a wrong path.
                 */
                if (!akeeba.Setup.FtpBrowser.params.directory)
                {
                    akeeba.Setup.FtpBrowser.params.directory = data.directory;
                }

                for (i = 0; i < data.list.length; i++)
                {
                    var item = data.list[i];

                    akeeba.Setup.FtpBrowser.createLink(data.directory + "/" + item, item, elFTPBrowserFolderList);
                }
            }
        },
        function (message)
        {
            document.getElementById("ftpBrowserError").innerHTML              = message;
            document.getElementById("ftpBrowserErrorContainer").style.display = "block";
            document.getElementById("ftpBrowserFolderList").style.display     = "none";
            document.getElementById("ftpBrowserCrumbs").style.display         = "none";
        },
        false
    );
};

/**
 * Creates a directory link for the FTP browser UI
 *
 * @param  path       The directory to link to
 * @param  label      How to display it
 * @param  container  The containing element
 * @param  ftpObject  FTP configuration information
 */
akeeba.Setup.FtpBrowser.createLink = function (path, label, container, ftpObject)
{
    if (typeof ftpObject === "undefined")
    {
        ftpObject = akeeba.Setup.FtpBrowser;
    }

    var row  = document.createElement("tr");
    var cell = document.createElement("td");
    row.appendChild(cell);

    var myElement       = document.createElement("a");
    myElement.innerText = label;
    akeeba.System.addEventListener(myElement, "click", function (e)
    {
        ftpObject.params.directory = path;
        ftpObject.open();
    });
    cell.appendChild(myElement);

    container.appendChild(row);
};

/**
 * Adds a breadcrumb to the FTP browser
 *
 * @param  crumb         How to display it
 * @param  relativePath  The relative path to the current directory
 * @param  container     The containing element
 * @param  ftpObject  FTP configuration information
 */
akeeba.Setup.FtpBrowser.addCrumb = function (crumb, relativePath, container, ftpObject)
{
    if (typeof ftpObject == "undefined")
    {
        ftpObject = akeeba.Setup.FtpBrowser;
    }

    var li = document.createElement("li");

    var myLink       = document.createElement("a");
    myLink.innerHTML = crumb;

    akeeba.System.addEventListener(myLink, "click", function (e)
    {
        ftpObject.params.directory = relativePath;
        ftpObject.open();

        if (e.preventDefault)
        {
            e.preventDefault();
        }
        else
        {
            e.returnValue = false;
        }
    });

    li.appendChild(myLink);
    container.appendChild(li);
};

/**
 * Initialises an SFTP folder browser
 *
 * @param  key        The Akeeba Engine configuration key of the field holding the SFTP directory we're outputting
 * @param  paramsKey  The Akeeba Engine configuration key prefix of the fields holding SFTP connection information
 */
akeeba.Setup.SftpBrowser.initialise = function (key, paramsKey)
{
    akeeba.Setup.SftpBrowser.params.host      = document.getElementById(paramsKey + "_host").value;
    akeeba.Setup.SftpBrowser.params.port      = document.getElementById(paramsKey + "_port").value;
    akeeba.Setup.SftpBrowser.params.username  = document.getElementById(paramsKey + "_username").value;
    akeeba.Setup.SftpBrowser.params.password  = document.getElementById(paramsKey + "_password").value;
    akeeba.Setup.SftpBrowser.params.directory = document.getElementById(paramsKey + "_directory").value;
    akeeba.Setup.SftpBrowser.params.privKey   = "";
    akeeba.Setup.SftpBrowser.params.pubKey    = "";

    akeeba.Setup.FtpBrowser.params.key = key;

    akeeba.Setup.SftpBrowser.open();
};

/**
 * Opens the SFTP directory browser
 */
akeeba.Setup.SftpBrowser.open = function ()
{
    var ftp_dialog_element = document.getElementById("sftpdialog");

    ftp_dialog_element.style.display = "block";

    akeeba.System.addEventListener(document.getElementById("sftpdialogOkButton"), "click", function (e)
    {
        akeeba.Setup.SftpBrowser.callback(akeeba.Setup.SftpBrowser.params.directory);

        if ((typeof akeeba.Setup.SftpBrowser.Modal === "object") && akeeba.Setup.SftpBrowser.Modal.close)
        {
            akeeba.Setup.SftpBrowser.Modal.close();
        }
    });

    akeeba.System.addEventListener(document.getElementById("sftpdialogCancelButton"), "click", function (e)
    {
        if ((typeof akeeba.Setup.SftpBrowser.Modal === "object") && akeeba.Setup.SftpBrowser.Modal.close)
        {
            akeeba.Setup.SftpBrowser.Modal.close();
        }
    });

    akeeba.Setup.SftpBrowser.Modal = akeeba.Modal.open({
        inherit: ftp_dialog_element,
        width:   "80%"
    });

    document.getElementById("sftpBrowserErrorContainer").style.display = "none";
    document.getElementById("sftpBrowserFolderList").innerHTML         = "";
    document.getElementById("sftpBrowserCrumbs").innerHTML             = "";

    if (empty(akeeba.Setup.SftpBrowser.params.directory))
    {
        akeeba.Setup.SftpBrowser.params.directory = "";
    }

    var data = {
        "host":      akeeba.Setup.SftpBrowser.params.host,
        "port":      akeeba.Setup.SftpBrowser.params.port,
        "username":  akeeba.Setup.SftpBrowser.params.username,
        "password":  akeeba.Setup.SftpBrowser.params.password,
        "directory": akeeba.Setup.SftpBrowser.params.directory,
        "privkey":   akeeba.Setup.SftpBrowser.params.privKey,
        "pubkey":    akeeba.Setup.SftpBrowser.params.pubKey
    };

    // URL to load the browser
    data.ajaxURL = akeeba.Setup.URLs.sftpBrowser;

    // Do AJAX call & Render results
    akeeba.System.doAjax(
        data,
        function (data)
        {
            var elSFTPBrowserFolderList = document.getElementById("sftpBrowserFolderList");
            var elSFTPCrumbs            = document.getElementById("ak_scrumbs");

            elSFTPBrowserFolderList.style.display = "none";
            elSFTPCrumbs.style.display            = "none";

            if (data.error != false)
            {
                // An error occured
                document.getElementById("sftpBrowserError").innerHTML              = data.error;
                document.getElementById("sftpBrowserErrorContainer").style.display = "block";

                return;
            }

            // Create the interface
            document.getElementById("ftpBrowserErrorContainer").style.display = "none";

            // Display the crumbs
            if (!empty(data.breadcrumbs))
            {
                elSFTPCrumbs.style.display = "block";
                elSFTPCrumbs.innerHTML     = "";
                var relativePath           = "/";

                akeeba.Setup.FtpBrowser.addCrumb(
                    akeeba.System.Text._("SOLO_COMMON_LBL_ROOT"), "/", elSFTPCrumbs, akeeba.Setup.SftpBrowser);

                for (var i = 0; i < data.breadcrumbs.length; i++)
                {
                    var crumb = data.breadcrumbs[i];

                    relativePath += "/" + crumb;

                    akeeba.Setup.FtpBrowser.addCrumb(crumb, relativePath, elSFTPCrumbs, akeeba.Setup.SftpBrowser);
                }
            }

            // Display the list of directories
            if (!empty(data.list))
            {
                elSFTPBrowserFolderList.style.display = "block";

                // If the directory in the browser is empty, let's inject it with the parent dir, otherwise if the user
                // immediately clicks on "Use" gets a wrong path
                if (!akeeba.Solo.SftpBrowser.params.directory)
                {
                    akeeba.Solo.SftpBrowser.params.directory = data.directory;
                }

                for (i = 0; i < data.list.length; i++)
                {
                    var item = data.list[i];

                    akeeba.Setup.FtpBrowser.createLink(
                        data.directory + "/" + item, item, elSFTPBrowserFolderList, akeeba.Configuration.SftpBrowser);
                }
            }
        },
        function (message)
        {
            document.getElementById("sftpBrowserError").innerHTML              = message;
            document.getElementById("sftpBrowserErrorContainer").style.display = "block";
            document.getElementById("sftpBrowserFolderList").style.display     = "none";
            document.getElementById("sftpBrowserCrumbs").style.display         = "none";
        },
        false
    );
};

/**
 * SFTP browser callback, used to set the FTP root directory in an element
 *
 * @param  path  The path returned by the browser
 */
akeeba.Setup.SftpBrowser.callback = function (path)
{
    var charlist = ("/").replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, "$1");
    var re       = new RegExp("^[" + charlist + "]+", "g");
    path         = "/" + (path + "").replace(re, "");

    document.getElementById(akeeba.Setup.SftpBrowser.params.key).value = path;
};

akeeba.Setup.FtpTest.testConnection = function (buttonKey, configKey)
{
    var button                     = document.getElementById(buttonKey);
    akeeba.Setup.FtpTest.buttonKey = buttonKey;

    button.setAttribute("disabled", "disabled");

    var data = {
        host:    document.getElementById(configKey + "_host").value,
        port:    document.getElementById(configKey + "_port").value,
        user:    document.getElementById(configKey + "_username").value,
        pass:    document.getElementById(configKey + "_password").value,
        initdir: document.getElementById(configKey + "_directory").value,
        usessl:  false,
        passive: true
    };

    // Construct the query
    data.ajaxURL = akeeba.Setup.URLs.testFtp;

    akeeba.System.doAjax(
        data,
        function (res)
        {
            var button = document.getElementById(akeeba.Setup.FtpTest.buttonKey);
            button.removeAttribute("disabled");

            var testFtpDialogBodyOk   = document.getElementById("testFtpDialogBodyOk");
            var testFtpDialogBodyFail = document.getElementById("testFtpDialogBodyFail");
            var testFtpDialogLabel    = document.getElementById("testFtpDialogLabel");

            testFtpDialogBodyOk.style.display   = "none";
            testFtpDialogBodyFail.style.display = "none";

            if ((res?.status ?? false) === true)
            {
                testFtpDialogLabel.innerHTML        = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTFTP_TEST_OK");
                testFtpDialogBodyOk.innerHTML       = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTFTP_TEST_FAIL");
                testFtpDialogBodyOk.style.display   = "block";
                testFtpDialogBodyFail.style.display = "none";
            }
            else
            {
                testFtpDialogLabel.innerHTML        = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTFTP_TEST_FAIL");
                testFtpDialogBodyFail.innerHTML     = res?.status ?? '???';
                testFtpDialogBodyOk.style.display   = "none";
                testFtpDialogBodyFail.style.display = "block";
            }

            akeeba.Setup.FtpTest.Modal = akeeba.Modal.open({
                inherit: document.getElementById("testFtpDialog"),
                width:   "80%"
            });
        }, null, false, 15000
    )
};

akeeba.Setup.SftpTest.testConnection = function (buttonKey, configKey)
{
    var button                      = document.getElementById(buttonKey);
    akeeba.Setup.SftpTest.buttonKey = buttonKey;

    button.setAttribute("disabled", "disabled");

    var data = {
        host:    document.getElementById(configKey + "_host").value,
        port:    document.getElementById(configKey + "_port").value,
        user:    document.getElementById(configKey + "_username").value,
        pass:    document.getElementById(configKey + "_password").value,
        initdir: document.getElementById(configKey + "_directory").value,
        privkey: "",
        pubkey:  ""
    };

    // Construct the query
    data.ajaxURL = akeeba.Setup.URLs.testSftp;

    akeeba.System.doAjax(
        data,
        function (res)
        {
            var button = document.getElementById(akeeba.Setup.SftpTest.buttonKey);
            button.removeAttr("disabled");

            var testFtpDialogBodyOk   = document.getElementById("testFtpDialogBodyOk");
            var testFtpDialogBodyFail = document.getElementById("testFtpDialogBodyFail");
            var testFtpDialogLabel    = document.getElementById("testFtpDialogLabel");

            testFtpDialogBodyOk.style.display   = "none";
            testFtpDialogBodyFail.style.display = "none";

            if ((res?.status ?? false) === true)
            {
                testFtpDialogLabel.innerHTML        = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_OK");
                testFtpDialogBodyOk.innerHTML       = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_OK");
                testFtpDialogBodyOk.style.display   = "block";
                testFtpDialogBodyFail.style.display = "none";
            }
            else
            {
                testFtpDialogLabel.innerHTML        = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_FAIL");
                testFtpDialogBodyFail.innerHTML     = res?.status ?? '???';
                testFtpDialogBodyOk.style.display   = "none";
                testFtpDialogBodyFail.style.display = "block";
            }

            akeeba.Setup.FtpTest.Modal = akeeba.Modal.open({
                inherit: document.getElementById("testFtpDialog"),
                width:   "80%"
            });
        }, null, false, 15000
    )
};


akeeba.Setup.initFtpSftpBrowser = function ()
{
    var driver = document.getElementById("fs_driver").value;

    if (driver === "ftp")
    {
        akeeba.Setup.FtpBrowser.initialise("fs_directory", "fs")
    }
    else if (driver === "sftp")
    {
        akeeba.Setup.SftpBrowser.initialise("fs_directory", "fs")
    }

    return false;
};

akeeba.Setup.testFtpSftpConnection = function ()
{
    var driver = document.getElementById("fs_driver").value;

    if (driver === "ftp")
    {
        akeeba.Setup.FtpTest.testConnection("btnFtpTest", "fs");
    }
    else if (driver === "sftp")
    {
        akeeba.Setup.SftpTest.testConnection("btnFtpTest", "fs");
    }

    return false;
};