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
    akeeba.Setup      = {};
    akeeba.Setup.URLs = {};
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
