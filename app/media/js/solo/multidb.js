/**
 * @package   solo
 * @copyright Copyright (c)2014-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Object initialisation
if (typeof akeeba == "undefined")
{
    var akeeba = {};
}

if (typeof akeeba.Multidb == "undefined")
{
    akeeba.Multidb = {
        modalDialog: null
    }
}

/**
 * Render the additional databases interface
 *
 * @param data
 */
akeeba.Multidb.render = function (data)
{
    var tbody       = document.getElementById("ak_list_contents");
    tbody.innerHTML = "";

    for (rootname in data)
    {
        if (!data.hasOwnProperty(rootname))
        {
            continue;
        }

        var def = data[rootname];

        akeeba.Multidb.addRow(rootname, def, tbody);
    }

    akeeba.Multidb.addNewRecordButton(tbody);
};

/**
 * Add a single row to the additional databases interface
 *
 * @param root
 * @param def
 * @param append_to_here
 */
akeeba.Multidb.addRow = function (root, def, append_to_here)
{
    var elTr = document.createElement("tr");

    elTr.className = "ak_filter_row";
    akeeba.System.data.set(elTr, "root", root);
    akeeba.System.data.set(elTr, "def", JSON.stringify(def));

    // Delete button
    var elTdDelete         = document.createElement("td");
    elTdDelete.style.width = "2em";

    var elDeleteSpan       = document.createElement("span");
    elDeleteSpan.className = "ak_filter_tab_icon_container akeeba-btn--red--mini";
    akeeba.System.addEventListener(elDeleteSpan, "click", function ()
    {
        var elRootNode = this.parentNode.parentNode;

        var new_data = {
            root: akeeba.System.data.get(elRootNode, "root"),
            verb: "remove"
        };

        akeeba.Fsfilters.toggle(
            new_data,
            this,
            function (response, caller)
            {
                if (response.success == true)
                {
                    var elRemove = caller.parentNode.parentNode;
                    elRemove.parentNode.removeChild(elRemove);
                }
            }
        );
    });

    var elDeleteIcon       = document.createElement("span");
    elDeleteIcon.className = "ak-toggle-button deletebutton";
    elDeleteIcon.insertAdjacentHTML("beforeend", "<span class=\"akion-trash-a\"></span>");

    elDeleteSpan.appendChild(elDeleteIcon);

    elTdDelete.appendChild(elDeleteSpan);

    // Edit button
    var elTdEdit         = document.createElement("td");
    elTdEdit.style.width = "2em";

    var elEditSpan       = document.createElement("span");
    elEditSpan.className = "ak_filter_tab_icon_container akeeba-btn--teal--mini";
    akeeba.System.addEventListener(elEditSpan, "click", function ()
    {
        var cache_element = this.parentNode.parentNode;
        var cache_data    = JSON.parse(akeeba.System.data.get(cache_element, "def", "{}"));
        var cache_root    = akeeba.System.data.get(cache_element, "root");
        var editor        = document.getElementById("akEditorDialog");

        // Select the correct driver
        if (cache_data.driver == "")
        {
            cache_data.driver = "mysqli";
        }

        // Set the parameters
        document.getElementById("ake_driver").value   = cache_data.driver;
        document.getElementById("ake_host").value     = cache_data.host;
        document.getElementById("ake_username").value = cache_data.username;
        document.getElementById("ake_password").value = cache_data.password;
        document.getElementById("ake_database").value = cache_data.database;
        document.getElementById("ake_prefix").value   = cache_data.prefix;

        // Remove any leftover notifier
        try
        {
            var elRemove = document.getElementById("ak_editor_notifier");
            elRemove.parentNode.removeChild(elRemove);
        }
        catch (e)
        {
        }

        // Test connection button
        /**
         * Node cloning removes leftover event listeners preventing the bug in tickets #28300 and #28317.
         *
         * See https://stackoverflow.com/questions/9251837/how-to-remove-all-listeners-in-an-element
         */
        var elEditorDefaultOld = document.getElementById("akEditorBtnDefault");
        var elEditorDefault    = elEditorDefaultOld.cloneNode(true);
        elEditorDefaultOld.parentNode.replaceChild(elEditorDefault, elEditorDefaultOld);

        akeeba.System.addEventListener(elEditorDefault, "click", function ()
        {
            // Remove any leftover notifier
            try
            {
                var elRemove = document.getElementById("ak_editor_notifier");
                elRemove.parentNode.removeChild(elRemove);
            }
            catch (e)
            {
            }

            // Create the placeholder div and show a loading message
            var elAlertDiv       = document.createElement("div");
            elAlertDiv.className = "akeeba-block--info";
            elAlertDiv.id        = "ak_editor_notifier";

            var elSpanNotifierContent = document.createElement("p");
            elSpanNotifierContent.id  = "ak_editor_notifier_content";
            elAlertDiv.appendChild(elSpanNotifierContent);

            var elSpinner = document.createElement("img");
            elSpinner.setAttribute("border", 0);
            elSpinner.setAttribute("src", akeeba.System.getOptions("akeeba.Multidb.loadingGif", ""));
            elSpanNotifierContent.appendChild(elSpinner);

            var elLoadingText         = document.createElement("span");
            elLoadingText.textContent = akeeba.System.Text._("COM_AKEEBA_MULTIDB_GUI_LBL_LOADING");
            elSpanNotifierContent.appendChild(elLoadingText);

            var elEditorTable = document.getElementById("ak_editor_table");
            elEditorTable.insertAdjacentHTML("beforebegin", elAlertDiv.outerHTML);

            // Test the connection via AJAX
            var elDriverDropdown = document.getElementById("ake_driver");
            var elSelectedOption = elDriverDropdown.options[elDriverDropdown.selectedIndex];
            var driver           = (elSelectedOption == null) ? "" : elSelectedOption.value;
            var req              = {
                verb: "test",
                root: root,
                data: {
                    host:     document.getElementById("ake_host").value,
                    driver:   driver,
                    port:     document.getElementById("ake_port").value,
                    user:     document.getElementById("ake_username").value,
                    password: document.getElementById("ake_password").value,
                    database: document.getElementById("ake_database").value,
                    prefix:   document.getElementById("ake_prefix").value
                }
            };

            var query = {
                akaction: JSON.stringify(req)
            };

            akeeba.System.doAjax(query, function (response)
            {
                var elEditorNotifierContent = document.getElementById("ak_editor_notifier_content");

                if (response.status == true)
                {
                    document.getElementById("ak_editor_notifier").className = "akeeba-block--success";
                    elEditorNotifierContent.textContent                     =
                        akeeba.System.Text._("COM_AKEEBA_MULTIDB_GUI_LBL_CONNECTOK");
                }
                else
                {
                    document.getElementById("ak_editor_notifier").className = "akeeba-block--failure";
                    elEditorNotifierContent.innerHTML                       =
                        akeeba.System.Text._("COM_AKEEBA_MULTIDB_GUI_LBL_CONNECTFAIL") +
                        "<br/>" +
                        "<code>" + response.message + "</code>";
                }
            }, function (message)
            {
                var elEditorNotifierContent = document.getElementById("ak_editor_notifier_content");

                document.getElementById("ak_editor_notifier").className = "akeeba-block--failure";
                elEditorNotifierContent.textContent                     =
                    akeeba.System.Text._("COM_AKEEBA_MULTIDB_GUI_LBL_CONNECTFAIL");

                if ((typeof akeeba.Multidb.modalDialog == "object") && akeeba.Multidb.modalDialog.close)
                {
                    akeeba.Multidb.modalDialog.close();
                }

                akeeba.System.params.errorCallback(message);
            }, false, 15000);
        });

        // Save button
        /**
         * Node cloning removes leftover event listeners preventing the bug in tickets #28300 and #28317.
         *
         * See https://stackoverflow.com/questions/9251837/how-to-remove-all-listeners-in-an-element
         */
        var elEditorSaveOld = document.getElementById("akEditorBtnSave");
        var elEditorSave    = elEditorSaveOld.cloneNode(true);
        elEditorSaveOld.parentNode.replaceChild(elEditorSave, elEditorSaveOld);

        akeeba.System.addEventListener(elEditorSave, "click", function ()
        {
            // Remove any leftover notifier
            try
            {
                var elRemove = document.getElementById("ak_editor_notifier");
                elRemove.parentNode.removeChild(elRemove);
            }
            catch (e)
            {
            }

            var elAlertDiv       = document.createElement("div");
            elAlertDiv.className = "akeeba-block--info";
            elAlertDiv.id        = "ak_editor_notifier";

            var elSpanNotifierContent = document.createElement("p");
            elSpanNotifierContent.id  = "ak_editor_notifier_content";
            elAlertDiv.appendChild(elSpanNotifierContent);

            var elSpinner = document.createElement("img");
            elSpinner.setAttribute("border", 0);
            elSpinner.setAttribute("src", akeeba.System.getOptions("akeeba.Multidb.loadingGif", ""));
            elSpanNotifierContent.appendChild(elSpinner);

            var elLoadingText         = document.createElement("span");
            elLoadingText.textContent = akeeba.System.Text._("COM_AKEEBA_MULTIDB_GUI_LBL_LOADING");
            elSpanNotifierContent.appendChild(elLoadingText);

            var elEditorTable = document.getElementById("ak_editor_table");
            elEditorTable.insertAdjacentHTML("beforebegin", elAlertDiv.outerHTML);

            // Send AJAX save request
            var elDriverDropdown = document.getElementById("ake_driver");
            var elSelectedOption = elDriverDropdown.options[elDriverDropdown.selectedIndex];
            var driver           = (elSelectedOption == null) ? "" : elSelectedOption.value;
            var req              = {
                verb: "set",
                root: root,
                data: {
                    host:     document.getElementById("ake_host").value,
                    driver:   driver,
                    port:     document.getElementById("ake_port").value,
                    username: document.getElementById("ake_username").value,
                    password: document.getElementById("ake_password").value,
                    database: document.getElementById("ake_database").value,
                    prefix:   document.getElementById("ake_prefix").value,
                    dumpFile: String(root).substr(0, 9) + document.getElementById("ake_database").value + ".sql"
                }
            };

            var query = {
                akaction: JSON.stringify(req)
            };

            akeeba.System.doAjax(query, function (response)
            {
                if ((response?.success ?? false) !== true)
                {
                    document.getElementById("ak_editor_notifier_content").textContent =
                        akeeba.System.Text._("COM_AKEEBA_MULTIDB_GUI_LBL_SAVEFAIL");

                    return;
                }

                // Cache new data
                akeeba.System.data.set(cache_element, "def", JSON.stringify(req.data));

                // Update grid cells (host & db)
                var cells = cache_element.querySelectorAll("td");

                cache_element.querySelector("span.ak_dbhost").textContent = req.data.host;
                cache_element.querySelector("span.ak_dbname").textContent = req.data.database;

                // Handle new row case
                if (cache_element.querySelector("span.editbutton").firstChild.className.indexOf("akion-edit") == -1)
                {
                    // This was a new row. Add the normal buttons...
                    cache_element.querySelector("span.deletebutton").parentNode.style.display = "inline-block";

                    var elEditIcon                  = cache_element.querySelector("span.editbutton");
                    elEditIcon.firstChild.className = "akion-edit";

                    // ...then add a new "add new row" at the bottom.
                    akeeba.Multidb.addNewRecordButton(cache_element.parentNode);
                }

                // Finally close the dialog
                if ((typeof akeeba.Multidb.modalDialog == "object") && akeeba.Multidb.modalDialog.close)
                {
                    akeeba.Multidb.modalDialog.close();
                }

            }, function (message)
            {
                document.getElementById("ak_editor_notifier_content").textContent =
                    akeeba.System.Text._("COM_AKEEBA_MULTIDB_GUI_LBL_SAVEFAIL");

                if ((typeof akeeba.Multidb.modalDialog == "object") && akeeba.Multidb.modalDialog.close)
                {
                    akeeba.Multidb.modalDialog.close();
                }

                akeeba.System.params.errorCallback(message);
            }, false, 15000);
        });

        // Cancel button
        /**
         * Node cloning removes leftover event listeners preventing the bug in tickets #28300 and #28317.
         *
         * See https://stackoverflow.com/questions/9251837/how-to-remove-all-listeners-in-an-element
         */
        var elEditorCancelOld = document.getElementById("akEditorBtnCancel");
        var elEditorCancel    = elEditorCancelOld.cloneNode(true);
        elEditorCancelOld.parentNode.replaceChild(elEditorCancel, elEditorCancelOld);

        akeeba.System.addEventListener(elEditorCancel, "click", function ()
        {
            // Close the dialog
            if ((typeof akeeba.Multidb.modalDialog == "object") && akeeba.Multidb.modalDialog.close)
            {
                akeeba.Multidb.modalDialog.close();
            }
        });

        // Show editor
        akeeba.Multidb.modalDialog = akeeba.Modal.open({
            inherit: editor,
            width:   "80%"
        });

        akeeba.System.triggerEvent(editor.querySelector("span"), "focus");
    });

    var elEditIcon       = document.createElement("span");
    elEditIcon.className = "editbutton ak-toggle-button";
    elEditIcon.insertAdjacentHTML("beforeend", "<span class=\"akion-edit\"></span>");
    elEditSpan.appendChild(elEditIcon);

    elTdEdit.appendChild(elEditSpan);

    // Database host
    var elTdHost       = document.createElement("td");
    elTdHost.className = "ak_filter_item";

    var elSpanHost         = document.createElement("span");
    elSpanHost.className   = "ak_filter_name ak_dbhost";
    elSpanHost.textContent = def.host;
    elTdHost.appendChild(elSpanHost);

    // Database name
    var elTdDBName       = document.createElement("td");
    elTdDBName.className = "ak_filter_item";

    var elSpanDBName         = document.createElement("span");
    elSpanDBName.className   = "ak_filter_name ak_dbname";
    elSpanDBName.textContent = def.database;
    elTdDBName.appendChild(elSpanDBName);

    elTr.appendChild(elTdDelete);
    elTr.appendChild(elTdEdit);
    elTr.appendChild(elTdHost);
    elTr.appendChild(elTdDBName);
    append_to_here.appendChild(elTr);
};

akeeba.Multidb.addNewRecordButton = function (append_to_here)
{
    var root      = Math.uuid();
    var dummyData = {
        host:     "",
        port:     "",
        username: "",
        password: "",
        database: "",
        prefix:   ""
    };
    akeeba.Multidb.addRow(root, dummyData, append_to_here);

    var trList = document.getElementById("ak_list_contents").children;
    var lastTr = trList[trList.length - 1];

    var tdList = lastTr.querySelectorAll("td");

    tdList[0].querySelector("span").style.display = "none";

    var spanList = tdList[1].querySelectorAll("span");
    var elPencil = spanList[spanList.length - 1];

    console.log(spanList, elPencil);

    elPencil.className = "akion-plus";
};

akeeba.System.documentReady(function ()
{
    akeeba.Multidb.render(akeeba.System.getOptions("akeeba.Multidb.guiData", {}));
});
