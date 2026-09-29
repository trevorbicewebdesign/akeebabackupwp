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

if (typeof akeeba.Regexfsfilters == "undefined")
{
    akeeba.Regexfsfilters = {
        currentRoot: null
    }
}

/**
 * Change the active root
 */
akeeba.Regexfsfilters.activeRootChanged = function ()
{
    var elRoot = document.getElementById("active_root");
    akeeba.Regexfsfilters.load(elRoot.options[elRoot.selectedIndex].value);
};

/**
 * Load data from the server
 *
 * @param   new_root  The root to load data for
 */
akeeba.Regexfsfilters.load = function load(new_root)
{
    var data = {
        root: new_root,
        verb: "list"
    };

    var request = {
        akaction: JSON.stringify(data)
    };
    akeeba.System.doAjax(request, function (response)
    {
        akeeba.Regexfsfilters.render(response);
    }, null, false, 15000);
};

/**
 * Render the data in the GUI
 *
 * @param   data
 */
akeeba.Regexfsfilters.render = function (data)
{
    const tbody       = document.getElementById("ak_list_contents");
    tbody.innerHTML = "";

    if (data?.list?.length)
    {
        data.list.forEach(def => akeeba.Regexfsfilters.addRow(def, tbody));
    }

    akeeba.Regexfsfilters.addNewRow(tbody);
};

/**
 * Adds a row to the GUI
 *
 * @param   def             Filter definition
 * @param   append_to_here  Element to append the row to
 */
