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

if (typeof akeeba.Extradirs == "undefined")
{
	akeeba.Extradirs = {};
}

akeeba.Extradirs.render = function (data)
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

		akeeba.Extradirs.addRow(rootname, def, tbody);
	}

	akeeba.Extradirs.addNewRecordButton(tbody);
};

akeeba.Extradirs.addRow = function (rootuuid, def, append_to_here)
{
	var elTr       = document.createElement("tr");
	elTr.className = "ak_filter_row";

	// Cache UUID of this entry
	akeeba.System.data.set(elTr, "rootuuid", rootuuid);
	// Cache the definition data (virtual directory)
	akeeba.System.data.set(elTr, "def", def);

	var elDeleteContainer = document.createElement("td");
	var elEditContainer   = document.createElement("td");
	var elDirPath         = document.createElement("td");
	var elVirtualPath     = document.createElement("td");

	// Delete button
	var elDeleteSpan       = document.createElement("span");
	elDeleteSpan.className = "ak_filter_tab_icon_container akeeba-btn--red--mini delete";
	akeeba.System.addEventListener(elDeleteSpan, "click", function ()
	{
		var new_data = {
			uuid: akeeba.System.data.get(this.parentNode.parentNode, "rootuuid"), verb: "remove"
		};

		akeeba.Fsfilters.toggle(new_data, this, function (response, caller)
		{
			if (response.success == true)
			{
				var elRemove = caller.parentNode.parentNode;
				elRemove.parentNode.removeChild(elRemove);
			}
		});
	});

	elDeleteSpan.insertAdjacentHTML("beforeend", "<span class=\"akion-trash-a deletebutton ak-toggle-button\"></span>");
	elDeleteContainer.appendChild(elDeleteSpan);

	// Edit button
	var elEditSpan       = document.createElement("span");
	elEditSpan.className = "ak_filter_tab_icon_container akeeba-btn--teal--mini";

	akeeba.System.addEventListener(elEditSpan, "click", function ()
	{
		// Get reference to data root
		var data_root = this.parentNode.parentNode;

		// Hide pencil icon
		this.style.display = "none";

		// Hide delete icon
		data_root.querySelector("span.delete").style.display = "none";

		var elTd = this.parentNode;
		var elTr = elTd.parentNode;

		// Add a disk icon (save)
		var elDiskIcon       = document.createElement("span");
		elDiskIcon.className =
			"ak_filter_tab_icon_container akeeba-btn--teal--mini save ak-toggle-button ak-stacked-button";
		elDiskIcon.insertAdjacentHTML("beforeend", "<span class=\"akion-checkmark\"></span>");

		akeeba.System.addEventListener(elDiskIcon, "click", function ()
		{
			var that = this;

			var new_directory = data_root.querySelector("input.folder_editor").value;
			new_directory     = trim(new_directory);

			var add_dir = data_root.querySelector("input.virtual_editor").value;
			add_dir     = trim(add_dir);

			if (empty(add_dir))
			{
				add_dir = Math.uuid(8) + "-" + basename(new_directory);
			}

			var old_data = akeeba.System.data.get(data_root, "def", ",");
			old_data     = old_data.split(",", 2);

			if (new_directory == "")
			{
				if (old_data[0] == "")
				{
					// Tried to save empty data on new row. That's like Cancel...
					akeeba.System.triggerEvent(that.parentNode.querySelector("span.cancel"), "click");
				}
				else
				{
					// Tried to save empty data on existing row. That's like Delete...
					var elDelete           = data_root.querySelector("span.delete");
					elDelete.style.display = "inline-block";
					akeeba.System.triggerEvent(elDelete, "click");
				}
			}
			else
			{
				// Save entry
				var new_data = {
					uuid: akeeba.System.data.get(data_root, "rootuuid"), root: new_directory, data: add_dir, verb: "set"
				};

				akeeba.Fsfilters.toggle(new_data, that, function (response, caller)
				{
					if (response.success == true)
					{
						// Catch case of new row
						if (old_data[0] == "")
						{
							// Change icon to pencil
							var elIcon = caller.parentNode.querySelector("span.editbutton");
							akeeba.System.removeClass(elIcon, "akion-plus");
							akeeba.System.addClass(elIcon, "akion-edit ak-toggle-button");

							// Add new row
							akeeba.Extradirs.addNewRecordButton(append_to_here);
						}

						// Update cached data
						var new_cache_data = [new_directory, add_dir];
						akeeba.System.data.set(data_root, "def", new_cache_data);

						// Update values in table
						console.debug(data_root);
						data_root.querySelector("span.ak_directory").textContent = new_directory;
						data_root.querySelector("span.ak_virtual").textContent   = add_dir;

						// Show pencil icon
						caller.parentNode.querySelector(
							"span.ak_filter_tab_icon_container").style.display = "inline-block";

						// Remove cancel icon
						var elRemove = caller.parentNode.querySelector("span.cancel");
						elRemove.parentNode.removeChild(elRemove);

						// Show the delete button
						data_root.querySelector("span.delete").style.display = "inline-block";

						// Remove disk icon
						caller.parentNode.removeChild(caller);

						// Remove input boxes
						elRemove = data_root.querySelector("input.folder_editor");
						elRemove.parentNode.removeChild(elRemove);

						elRemove = data_root.querySelector("input.virtual_editor");
						elRemove.parentNode.removeChild(elRemove);

						// Remove browser button
						elRemove = data_root.querySelector("span.browse");
						elRemove.parentNode.removeChild(elRemove);

						// Show values
						data_root.querySelector("span.ak_directory").style.display = "inline-block";
						data_root.querySelector("span.ak_virtual").style.display   = "inline-block";
					}
				}, false);
			}
		});

		elTd.appendChild(elDiskIcon);

		// Add a Cancel icon
		var elCancelIcon       = document.createElement("span");
		elCancelIcon.className = "ak_filter_tab_icon_container akeeba-btn--orange--mini cancel ak-toggle-button";
		elCancelIcon.insertAdjacentHTML("beforeend", "<span class=\"akion-close \"></span>");

		akeeba.System.addEventListener(elCancelIcon, "click", function ()
		{
			var that                                                                         = this;
			// Show pencil icon
			that.parentNode.querySelector("span.ak_filter_tab_icon_container").style.display = "inline-block";
			// Remove disk icon
			var elRemove                                                                     = that.parentNode.querySelector(
				"span.save");
			elRemove.parentNode.removeChild(elRemove);

			// Remove cancel icon
			that.parentNode.removeChild(that);

			// Remove input boxes
			elRemove = data_root.querySelector("input.folder_editor");
			elRemove.parentNode.removeChild(elRemove);

			elRemove = data_root.querySelector("input.virtual_editor");
			elRemove.parentNode.removeChild(elRemove);

			// Remove browser button
			elRemove = data_root.querySelector("span.browse");
			elRemove.parentNode.removeChild(elRemove);

			// Show values
			data_root.querySelector("span.ak_directory").style.display = "inline-block";
			data_root.querySelector("span.ak_virtual").style.display   = "inline-block";

			// Show the delete button (if it's NOT a new row)
			var old_data = akeeba.System.data.get(data_root, "def", ",");
			old_data     = old_data.split(",", 2);

			if (old_data[0] != "")
			{
				data_root.querySelector("span.delete").style.display = "inline-block";
			}

		});

		elTd.appendChild(elCancelIcon);

		// Show edit box
		var old_data           = akeeba.System.data.get(data_root, "def", ",");
		old_data               = old_data.split(",", 2);
		var elFilterContainer  = elTr.querySelector("td.ak_filter_item");
		var elVirtualContainer = elFilterContainer.nextElementSibling;

		// -- Show input element for the filter (folder to include)
		var elFilterInput = document.createElement("input");
		elFilterInput.setAttribute("type", "text");
		elFilterInput.setAttribute("size", "60");
		elFilterInput.className = "folder_editor";
		elFilterInput.value     = old_data[0];

		// -- Show browser button
		var elBrowser       = document.createElement("span");
		elBrowser.className = "ak_filter_tab_icon_container akeeba-btn--dark--mini browse ak-toggle-button";
		elBrowser.insertAdjacentHTML("beforeend", "<span class=\"akion-folder\"></span>");

		akeeba.System.addEventListener(elBrowser, "click", function ()
		{
			var that   = this;
			// Show folder open dialog
			var editor = that.parentNode.querySelector("input.folder_editor");
			var val    = trim(editor.value);

			if (val == "")
			{
				val = "[ROOTPARENT]";
			}

			akeeba.Configuration.onBrowser(val, editor);
		});

		elFilterContainer.appendChild(elFilterInput);
		elFilterContainer.appendChild(elBrowser);

		var elVirtualInput = document.createElement("input");

		elVirtualInput.setAttribute("type", "text");
		elVirtualInput.setAttribute("size", "60");
		elVirtualInput.className = "virtual_editor";
		elVirtualInput.value     = old_data[1];

		elVirtualContainer.appendChild(elVirtualInput);

		// Hide existing value boxes
		elFilterContainer.querySelector("span.ak_directory").style.display = "none";
		elVirtualContainer.querySelector("span.ak_virtual").style.display  = "none";
	});

	elEditSpan.insertAdjacentHTML("beforeend", "<span class=\"akion-edit editbutton ak-toggle-button\"></span>");
	elEditContainer.appendChild(elEditSpan);

	// Directory path
	elDirPath.className          = "ak_filter_item";
	var elFilterNameSpan         = document.createElement("span");
	elFilterNameSpan.className   = "ak_filter_name ak_directory";
	elFilterNameSpan.textContent = def[0];
	elDirPath.appendChild(elFilterNameSpan);

	// Virtual path
	elVirtualPath.className       = "ak_filter_item";
	var elVirtualNameSpan         = document.createElement("span");
	elVirtualNameSpan.className   = "ak_filter_name ak_virtual";
	elVirtualNameSpan.textContent = def[1];
	elVirtualPath.appendChild(elVirtualNameSpan);

	elTr.appendChild(elDeleteContainer);
	elTr.appendChild(elEditContainer);
	elTr.appendChild(elDirPath);
	elTr.appendChild(elVirtualPath);

	append_to_here.appendChild(elTr);
};

akeeba.Extradirs.addNewRecordButton = function (append_to_here)
{
	var newUUID   = Math.uuid();
	var dummyData = ["", ""];
	akeeba.Extradirs.addRow(newUUID, dummyData, append_to_here);

	var trList = document.getElementById("ak_list_contents").children;
	var lastTr = trList[trList.length - 1];

	var tdList = lastTr.querySelectorAll("td");

	tdList[0].querySelector("span").style.display = "none";

	var spanList = tdList[1].querySelectorAll("span");
	var elPencil = spanList[spanList.length - 1];
	akeeba.System.removeClass(elPencil, "akion-edit");
	akeeba.System.addClass(elPencil, "akion-plus ak-toggle-button");
};

akeeba.System.documentReady(function ()
{
	var guiData = akeeba.System.getOptions("akeeba.IncludeFolders.guiData", null);

	if (guiData === null)
	{
		return;
	}

	akeeba.Configuration.enablePopoverFor(document.querySelectorAll("[rel=\"popover\"]"));
	akeeba.Extradirs.render(guiData);
});