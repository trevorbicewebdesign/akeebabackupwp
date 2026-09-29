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

if (typeof akeeba.Configuration === "undefined")
{
    akeeba.Configuration                = {};
    akeeba.Configuration.GUI            = {};
    akeeba.Configuration.engines        = {};
    akeeba.Configuration.installers     = {};
    akeeba.Configuration.URLs           = {};
    akeeba.Configuration.FtpBrowser     = {
        params: {}
    };
    akeeba.Configuration.SftpBrowser    = {
        params: {}
    };
    akeeba.Configuration.FtpTest        = {};
    akeeba.Configuration.SftpTest       = {};
    akeeba.Configuration.FtpModal       = null;
    akeeba.Configuration.passwordFields = {};
    akeeba.Configuration.fsBrowser      = {
        params:      {
            dialogId:     "folderBrowserDialog",
            dialogBodyId: "folderBrowserDialogBody"
        },
        modalObject: null
    }
}

/**
 * Parses the JSON decoded data object defining engine and GUI parameters for the
 * configuration page
 *
 * @param  data {Object}  The nested objects of engine and GUI definitions
 * @param  [callback] {function}
 */
akeeba.Configuration.parseConfigData = function (data, callback)
{
    akeeba.Configuration.engines    = data.engines;
    akeeba.Configuration.installers = data.installers;
    akeeba.Configuration.parseGuiData(data.gui);

    if (typeof callback == "function")
    {
        callback();
    }
};

/**
 * Restores the contents of the password fields after brain-dead browsers with broken password managers try to auto-fill
 * the wrong password to the wrong field without warning you or asking you.
 */
akeeba.Configuration.restoreDefaultPasswords = function ()
{
    for (var curid in akeeba.Configuration.passwordFields)
    {
        if (!akeeba.Configuration.passwordFields.hasOwnProperty(curid))
        {
            continue;
        }

        var defvalue = akeeba.Configuration.passwordFields[curid];

        myElement = document.getElementById(curid);

        if (!myElement)
        {
            continue;
        }

        // Do not remove this line. It's required when defvalue is empty. Why? BECAUSE BROWSERS ARE BRAIN DEAD!
        myElement.value = "WORKAROUND FOR NAUGHTY BROWSERS";
        // This line finally sets the fields back to its default value.
        myElement.value = defvalue;
    }
};

/**
 * Opens a filesystem folder browser
 *
 * @param  folder   The folder to start browsing from
 * @param  element  The element whose value we'll modify when this browser returns
 */
akeeba.Configuration.onBrowser = function (folder, element)
{
    // Close dialog callback (user confirmed the new folder)
    akeeba.Configuration.onBrowserCallback = function (myFolder)
    {
        element.value = myFolder;

        if ((typeof akeeba.Configuration.fsBrowser.modalObject === "object") && akeeba.Configuration.fsBrowser.modalObject.close)
        {
            akeeba.Configuration.fsBrowser.modalObject.close()
        }
    };

    // URL to load the browser
    var browserSrc = akeeba.Configuration.URLs["browser"] + encodeURIComponent(folder);

    var dialogBody = document.getElementById(akeeba.Configuration.fsBrowser.params.dialogBodyId);

    dialogBody.innerHTML = "";

    var iFrame = document.createElement("iframe");
    iFrame.setAttribute("src", browserSrc);
    iFrame.setAttribute("width", "100%");
    iFrame.setAttribute("height", 400);
    iFrame.setAttribute("frameborder", 0);
    iFrame.setAttribute("allowtransparency", "true");

    dialogBody.appendChild(iFrame);

    akeeba.Configuration.fsBrowser.modalObject = akeeba.Modal.open({
        inherit: "#" + akeeba.Configuration.fsBrowser.params.dialogId,
        width:   "80%"
    });
};

/**
 * FTP browser callback, used to set the FTP root directory in an element
 *
 * @param  path  The path returned by the browser
 */
akeeba.Configuration.FtpBrowser.callback = function (path)
{
    var charlist = ("/").replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, "$1");
    var re       = new RegExp("^[" + charlist + "]+", "g");
    path         = "/" + (path + "").replace(re, "");

    document.getElementById("var[" + akeeba.Configuration.FtpBrowser.params.key + "]").value = path;
};

/**
 * Initialises an FTP folder browser
 *
 * @param  key        The Akeeba Engine configuration key of the field holding the FTP directory we're outputting
 * @param  paramsKey  The Akeeba Engine configuration key prefix of the fields holding FTP connection information
 */
akeeba.Configuration.FtpBrowser.initialise = function (key, paramsKey)
{
    akeeba.Configuration.FtpBrowser.params.host      = document.getElementById("var[" + paramsKey + ".host]").value;
    akeeba.Configuration.FtpBrowser.params.port      = document.getElementById("var[" + paramsKey + ".port]").value;
    akeeba.Configuration.FtpBrowser.params.username  = document.getElementById("var[" + paramsKey + ".user]").value;
    akeeba.Configuration.FtpBrowser.params.password  = document.getElementById("var[" + paramsKey + ".pass]").value;
    akeeba.Configuration.FtpBrowser.params.passive   =
        document.getElementById("var[" + paramsKey + ".passive_mode]_1").checked;
    akeeba.Configuration.FtpBrowser.params.ssl       = document.getElementById("var[" + paramsKey + ".ftps]_1").checked;
    akeeba.Configuration.FtpBrowser.params.directory =
        document.getElementById("var[" + paramsKey + ".initial_directory]").value;

    akeeba.Configuration.FtpBrowser.params.key = key;

    akeeba.Configuration.FtpBrowser.open();
};

/**
 * Opens the FTP directory browser
 */
