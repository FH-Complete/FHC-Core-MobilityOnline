/**
 * javascript file for Mobility Online outgoing course sync
 */

$(document).ready(function()
	{
		// get outgoings with courses
		MobilityOnlineOutgoingCourses.getOutgoingCourses($("#studiensemester").val(), $("#studiengang_kz").val());

		let getOutgoingFunc = function()
		{
			let studiensemester = $("#studiensemester").val();
			let studiengang_kz = $("#studiengang_kz").val();
			MobilityOnlineApplicationsHelper.resetSyncOutput();
			MobilityOnlineOutgoingCourses.getOutgoingCourses(studiensemester, studiengang_kz);
		}

		// get outgoings with courses when Dropdown selected
		$("#studiensemester,#studiengang_kz").change(
			getOutgoingFunc
		);

		$("#refreshBtn").click(
			getOutgoingFunc
		);

		//init sync
		$("#applicationsyncbtn").click(
			function()
			{
				let coursesElem = $("#applications input[type=checkbox]:checked");
				let courses = [];
				coursesElem.each(
					function()
					{
						courses.push(MobilityOnlineOutgoingCourses._findCourseByMoid($(this).val())[0]);
					}
				);

				$("#applicationsyncoutput div").empty();

				console.log(courses);

				MobilityOnlineOutgoingCourses.syncOutgoingCourses(courses, $("#studiensemester").val());
			}
		);

		//select all outgoing courses checkboxes
		MobilityOnlineApplicationsHelper.setSelectAllApplicationsEvent();
		//select outgoing courses which are not in FHC yet
		MobilityOnlineApplicationsHelper.setSelectNewApplicationsEvent();
	}
);

