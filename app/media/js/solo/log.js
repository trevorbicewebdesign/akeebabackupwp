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

if (typeof akeeba.Log == "undefined")
{
    akeeba.Log = {}
}

akeeba.Log.onClickShowLog = function (e)
{
	e.preventDefault();

    var iFrameHolder           = document.getElementById("iframe-holder");
    iFrameHolder.style.display = "block";

    var src = akeeba.System.getOptions("akeeba.Log.iFrameSrc");
    iFrameHolder.insertAdjacentHTML("beforeend", "<iframe width=\"99%\" src=\"" + src + "\" height=\"400px\"/>");

    this.parentNode.style.display = "none";

    return false;
};

akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener("showlog", "click", akeeba.Log.onClickShowLog);
});
