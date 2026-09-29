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

if (typeof akeeba.Dbfilters == "undefined")
{
	akeeba.Dbfilters = {
		currentRoot: null
	}
}

akeeba.Dbfilters.activeRootChanged = function ()
{
	var elRoot = document.getElementById("active_root");
	var data   = {
		root: elRoot.options[elRoot.selectedIndex].value
	};
	akeeba.Dbfilters.load(data);
};

akeeba.Dbfilters.activeTabRootChanged = function ()
{
	var elRoot = document.getElementById("active_root");
	akeeba.Dbfilters.loadTab(elRoot.options[elRoot.selectedIndex].value);
};

/**
 * Loads the contents of a database
 *
 * @param data
 */
akeeba.Dbfilters.load = function (data)
{
	// Add the verb to the data
	data.verb = "list";

	// Assemble the data array and send the AJAX request
	var new_data = {
		akaction: JSON.stringify(data)
	};

	akeeba.System.doAjax(new_data, function (response)
	{
		akeeba.Dbfilters.render(response);
	}, null, false, 15000);
};

/**
 * Toggles a database filter
 * @param data
 * @param caller
 */
akeeba.Dbfilters.toggle = function (data, caller, callback)
{
	akeeba.Fsfilters.toggle(data, caller, callback);
};

/**
 * Renders the Database Filters page
 * @param data
 * @return
 */
akeeba.Dbfilters.render = function (data)
{
	akeeba.Dbfilters.currentRoot = data.root;

	// ----- Render the tables
	var aktables       = document.getElementById("tables");
	aktables.innerHTML = "";

	for (var table in data.tables)
	{
		if (!data.tables.hasOwnProperty(table))
		{
			continue;
		}

		dbef = data.tables[table];

		var uielement       = document.createElement("div");
		uielement.className = "table-container";

		var available_filters = ["tables", "tabledata"];

		for (var counter = 0; counter < available_filters.length; counter++)
		{
			var filter = available_filters[counter];

			var ui_icon       = document.createElement("span");
			ui_icon.className = "table-icon-container akeeba-btn--grey akeeba-btn--mini";
			ui_icon.setAttribute("title", akeeba.System.Text._("COM_AKEEBA_DBFILTER_TYPE_" + filter.toUpperCase()));

			switch (filter)
			{
				case "tables":
					ui_icon.insertAdjacentHTML(
						"beforeend",
						"<span class=\"ak-toggle-button akion-close-circled\"></span>"
					);
					break;

				case "tabledata":
					ui_icon.insertAdjacentHTML("beforeend", "<span class=\"ak-toggle-button akion-ios-box\"></span>");
					break;
			}

			switch (dbef[filter])
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
					akeeba.System.addEventListener(ui_icon, "click", function (ui_icon, table, filter)
					{
						return function ()
						{
							var new_data = {
								root:   data.root,
								node:   table,
								filter: filter,
								verb:   "toggle"
							};
							akeeba.Dbfilters.toggle(new_data, ui_icon);
						};
					}(ui_icon, table, filter))
			}

			uielement.appendChild(ui_icon);

			/**
			 * DO NOT move this before we add the akion-* span. This would make the icon the last child of the button
			 * anchor tag which results in a smaller amount of margin to the right of the icon, making the buttons
			 * look squished. Putting this JS call after we have rendered the icon causes the tooltip markup to be added
			 * after the icon, therefore the CSS renders an equal margin to the left and right of the icon. As a result
			 * the button looks nice and wide.
			 */
			//akeeba.Tooltip.simpleTooltip(ui_icon);
		}


		// Add the table label
		var iconclass = "akion-link";
		var icontip   = "COM_AKEEBA_DBFILTER_TABLE_MISC";

		switch (dbef.type)
		{
			case "table":
				iconclass = "akion-ios-grid-view";
				icontip   = "COM_AKEEBA_DBFILTER_TABLE_TABLE";
				break;
			case "view":
				iconclass = "akion-android-list";
				icontip   = "COM_AKEEBA_DBFILTER_TABLE_VIEW";
				break;
			case "procedure":
				iconclass = "akion-cube";
				icontip   = "COM_AKEEBA_DBFILTER_TABLE_PROCEDURE";
				break;
			case "function":
				iconclass = "akion-ion-code";
				icontip   = "COM_AKEEBA_DBFILTER_TABLE_FUNCTION";
				break;
			case "trigger":
				iconclass = "akion-flash";
				icontip   = "COM_AKEEBA_DBFILTER_TABLE_TRIGGER";
				break;
		}

		var uiTableNameContainer = document.createElement("span");
		var uiTableSizeContainer = document.createElement("span");
		var uiTableType          = document.createElement("span");
		var uiSeparator          = document.createElement("span");

		uiTableNameContainer.className   = "table-name";
		uiTableNameContainer.textContent = table;

		uiTableSizeContainer.className = "table-rowcount";

		if (dbef.rows)
		{
			uiTableSizeContainer.textContent = dbef.rows;
			uiTableSizeContainer.setAttribute("title", akeeba.System.Text._("COM_AKEEBA_DBFILTER_TABLE_META_ROWCOUNT"));
			uiTableSizeContainer.setAttribute("data-akeeba-tooltip-position", "left");
			//akeeba.Tooltip.simpleTooltip(uiTableSizeContainer);
		}

		uiTableType.className = "table-icon-container table-icon-noclick table-icon-small";
		uiTableType.setAttribute("title", akeeba.System.Text._(icontip));
		// uiTableType.setAttribute("data-akeeba-tooltip-position", "bottom");
		//akeeba.Tooltip.simpleTooltip(uiTableType);

		var uiTableTypeIcon       = document.createElement("span");
		uiTableTypeIcon.className = iconclass;
		uiTableType.appendChild(uiTableTypeIcon);

		uiSeparator.className       = "table-icon-container table-icon-noclick table-icon-small";
		var uiSeparatorIcon         = document.createElement("span");
		uiSeparatorIcon.className   = "akion-android-more-vertical";
		uiSeparatorIcon.style.color = "#cccccc";
		uiSeparator.appendChild(uiSeparatorIcon);

		uielement.appendChild(uiSeparator);
		uielement.appendChild(uiTableType);
		uielement.appendChild(uiTableNameContainer);
		uielement.appendChild(uiTableSizeContainer);

		// Render
		aktables.appendChild(uielement);
	}

};

