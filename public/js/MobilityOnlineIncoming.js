/**
 * javascript file for Mobility Online incoming sync
 */

$(document).ready(function()
	{
		MobilityOnlineIncoming.getIncoming($("#studiensemester").val(), $("#studiengang_kz").val());

		// get Incomings when Dropdown selected
		let getIncomingFunc = function()
		{
			let studiensemester = $("#studiensemester").val();
			let studiengang_kz = $("#studiengang_kz").val();
			MobilityOnlineApplicationsHelper.resetSyncOutput();
			MobilityOnlineIncoming.getIncoming(studiensemester, studiengang_kz);
		}

		// get Outgoings when Dropdown selected
		$("#studiensemester,#studiengang_kz").change(
			getIncomingFunc
		);

		$("#refreshBtn").click(
			getIncomingFunc
		);

		//init sync
		$("#applicationsyncbtn").click(
			function()
			{
				let incomingelem = $("#applications input[type=checkbox]:checked");
				let incomings = [];
				incomingelem.each(
					function()
					{
						for (let incoming in MobilityOnlineIncoming.incomings)
						{
							let moinc = MobilityOnlineIncoming.incomings[incoming];
							if (moinc.moid == $(this).val())
							{
								incomings.push(moinc);
								break;
							}
						}
					}
				);

				let syncIncomingsFunc = function (data) {
					if (FHC_AjaxClient.hasData(data))
					{
						let maxPostSize = FHC_AjaxClient.getData(data);
						if ($.isNumeric(maxPostSize))
						{
							maxPostSize = (parseInt(maxPostSize));
							$("#applicationsyncoutput div").empty();

							MobilityOnlineIncoming.syncIncomings(incomings, $("#studiensemester").val(), maxPostSize);
						}
						else
						{
							FHC_DialogLib.alertError("non-numeric post max size!");
						}
					}
				};

				MobilityOnlineIncoming.getPostMaxSize(syncIncomingsFunc);
			}
		);

		//select all incoming checkboxes
		MobilityOnlineApplicationsHelper.setSelectAllApplicationsEvent();
		//select incoming checkboxes which are not in FHC yet
		MobilityOnlineApplicationsHelper.setSelectNewApplicationsEvent();
	}
);