akeeba.Regexfsfilters.addRow = function (def, append_to_here)
{
    var trow = document.createElement("tr");
    append_to_here.appendChild(trow);

    // Is this an existing filter or a new one?
    var edit_icon_class = "akion-edit";
    if (def.item == "")
    {
        edit_icon_class = "akion-plus";
    }

    var td_buttons = document.createElement("td");
    var elEdit     = document.createElement("span");
    var elDelete   = document.createElement("span");

    td_buttons.appendChild(elEdit);
    td_buttons.appendChild(elDelete);
    trow.appendChild(td_buttons);

    elEdit.className = "table-icon-container akeeba-btn--teal--mini edit ak-toggle-button";
    elEdit.insertAdjacentHTML("beforeend", "<span class=\"" + edit_icon_class + "\"></span>");

    akeeba.System.addEventListener(elEdit, "click", function ()
    {
        // Create the drop down
        var known_filters = [
            "regexfiles",
            "regexdirectories",
            "regexskipdirs",
            "regexskipfiles"
        ];
        var mySelect      = document.createElement("select");
        mySelect.setAttribute("name", "type");
        mySelect.className = "type-select";

        for (var i = 0; i < known_filters.length; i++)
        {
            var filter_name = known_filters[i];
            var selected    = false;

            if (filter_name === def.type)
            {
                selected = true;
            }

            var elOption = document.createElement("option");

            if (selected)
            {
                elOption.setAttribute("selected", "selected");
            }

            elOption.setAttribute("value", filter_name);
            elOption.textContent =
                akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_TYPE_" + String(filter_name).toUpperCase().substr(5));

            mySelect.appendChild(elOption);
        }

        // Switch the type span with the drop-down
        trow.querySelector("td.ak-type span").style.display = "none";
        trow.querySelector("td.ak-type").appendChild(mySelect);

        // Create the edit box
        var myEditBox = document.createElement("input");
        myEditBox.setAttribute("type", "text");
        myEditBox.setAttribute("name", "item");
        myEditBox.setAttribute("size", "100");
        myEditBox.value = def.item;

        // Switch the item code with the input box
        trow.querySelector("td.ak-item tt").style.display = "none";
        trow.querySelector("td.ak-item").appendChild(myEditBox);

        // Hide the edit/delete buttons, add save/cancel buttons
        var tdFirst                                        = trow.children[0];
        tdFirst.querySelector("span.edit").style.display   = "none";
        tdFirst.querySelector("span.delete").style.display = "none";

        var elSave       = document.createElement("span");
        elSave.className = "table-icon-container akeeba-btn--teal--mini save ak-toggle-button";
        elSave.insertAdjacentHTML("beforeend", "<span class=\"akion-checkmark\"></span>");

        akeeba.System.addEventListener(elSave, "click", function ()
        {
            var tdFirst                                        = trow.children[0];
            tdFirst.querySelector("span.delete").style.display = "none";
            var new_type                                       = mySelect.options[mySelect.selectedIndex].value;
            var new_item                                       = myEditBox.value;

            if (trim(new_item) === "")
            {
                // Empty item detected. It is equivalent to delete or cancel.
                if (def.item === "")
                {
                    akeeba.System.triggerEvent(tdFirst.querySelector("span.cancel"), "click");
                    return;
                }
                else
                {
                    akeeba.System.triggerEvent(tdFirst.querySelector("span.delete"), "click");
                    return;
                }
            }

            // If no change is detected we have to cancel, not save
            if ((def.item === new_item) && (def.type === new_type))
            {
                akeeba.System.triggerEvent(tdFirst.querySelector("span.cancel"), "click");
                return;
            }

            var elRoot   = document.getElementById("active_root");
            var new_data = {
                verb: "set",
                type: new_type,
                node: new_item,
                root: elRoot.options[elRoot.selectedIndex].value
            };

            akeeba.Fsfilters.toggle(new_data, this, function (response, caller)
            {
                // Now that we saved the new filter, delete the old one
                var haveToDelete = (def.item != "") && (def.type != "") &&
                    ((def.item != new_item) || (def.type != new_type));
                var addedNewItem = (def.item == "") || (def.type == "");
                var tdFirst      = trow.children[0];

                if (def.item == "")
                {
                    var elEdit       = tdFirst.querySelector("span.edit").firstChild;
                    elEdit.className = elEdit.className.replace("akion-plus", "akion-edit");
                }

                new_data.type = def.type;
                new_data.node = def.item;
                def.type      = new_type;
                def.item      = new_item;

                var type_translation_key = "COM_AKEEBA_FILEFILTERS_TYPE_" + String(def.type).toUpperCase().substr(5);

                trow.querySelector("td.ak-type span").textContent = akeeba.System.Text._(type_translation_key);
                trow.querySelector("td.ak-item tt").textContent   = escapeHTML(def.item);
                akeeba.System.triggerEvent(tdFirst.querySelector("span.cancel"), "click");

                if (haveToDelete)
                {
                    new_data.verb = "remove";
                    akeeba.Fsfilters.toggle(new_data, this, function (response, caller)
                    {
                    }, false);
                }
                else if ((def.item != new_item) || (def.type != new_type) || addedNewItem)
                {
                    akeeba.Regexfsfilters.addNewRow(append_to_here);
                }
            }, false);
        });
        tdFirst.appendChild(elSave);

        var elCancel       = document.createElement("span");
        elCancel.className = "table-icon-container akeeba-btn--orange--mini cancel ak-toggle-button";
        elCancel.insertAdjacentHTML("beforeend", "<span class=\"akion-close\"></span>");

        akeeba.System.addEventListener(elCancel, "click", function ()
        {
            var tdFirst = trow.children[0];

            // Cancel changes; remove editing GUI elements, show the original elements
            var elSave = tdFirst.querySelector("span.save");
            elSave.parentNode.removeChild(elSave);

            var elCancel = tdFirst.querySelector("span.cancel");
            elCancel.parentNode.removeChild(elCancel);

            tdFirst.querySelector("span.edit").style.display = "inline-block";
            if (def.item != "")
            {
                tdFirst.querySelector("span.delete").style.display = "inline-block";
            }

            mySelect.parentNode.removeChild(mySelect);
            trow.querySelector("td.ak-type span").style.display = "inline-block";
            myEditBox.parentNode.removeChild(myEditBox);
            trow.querySelector("td.ak-item tt").style.display = "inline";
        });
        tdFirst.appendChild(elCancel);

    });

    elDelete.className = "table-icon-container akeeba-btn--red--mini delete ak-toggle-button";
    elDelete.insertAdjacentHTML("beforeend", "<span class=\"akion-trash-a\"></span>");

    akeeba.System.addEventListener(elDelete, "click", function ()
    {
        var elRoot = document.getElementById("active_root");

        var new_data = {
            verb: "remove",
            type: def.type,
            node: def.item,
            root: elRoot.options[elRoot.selectedIndex].value
        };

        akeeba.Fsfilters.toggle(new_data, this, function (response, caller)
        {
            trow.parentNode.removeChild(trow);
            if (def.item == "")
            {
                akeeba.Regexfsfilters.addNewRow(append_to_here);
            }
        }, false);
    });

    // Hide the delete button on new rows
    if (def.item == "")
    {
        td_buttons.querySelector("span.delete").style.display = "none";
    }

    // Filter type and filter item rows
    var type_translation_key = "COM_AKEEBA_FILEFILTERS_TYPE_" + String(def.type).toUpperCase().substr(5);
    var type_localized       = akeeba.System.Text._(type_translation_key);
    if (def.type == "")
    {
        type_localized = "";
    }

    var elType       = document.createElement("td");
    elType.className = "ak-type";
    elType.innerHTML = "<span>" + type_localized + "</span>";
    trow.appendChild(elType);

    var elItem       = document.createElement("td");
    elItem.className = "ak-item";
    elItem.innerHTML = "<tt>" + ((def.item == null) ? "" : escapeHTML(def.item)) + "</tt>";
    trow.appendChild(elItem);
};

/**
 * Add a new row to the GUI
 *
 * @param   append_to_here  Element where to append the row
 */
akeeba.Regexfsfilters.addNewRow = function (append_to_here)
{
    var newdef = {
        type: "",
        item: ""
    };

    akeeba.Regexfsfilters.addRow(newdef, append_to_here);
};

akeeba.System.documentReady(function ()
{
    akeeba.System.addEventListener("active_root", "change", akeeba.Regexfsfilters.activeRootChanged);
    akeeba.Regexfsfilters.render(akeeba.System.getOptions("akeeba.RegExFileFilter.guiData", {}));
});