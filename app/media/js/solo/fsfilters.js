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

if (typeof akeeba.Fsfilters == "undefined")
{
    akeeba.Fsfilters = {
        currentRoot: null
    }
}

akeeba.Fsfilters.activeRootChanged = function ()
{
    var elRoot  = document.getElementById("active_root");
    var data    = {};
    data.root   = elRoot.options[elRoot.selectedIndex].value;
    data.crumbs = [];
    data.node   = "";
    akeeba.Fsfilters.load(data);
};

akeeba.Fsfilters.activeTabRootChanged = function ()
{
    var elRoot = document.getElementById("active_root");
    akeeba.Fsfilters.loadTab(elRoot.options[elRoot.selectedIndex].value);
};

/**
 * Loads the contents of a directory
 *
 * @param  data
 */
akeeba.Fsfilters.load = function (data)
{
    // Add the verb to the data
    data.verb = "list";

    // Convert to JSON
    var json = JSON.stringify(data);

    // Assemble the data array and send the AJAX request
    var new_data      = {};
    new_data.akaction = json;

    akeeba.System.doAjax(new_data, function (response)
    {
        akeeba.Fsfilters.render(response);
    }, null, false, 15000);
};

/**
 * Toggles a filesystem filter
 */
akeeba.Fsfilters.toggle = function (data, caller, callback, use_inner_child)
{
    if (use_inner_child == null)
    {
        use_inner_child = true;
    }

    // Make the icon spin
    if (caller != null)
    {
        // Do not allow multiple simultaneous AJAX requests on the same object
        if (akeeba.System.data.get(caller, "loading", false) == true)
        {
            return;
        }

        akeeba.System.data.set(caller, "loading", true);
        var icon_span = caller;

        if (use_inner_child)
        {
            icon_span = caller.querySelector("span");
        }

        var existingIconClass = akeeba.System.data.get(caller, "iconClass", "");

        if (!existingIconClass)
        {
            akeeba.System.data.set(caller, "iconClass", icon_span.className);
        }

        icon_span.className = "ak-toggle-button ak-toggle-button-spinning akion-android-star-outline";

        var timer = setInterval(function ()
        {
            if (typeof icon_span.className === "undefined")
            {
                clearInterval(timer);

                return;
            }

            if (icon_span.className.indexOf("akion-android-star-outline") != -1)
            {
                akeeba.System.removeClass(icon_span, "akion-android-star-outline");
                akeeba.System.addClass(icon_span, "akion-android-star");
            }
            else if (icon_span.className.indexOf("akion-android-star") != -1)
            {
                akeeba.System.addClass(icon_span, "akion-android-star-outline");
                akeeba.System.removeClass(icon_span, "akion-android-star");
            }
        }, 250);

        akeeba.System.data.set(caller, "akeebatimer", timer);
    }

    // Convert to JSON
    var json     = JSON.stringify(data);
    // Assemble the data array and send the AJAX request
    var new_data = {
        akaction: json
    };

    akeeba.System.doAjax(new_data, function (response)
    {
        if (caller != null)
        {
            var timer = akeeba.System.data.get(caller, "akeebatimer");
            clearInterval(timer);

            var storedClassName = akeeba.System.data.get(caller, "iconClass");

            if (storedClassName)
            {
                icon_span.className = storedClassName;
            }

            akeeba.System.data.set(caller, "iconClass", null);
            akeeba.System.data.set(caller, "loading", null);
        }

        if (response.success == true)
        {
            if (caller != null)
            {
                if (use_inner_child)
                {
                    // Update the on-screen filter state
                    if (response.newstate == true)
                    {
                        akeeba.System.removeClass(caller, "akeeba-btn--grey");
                        akeeba.System.addClass(caller, "akeeba-btn--orange");
                    }
                    else
                    {
                        akeeba.System.removeClass(caller, "akeeba-btn--orange");
                        akeeba.System.addClass(caller, "akeeba-btn--grey");
                    }
                }
            }

            if (!(callback == null))
            {
                callback(response, caller);
            }
        }
        else
        {
            if (!(callback == null))
            {
                callback(response, caller);
            }

            akeeba.System.modalErrorHandler(
                akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_LABEL_UIERRORFILTER").replace("%s", data.node));
        }
    }, function (msg)
    {
        // Error handler
        if (caller != null)
        {
            var timer = akeeba.System.data.get(caller, "akeebatimer");
            clearInterval(timer);
            icon_span.className = akeeba.System.data.get(caller, "iconClass");
            akeeba.System.data.set(caller, "iconClass");
            akeeba.System.data.set(caller, "loading");
        }

        akeeba.System.params.errorCallback(msg);
    }, true, 15000);
};

/**
 * Renders the Filesystem Filters page
 * @param data
 * @return
 */
akeeba.Fsfilters.render = function (data)
{
    akeeba.Fsfilters.currentRoot = data.root;

    // ----- Render the crumbs bar
    crumbs = akeeba.Fsfilters.renderCrumbs(data);

    // ----- Render the subdirectories
    var akfolders       = document.getElementById("folders");
    akfolders.innerHTML = "";

    if (data.crumbs.length > 0)
    {
        akeeba.Fsfilters.renderParentFolderElement();
    }

    // Append the "Apply to all" buttons
    if (Object.keys(data.folders).length > 0)
    {
        var headerFilters    = ["directories_all", "skipdirs_all", "skipfiles_all"];
        var headerDirs       = document.createElement("div");
        headerDirs.className = "folder-header folder-container";

        for (var index = 0; index < headerFilters.length; index++)
        {
            var filter = headerFilters[index];

            var ui_icon       = document.createElement("span");
            ui_icon.className = "folder-icon-container akeeba-btn--mini akeeba-btn--grey";
            // ui_icon.setAttribute(
            //     "title",
            //     "<div class=\"tooltip-arrow-up-leftaligned\"></div><div>" + akeeba.System.Text._(
            //     "COM_AKEEBA_FILEFILTERS_TYPE_" + filter.toUpperCase()) + "</div>"
            // );
            // ui_icon.setAttribute("data-akeeba-tooltip-position", "right");
            ui_icon.setAttribute("title", akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_TYPE_" + filter.toUpperCase()));

            var applyTo = "";

            switch (filter)
            {
                case "directories_all":
                    applyTo = "akion-close-circled";
                    ui_icon.insertAdjacentHTML(
                        "beforeend", "<span class=\"ak-toggle-button akion-close-circled\"></span>");
                    break;
                case "skipdirs_all":
                    applyTo = "akion-folder";
                    ui_icon.insertAdjacentHTML("beforeend", "<span class=\"ak-toggle-button akion-folder\"></span>");
                    break;
                case "skipfiles_all":
                    applyTo = "akion-document";
                    ui_icon.insertAdjacentHTML("beforeend", "<span class=\"ak-toggle-button akion-document\"></span>");
                    break;
            }

            akeeba.System.addEventListener(ui_icon, "click", function (ui_icon, applyTo)
            {
                return function ()
                {
                    var selected;

                    if (ui_icon.className.indexOf("akeeba-btn--orange") != -1)
                    {
                        akeeba.System.removeClass(ui_icon, "akeeba-btn--orange");
                        akeeba.System.addClass(ui_icon, "akeeba-btn--grey");
                        selected = false;
                    }
                    else
                    {
                        akeeba.System.removeClass(ui_icon, "akeeba-btn--grey");
                        akeeba.System.addClass(ui_icon, "akeeba-btn--orange");
                        selected = true;
                    }

                    // Start iterating from the second element (the first is the header, we want to skip it!)
                    for (var j = 1; j < akfolders.children.length; j++)
                    {
                        var folderElement = akfolders.children[j];
                        var item          = folderElement.querySelector("span." + applyTo);

                        var hasClass = item.parentNode.className.indexOf("akeeba-btn--orange") != -1;

                        // I have to exclude items that have the same state of the desired one, otherwise I'll toggle it
                        if ((!selected && !hasClass) || (selected && hasClass))
                        {
                            continue;
                        }

                        akeeba.System.triggerEvent(item, "click");
                    }
                };
            }(ui_icon, applyTo));

            headerDirs.appendChild(ui_icon);

            /**
             * DO NOT move this before we add the akion-* span. This would make the icon the last child of the button
             * anchor tag which results in a smaller amount of margin to the right of the icon, making the buttons
             * look squished. Putting this JS call after we have rendered the icon causes the tooltip markup to be added
             * after the icon, therefore the CSS renders an equal margin to the left and right of the icon. As a result
             * the button looks nice and wide.
             */
            //akeeba.Tooltip.simpleTooltip(ui_icon);
        }

        var elButton       = document.createElement("span");
        elButton.className = "folder-name";
        elButton.innerHTML =
            "<span class=\"pull-left akion-arrow-down-a\"></span>" + akeeba.System.Text._(
            "COM_AKEEBA_FILEFILTERS_TYPE_APPLYTOALLDIRS");
        headerDirs.appendChild(elButton);
        akfolders.appendChild(headerDirs);
    }

    for (var folder in data.folders)
    {
        if (!data.folders.hasOwnProperty(folder))
        {
            return;
        }

        var def = data.folders[folder];

        uielement           = document.createElement("div");
        uielement.className = "folder-container";

        var available_filters = ["directories", "skipdirs", "skipfiles"];

        for (var ctFilter = 0; ctFilter < available_filters.length; ctFilter++)
        {
            var filter = available_filters[ctFilter];

            var ui_icon       = document.createElement("span");
            ui_icon.className = "akeeba-btn--mini akeeba-btn--grey folder-icon-container";
            // ui_icon.setAttribute(
            //     "title",
            //     "<div class=\"tooltip-arrow-up-leftaligned\"></div><div>" + akeeba.System.Text._(
            //     "COM_AKEEBA_FILEFILTERS_TYPE_" + filter.toUpperCase()) + "</div>"
            // );
            ui_icon.setAttribute("title", akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_TYPE_" + filter.toUpperCase()));

            switch (filter)
            {
                case "directories":
                    ui_icon.insertAdjacentHTML(
                        "beforeend", "<span class=\"ak-toggle-button akion-close-circled\"></span>");
                    break;
                case "skipdirs":
                    ui_icon.insertAdjacentHTML("beforeend", "<span class=\"ak-toggle-button akion-folder\"></span>");
                    break;
                case "skipfiles":
                    ui_icon.insertAdjacentHTML("beforeend", "<span class=\"ak-toggle-button akion-document\"></span>");
                    break;
            }

            ui_icon.setAttribute("data-akeeba-tooltip-position", "right");
            //akeeba.Tooltip.simpleTooltip(ui_icon);

            switch (def[filter])
            {
                case 2:
                    akeeba.System.removeClass(ui_icon, "akeeba-btn--grey");
                    akeeba.System.addClass(ui_icon, "akeeba-btn--red");
                    break;

                case 1:
                    akeeba.System.removeClass(ui_icon, "akeeba-btn--grey");
                    akeeba.System.addClass(ui_icon, "akeeba-btn--orange");

                // Don't break; we have to add the handler!

                case 0:
                    akeeba.System.addEventListener(ui_icon, "click", function (folder, filter, ui_icon)
                    {
                        return function ()
                        {
                            var new_data = {
                                root:   data.root,
                                crumbs: crumbs,
                                node:   folder,
                                filter: filter,
                                verb:   "toggle"
                            };

                            akeeba.Fsfilters.toggle(new_data, ui_icon);
                        };
                    }(folder, filter, ui_icon));
            }

            uielement.appendChild(ui_icon);
        }

        // Add the folder label and make clicking on it load its listing
        var elFolderName         = document.createElement("span");
        elFolderName.textContent = folder;

        if (def["link"])
        {
            elFolderName.innerHTML =
                "<span class=\"akion-link\" aria-hidden=\"true\"></span> " + elFolderName.innerHTML;
        }

        elFolderName.className = "folder-name";
        akeeba.System.addEventListener(elFolderName, "click", function (folder)
        {
            return function ()
            {
                // Show "loading" animation
                var elImg = document.createElement("img");
                elImg.setAttribute("src", akeeba.System.getOptions("akeeba.Fsfilters.loadingGif", ""));
                elImg.setAttribute("width", 16);
                elImg.setAttribute("height", 11);
                elImg.setAttribute("border", 0);
                elImg.setAttribute("alt", "Loading...");
                elImg.style.marginTop  = "3px";
                elImg.style.marginLeft = "5px";
                this.appendChild(elImg);

                var new_data = {
                    root:   data.root,
                    crumbs: crumbs,
                    node:   folder
                };
                akeeba.Fsfilters.load(new_data);
            };
        }(folder));

        uielement.appendChild(elFolderName);
        // Render
        akfolders.appendChild(uielement);
    }

    // ----- Render the files
    var akfiles       = document.getElementById("files");
    akfiles.innerHTML = "";

    // Append the "Apply to all" buttons
    if (Object.keys(data.files).length > 0)
    {
        var headerFiles       = document.createElement("div");
        headerFiles.className = "file-header file-container";

        var ui_icon       = document.createElement("span");
        ui_icon.className = "file-icon-container akeeba-btn--mini akeeba-btn--grey";
        ui_icon.insertAdjacentHTML("beforeend", "<span class=\"ak-toggle-button akion-close-circled\"></span>");

        // ui_icon.setAttribute(
        //     "title",
        //     "<div class=\"tooltip-arrow-up-leftaligned\"></div><div>" + akeeba.System.Text._(
        //     "COM_AKEEBA_FILEFILTERS_TYPE_FILES_ALL") + "</div>"
        // );
        // ui_icon.setAttribute("data-akeeba-tooltip-position", "right");
        //akeeba.Tooltip.simpleTooltip(ui_icon);
        ui_icon.setAttribute("title", akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_TYPE_FILES_ALL"));

        akeeba.System.addEventListener(ui_icon, "click", function (ui_icon)
        {
            return function ()
            {
                var selected;

                if (this.className.indexOf("akeeba-btn--orange") != -1)
                {
                    akeeba.System.removeClass(this, "akeeba-btn--orange");
                    akeeba.System.addClass(this, "akeeba-btn--grey");
                    selected = false;
                }
                else
                {
                    akeeba.System.removeClass(this, "akeeba-btn--grey");
                    akeeba.System.addClass(this, "akeeba-btn--orange");
                    selected = true;
                }

                // We iterate from the second element since we don't want to trigger the header (would cause infinite
                // loop)
                var akfiles = document.getElementById("files");
                for (var ctFiles = 1; ctFiles < akfiles.children.length; ctFiles++)
                {
                    var elFile = akfiles.children[ctFiles];
                    var item   = elFile.querySelector("span.akion-close-circled");

                    var hasClass = item.parentNode.className.indexOf("akeeba-btn--orange") != -1;

                    // I have to exclude items that have the same state of the desidered one, otherwise I'll toggle it
                    if ((!selected && !hasClass) || (selected && hasClass))
                    {
                        continue;
                    }

                    akeeba.System.triggerEvent(item, "click");
                }
            };
        }(ui_icon));

        headerFiles.appendChild(ui_icon);

        var elFilename       = document.createElement("span");
        elFilename.className = "file-name";
        elFilename.innerHTML =
            "<span class=\"pull-left akion-arrow-down-a\"></span>" + akeeba.System.Text._(
            "COM_AKEEBA_FILEFILTERS_TYPE_APPLYTOALLFILES");
        headerFiles.appendChild(elFilename);
        akfiles.appendChild(headerFiles);
    }

    for (fileName in data.files)
    {
        if (!data.files.hasOwnProperty(fileName))
        {
            continue;
        }

        def = data.files[fileName];

        uielement           = document.createElement("div");
        uielement.className = "file-container";

        var available_filters = ["files"];

        for (var ctFileFilter = 0; ctFileFilter < available_filters.length; ctFileFilter++)
        {
            var filter = available_filters[ctFileFilter];

            var ui_icon       = document.createElement("span");
            ui_icon.className = "file-icon-container akeeba-btn--mini akeeba-btn--grey";

            switch (filter)
            {
                case "files":
                    ui_icon.insertAdjacentHTML(
                        "beforeend", "<span class=\"ak-toggle-button akion-close-circled\"></span>");
                    break;
            }

            // ui_icon.setAttribute(
            //     "title",
            //     "<div class=\"tooltip-arrow-up-leftaligned\"></div><div>" + akeeba.System.Text._(
            //     "COM_AKEEBA_FILEFILTERS_TYPE_" + filter.toUpperCase()) + "</div>"
            // );
            // ui_icon.setAttribute("data-akeeba-tooltip-position", "right");
            //akeeba.Tooltip.simpleTooltip(ui_icon);
            ui_icon.setAttribute("title", akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_TYPE_" + filter.toUpperCase()));

            switch (def[filter])
            {
                case 2:
                    akeeba.System.removeClass(ui_icon, "akeeba-btn--grey");
                    akeeba.System.addClass(ui_icon, "akeeba-btn--red");
                    break;

                case 1:
                    akeeba.System.removeClass(ui_icon, "akeeba-btn--grey");
                    akeeba.System.addClass(ui_icon, "akeeba-btn--orange");
                // Don't break; we have to add the handler!

                case 0:
                    akeeba.System.addEventListener(ui_icon, "click", function (fileName, filter, ui_icon)
                    {
                        return function ()
                        {
                            var new_data = {
                                root:   data.root,
                                crumbs: crumbs,
                                node:   fileName,
                                filter: filter,
                                verb:   "toggle"
                            };
                            akeeba.Fsfilters.toggle(new_data, ui_icon);
                        };
                    }(fileName, filter, ui_icon));
            }

            uielement.appendChild(ui_icon);
        }

        // Add the file label
        var elName         = document.createElement("span");
        elName.className   = "file-name";
        elName.textContent = fileName;

        if (def["link"])
        {
            elName.innerHTML = "<span class=\"akion-link\" aria-hidden=\"true\"></span> " + elName.innerHTML;
        }

        uielement.appendChild(elName);

        var elSize         = document.createElement("span");
        elSize.className   = "file-size";
        elSize.textContent = def["size"];
        uielement.appendChild(elSize);

        // Render
        akfiles.appendChild(uielement);
    }
};

/**
 * Wipes out the filesystem filters
 * @return
 */
akeeba.Fsfilters.nuke = function ()
{
    var data     = {
        root: akeeba.Fsfilters.currentRoot,
        verb: "reset"
    };
    // Assemble the data array and send the AJAX request
    var new_data = {
        akaction: JSON.stringify(data)
    };
    akeeba.System.doAjax(new_data, function (response)
    {
        akeeba.Fsfilters.render(response);
    }, null, false, 15000);
};

/**
 * Loads the tabular view of the Filesystems Filter for a given root
 * @param root
 * @return
 */
akeeba.Fsfilters.loadTab = function (root)
{
    var data     = {
        verb: "tab",
        root: root
    };
    // Assemble the data array and send the AJAX request
    var new_data = {
        akaction: JSON.stringify(data)
    };
    akeeba.System.doAjax(new_data, function (response)
    {
        akeeba.Fsfilters.renderTab(response);
    }, null, false, 15000);
};

/**
 * Add a row in the tabular view of the Filesystems Filter
 * @param def
 * @param append_to_here
 * @return
 */
akeeba.Fsfilters.addRow = function (def, append_to_here)
{
    // Turn def.type into something human readable
    var type_text = akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_TYPE_" + def.type.toUpperCase());

    if (type_text == null)
    {
        type_text = def.type;
    }

    var elRow       = document.createElement("tr");
    elRow.className = "ak_filter_row";

    var elFilterTitle = document.createElement("td");
    elRow.appendChild(elFilterTitle);
    elFilterTitle.className = "ak_filter_type";
    elFilterTitle.insertAdjacentHTML("beforeend", type_text);

    var elIcons = document.createElement("td");
    elRow.appendChild(elIcons);
    elIcons.className = "ak_filter_item";

    var elDeleteButton = document.createElement("span");
    elIcons.appendChild(elDeleteButton);
    elDeleteButton.className = "ak_filter_tab_icon_container akeeba-btn--mini akeeba-btn--red deletecontainer";
    akeeba.System.addEventListener(elDeleteButton, "click", function ()
    {
        if (def.node == "")
        {
            // An empty filter is normally not saved to the database; it's a new record row which has to be removed...
            var elRemove = this.parentNode.parentNode;
            elRemove.parentNode.removeChild(elRemove);

            return;
        }

        var activeRoot = document.getElementById("active_root");

        var new_data = {
            root:   activeRoot.options[activeRoot.selectedIndex].value,
            crumbs: [],
            node:   def.node,
            filter: def.type,
            verb:   "toggle"
        };

        akeeba.Fsfilters.toggle(new_data, this, function (response, caller)
        {
            if (response.success)
            {
                var elRemove = caller.parentNode.parentNode;
                elRemove.parentNode.removeChild(elRemove);
            }
        });
    });
    elDeleteButton.insertAdjacentHTML(
        "beforeend", "<span class=\"ak-toggle-button akion-trash-a deletebutton\"></span>");

    var elEditButton = document.createElement("span");
    elIcons.appendChild(elEditButton);
    elEditButton.className = "ak_filter_tab_icon_container akeeba-btn--mini akeeba-btn--teal editcontainer";
    akeeba.System.addEventListener(elEditButton, "click", function ()
    {
        // If I'm editing there's an input box appended to the parent element of this edit button
        var inputBox = this.parentNode.querySelector("input");

        // So, if I'm already editing quit; we mustn't show multiple edit boxes!
        if (inputBox != null)
        {
            return;
        }

        // Hide the text label
        this.parentNode.querySelector("span.ak_filter_name").style.display = "none";

        var elInput = document.createElement("input");
        elInput.setAttribute("type", "text");
        elInput.setAttribute("size", 60);
        elInput.value = this.parentNode.querySelector("span.ak_filter_name").textContent;
        this.parentNode.appendChild(elInput);
        akeeba.System.addEventListener(elInput, "blur", function ()
        {
            var new_value = this.value;
            var that      = this;

            if (new_value == "")
            {
                // Well, if the user meant to remove the filter, let's help him!
                akeeba.System.triggerEvent(that.parentNode.querySelector("span.deletebutton"), "click");

                return;
            }

            // First, remove the old filter
            var elRoot = document.getElementById("active_root");

            var new_data = {
                root:     elRoot.options[elRoot.selectedIndex].value,
                crumbs:   [],
                old_node: def.node,
                new_node: new_value,
                filter:   def.type,
                verb:     "swap"
            };

            var elEditContainer = that.parentNode.querySelector("span.editcontainer");

            akeeba.Fsfilters.toggle(
                new_data,
                elEditContainer,
                function (response, caller)
                {
                    // Remove the editor
                    var elFilterName           = that.parentNode.querySelector("span.ak_filter_name");
                    elFilterName.style.display = "inline-block";
                    elFilterName.textContent   = new_value;

                    that.parentNode.removeChild(that);
                    def.node = new_value;
                }
            );
        });
        elInput.focus();
    });
    elEditButton.insertAdjacentHTML("beforeend", "<span class=\"ak-toggle-button akion-edit editbutton\"></span>");

    var elFilterName         = document.createElement("span");
    elFilterName.className   = "ak_filter_name";
    elFilterName.textContent = def.node;
    elIcons.appendChild(elFilterName);

    append_to_here.appendChild(elRow);
};

akeeba.Fsfilters.addNew = function (filtertype)
{
    // Add a row below ourselves
    var new_def = {
        type: filtertype,
        node: ""
    };
    akeeba.Fsfilters.addRow(new_def, document.getElementById("ak_list_table").children[1]);

    var trList = document.getElementById("ak_list_table").children[1].children;
    var lastTr = trList[trList.length - 1];
    akeeba.System.triggerEvent(lastTr.querySelector("span.editcontainer"), "click");
};

/**
 * Renders the tabular view of the Filesystems Filter
 * @param data
 * @return
 */
akeeba.Fsfilters.renderTab = function (data)
{
    const tbody       = document.getElementById("ak_list_contents");
    tbody.innerHTML = "";

    if (data?.list?.length)
    {
        data.list.forEach(def => akeeba.Fsfilters.addRow(def, tbody));
    }
};

akeeba.Fsfilters.renderCrumbs = function (data)
{
    // Create a new crumbs data array
    var crumbsdata = [];
    // Push the "navigate to root" element
    var newCrumb   = [
        akeeba.System.Text._("COM_AKEEBA_FILEFILTERS_LABEL_UIROOT"), // [0] : UI Label
        data.root,                     // [1] : Root node
        [],                            // [2] : Crumbs to current directory
        ""                             // [3] : Node element
    ];

    crumbsdata.push(newCrumb);
    var counter = 0;

    // Iterate existing crumbs
    if (data.crumbs.length > 0)
    {
        var crumbs = [];

        for (counter = 0; counter < data.crumbs.length; counter++)
        {
            var crumb = data.crumbs[counter];

            newCrumb = [
                crumb,
                data.root,
                crumbs.slice(0), // Otherwise it is copied by reference
                crumb
            ];

            crumbsdata.push(newCrumb);
            crumbs.push(crumb); // Push this dir into the crumb list
        }
    }

    // Render the UI crumbs elements
    var akcrumbs       = document.getElementById("ak_crumbs");
    akcrumbs.innerHTML = "";

    var def = null;

    for (counter = 0; counter < crumbsdata.length; counter++)
    {
        def      = crumbsdata[counter];
        var myLi = document.createElement("li");

        var elLink = document.createElement("a");
        elLink.setAttribute("href", "javascript:");
        def[0]             = def[0].replace("&lt;", "<").replace("&gt;", ">");
        elLink.textContent = def[0];
        akeeba.System.addEventListener(elLink, "click", function (def)
        {
            return function ()
            {
                var elImg = document.createElement("img");
                elImg.setAttribute("src", akeeba.System.getOptions("akeeba.Fsfilters.loadingGif", ""));
                elImg.setAttribute("width", 16);
                elImg.setAttribute("height", 11);
                elImg.setAttribute("border", 0);
                elImg.setAttribute("alt", "Loading...");
                elImg.style.marginTop  = "5px";
                elImg.style.marginLeft = "5px";
                this.appendChild(elImg);

                var new_data = {
                    root:   def[1],
                    crumbs: def[2],
                    node:   def[3]
                };
                akeeba.Fsfilters.load(new_data);
            };
        }(def));

        myLi.appendChild(elLink);

        var elSeparator         = document.createElement("span");
        elSeparator.textContent = "/";
        myLi.appendChild(elSeparator);

        akcrumbs.appendChild(myLi);
    }

    return crumbs;
};

akeeba.Fsfilters.renderParentFolderElement = function ()
{
    var akfolders = document.getElementById("folders");

    // The parent directory element
    var uielement       = document.createElement("div");
    uielement.className = "folder-container folder-header";
    uielement.insertAdjacentHTML("beforeend", "<span class=\"folder-padding\"></span>");
    uielement.insertAdjacentHTML("beforeend", "<span class=\"folder-padding\"></span>");
    uielement.insertAdjacentHTML("beforeend", "<span class=\"folder-padding\"></span>");

    uielement.insertAdjacentHTML("beforeend", "<span class=\"akion-arrow-up-a\"></span>");

    var elFolderUp       = document.createElement("span");
    elFolderUp.className = "folder-name folder-up";

    var elCrumbs           = document.getElementById("ak_crumbs").children;
    var elParentFolder     = elCrumbs[elCrumbs.length - 2].querySelector("a");
    elFolderUp.textContent = " (" + elParentFolder.textContent + ")";

    akeeba.System.addEventListener(elFolderUp, "click", function (elParentFolder)
    {
        return function ()
        {
            akeeba.System.triggerEvent(elParentFolder, "click");
        }
    }(elParentFolder));


    uielement.appendChild(elFolderUp);
    akfolders.appendChild(uielement);
};

akeeba.System.documentReady(function ()
{
    // This file may be included in the other views. In this case do NOT run our GUI initialization.
    var guiData = akeeba.System.getOptions("akeeba.FileFilters.guiData", null);

    if (guiData === null)
    {
        return;
    }

    if (akeeba.System.getOptions("akeeba.FileFilters.viewType") === "list")
    {
        akeeba.Fsfilters.render(guiData);
        akeeba.System.addEventListener("active_root", "change", akeeba.Fsfilters.activeRootChanged);
    }
    else
    {
        akeeba.Fsfilters.renderTab(guiData);
        akeeba.System.addEventListener("active_root", "change", akeeba.Fsfilters.activeTabRootChanged);
    }

    akeeba.System.addEventListener("comAkeebaFileFiltersNuke", "click", function ()
    {
        akeeba.Fsfilters.nuke();

        return false;
    });

    akeeba.System.addEventListener("comAkeebaFileFiltersAddDirectories", "click", function ()
    {
        akeeba.Fsfilters.addNew("directories");

        return false;
    });

    akeeba.System.addEventListener("comAkeebaFileFiltersAddSkipfiles", "click", function ()
    {
        akeeba.Fsfilters.addNew("skipfiles");

        return false;
    });

    akeeba.System.addEventListener("comAkeebaFileFiltersAddSkipdirs", "click", function ()
    {
        akeeba.Fsfilters.addNew("skipdirs");

        return false;
    });

    akeeba.System.addEventListener("comAkeebaFileFiltersAddFiles", "click", function ()
    {
        akeeba.Fsfilters.addNew("files");

        return false;
    });
});