var MobilityOnlineIncoming = {
	incomings: null,
	getIncoming: function(studiensemester, studiengang_kz)
	{
		if (studiensemester == null || studiensemester === "" || studiengang_kz == null
			|| (!$.isNumeric(studiengang_kz) && studiengang_kz !== "all"))
			return;

		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getIncomingJson',
			{
				"studiensemester": studiensemester,
				"studiengang_kz": studiengang_kz
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					$("#applications").empty();

					if (FHC_AjaxClient.hasData(data))
					{
						let incomings = FHC_AjaxClient.getData(data);
						MobilityOnlineIncoming.incomings = incomings;

						for (let incoming in incomings)
						{
							let incomingobj = incomings[incoming];
							let incomingdata = incomingobj.data;

							let person = incomingdata.person;
							let hasError = incomingobj.error;
							let chkbxstring, stgnotsettxt, errorclass, newicon;
							chkbxstring = stgnotsettxt = errorclass = "";

							// show errors in tooltip if sync not possible
							if (hasError)
							{
								errorclass = " class='inactive' data-toggle='tooltip' title='";
								let firstmsg = true;
								for (let i in incomingobj.errorMessages)
								{
									if (!firstmsg)
										errorclass += ', ';
									errorclass += incomingobj.errorMessages[i];
									firstmsg = false;
								}
								errorclass += "'";
							}
							else
							{
								chkbxstring = "<input type='checkbox' value='" + incomingobj.moid + "' name='applications[]'>";
							}

							// courses from MobilityOnline
							let coursesstring = '';
							let firstcourse = true;

							for (let courseidx in incomingdata.mocourses)
							{
								let course = incomingdata.mocourses[courseidx];
								if (!firstcourse)
									coursesstring += ' | ';
								coursesstring += course.number + ': ' + course.name;
								firstcourse = false;
							}

							if (incomingobj.infhc)
							{
								newicon = "<i id='infhcicon_"+incomingobj.moid+"' class='fa fa-check'></i><input type='hidden' id='infhc_"+incomingobj.moid+"' class='infhc' value='1'>";
							}
							else
							{
								newicon = "<i id='infhcicon_"+incomingobj.moid+"' class='fa fa-times'></i><input type='hidden' id='infhc_"+incomingobj.moid+"' class='infhc' value='0'>";
							}

							$("#applications").append(
								"<tr" + errorclass + ">" +
								"<td class='text-center'>" + chkbxstring + "</td>" +
								"<td>" + person.nachname + ", " + person.vorname + "</td>" +
								"<td>" + incomingdata.kontaktmail.kontakt + "</td>" +
								"<td>" + incomingdata.pipelineStatusDescription + "</td>" +
								"<td>" + coursesstring + "</td>" +
								"<td class='text-center'>" + newicon + "</td>" +
								"</tr>"
							);

							$("#applications input[type=checkbox][name='applications[]']").change(
								MobilityOnlineApplicationsHelper.refreshApplicationsNumber
							);
							MobilityOnlineApplicationsHelper.refreshApplicationsNumber();
						}
						let headers = {headers: { 0: { sorter: false, filter: false}, 5: {sorter: false, filter: false} }};

						Tablesort.addTablesorter("applicationstbl", [[1, 0], [2, 0]], ["filter"], 2, headers);
					}
					else
					{
						$("#applicationsyncoutputtext").html("<div class='text-center'>No incomings found!</div>");
					}
				},
				errorCallback: function()
				{
					$("#applicationsyncoutputtext").html("<div class='text-center'>error occured while getting incomings!</div>");
				}
			}
		);
	},
	syncIncomings: function(incomings, studiensemester, maxPostSize)
	{
		let incomingJson = JSON.stringify(incomings);

		// post data might be too big - then split in in half. factor 3.5 approx. scales up to actual data size
		let postlength = incomingJson.length + 3.5 * incomings.length;

		if (postlength > maxPostSize)
		{
			let indexhalf = incomings.length / 2;
			let incomingsPartOne = incomings.splice(0, indexhalf);
			let incomingsPartTwo = incomings;
			MobilityOnlineIncoming.syncIncomings(incomingsPartOne, studiensemester, maxPostSize);
			MobilityOnlineIncoming.syncIncomings(incomingsPartTwo, studiensemester, maxPostSize);
		}
		else
		{
			FHC_AjaxClient.ajaxCallPost(
				FHC_JS_DATA_STORAGE_OBJECT.called_path + '/syncIncomings',
				{
					"incomings": JSON.stringify(incomings),
					"studiensemester": studiensemester
				},
				{
					successCallback: function (data, textStatus, jqXHR) {
						if (FHC_AjaxClient.hasData(data))
						{
							let syncres = FHC_AjaxClient.getData(data);

							MobilityOnlineApplicationsHelper.writeSyncOutput(syncres.syncoutput);

							if ($("#applicationsyncoutputheading").text().length > 0)
							{
								$("#nradd").text(parseInt($("#nradd").text()) + syncres.added);
								$("#nrupdate").text(parseInt($("#nrupdate").text()) + syncres.updated);
							}
							else
							{
								$("#applicationsyncoutputheading")
									.append("<br />MOBILITY ONLINE INCOMINGS SYNC FINISHED<br /><span id = 'nradd'>"+syncres.added+"</span> added, <span id = 'nrupdate'>"+syncres.updated+"</span> updated</div>")
									.append("<br />-----------------------------------------------<br />");
							}
							MobilityOnlineIncoming.refreshIncomingsSyncStatus();
						}
					},
					errorCallback: function()
					{
						$("#applicationsyncoutputtext").html("<div class='text-center'>error occured while syncing!</div>");
					}
				}
			);
		}
	},
	/**
	 * Refreshes status (infhc, not in fhc) of incomings
	 */
	refreshIncomingsSyncStatus: function()
	{
		let moidsel = $("#applications input[name='applications[]']");
		let moids = [];

		$(moidsel).each(
			function()
			{
				moids.push($(this).val());
			}
		);

		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/checkMoidsInFhc',
			{
				"moids": moids
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						let moidres = FHC_AjaxClient.getData(data)

						for (let moid in moidres)
						{
							let prestudent_id = moidres[moid];
							let infhc = $.isNumeric(prestudent_id);

							// refresh JS array
							for (let incoming in MobilityOnlineIncoming.incomings)
							{
								let incomingobj = MobilityOnlineIncoming.incomings[incoming];

								if (incomingobj.moid === parseInt(moid))
								{
									if (infhc)
									{
										incomingobj.infhc = true;
										incomingobj.prestudent_id = prestudent_id;
									}
									else
									{
										incomingobj.infhc = false;
									}
									break;
								}
							}

							// refresh Incomings Table "in FHC" field
							let infhciconel = $("#infhcicon_" + moid);
							let infhcel = $("#infhc_" + moid);

							infhciconel.removeClass();
							if (infhc)
							{
								infhcel.val("1");
								infhciconel.addClass("fa fa-check");
							}
							else
							{
								infhcel.val("0");
								infhciconel.addClass("fa fa-times");
							}
						}
					}
				},
				errorCallback: function()
				{
					FHC_DialogLib.alertError("error when refreshing FHC column!");
				}
			}
		);
	},
	getPostMaxSize: function(callback)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getPostMaxSize',
			null,
			{
				successCallback: callback,
				errorCallback: function()
				{
					FHC_DialogLib.alertError("error when getting post max size!");
				}
			}
		);
	}
};