var MobilityOnlineOutgoingCourses = {
	outgoings: null,
	getOutgoingCourses: function(studiensemester, studiengang_kz)
	{
		if (studiensemester == null || studiensemester === "" || studiengang_kz == null
			|| (!$.isNumeric(studiengang_kz) && studiengang_kz !== "all"))
			return;

		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getOutgoingCoursesJson',
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
						let outgoings = FHC_AjaxClient.getData(data);
						MobilityOnlineOutgoingCourses.outgoings = outgoings;

						// show the courses
						MobilityOnlineOutgoingCourses._showKurse();

						// courses selected via checkboxes
						$("#applications input[type=checkbox][name='applications[]']").change(
							MobilityOnlineApplicationsHelper.refreshApplicationsNumber
						);
						MobilityOnlineApplicationsHelper.refreshApplicationsNumber();
					}
					else
					{
						$("#applicationsyncoutputtext").html("<div class='text-center'>Keine Outgoings gefunden!</div>");
					}
				},
				errorCallback: function()
				{
					$("#applicationsyncoutputtext").html("<div class='text-center'>Fehler beim Holen der Outgoings mit Kursen!</div>");
				}
			}
		);
	},
	syncOutgoingCourses: function(outgoingCourses, studiensemester)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/syncOutgoingCourses',
			{
				"outgoingCourses": JSON.stringify(outgoingCourses),
				"studiensemester": studiensemester
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						let syncRes = FHC_AjaxClient.getData(data);

						MobilityOnlineApplicationsHelper.writeSyncOutput(syncRes.syncoutput);

						if ($("#applicationsyncoutputheading").text().length > 0)
						{
							$("#nradd").text(parseInt($("#nradd").text()) + syncRes.added.length);
							$("#nrupdate").text(parseInt($("#nrupdate").text()) + syncRes.updated.length);
						}
						else
						{
							$("#applicationsyncoutputheading")
								.append("<br />MOBILITY ONLINE OUTGOING SYNC ENDE<br />"+
									"<span id = 'nradd'>" +syncRes.added.length + "</span> hinzugefügt, "+
									"<span id = 'nrupdate'>" + syncRes.updated.length + "</span> aktualisiert</div>")
								.append("<br />-----------------------------------------------<br />");
						}

						MobilityOnlineOutgoingCourses.refreshOutgoingsSyncStatus(syncRes.added.concat(syncRes.updated));
					}
				},
				errorCallback: function()
				{
					$("#applicationsyncoutputtext").html(
						MobilityOnlineApplicationsHelper.getMessageHtml("Fehler beim Synchronisieren!", "error")
					);
				}
			}
		);
	},
	/**
	 * Refreshes status (infhc, not in fhc) of outgoing courses
	 */
	refreshOutgoingsSyncStatus: function(synced_moids)
	{
		console.log(synced_moids);
		for (let idx in synced_moids)
		{
			let moId = synced_moids[idx];

			console.log(MobilityOnlineOutgoingCourses.outgoings);

			// refresh JS array
			for (let outgoing in MobilityOnlineOutgoingCourses.outgoings)
			{
				let outgoingsObj = MobilityOnlineOutgoingCourses.outgoings[outgoing];

				outgoingData = outgoingsObj.data;

				for (let kurs in outgoingData.kurse)
				{
					if (outgoingData.kurse[kurs].mo_outgoing_lv == parseInt(moId))
					{
						outgoingData.kurse[kurs].infhc = true;
					}
				}
			}
			console.log(MobilityOnlineOutgoingCourses.outgoings);

			// refresh courses "in FHC" field
			let inFhcIconEl = $("#infhcicon_" + moId);
			let inFhcEl = $("#infhc_" + moId);

			inFhcIconEl.removeClass();
			inFhcEl.val("1");
			inFhcIconEl.addClass("fa fa-check");
		}
	},
	_showKurse: function()
	{
		let numCourses = 0;
		for (let outgoingIdx in MobilityOnlineOutgoingCourses.outgoings)
		{
			let outgoing = MobilityOnlineOutgoingCourses.outgoings[outgoingIdx];
			let errorTexts = [];

			// show errors in tooltip if sync not possible
			if (outgoing.error)
			{
				for (let i in outgoing.errorMessages)
				{
					errorTexts.push(outgoing.errorMessages[i]);
				}
			}

			let person = outgoing.data.person;
			let kontaktmail = outgoing.data.kontaktmail;
			let kurse = outgoing.data.kurse;
			numCourses += kurse.length;
			for (let krs in kurse)
			{
				let kurs = kurse[krs];
				let kursinfo = kurs.kursinfo;
				let mo_outgoing_lv = kurs.mo_outgoing_lv;
				let mo_lvid = mo_outgoing_lv.mo_lvid;

				// display errors from application and courses
				let allErrorTexts = errorTexts;
				if (kursinfo.error)
				{
					allErrorTexts = allErrorTexts.concat(kursinfo.errorMessages);
				}

				// show row selection checkbox
				let inactive = "";
				let tooltip = "";
				let chkbxString = "";
				if (allErrorTexts.length > 0)
				{
					inactive = " inactive";
					tooltip = " data-toggle='tooltip' title='"+allErrorTexts.join(', ')+"'";
				}
				else
					chkbxString = "<input type='checkbox' value='" + mo_lvid + "' name='applications[]'>";

				// show "in fhcomplete" indicator
				if (kursinfo.infhc)
				{
					newicon = "<i id='infhcicon_" + mo_lvid + "' class='fa fa-check courseInFhc_" + mo_lvid + "'></i>"
					+"<input type='hidden' id='infhc_" + mo_lvid + "' class='infhc' value='1'>";
				}
				else
				{
					newicon = "<i id='infhcicon_" + mo_lvid + "' class='fa fa-times courseInFhc_" + mo_lvid + "'></i>"
					+"<input type='hidden' id='infhc_" + mo_lvid + "' class='infhc' value='0'>";
				}

				// append the row
				$("#applications").append(
					"<tr class='courseRow courserow_" + mo_lvid + inactive+"'"+tooltip+">" +
						"<td class='text-center'>" + chkbxString + "</td>" +
						"<td class='text-center'>" + person.vorname + person.nachname + "</td>" +
						"<td class='text-center'>" + kontaktmail.kontakt + "</td>" +
						"<td class='text-center'>" + mo_outgoing_lv.lv_bez_gast + "</td>" +
						"<td class='text-center'>" + mo_outgoing_lv.ects_punkte_gast + "</td>" +
						"<td class='text-center'>" + mo_outgoing_lv.note_local_gast + "</td>" +
						"<td class='text-center'>" + mo_outgoing_lv.lv_nr_gast + "</td>" +
						"<td class='text-center'>" + mo_outgoing_lv.mo_lvid + "</td>" +
						"<td class='text-center'>" + newicon + "</td>" +
					"</tr>"
				);
			}

			// add tablesorter
			let tablesortParams = {
				headers: {
					0: {sorter: false, filter: false},
					8: {sorter: false, filter: false}
				},
				dateFormat: "ddmmyyyy"
			};

			Tablesort.addTablesorter("applicationstbl", [[1, 0], [2, 0], [3, 0], [7, 0]], ["filter"], 2, tablesortParams);
		}

		if (numCourses <= 0)
			$("#applicationsyncoutputtext").html("<div class='text-center'>Keine Kurse gefunden!</div>");
	},
	_findCourseByMoid(mo_lvid)
	{
		let coursesFound = [];
		for (let outgoing in MobilityOnlineOutgoingCourses.outgoings)
		{
			let mooutg = MobilityOnlineOutgoingCourses.outgoings[outgoing];

			let kurse = mooutg.data.kurse;
			for (let i = 0; i < kurse.length; i++)
			{
				if (kurse[i].mo_outgoing_lv.mo_lvid == mo_lvid)
					coursesFound.push(kurse[i]);
			}
		}

		return coursesFound;
	}
};