/**
 * Loads the tabular view of the Database Filter for a given root
 * @param root
 * @return
 */
akeeba.Dbfilters.loadTab = function (root)
{
	var data = {
		verb: "tab",
		root: root
	};

	// Assemble the data array and send the AJAX request
	var new_data = {
		akaction: JSON.stringify(data)
	};

	akeeba.System.doAjax(new_data, function (response)
	{
		akeeba.Dbfilters.renderTab(response);
	}, null, false, 15000);
};

/**
 * Add a row in the tabular view of the Filesystems Filter
 * @param def
 * @param append_to_here
 * @return
 */
akeeba.Dbfilters.addRow = function (def, append_to_here)
{
	// Turn def.type into something human readable
	var type_text = akeeba.System.Text._("COM_AKEEBA_DBFILTER_TYPE_" + def.type.toUpperCase());

	if (type_text == null)
	{
		type_text = def.type;
	}

	var elRow        = document.createElement("tr");
	var elFilterType = document.createElement("td");
	var elFilterItem = document.createElement("td");

	elRow.className = "ak_filter_row";

	// Filter title
	elFilterType.className   = "ak_filter_type";
	elFilterType.textContent = type_text;

	// Filter item
	elFilterItem.className = "ak_filter_item";

	// delete button, edit button, filter name
	var elDeleteContainer = document.createElement("span");
	var elEditContainer   = document.createElement("span");
	var elFilterName      = document.createElement("span");

	elDeleteContainer.className = "ak_filter_tab_icon_container akeeba-btn--mini akeeba-btn--red";
	akeeba.System.addEventListener(elDeleteContainer, "click", function ()
	{
		if (def.node == "")
		{
			// An empty filter is normally not saved to the database; it's a new record row which has to be removed...
			var elRemove = this.parentNode.parentNode;
			elRemove.parentNode.removeChild(elRemove);

			return;
		}

		var elRoot   = document.getElementById("active_root");
		var new_data = {
			root:   elRoot.options[elRoot.selectedIndex].value,
			node:   def.node,
			filter: def.type,
			verb:   "remove"
		};

		akeeba.Dbfilters.toggle(new_data, this, function (response, caller)
		{
			if (response.success)
			{
				var elRemove = caller.parentNode.parentNode;
				elRemove.parentNode.removeChild(elRemove);
			}
		});
	});

	var elDeleteIcon       = document.createElement("span");
	elDeleteIcon.className = "ak-toggle-button akion-ios-trash deletebutton";
	elDeleteContainer.appendChild(elDeleteIcon);

	elEditContainer.className = "ak_filter_tab_icon_container akeeba-btn--mini akeeba-btn--teal";
	akeeba.System.addEventListener(elEditContainer, "click", function ()
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
			var elRoot   = document.getElementById("active_root");
			var new_data = {
				root:     elRoot.options[elRoot.selectedIndex].value,
				old_node: def.node,
				new_node: new_value,
				filter:   def.type,
				verb:     "swap"
			};

			var elEditContainer = that.parentNode.querySelector("span.editcontainer");

			akeeba.Dbfilters.toggle(
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

	elEditIcon           = document.createElement("span");
	elEditIcon.className = "ak-toggle-button akion-edit editbutton";
	elEditContainer.appendChild(elEditIcon);

	elFilterName.className   = "ak_filter_name";
	elFilterName.textContent = def.node;

	elFilterItem.appendChild(elDeleteContainer);
	elFilterItem.appendChild(elEditContainer);
	elFilterItem.appendChild(elFilterName);

	elRow.appendChild(elFilterType);
	elRow.appendChild(elFilterItem);

	append_to_here.appendChild(elRow);
};

akeeba.Dbfilters.addNew = function (filtertype)
{
	// Add a row below ourselves
	var new_def = {
		type: filtertype,
		node: ""
	};
	akeeba.Dbfilters.addRow(new_def, document.getElementById("ak_list_table").children[1]);

	var trList = document.getElementById("ak_list_table").children[1].children;
	var lastTr = trList[trList.length - 1];
	akeeba.System.triggerEvent(lastTr.querySelector("span.editbutton"), "click");
};

/**
 * Renders the tabular view of the Database Filter
 * @param data
 * @return
 */
akeeba.Dbfilters.renderTab = function (data)
{
	const tbody       = document.getElementById("ak_list_contents");
	tbody.innerHTML = "";

	if (data?.list?.length)
	{
		data.list.forEach(def => akeeba.Dbfilters.addRow(def, tbody));
	}
};

/**
 * Activates the exclusion filters for non-CMS tables
 */
akeeba.Dbfilters.excludeNonCMS = function ()
{
	var tables = document.getElementById("tables").children;

	for (counter = 0; counter < tables.length; counter++)
	{
		var element = tables[counter];

		// Get the table name
		var tablename = element.querySelector("span.table-name").textContent;
		var prefix    = tablename.substr(0, 3);

		// If the prefix is not #__ it's a core table and I have to exclude it
		if (prefix != "#__")
		{
			var iconContainer = element.querySelector("span.table-icon-container");
			var icon          = iconContainer.querySelector("span.ak-toggle-button");

			if (iconContainer.className.indexOf("akeeba-btn--orange") == -1)
			{
				akeeba.System.triggerEvent(icon, "click");
			}
		}
	}
};

/**
 * Wipes out the database filters
 * @return
 */
akeeba.Dbfilters.nuke = function ()
{
	var data     = {
		root: akeeba.Dbfilters.currentRoot,
		verb: "reset"
	};
	var new_data = {
		akaction: JSON.stringify(data)
	};
	akeeba.System.doAjax(new_data, function (response)
	{
		akeeba.Dbfilters.render(response);
	}, null, false, 15000);
};

akeeba.System.documentReady(function ()
{
	var guiData                  = akeeba.System.getOptions("akeeba.DatabaseFilters.guiData", null);
	var viewType                 = akeeba.System.getOptions("akeeba.DatabaseFilters.viewType", null);

	// Take into account the different view types
	if (viewType === "list")
	{
		// List view
		akeeba.System.addEventListener("active_root", "change", akeeba.Dbfilters.activeRootChanged);
		akeeba.Dbfilters.render(guiData);
	}
	else
	{
		// Tabular view
		akeeba.System.addEventListener("active_root", "change", akeeba.Dbfilters.activeTabRootChanged);
		akeeba.Dbfilters.renderTab(guiData);
	}

	akeeba.System.addEventListener("comAkeebaDatabaseFiltersExcludeNonCMS", "click", function ()
	{
		akeeba.Dbfilters.excludeNonCMS();

		return false;
	});

	akeeba.System.addEventListener("comAkeebaDatabaseFiltersNuke", "click", function ()
	{
		akeeba.Dbfilters.nuke();

		return false;
	});

	akeeba.System.addEventListener("comAkeebaDatabaseFiltersAddNewTables", "click", function ()
	{
		akeeba.Dbfilters.addNew("tables");

		return false;
	});

	akeeba.System.addEventListener("comAkeebaDatabaseFiltersAddNewTableData", "click", function ()
	{
		akeeba.Dbfilters.addNew("tabledata");

		return false;
	});
});