akeeba.Configuration.FtpBrowser.open = function ()
{
    var ftp_dialog_element = document.getElementById("ftpdialog");

    akeeba.System.addEventListener(document.getElementById("ftpdialogOkButton"), "click", function (e)
    {
        akeeba.Configuration.FtpBrowser.callback(akeeba.Configuration.FtpBrowser.params.directory);

        if ((typeof akeeba.Configuration.FtpModal === "object") && akeeba.Configuration.FtpModal.close)
        {
            akeeba.Configuration.FtpModal.close();
        }
    });

    akeeba.System.addEventListener(document.getElementById("ftpdialogCancelButton"), "click", function (e)
    {
        if ((typeof akeeba.Configuration.FtpModal === "object") && akeeba.Configuration.FtpModal.close)
        {
            akeeba.Configuration.FtpModal.close();
        }
    });

    akeeba.Configuration.FtpModal = akeeba.System.Modal.open({
        inherit: ftp_dialog_element,
        width:   "80%"
    });

    document.getElementById("ftpBrowserErrorContainer").style.display = "none";
    document.getElementById("ftpBrowserFolderList").innerHTML         = "";
    document.getElementById("ftpBrowserCrumbs").innerHTML             = "";

    if (empty(akeeba.Configuration.FtpBrowser.params.directory))
    {
        akeeba.Configuration.FtpBrowser.params.directory = "";
    }

    var data = {
        "host":      akeeba.Configuration.FtpBrowser.params.host,
        "username":  akeeba.Configuration.FtpBrowser.params.username,
        "password":  akeeba.Configuration.FtpBrowser.params.password,
        "passive":   (akeeba.Configuration.FtpBrowser.params.passive ? 1 : 0),
        "ssl":       (akeeba.Configuration.FtpBrowser.params.ssl ? 1 : 0),
        "directory": akeeba.Configuration.FtpBrowser.params.directory
    };

    // URL to load the browser
    data.ajaxURL = akeeba.Configuration.URLs.ftpBrowser;

    // Do AJAX call & Render results
    akeeba.System.doAjax(
        data,
        function (data)
        {
            var elBreadCrumbs          = document.getElementById("ak_crumbs2");
            var elFTPBrowserFolderList = document.getElementById("ftpBrowserFolderList");

            elFTPBrowserFolderList.style.display = "none";
            elBreadCrumbs.style.display          = "none";

            if (data.error != false)
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

                akeeba.Configuration.FtpBrowser.addCrumb(
                    akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_LABEL_UIROOT"), "/", elBreadCrumbs);

                for (i = 0; i < data.breadcrumbs.length; i++)
                {
                    var crumb = data.breadcrumbs[i];

                    relativePath += "/" + crumb;

                    akeeba.Configuration.FtpBrowser.addCrumb(crumb, relativePath, elBreadCrumbs);
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
                if (!akeeba.Configuration.FtpBrowser.params.directory)
                {
                    akeeba.Configuration.FtpBrowser.params.directory = data.directory;
                }

                for (i = 0; i < data.list.length; i++)
                {
                    var item = data.list[i];

                    akeeba.Configuration.FtpBrowser.createLink(
                        data.directory + "/" + item, item, elFTPBrowserFolderList);
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
 * @param  ftpObject  The object which contains the FTP browser methods
 */
akeeba.Configuration.FtpBrowser.createLink = function (path, label, container, ftpObject)
{
    if (typeof ftpObject === "undefined")
    {
        ftpObject = akeeba.Configuration.FtpBrowser;
    }

    var row  = document.createElement("tr");
    var cell = document.createElement("td");
    row.appendChild(cell);

    var myElement         = document.createElement("a");
    myElement.textContent = label;
    akeeba.System.addEventListener(myElement, "click", function ()
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
 * @param  ftpObject  The object which contains the FTP browser methods
 */
akeeba.Configuration.FtpBrowser.addCrumb = function (crumb, relativePath, container, ftpObject)
{
    if (typeof ftpObject === "undefined")
    {
        ftpObject = akeeba.Configuration.FtpBrowser;
    }

    var li = document.createElement("li");

    var myLink         = document.createElement("a");
    myLink.textContent = crumb;
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
akeeba.Configuration.SftpBrowser.initialise = function (key, paramsKey)
{
    akeeba.Configuration.SftpBrowser.params.host      = document.getElementById("var[" + paramsKey + ".host]").value;
    akeeba.Configuration.SftpBrowser.params.port      = document.getElementById("var[" + paramsKey + ".port]").value;
    akeeba.Configuration.SftpBrowser.params.username  = document.getElementById("var[" + paramsKey + ".user]").value;
    akeeba.Configuration.SftpBrowser.params.password  = document.getElementById("var[" + paramsKey + ".pass]").value;
    akeeba.Configuration.SftpBrowser.params.directory =
        document.getElementById("var[" + paramsKey + ".initial_directory]").value;
    akeeba.Configuration.SftpBrowser.params.privKey   = document.getElementById("var[" + paramsKey + ".privkey]").value;
    akeeba.Configuration.SftpBrowser.params.pubKey    = document.getElementById("var[" + paramsKey + ".pubkey]").value;

    akeeba.Configuration.SftpBrowser.params.key = key;

    akeeba.Configuration.SftpBrowser.open();
};

/**
 * Opens the SFTP directory browser
 */
akeeba.Configuration.SftpBrowser.open = function ()
{
    var ftp_dialog_element = document.getElementById("sftpdialog");

    ftp_dialog_element.style.display = "block";

    akeeba.System.addEventListener(document.getElementById("sftpdialogOkButton"), "click", function (e)
    {
        akeeba.Configuration.SftpBrowser.callback(akeeba.Configuration.SftpBrowser.params.directory);

        if ((typeof akeeba.Configuration.FtpModal === "object") && akeeba.Configuration.FtpModal.close)
        {
            akeeba.Configuration.FtpModal.close();
        }
    });

    akeeba.System.addEventListener(document.getElementById("sftpdialogCancelButton"), "click", function (e)
    {
        if ((typeof akeeba.Configuration.FtpModal === "object") && akeeba.Configuration.FtpModal.close)
        {
            akeeba.Configuration.FtpModal.close();
        }
    });

    akeeba.Configuration.FtpModal = akeeba.System.Modal.open({
        inherit: ftp_dialog_element,
        width:   "80%"
    });

    document.getElementById("sftpBrowserErrorContainer").style.display = "none";
    document.getElementById("sftpBrowserFolderList").innerHTML         = "";
    document.getElementById("sftpBrowserCrumbs").innerHTML             = "";

    if (empty(akeeba.Configuration.SftpBrowser.params.directory))
    {
        akeeba.Configuration.SftpBrowser.params.directory = "";
    }

    var data = {
        "host":      akeeba.Configuration.SftpBrowser.params.host,
        "port":      akeeba.Configuration.SftpBrowser.params.port,
        "username":  akeeba.Configuration.SftpBrowser.params.username,
        "password":  akeeba.Configuration.SftpBrowser.params.password,
        "directory": akeeba.Configuration.SftpBrowser.params.directory,
        "privkey":   akeeba.Configuration.SftpBrowser.params.privKey,
        "pubkey":    akeeba.Configuration.SftpBrowser.params.pubKey
    };

    // URL to load the browser
    data.ajaxURL = akeeba.Configuration.URLs.sftpBrowser;

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

                akeeba.Configuration.FtpBrowser.addCrumb(
                    akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_LABEL_UIROOT"), "/", elSFTPCrumbs,
                    akeeba.Configuration.SftpBrowser
                );

                for (i = 0; i < data.breadcrumbs.length; i++)
                {
                    var crumb = data.breadcrumbs[i];

                    relativePath += "/" + crumb;

                    akeeba.Configuration.FtpBrowser.addCrumb(
                        crumb, relativePath, elSFTPCrumbs, akeeba.Configuration.SftpBrowser);
                }
            }

            // Display the list of directories
            if (!empty(data.list))
            {
                elSFTPBrowserFolderList.style.display = "block";

                // If the directory in the browser is empty, let's inject it with the parent dir, otherwise if the user
                // immediately clicks on "Use" gets a wrong path
                if (!akeeba.Configuration.SftpBrowser.params.directory)
                {
                    akeeba.Configuration.SftpBrowser.params.directory = data.directory;
                }

                for (i = 0; i < data.list.length; i++)
                {
                    var item = data.list[i];

                    akeeba.Configuration.FtpBrowser.createLink(
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
akeeba.Configuration.SftpBrowser.callback = function (path)
{
    var charlist = ("/").replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, "$1");
    var re       = new RegExp("^[" + charlist + "]+", "g");
    path         = "/" + (path + "").replace(re, "");

    document.getElementById("var[" + akeeba.Configuration.SftpBrowser.params.key + "]").value = path;
};

akeeba.Configuration.FtpTest.testConnection = function (buttonKey, configKey, isCurl)
{
    var button                             = document.getElementById("var[" + buttonKey + "]");
    akeeba.Configuration.FtpTest.buttonKey = "var[" + buttonKey + "]";

    if (button === null)
    {
        button                                 = document.getElementById(buttonKey);
        akeeba.Configuration.FtpTest.buttonKey = buttonKey;
    }

    if (button === null)
    {
        console.warn("Button " + akeeba.Configuration.FtpTest.buttonKey + " not found");
    }

    button.setAttribute("disabled", "disabled");

    var data = {};

    try
    {
        data = {
            isCurl:                  (isCurl ? 1 : 0),
            host:                    document.getElementById("var[" + configKey + ".host]").value,
            port:                    document.getElementById("var[" + configKey + ".port]").value,
            user:                    document.getElementById("var[" + configKey + ".user]").value,
            pass:                    document.getElementById("var[" + configKey + ".pass]").value,
            initdir:                 document.getElementById("var[" + configKey + ".initial_directory]").value,
            usessl:                  document.getElementById("var[" + configKey + ".ftps]_1").checked ? 1 : 0,
            passive:                 document.getElementById("var[" + configKey + ".passive_mode]_1").checked ? 1 : 0,
            passive_mode_workaround: 0
        };
    }
    catch (e)
    {
        data = {
            isCurl:                  (isCurl ? 1 : 0),
            host:                    document.getElementById(configKey + "_host").value,
            port:                    document.getElementById(configKey + "_port").value,
            user:                    document.getElementById(configKey + "_user").value,
            pass:                    document.getElementById(configKey + "_pass").value,
            initdir:                 document.getElementById(configKey + "_initial_directory").value,
            usessl:                  document.getElementById(configKey + "_ftps").checked ? 1 : 0,
            passive:                 document.getElementById(configKey + "_passive_mode").checked ? 1 : 0,
            passive_mode_workaround: 0
        };
    }

    // The passive_mode_workaround input is only defined for cURL
    if (isCurl)
    {
        try
        {
            data.passive_mode_workaround =
                document.getElementById("var[" + configKey + ".passive_mode_workaround]_1").checked;
        }
        catch (e)
        {
            data.passive_mode_workaround =
                document.getElementById(configKey + "_passive_mode_workaround").checked;
        }
    }

    // Construct the query
    data.ajaxURL = akeeba.Configuration.URLs.testFtp;

    akeeba.System.doAjax(
        data,
        function (res)
        {
            var button = document.getElementById(akeeba.Configuration.FtpTest.buttonKey);
            button.removeAttribute("disabled");

            var elTestFTPBodyOK   = document.getElementById("testFtpDialogBodyOk");
            var elTestFTPBodyFail = document.getElementById("testFtpDialogBodyFail");
            var elTestFTPLabel    = document.getElementById("testFtpDialogLabel");

            elTestFTPBodyOK.style.display   = "none";
            elTestFTPBodyFail.style.display = "none";

            if ((res?.status ?? false) === true)
            {
                elTestFTPLabel.textContent      = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTFTP_TEST_OK");
                elTestFTPBodyOK.textContent     = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTFTP_TEST_OK");
                elTestFTPBodyOK.style.display   = "block";
                elTestFTPBodyFail.style.display = "none";
            }
            else
            {
                elTestFTPLabel.textContent      = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTFTP_TEST_FAIL");
                elTestFTPBodyFail.textContent   = res?.status ?? '???';
                elTestFTPBodyOK.style.display   = "none";
                elTestFTPBodyFail.style.display = "block";
            }

            akeeba.Modal.open({
                inherit: "#testFtpDialog",
                width:   "80%"
            });
        }, null, false, 15000
    )
};

akeeba.Configuration.SftpTest.testConnection = function (buttonKey, configKey, isCurl)
{
    var button                              = document.getElementById("var[" + buttonKey + "]");
    akeeba.Configuration.SftpTest.buttonKey = "var[" + buttonKey + "]";

    button.setAttribute("disabled", "disabled");

    var data = {
        isCurl:  (isCurl ? 1 : 0),
        host:    document.getElementById("var[" + configKey + ".host]").value,
        port:    document.getElementById("var[" + configKey + ".port]").value,
        user:    document.getElementById("var[" + configKey + ".user]").value,
        pass:    document.getElementById("var[" + configKey + ".pass]").value,
        initdir: document.getElementById("var[" + configKey + ".initial_directory]").value,
        privkey: document.getElementById("var[" + configKey + ".privkey]").value,
        pubkey:  document.getElementById("var[" + configKey + ".pubkey]").value
    };

    // Construct the query
    data.ajaxURL = akeeba.Configuration.URLs.testSftp;

    akeeba.System.doAjax(
        data,
        function (res)
        {
            var button = document.getElementById(akeeba.Configuration.SftpTest.buttonKey);
            button.removeAttribute("disabled");

            var elTestFTPBodyOK   = document.getElementById("testFtpDialogBodyOk");
            var elTestFTPBodyFail = document.getElementById("testFtpDialogBodyFail");
            var elTestFTPLabel    = document.getElementById("testFtpDialogLabel");

            elTestFTPBodyOK.style.display   = "none";
            elTestFTPBodyFail.style.display = "none";

            if ((res?.status ?? false) === true)
            {
                elTestFTPLabel.textContent      = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_OK");
                elTestFTPBodyOK.textContent     = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_OK");
                elTestFTPBodyOK.style.display   = "block";
                elTestFTPBodyFail.style.display = "none";
            }
            else
            {
                elTestFTPLabel.textContent      = akeeba.System.Text._("COM_AKEEBA_CONFIG_DIRECTSFTP_TEST_FAIL");
                elTestFTPBodyFail.textContent   = res?.status ?? '???';
                elTestFTPBodyOK.style.display   = "none";
                elTestFTPBodyFail.style.display = "block";
            }

            akeeba.Modal.open({
                inherit: "#testFtpDialog",
                width:   "80%"
            });
        }, null, false, 15000
    )
};

akeeba.Configuration.enablePopoverFor = function (el)
{
    if ((typeof el == "object") && NodeList.prototype.isPrototypeOf(el))
    {
        for (i = 0; i < el.length; i++)
        {
            var e = el[i];

            akeeba.Configuration.enablePopoverFor(e);
        }

        return;
    }

    akeeba.Tooltip.enableFor(el);
};

/**
 * Parses the main configuration GUI definition, generating the on-page widgets
 *
 * @param  data      The nested objects of the GUI definition ('gui' key of JSON data)
 * @param  rootnode  The jroot DOM element in which to create the widgets
 */
akeeba.Configuration.parseGuiData = function (data, rootnode)
{
    if (rootnode == null)
    {
        // The default root node is the form itself
        rootnode = document.getElementById("akeebagui");
    }

    // Begin by slashing contents of the akeebagui DIV
    rootnode.innerHTML = "";

    // This is the workhorse, looping through groupdefs and creating HTML elements
    var group_id = 0;

    for (var headertext in data)
    {
        if (!data.hasOwnProperty(headertext))
        {
            continue;
        }

        var groupdef = data[headertext];

        // Loop for each group definition
        group_id++;

        if (empty(groupdef))
        {
            continue;
        }

        // Create a container for the group
        var container       = document.createElement("div");
        container.className = "akeeba-panel--info";

        rootnode.appendChild(container);

        // Create a group header
        var header            = document.createElement("header");
        header.id             = "auigrp_" + rootnode.id + "_" + group_id;
        header.className      = "akeeba-block-header";
        var headerInner       = document.createElement("h5");
        headerInner.innerHTML = headertext;
        header.appendChild(headerInner);

        container.appendChild(header);

        // Loop each element
        for (var config_key in groupdef)
        {
            if (!groupdef.hasOwnProperty(config_key))
            {
                continue;
            }

            var defdata = groupdef[config_key];

            // Parameter ID
            var current_id = "var[" + config_key + "]";

            // Option row DIV
            var showOn        = defdata['showon'] ?? null;
            var row_div       = document.createElement("div");
            row_div.className = "akeeba-ui-optionrow akeeba-form-group";
            row_div.id        = "akconfigrow." + config_key;

            if (showOn)
            {
                row_div.dataset.showon = JSON.stringify(showOn);
            }
            /**
             * We must append the option row to the container only if the option type is NOT 'hidden' or 'none'.
             * These two option types are non-GUI elements. We only render a hidden field for them. The hidden field
             * is rendered without a row container so that we don't create an empty row in the interface.
             */
            if ((defdata["type"] !== "hidden") && (defdata["type"] !== "none"))
            {
                container.appendChild(row_div);
            }

            // Render the label, if applicable
            akeeba.Configuration.GUI.renderOptionLabel(current_id, defdata, row_div);

            // Create GUI representation based on type
            var controlWrapper       = document.createElement("div");
            controlWrapper.className = "akeeba-form-controls";

            var ucfirstType  = defdata["type"][0].toUpperCase() + defdata["type"].slice(1);
            var renderMethod = "renderOptionType" + ucfirstType;

            if (typeof akeeba.Configuration.GUI[renderMethod] === "function")
            {
                akeeba.Configuration.GUI[renderMethod](current_id, defdata, controlWrapper, row_div, container);
            }
            else
            {
                akeeba.Configuration.GUI.renderOptionTypeUnknown(
                    current_id, defdata, controlWrapper, row_div, container);
            }
        }
    }

    // Re-initialise the ShowOn JavaScript
    akeeba.Showon.initialise(rootnode);

    // Enable popovers
    akeeba.Configuration.enablePopoverFor(rootnode.querySelectorAll("[rel=\"akeeba-sticky-tooltip\"]"));
};

/**
 * Renders the label of a configuration option, appending it to the container element
 *
 * @param   {string}   current_id  The input name, e.g. var[something.or.another]
 * @param   {Object}   defdata     The option definition data
 * @param   {Element}  row_div    The element which contains the option itself (the DIV of the current row)
 */
akeeba.Configuration.GUI.renderOptionLabel = function (current_id, defdata, row_div)
{
    const noLabel = (defdata['nolabel'] ?? 0) != 0;

    // No interface is rendered for 'hidden' and 'none' option types
    if (noLabel || (defdata["type"] == "hidden") || (defdata["type"] == "none"))
    {
        return;
    }

    // Create label
    var label       = document.createElement("label");
    label.className = "akeeba-control-label";
    label.setAttribute("for", current_id);
    label.innerHTML = defdata["title"];

    if (defdata["description"])
    {
        label.setAttribute("rel", "akeeba-sticky-tooltip");
        label.setAttribute("data-original-title", defdata["title"]);
        label.setAttribute("data-content", defdata["description"]);
    }

    if (defdata["bold"])
    {
        label.style.fontWeight = "bold";
    }

    row_div.appendChild(label);
};

/**
 * Renders an option of type "none". A do-not-display field. It doesn't render any input element at all.
 *
 * @param   {string}  current_id       The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata          The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeNone = function (current_id, defdata, controlWrapper, row_div, container)
{
    // Nothing to render
};

/**
 * Renders an option of type "hidden". A hidden field.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeHidden = function (current_id, defdata, controlWrapper, row_div, container)
{
    var hiddenfield = document.createElement("input");
    hiddenfield.id  = current_id;
    hiddenfield.setAttribute("type", "hidden");
    hiddenfield.setAttribute("name", current_id);
    hiddenfield.value = defdata["default"];

    container.appendChild(hiddenfield);
};

/**
 * Renders an option of type "separator". A GUI row separator.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeSeparator = function (current_id, defdata, controlWrapper, row_div, container)
{
    const element   = defdata["element"] ?? "div";
    const content   = defdata["content"] ?? null;
    const separator = document.createElement(element);

    if (content)
    {
        separator.innerHTML = content;
    }

    separator.className = "akeeba_ui_separator";
    container.appendChild(separator);
};

/**
 * Renders an option of type "installer". An installer selection.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeInstaller = function (current_id, defdata, controlWrapper, row_div, container)
{
    // Create the select element
    var editor       = document.createElement("select");
    editor.className = "akeeba-configuration-select-installer";
    editor.id        = current_id;
    editor.setAttribute("name", current_id);

    for (key in akeeba.Configuration.installers)
    {
        if (!akeeba.Configuration.installers.hasOwnProperty(key))
        {
            continue;
        }

        var element = akeeba.Configuration.installers[key];

        var option       = document.createElement("option");
        option.value     = key;
        option.innerHTML = element.name;

        if (defdata["default"] == key)
        {
            option.setAttribute("selected", 1);
        }

        editor.appendChild(option);
    }

    controlWrapper.appendChild(editor);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of type "engine". An engine selection.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeEngine = function (current_id, defdata, controlWrapper, row_div, container)
{
    var engine_type = defdata["subtype"];

    if (akeeba.Configuration.engines[engine_type] == null)
    {
        return;
    }

    var config_key = current_id.substr(4, current_id.length - 5);

    // Container for engine parameters, initially hidden
    var engine_config_container       = document.createElement("div");
    engine_config_container.id        = config_key + "_config";
    //engine_config_container.className = "akeeba-hidden-mobile akeeba-hidden-desktop akeeba-engine-options";
    engine_config_container.className = "akeeba-engine-options";

    // Create the select element
    var editor = document.createElement("select");
    editor.id  = current_id;
    editor.setAttribute("name", current_id);

    var engineOptions = akeeba.Configuration.engines[engine_type];

    for (var key in engineOptions)
    {
        if (!engineOptions.hasOwnProperty(key))
        {
            continue;
        }

        var element = engineOptions[key];

        var option       = document.createElement("option");
        option.value     = key;
        option.innerHTML = element.information.title;

        if (defdata["default"] == key)
        {
            option.setAttribute("selected", "selected");
        }

        editor.appendChild(option);
    }

    akeeba.System.addEventListener(editor, "change", function (e)
    {
        // When the selection changes, we have to repopulate the config container
        // First, save any changed values
        var old_values = {};

        var allElements = [
            document.getElementById(config_key + "_config").querySelectorAll("input"),
            document.getElementById(config_key + "_config").querySelectorAll("select")
        ];

        var allInputs = null;
        var input     = null;
        var id        = null;

        for (i = 0; i < allElements.length; i++)
        {
            allInputs = allElements[i];

            if (!allInputs.length)
            {
                continue;
            }

            for (j = 0; j < allInputs.length; j++)
            {
                input = allInputs[j];
                id    = input.id;

                old_values[id] = input.value;

                if (input.getAttribute("type") == "checkbox")
                {
                    old_values[id] = input.checked;
                }
                else if (input.getAttribute("type") == "select")
                {
                    old_values[id] = input.options[input.selectedIndex].value;
                }
            }

        }

        // Create the new interface
        var new_engine        = editor.value;
        var enginedef         = akeeba.Configuration.engines[engine_type][new_engine];
        var enginetitle       = enginedef.information.title;
        var new_data          = {};
        new_data[enginetitle] = enginedef.parameters;

        akeeba.Configuration.parseGuiData(new_data, engine_config_container);

        var elLegend = engine_config_container.querySelector("header");
        if (elLegend instanceof Element)
        {
            elLegend.insertAdjacentHTML(
                "afterend", "<p class=\"akeeba-block--information\">" + enginedef.information.description + "</p>");
        }

        // Reapply changed values
        allElements = [
            document.getElementById(config_key + "_config").querySelectorAll("input"),
            document.getElementById(config_key + "_config").querySelectorAll("select")
        ];

        for (i = 0; i < allElements.length; i++)
        {
            allInputs = allElements[i];

            if (!allInputs.length)
            {
                continue;
            }

            for (j = 0; j < allInputs.length; j++)
            {
                input = allInputs[j];
                id    = input.id;

                var old = old_values[id];

                if ((old == null) || (old == undefined))
                {
                    continue;
                }

                if (input.getAttribute("type") == "checkbox")
                {
                    if (old)
                    {
                        input.setAttribute("checked", "checked");
                    }
                    else
                    {
                        input.removeAttribute("checked");
                    }
                }
                else
                {
                    input.value = old;
                }

                // Trigger the change event for drop-downs
                if (i == 1)
                {
                    akeeba.System.triggerEvent(input, "change");
                }
            }
        }

        // Finally, run the activation_callback
        if (typeof enginedef.information.activation_callback !== "undefined")
        {
            window[enginedef.information.activation_callback](enginedef.parameters);
        }
    });

    // Add a configuration show/hide button
    var button       = document.createElement("button");
    button.className = "akeeba-btn--small--dark";

    var icon       = document.createElement("span");
    icon.className = "akion-wrench";
    button.appendChild(icon);

    var btnText       = document.createElement("span");
    btnText.innerHTML = akeeba.System.Text._("COM_AKEEBA_CONFIG_UI_CONFIG");
    button.appendChild(btnText);

    akeeba.System.addEventListener(button, "click", function (e)
    {
        akeeba.System.toggleClass(engine_config_container, "akeeba-hidden-mobile");
        akeeba.System.toggleClass(engine_config_container, "akeeba-hidden-desktop");

        if (e.preventDefault)
        {
            e.preventDefault();
        }
        else
        {
            e.returnValue = false;
        }
    });

    var spacerSpan       = document.createElement("span");
    spacerSpan.innerHTML = "&nbsp;";

    controlWrapper.appendChild(editor);
    //controlWrapper.appendChild(spacerSpan);
    //controlWrapper.appendChild(button);
    controlWrapper.appendChild(engine_config_container);

    row_div.appendChild(controlWrapper);

    // Populate config container with the default engine data
    if (akeeba.Configuration.engines[engine_type][defdata["default"]] != null)
    {
        var new_engine        = defdata["default"];
        var enginedef         = akeeba.Configuration.engines[engine_type][new_engine];
        var enginetitle       = enginedef.information.title;
        var new_data          = {};
        new_data[enginetitle] = enginedef.parameters;

        // Is it a protected field?
        if (defdata["protected"] != 0)
        {
            var titleSpan         = document.createElement("span");
            titleSpan.textContent = enginetitle;
            //span.appendChild(titleSpan);
            editor.style.display  = "none";
        }

        akeeba.Configuration.parseGuiData(new_data, engine_config_container);

        var elLegend = engine_config_container.querySelector("header");
        if (elLegend instanceof Element)
        {
            elLegend.insertAdjacentHTML(
                "afterend", "<p class=\"akeeba-block--information\">" + enginedef.information.description + "</p>");
        }

        // Finally, run the activation_callback
        if (typeof enginedef.information.activation_callback !== "undefined")
        {
            window[enginedef.information.activation_callback](enginedef.parameters);
        }
    }
};

/**
 * Renders an option of type "browsedir". A text box with an option to launch a browser.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeBrowsedir = function (current_id, defdata, controlWrapper, row_div, container)
{
    var editor = document.createElement("input");
    editor.setAttribute("type", "text");
    editor.setAttribute("name", current_id);
    editor.setAttribute("size", "30");
    editor.value = defdata["default"];

    var button       = document.createElement("button");
    button.className = "akeeba-btn--grey";
    button.setAttribute("title", akeeba.System.Text._("COM_AKEEBA_CONFIG_UI_BROWSE"));

    var icon       = document.createElement("span");
    icon.className = "akion-folder";
    button.appendChild(icon);

    akeeba.System.addEventListener(button, "click", function (e)
    {
        if (e.preventDefault)
        {
            e.preventDefault();
        }
        else
        {
            e.returnValue = false;
        }

        if (akeeba.Configuration.onBrowser != null)
        {
            akeeba.Configuration.onBrowser(editor.value, editor);
        }

        return false;
    });

    var containerDiv       = document.createElement("div");
    containerDiv.className = "akeeba-input-group";
    containerDiv.id    = current_id;

    var buttonContainer       = document.createElement("span");
    buttonContainer.className = "akeeba-input-group-btn";

    containerDiv.appendChild(editor);
    buttonContainer.appendChild(button);
    containerDiv.appendChild(buttonContainer);
    controlWrapper.appendChild(containerDiv);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of type "enum". A drop-down list.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeEnum = function (current_id, defdata, controlWrapper, row_div, container)
{
    var editor       = document.createElement("select");
    editor.className = "akeeba-configuration-select-enum";
    editor.id        = current_id;
    editor.setAttribute("name", current_id);

    // Create and append options
    var enumvalues = defdata["enumvalues"].split("|");
    var enumkeys   = defdata["enumkeys"].split("|");

    for (counter = 0; counter < enumvalues.length; counter++)
    {
        var value = enumvalues[counter];

        var item_description = enumkeys[counter];
        var option           = document.createElement("option");
        option.value         = value;
        option.innerHTML     = item_description;

        if (value == defdata["default"])
        {
            option.setAttribute("selected", "selected");
        }

        editor.appendChild(option);
    }

    if (typeof defdata["onchange"] !== "undefined")
    {
        akeeba.System.addEventListener(editor, "change", function ()
        {
            var callback_onchange = defdata["onchange"];
            callback_onchange(editor);
        });
    }

    controlWrapper.appendChild(editor);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of type "string". A simple single-line, unvalidated text box.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeString = function (current_id, defdata, controlWrapper, row_div, container)
{
    var editor       = document.createElement("input");
    editor.className = "akeeba-configuration-string";
    editor.setAttribute("type", "text");
    editor.id = current_id;
    editor.setAttribute("name", current_id);
    editor.setAttribute("size", 40);
    editor.value = defdata["default"];

    controlWrapper.appendChild(editor);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of type "password". A simple single-line, unvalidated password box.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypePassword = function (current_id, defdata, controlWrapper, row_div, container)
{
    akeeba.Configuration.passwordFields[current_id] = defdata["default"];

    var editor       = document.createElement("input");
    editor.className = "akeeba-configuration-password";
    editor.setAttribute("type", "password");
    editor.id = current_id;
    editor.setAttribute("name", current_id);
    editor.setAttribute("size", 40);
    editor.value = defdata["default"];
    editor.setAttribute("autocomplete", "off");

    controlWrapper.appendChild(editor);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of type "integer". Hidden form element with the real value.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeInteger = function (current_id, defdata, controlWrapper, row_div, container)
{
    var config_key = current_id.substr(4, current_id.length - 5);

    // Hidden input field. Holds the actual value saved to the configuration.
    var elHiddenInput = document.createElement("input");
    elHiddenInput.id  = config_key;
    elHiddenInput.setAttribute("name", current_id);
    elHiddenInput.setAttribute("type", "hidden");
    elHiddenInput.value = defdata["default"];

    // Custom value input box
    var elCustomValue = document.createElement("input");

    elCustomValue.setAttribute("type", "text");
    elCustomValue.setAttribute("size", "10");
    elCustomValue.id            = config_key + "_custom";
    elCustomValue.style.display = "none";
    //elCustomValue.style.marginLeft = '6px';
    elCustomValue.className     = "akeeba-form-input-mini";

    akeeba.System.addEventListener(elCustomValue, "blur", function ()
    {
        var value = parseFloat(elCustomValue.value);
        value     = value * defdata["scale"];

        if (value < defdata["min"])
        {
            value = defdata["min"];
        }
        else if (value > defdata["max"])
        {
            value = defdata["max"];
        }

        elHiddenInput.value = value;

        var newValue = value / defdata["scale"];

        elCustomValue.value = newValue.toFixed(2);
    });

    // Select element with preset options
    var elDropdown = document.createElement("select");
    elDropdown.id  = config_key + "_dropdown";
    elDropdown.setAttribute("name", config_key + "_dropdown");
    elDropdown.className = "akeeba-form-input-small";

    // Create and append the preset options to the select element
    var enumvalues     = defdata["shortcuts"].split("|");
    var quantizer      = defdata["scale"];
    var isPresetOption = false;

    for (counter = 0; counter < enumvalues.length; counter++)
    {
        var value = enumvalues[counter];

        var item_description       = value / quantizer;
        var elDropdownOption       = document.createElement("option");
        elDropdownOption.value     = value;
        elDropdownOption.innerHTML = item_description.toFixed(2);

        if (value == defdata["default"])
        {
            elDropdownOption.setAttribute("selected", "selected");
            isPresetOption = true;
        }

        elDropdown.appendChild(elDropdownOption);
    }

    // Create one last option called "Custom"
    var option       = document.createElement("option");
    option.value     = -1;
    // TODO Translate this text
    option.innerHTML = "Custom...";

    if (!isPresetOption)
    {
        option.setAttribute("selected", "selected");
        elCustomValue.value         = (defdata["default"] / defdata["scale"]).toFixed(2);
        elCustomValue.style.display = "inline-block";
    }

    elDropdown.appendChild(option);

    // Add actions to the dropdown
    akeeba.System.addEventListener(elDropdown, "change", function ()
    {
        var value = elDropdown.value;

        if (value == -1)
        {
            elCustomValue.value         = (defdata["default"] / defdata["scale"]).toFixed(2);
            elCustomValue.style.display = "inline-block";
            akeeba.System.triggerEvent(elCustomValue, "focus");

            return;
        }

        elHiddenInput.value         = value;
        elCustomValue.style.display = "none";
    });

    // Label
    var uom = defdata["uom"];

    if ((typeof (uom) != "string") || empty(uom))
    {
        uom = "";

        controlWrapper.appendChild(elDropdown);
        controlWrapper.appendChild(elCustomValue);
    }
    else
    {
        var inputAppendWrapper       = document.createElement("div");
        inputAppendWrapper.className = "akeeba-input-group--small";

        var label         = document.createElement("span");
        //akeeba.System.addClass(label, 'add-on');
        label.textContent = " " + uom;

        inputAppendWrapper.appendChild(elDropdown);
        inputAppendWrapper.appendChild(elCustomValue);
        inputAppendWrapper.appendChild(label);
        controlWrapper.appendChild(inputAppendWrapper);
    }

    controlWrapper.appendChild(elHiddenInput);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of type "bool". A toggle button.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeBool = function (current_id, defdata, controlWrapper, row_div, container)
{
    var wrap_div       = document.createElement("div");
    wrap_div.className = "akeeba-toggle";
    wrap_div.id        = current_id;

    var elYesInput = document.createElement('input');
    elYesInput.id = current_id + '_1';
    elYesInput.type = 'radio';
    elYesInput.name = current_id;
    elYesInput.value = "1";

    var elYesLabel = document.createElement('label');
    elYesLabel.setAttribute('for', current_id + '_1');
    elYesLabel.classList.add('green');
    elYesLabel.innerText = akeeba.System.Text._('SOLO_YES');

    var elNoInput = document.createElement('input');
    elNoInput.id = current_id + '_0';
    elNoInput.type = 'radio';
    elNoInput.name = current_id;
    elNoInput.value = "0";

    var elNoLabel = document.createElement('label');
    elNoLabel.setAttribute('for', current_id + '_0');
    elNoLabel.classList.add('red');
    elNoLabel.innerText = akeeba.System.Text._('SOLO_NO');

    if (defdata["default"] != 0)
    {
        elYesInput.setAttribute("checked", "checked");
    }
    else
    {
        elNoInput.setAttribute("checked", "checked");
    }

    wrap_div.appendChild(elYesInput);
    wrap_div.appendChild(elYesLabel);
    wrap_div.appendChild(elNoInput);
    wrap_div.appendChild(elNoLabel);

    controlWrapper.appendChild(wrap_div);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of type "button". Button with a custom hook function.
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeButton = function (current_id, defdata, controlWrapper, row_div, container)
{
    // Create the button
    var label        = row_div.querySelector("label");
    var hook         = defdata["hook"];
    var labeltext    = label.innerHTML;
    var editor       = document.createElement("button");
    editor.id        = current_id;
    editor.innerHTML = labeltext;
    editor.className = "akeeba-btn--grey";
    label.innerHTML  = "&nbsp;";

    akeeba.System.addEventListener(editor, "click", function (e)
    {
        if (e.preventDefault)
        {
            e.preventDefault();
        }
        else
        {
            e.returnValue = false;
        }

        try
        {
            window[hook]();
        }
        catch (err)
        {
        }
    });

    controlWrapper.appendChild(editor);
    row_div.appendChild(controlWrapper);
};

/**
 * Renders an option of an unknown type (an extension is used).
 *
 * @param   {string}  current_id      The input name, e.g. var[something.or.another]
 * @param   {Object}  defdata         The option definition data+
 * @param   {Element} controlWrapper  The element which contains the option's input object
 * @param   {Element} row_div         The element which contains the option itself (the DIV of the current row)
 * @param   {Element} container       The element which contains the row_div (option group container)
 */
akeeba.Configuration.GUI.renderOptionTypeUnknown = function (current_id, defdata, controlWrapper, row_div, container)
{
    var config_key = current_id.substr(4, current_id.length - 5);

    var method = "akeeba_render_" + defdata["type"];
    var fn     = window[method];

    if (typeof fn == "function")
    {
        fn(config_key, defdata, label, row_div);
    }
    else
    {
        try
        {
            window[method](config_key, defdata, label, row_div);
        }
        catch (e)
        {

        }
    }
};

akeeba.Configuration.onChangeScriptType = function (selectElement)
{
    // Currently selected value
    var value             = selectElement.options[selectElement.selectedIndex].value;
    var possibleInstaller = (value === "joomla") ? "angie" : ("angie-" + value);

    // All possible installers
    var installerSelect   = document.getElementById("var[akeeba.advanced.embedded_installer]");
    var installerElements = installerSelect.children;

    for (var i = 0; i < installerElements.length; i++)
    {
        var element = installerElements[i];

        if (element.value === possibleInstaller)
        {
            installerSelect.value = possibleInstaller;

            return;
        }
    }
};

akeeba.Configuration.onChangeDbDriver = function ()
{
    var myVal      = this.value;
    var elHost     = document.getElementById("akconfigrow.akeeba.platform.dbhost");
    var elPort     = document.getElementById("akconfigrow.akeeba.platform.dbport");
    var elUsername = document.getElementById("akconfigrow.akeeba.platform.dbusername");
    var elPassword = document.getElementById("akconfigrow.akeeba.platform.dbpassword");
    var elPrefix   = document.getElementById("akconfigrow.akeeba.platform.dbprefix");
    var elName     = document.getElementById("akconfigrow.akeeba.platform.dbname");

    elHost.style.display     = "grid";
    elPort.style.display     = "grid";
    elUsername.style.display = "grid";
    elPassword.style.display = "grid";
    elPrefix.style.display   = "grid";
    elName.style.display     = "grid";

    if ((myVal == "sqlite") || (myVal == "none"))
    {
        elHost.style.display     = "none";
        elPort.style.display     = "none";
        elUsername.style.display = "none";
        elPassword.style.display = "none";
        elPrefix.style.display   = "none";

        elHost.value     = "";
        elPort.value     = "";
        elUsername.value = "";
        elPassword.value = "";
        elPrefix.value   = "";
    }

    if (myVal == "none")
    {
        elName.value         = "";
        elName.style.display = "none";
    }
}

akeeba.Configuration.onFormSubmit = function(event) {
    const adminForm = document.getElementById("adminForm");

    // Old browsers: submit the form with 100+ parameters.
    if (
        !adminForm || typeof window.FormData === "undefined"
        || typeof Object.fromEntries === "undefined" || typeof JSON.stringify === "undefined"
    )
    {
        return true;
    }

    // Newer browsers: submit as a single JSON document
    event.preventDefault();

    akeeba.Configuration.submitJsonForm(
        JSON.stringify(Object.fromEntries(new FormData(adminForm)))
    );
};

akeeba.Configuration.submitJsonForm = async function (json) {
    // Set up our virtual form
    const formData = new FormData();
    formData.set('jsonForm', json);
    formData.set(akeeba.System.getOptions('akeeba.Configuration.token'), 1);
    formData.set('task', document.forms['adminForm']['task'].value);

    // Initialise the redirection detection code.
    let url = null;

    try {
        // Do an async POST to the profile config save endpoint
        const response = await fetch(
            document.forms['adminForm'].action,
            {
                method: "POST",
                body: formData,
            });

        // Redirections will be respected by using their URL as the ultimate redirection target.
        if (response.redirected)
        {
            url = response.url;
        }

        // Something went wrong. Reload the configuration page.
        if (!response.ok) {
            throw new Error('Error found in network response');
        }
    } catch (error) {
        location.reload();
        return;
    }

    // No redirection URL detected; reload the page.
    if (!url)
    {
        location.reload();

        return;
    }

    // We have a redirection URL; follow it.
    window.location = url;
}

// =====================================================================================================================
// Initialise hooks used by the engine definitions INI files
// =====================================================================================================================

akeeba_directftp_init_browser = function ()
{
    akeeba.Configuration.FtpBrowser.initialise(
        "engine.archiver.directftp.initial_directory", "engine.archiver.directftp");
};

akeeba_postprocftp_init_browser = function ()
{
    akeeba.Configuration.FtpBrowser.initialise("engine.postproc.ftp.initial_directory", "engine.postproc.ftp");
};

akeeba_directsftp_init_browser = function ()
{
    akeeba.Configuration.SftpBrowser.initialise(
        "engine.archiver.directsftp.initial_directory", "engine.archiver.directsftp");
};

akeeba_postprocsftp_init_browser = function ()
{
    akeeba.Configuration.FtpBrowser.initialise("engine.postproc.sftp.initial_directory", "engine.postproc.sftp");
};

directftp_test_connection = function ()
{
    akeeba.Configuration.FtpTest.testConnection("engine.archiver.directftp.ftp_test", "engine.archiver.directftp", 0);
};

postprocftp_test_connection = function ()
{
    akeeba.Configuration.FtpTest.testConnection("engine.postproc.ftp.ftp_test", "engine.postproc.ftp", 0);
};

directftpcurl_test_connection = function ()
{
    akeeba.Configuration.FtpTest.testConnection(
        "engine.archiver.directftpcurl.ftp_test", "engine.archiver.directftpcurl", 1);
};

postprocftpcurl_test_connection = function ()
{
    akeeba.Configuration.FtpTest.testConnection("engine.postproc.ftpcurl.ftp_test", "engine.postproc.ftpcurl", 1);
};

directsftp_test_connection = function ()
{
    akeeba.Configuration.SftpTest.testConnection(
        "engine.archiver.directsftp.sftp_test", "engine.archiver.directsftp", 0);
};

postprocsftp_test_connection = function ()
{
    akeeba.Configuration.SftpTest.testConnection("engine.postproc.sftp.sftp_test", "engine.postproc.sftp", 0);
};

directsftpcurl_test_connection = function ()
{
    akeeba.Configuration.SftpTest.testConnection(
        "engine.archiver.directsftpcurl.sftp_test", "engine.archiver.directsftpcurl", 1);
};

postprocsftpcurl_test_connection = function ()
{
    akeeba.Configuration.SftpTest.testConnection("engine.postproc.sftpcurl.sftp_test", "engine.postproc.sftpcurl", 1);
};

akconfig_dropbox_openoauth = function ()
{
    var url = akeeba.Configuration.URLs.dpeauthopen;

    if (url.indexOf("?") == -1)
    {
        url = url + "?";
    }
    else
    {
        url = url + "&";
    }

    window.open(url + "engine=dropbox", "akeeba_dropbox_window", "width=1010,height=500,opener");
};

akconfig_dropbox_gettoken = function ()
{
    akeeba.System.AjaxURL = akeeba.Configuration.URLs["dpecustomapi"];

    var data = {
        engine: "dropbox",
        method: "getauth"
    };

    akeeba.System.doAjax(
        data,
        function (res)
        {
            if (res["error"] != "")
            {
                alert("ERROR: Could not complete authentication; please retry");
            }
            else
            {
                document.getElementById("var[engine.postproc.dropbox.token]").value        = res.token.oauth_token;
                document.getElementById("var[engine.postproc.dropbox.token_secret]").value =
                    res.token.oauth_token_secret;
                document.getElementById("var[engine.postproc.dropbox.uid]").value          = res.token.uid;
                alert("Authentication successful!");
            }
        }, function (errorMessage)
        {
            alert("ERROR: Could not complete authentication; please retry" + "\n" + errorMessage);
        }, false, 15000
    );
};

akconfig_dropbox2_openoauth = function ()
{
    var url = akeeba.Configuration.URLs.dpeauthopen;

    if (url.indexOf("?") == -1)
    {
        url = url + "?";
    }
    else
    {
        url = url + "&";
    }

    window.open(url + "engine=dropbox2", "akeeba_dropbox2_window", "width=1010,height=500,opener");
};

akeeba_dropbox2_oauth_callback = function (data)
{
    // Update the tokens
    document.getElementById("var[engine.postproc.dropbox2.access_token]").value = data.access_token;
    document.getElementById("var[engine.postproc.dropbox2.refresh_token]").value = data.refresh_token;

    // Close the window
    myWindow = window.open("", "akeeba_dropbox2_window");
    myWindow.close();
};

akconfig_onedrive_openoauth = function ()
{
    var url = akeeba.Configuration.URLs.dpeauthopen;

    if (url.indexOf("?") == -1)
    {
        url = url + "?";
    }
    else
    {
        url = url + "&";
    }

    window.open(url + "engine=onedrive", "akeeba_onedrive_window", "width=1010,height=500,opener");
};

akeeba_onedrive_oauth_callback = function (data)
{
    // Update the tokens
    document.getElementById("var[engine.postproc.onedrive.access_token]").value  = data.access_token;
    document.getElementById("var[engine.postproc.onedrive.refresh_token]").value = data.refresh_token;

    // Close the window
    myWindow = window.open("", "akeeba_onedrive_window");
    myWindow.close();
};

akconfig_onedrivebusiness_openoauth = function ()
{
    var url = akeeba.Configuration.URLs.dpeauthopen;

    if (url.indexOf("?") == -1)
    {
        url = url + "?";
    }
    else
    {
        url = url + "&";
    }

    window.open(url + "engine=onedrivebusiness", "akeeba_onedrivebusiness_window", "width=1010,height=500,opener");
};

akeeba_onedrivebusiness_oauth_callback = function (data)
{
    // Update the tokens
    document.getElementById("var[engine.postproc.onedrivebusiness.access_token]").value  = data.access_token;
    document.getElementById("var[engine.postproc.onedrivebusiness.refresh_token]").value = data.refresh_token;

    // Close the window
    myWindow = window.open("", "akeeba_onedrivebusiness_window");
    myWindow.close();
};

akeeba_onedrivebusiness_refreshdrives = function (params)
{
    if (document.getElementById('var[akeeba.advanced.postproc_engine]').value !== 'onedrivebusiness')
    {
        return;
    }

    params = params || {};

    console.log(params);

    if (typeof params["engine.postproc.onedrivebusiness.drive"] === "undefined")
    {
        params["engine.postproc.onedrivebusiness.drive"] = {
            "default": document.getElementById("var[engine.postproc.googledrive.team_drive]").value
        };
    }

    akeeba.System.AjaxURL = akeeba.Configuration.URLs["dpecustomapi"];

    var data = {
        engine: "onedrivebusiness",
        method: "getDrives",
        params: {
            "engine.postproc.onedrivebusiness.access_token":  document.getElementById(
                "var[engine.postproc.googledrive.access_token]").value,
            "engine.postproc.onedrivebusiness.refresh_token": document.getElementById(
                "var[engine.postproc.googledrive.refresh_token]").value
        }
    };

    akeeba.System.doAjax(
        data,
        function (res)
        {
            if (!res?.list?.length)
            {
                alert("ERROR: Could not retrieve list of OneDrive Drives.");

                return;
            }

            var dropDown       = document.getElementById("var[engine.postproc.onedrivebusiness.drive]");
            dropDown.innerHTML = "";

            res.list.forEach(item => {
                var elOption   = document.createElement("option");
                elOption.value = item[0];
                elOption.text  = item[1];

                if (params["engine.postproc.onedrivebusiness.drive"]["default"] === elOption.value)
                {
                    elOption.selected = true;
                }

                dropDown.appendChild(elOption);
            });
        }, function (errorMessage)
        {
            alert("ERROR: Could not retrieve list of OneDrive Drives. Error: " + "\n" + errorMessage);
        }, false, 15000
    );
};

akconfig_googledrive_openoauth = function ()
{
    var url = akeeba.Configuration.URLs.dpeauthopen;

    if (url.indexOf("?") == -1)
    {
        url = url + "?";
    }
    else
    {
        url = url + "&";
    }

    window.open(url + "engine=googledrive", "akeeba_googledrive_window", "width=1010,height=500,opener");
};

akeeba_googledrive_oauth_callback = function (data)
{
    // Update the tokens
    document.getElementById("var[engine.postproc.googledrive.access_token]").value  = data.access_token;
    document.getElementById("var[engine.postproc.googledrive.refresh_token]").value = data.refresh_token;

    // Close the window
    myWindow = window.open("", "akeeba_googledrive_window");
    myWindow.close();

    // Refresh the list of drives
    akeeba_googledrive_refreshdrives();
};

akeeba_googledrive_refreshdrives = function (params)
{
    if (document.getElementById('var[akeeba.advanced.postproc_engine]').value !== 'googledrive')
    {
        return;
    }

    params = params || {};

    if (typeof params["engine.postproc.googledrive.team_drive"] === "undefined")
    {
        params["engine.postproc.googledrive.team_drive"] = {
            "default": document.getElementById("var[engine.postproc.googledrive.team_drive]").value
        };
    }

    akeeba.System.AjaxURL = akeeba.Configuration.URLs["dpecustomapi"];

    var data = {
        engine: "googledrive",
        method: "getDrives",
        params: {
            "engine.postproc.googledrive.access_token":  document.getElementById(
                "var[engine.postproc.googledrive.access_token]").value,
            "engine.postproc.googledrive.refresh_token": document.getElementById(
                "var[engine.postproc.googledrive.refresh_token]").value
        }
    };

    akeeba.System.doAjax(
        data,
        function (res)
        {
            if (!res?.list?.length) {
                alert("ERROR: Could not retrieve list of Google Drives.");

                return;
            }

            var dropDown = document.getElementById("var[engine.postproc.googledrive.team_drive]");
            dropDown.innerHTML = "";

            res.list.forEach(item => {
                var elOption = document.createElement("option");
                elOption.value = item[0];
                elOption.text = item[1];

                if (params["engine.postproc.googledrive.team_drive"]["default"] === elOption.value) {
                    elOption.selected = true;
                }

                dropDown.appendChild(elOption);
            });
        }, function (errorMessage)
        {
            alert("ERROR: Could not retrieve list of Google Drives. Error: " + "\n" + errorMessage);
        }, false, 15000
    );
};

akconfig_box_openoauth = function ()
{
    var url = akeeba.Configuration.URLs.dpeauthopen;

    if (url.indexOf("?") == -1)
    {
        url = url + "?";
    }
    else
    {
        url = url + "&";
    }

    window.open(url + "engine=box", "akeeba_box_window", "width=1010,height=500,opener");
};

akconfig_box_oauth_callback = function (data)
{
    // Update the tokens
    document.getElementById("var[engine.postproc.box.access_token]").value  = data.access_token;
    document.getElementById("var[engine.postproc.box.refresh_token]").value = data.refresh_token;

    // Close the window
    myWindow = window.open("", "akeeba_box_window");
    myWindow.close();
};

function akconfig_onedriveapp_openoauth()
{
    var url = akeebabackup.Configuration.URLs.dpeauthopen;

    if (url.indexOf("?") == -1)
    {
        url = url + "?";
    }
    else
    {
        url = url + "&";
    }

    window.open(url + "engine=onedriveapp", "akeeba_onedriveapp_window", "width=1010,height=500,opener");
}

function akeeba_onedriveapp_oauth_callback(data)
{
    // Update the tokens
    document.getElementById("var[engine.postproc.onedriveapp.access_token]").value  = data.access_token;
    document.getElementById("var[engine.postproc.onedriveapp.refresh_token]").value = data.refresh_token;

    // Close the window
    var myWindow = window.open("", "akeeba_onedriveapp_window");
    myWindow.close();
}

// Initialization
akeeba.System.documentReady(function ()
{
    // Get the configured URLs
    akeeba.Configuration.URLs = akeeba.System.getOptions("akeeba.Configuration.URLs", {});

    // Configuration page: we will be doing AJAX calls to the Data Processing Engine Custom API URL
    if (typeof akeeba.Configuration.URLs["dpecustomapi"] !== "undefined")
    {
        akeeba.System.params.AjaxURL = akeeba.Configuration.URLs["dpecustomapi"];
    }

    /**
     * The rest of the code only applies to the Configuration GUI.
     *
     * Therefore, if we have no Configuration GUI data (e.g. this file was included from a different view) we have to
     * return without trying to do anything else.
     */
    var guiData = akeeba.System.getOptions("akeeba.Configuration.GUIData", null);

    if (guiData === null)
    {
        return;
    }

    akeeba.Configuration.parseConfigData(guiData, function ()
    {
        // Enable popovers. Must obviously run after we have the UI set up.
        akeeba.Configuration.enablePopoverFor(document.querySelectorAll("[rel=\"popover\"]"));

        // Capture database driver change event
        var elDbDriver = document.getElementById("var[akeeba.platform.dbdriver]");
        akeeba.System.addEventListener(elDbDriver, "change", akeeba.Configuration.onChangeDbDriver);
        akeeba.System.triggerEvent(elDbDriver, "change");

        // Work around browsers now ignoring autocomplete=off
        setTimeout(akeeba.Configuration.restoreDefaultPasswords, 1000);

        // Reload drive lists if applicable
        akeeba_googledrive_refreshdrives(akeeba.Configuration.engines.postproc.googledrive.parameters);
        akeeba_onedrivebusiness_refreshdrives(akeeba.Configuration.engines.postproc.onedrivebusiness.parameters);

        // Post the form as a big JSON blob if possible
        const adminForm = document.getElementById('adminForm');

        if (adminForm) {
            adminForm.addEventListener('submit', akeeba.Configuration.onFormSubmit);
        }
    });

});
