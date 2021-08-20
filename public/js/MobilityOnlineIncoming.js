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
				let incomingElem = $("#applications input[type=checkbox]:checked");
				let incomings = [];
				incomingElem.each(
					function()
					{
						for (let incoming in MobilityOnlineIncoming.incomings)
						{
							let moInc = MobilityOnlineIncoming.incomings[incoming];
							if (moInc.moid == $(this).val())
							{
								incomings.push(moInc);
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
							FHC_DialogLib.alertError("Maximale POST Größe nicht numerisch!");
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
							let incomingObj = incomings[incoming];
							let incomingData = incomingObj.data;

							let person = incomingData.person;
							let hasError = incomingObj.error;
							let chkbxString, stgNotSetTxt, errorClass, newIcon;
							chkbxString = stgNotSetTxt = errorClass = "";

							// show errors in tooltip if sync not possible
							if (hasError)
							{
								errorClass = " class='inactive' data-toggle='tooltip' title='";
								let firstMsg = true;
								for (let i in incomingObj.errorMessages)
								{
									if (!firstMsg)
										errorClass += ', ';
									errorClass += incomingObj.errorMessages[i];
									firstMsg = false;
								}
								errorClass += "'";
							}
							else
							{
								chkbxString = "<input type='checkbox' value='" + incomingObj.moid + "' name='applications[]'>";
							}

							// courses from MobilityOnline
							let coursesString = '';
							let firstCourse = true;

							for (let courseIdx in incomingData.mocourses)
							{
								let course = incomingData.mocourses[courseIdx];
								if (!firstCourse)
									coursesString += ' | ';
								coursesString += course.number + ': ' + course.name;
								firstCourse = false;
							}

							if (incomingObj.infhc)
							{
								newIcon = "<i id='infhcicon_"+incomingObj.moid+"' class='fa fa-check'></i><input type='hidden' id='infhc_"+incomingObj.moid+"' class='infhc' value='1'>";
							}
							else
							{
								newIcon = "<i id='infhcicon_"+incomingObj.moid+"' class='fa fa-times'></i><input type='hidden' id='infhc_"+incomingObj.moid+"' class='infhc' value='0'>";
							}

							$("#applications").append(
								"<tr" + errorClass + ">" +
								"<td class='text-center'>" + chkbxString + "</td>" +
								"<td>" + person.nachname + ", " + person.vorname + "</td>" +
								"<td>" + incomingData.kontaktmail.kontakt + "</td>" +
								"<td>" + incomingData.pipelineStatusDescription + "</td>" +
								"<td>" + coursesString + "</td>" +
								"<td class='text-center'>" + newIcon + "</td>" +
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
						$("#applicationsyncoutputtext").html("<div class='text-center'>Keine Incomings gefunden!</div>");
					}
				},
				errorCallback: function()
				{
					$("#applicationsyncoutputtext").html("<div class='text-center'>Fehler beim Holen der Incomings!</div>");
				}
			}
		);
	},
	syncIncomings: function(incomings, studiensemester, maxPostSize)
	{
		let incomingJson = JSON.stringify(incomings);

		// post data might be too big - then split in in half. factor 3.5 approx. scales up to actual data size
		let postLength = incomingJson.length + 3.5 * incomings.length;

		if (postLength > maxPostSize)
		{
			let indexHalf = incomings.length / 2;
			let incomingsPartOne = incomings.splice(0, indexHalf);
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
							let syncRes = FHC_AjaxClient.getData(data);

							MobilityOnlineApplicationsHelper.writeSyncOutput(syncRes.syncoutput);

							if ($("#applicationsyncoutputheading").text().length > 0)
							{
								$("#nradd").text(parseInt($("#nradd").text()) + syncRes.added);
								$("#nrupdate").text(parseInt($("#nrupdate").text()) + syncRes.updated);
							}
							else
							{
								$("#applicationsyncoutputheading")
									.append("<br />MOBILITY ONLINE INCOMINGS SYNC ENDE<br /><span id = 'nradd'>"+syncRes.added+"</span> hinzugefügt, <span id = 'nrupdate'>"+syncRes.updated+"</span> aktualisiert</div>")
									.append("<br />-----------------------------------------------<br />");
							}
							MobilityOnlineIncoming.refreshIncomingsSyncStatus();
						}
					},
					errorCallback: function()
					{
						$("#applicationsyncoutputtext").html("<div class='text-center'>Fehler beim Synchronisieren!</div>");
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
		let moidSel = $("#applications input[name='applications[]']");
		let moIds = [];

		$(moidSel).each(
			function()
			{
				moIds.push($(this).val());
			}
		);

		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/checkMoidsInFhc',
			{
				"moids": moIds
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						let moIdRes = FHC_AjaxClient.getData(data)

						for (let moId in moIdRes)
						{
							let prestudent_id = moIdRes[moId];
							let inFhc = $.isNumeric(prestudent_id);

							// refresh JS array
							for (let incoming in MobilityOnlineIncoming.incomings)
							{
								let incomingObj = MobilityOnlineIncoming.incomings[incoming];

								if (incomingObj.moid === parseInt(moId))
								{
									if (inFhc)
									{
										incomingObj.infhc = true;
										incomingObj.prestudent_id = prestudent_id;
									}
									else
									{
										incomingObj.infhc = false;
									}
									break;
								}
							}

							// refresh Incomings Table "in FHC" field
							let inFhcIconEl = $("#infhcicon_" + moId);
							let inFhcEl = $("#infhc_" + moId);

							inFhcIconEl.removeClass();
							if (inFhc)
							{
								inFhcEl.val("1");
								inFhcIconEl.addClass("fa fa-check");
							}
							else
							{
								inFhcEl.val("0");
								inFhcIconEl.addClass("fa fa-times");
							}
						}
					}
				},
				errorCallback: function()
				{
					FHC_DialogLib.alertError("Fehler beim Aktualisieren des Sync Status!");
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
					FHC_DialogLib.alertError("Fehler beim Holen der maximalen POST Größe!");
				}
			}
		);
	}
};
