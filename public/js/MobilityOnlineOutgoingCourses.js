/**
 * javascript file for Mobility Online incoming sync
 */

$(document).ready(function()
	{
		// get outgoings
		MobilityOnlineOutgoingCourses.getOutgoingCourses($("#studiensemester").val(), $("#studiengang_kz").val());

		let getOutgoingFunc = function()
		{
			let studiensemester = $("#studiensemester").val();
			let studiengang_kz = $("#studiengang_kz").val();
			MobilityOnlineApplicationsHelper.resetSyncOutput();
			MobilityOnlineOutgoingCourses.getOutgoingCourses(studiensemester, studiengang_kz);
		}

		// get Outgoings when Dropdown selected
		$("#studiensemester,#studiengang_kz").change(
			getOutgoingFunc
		);

		$("#refreshBtn").click(
			getOutgoingFunc
		);

		//init sync
		//~ $("#applicationsyncbtn").click(
			//~ function()
			//~ {
				//~ let outgoingElem = $("#applications input[type=checkbox]:checked");
				//~ let outgoings = [];
				//~ outgoingElem.each(
					//~ function()
					//~ {
						//~ outgoings.push(MobilityOnlineOutgoingCourses._findOutgoingByMoid($(this).val())[0]);
					//~ }
				//~ );

				//~ $("#applicationsyncoutput div").empty();

				//~ MobilityOnlineOutgoingCourses.syncOutgoingCourses(outgoings, $("#studiensemester").val());
			//~ }
		//~ );

		//select all incoming checkboxes
		MobilityOnlineApplicationsHelper.setSelectAllApplicationsEvent();
		//select incoming application which are not in FHC yet
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
						console.log(outgoings);
						MobilityOnlineOutgoingCourses.outgoings = outgoings;

						MobilityOnlineOutgoingCourses._showKurse();

							// number of applications selected via checkboxes
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
					$("#applicationsyncoutputtext").html("<div class='text-center'>Fehler beim Holen der Outgoings!</div>");
				}
			}
		);
	},
	syncOutgoingCourses: function(outgoings, studiensemester)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/syncOutgoingCourses',
			{
				"outgoings": JSON.stringify(outgoings),
				"studiensemester": studiensemester
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						let syncRes = FHC_AjaxClient.getData(data);

						$("#applications td").css("background-color", ""); // remove background color of applications table

						MobilityOnlineApplicationsHelper.writeSyncOutput(syncRes.syncoutput);

						$("#applicationsyncoutputtext").append(data.retval.syncoutput);

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
	 * Refreshes status (infhc, not in fhc) of outgoings
	 */
	refreshOutgoingsSyncStatus: function(synced_moids)
	{
		for (let idx in synced_moids)
		{
			let moId = synced_moids[idx];

			// refresh JS array
			for (let outgoing in MobilityOnlineOutgoingCourses.outgoings)
			{
				let outgoingsObj = MobilityOnlineOutgoingCourses.outgoings[outgoing];

				if (outgoingsObj.moid === parseInt(moId))
				{
					outgoingsObj.infhc = true;

					for (let zlg in outgoingsObj.data.zahlungen)
					{
						outgoingsObj.data.zahlungen[zlg].buchungsinfo.infhc = true;
					}
					break;
				}
			}

			// refresh Outgoings Table "in FHC" field
			let inFhcIconEl = $("#infhcicon_" + moId);
			let inFhcEl = $("#infhc_" + moId);

			inFhcIconEl.removeClass();
			inFhcEl.val("1");
			inFhcIconEl.addClass("fa fa-check");

			// refresh zahlungen infhc flags too
			let zlgInFhcIconEl = $(".zlgInFhc_"+moId);
			let zlginfhcel = $("#infhc_" + moId);

			zlgInFhcIconEl.removeClass();
			zlginfhcel.val("1");
			zlgInFhcIconEl.addClass("fa fa-check");
		}
	},
	_showKurse: function()
	{
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
			for (let krs in kurse)
			{
				let kurs = kurse[krs];
				let kursinfo = kurs.kursinfo;
				let mo_outgoing_lv = kurs.mo_outgoing_lv;
				let mo_lvid = mo_outgoing_lv.mo_lvid;

				console.log(kursinfo);

				let allErrorTexts = errorTexts;
				if (kursinfo.error)
				{
					allErrorTexts = allErrorTexts.concat(kursinfo.errorMessages);
				}

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

				if (kursinfo.infhc)
				{
					newicon = "<i id='courseInFhcicon_" + mo_lvid + "' class='fa fa-check courseInFhc_" + mo_lvid + "'></i>"
					+"<input type='hidden' id='infhc_" + mo_lvid + "' class='infhc' value='1'>";
				}
				else
				{
					newicon = "<i id='courseInFhcicon_" + mo_lvid + "' class='fa fa-times courseInFhc_" + mo_lvid + "'></i>"
					+"<input type='hidden' id='infhc_" + mo_lvid + "' class='infhc' value='0'>";
				}

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

			let tablesortParams = {
				headers: {
					0: {sorter: false, filter: false},
					8: {sorter: false, filter: false}
				},
				dateFormat: "ddmmyyyy"
			};

			Tablesort.addTablesorter("applicationstbl", [[1, 0], [2, 0], [3, 0], [7, 0]], ["filter"], 2, tablesortParams);
		}
	},
	_findOutgoingByMoid(moid)
	{
		let outgoingFound = [];
		for (let outgoing in MobilityOnlineOutgoingCourses.outgoings)
		{
			let moinc = MobilityOnlineOutgoingCourses.outgoings[outgoing];
			if (moinc.moid == moid)
			{
				outgoingFound.push(moinc);
				break;
			}
		}

		return outgoingFound;
	}
	//~ _blackInApplicationRow: function(moid)
	//~ {
		//~ $("#applicationsyncoutputheading").html('');
		//~ let applicationsrowEl = $("#applicationsrow_"+moid);
		//~ applicationsrowEl.css("color", "black");
		//~ applicationsrowEl.off("click"); // row not clickable anymore
		//~ applicationsrowEl.removeClass("clickableApplicationsrow"); // row not clickable anymore
		//~ applicationsrowEl.removeAttr("title"); // remove tooltip

		//~ let chkboxElement = $("<input type='checkbox' value='" + moid + "' name='applications[]'>");

		//~ // reassign outgoing number event to new checkbox
		//~ chkboxElement.change(
			//~ MobilityOnlineApplicationsHelper.refreshApplicationsNumber
		//~ );
		//~ $("#checkboxcell_"+moid).append(chkboxElement);
	//~ }
};
