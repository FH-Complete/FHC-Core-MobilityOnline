/**
 * javascript file for Mobility Online incoming courses sync and Lehreinheitassignment
 */
$(document).ready(function()
	{
		// change displayed courses when button clicked
		$("#showincomingsbtn").click(
			function()
			{
				let studiensemester = $("#studiensemester").val();
				let studiengang_kz = $("#studiengang_kz").val();
				$("#studiengang_kz").parent().removeClass("has-error");
				if (studiengang_kz === '')
					$("#studiengang_kz").parent().addClass("has-error");
				else
					MobilityOnlineIncomingCourses.getIncomingCourses(studiensemester, studiengang_kz);
			}
		);

		// make right MO courses box follow on scroll
		let moCoursesWell = $("#mocourseswell");
		moCoursesWell.css('position', 'relative');

		$(window).on('scroll', function(event) {
			if ($("#fhcles").length > 0)
			{
				let scrollTop = $(window).scrollTop();
				let maxScrollHeight = $("#fhcles")[0].scrollHeight;
				let wellHeight = $("#mocourseswell")[0].scrollHeight;
				if (wellHeight < maxScrollHeight && scrollTop < maxScrollHeight)
				{
					let maxScreenHeight = screen.height;
					let windowHeight = $(window).height();
					let screenHeightFactor = maxScreenHeight / windowHeight;
					let top = scrollTop / screenHeightFactor;
					moCoursesWell.css('top', top + 'px');
				}
			}
		});
	}
);

var MobilityOnlineIncomingCourses = {
	incomingCourses: null,// object for storing array with incomings and their courses
	uidsOfFetchedCourses: [],
	/**
	 * Gets incomings from MobilityOnline and fhcomplete together with
	 * their courses assigned in MobilityOnline
	 * @param studiensemester
	 * @param studiengang_kz
	 */
	getIncomingCourses: function(studiensemester, studiengang_kz)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getIncomingWithCoursesJson',
			{
				"studiensemester": studiensemester,
				"studiengang_kz": studiengang_kz
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						$("#incomingprestudents").empty();
						let incomingCourses = FHC_AjaxClient.getData(data);
						MobilityOnlineIncomingCourses.incomingCourses = incomingCourses;
						MobilityOnlineIncomingCourses._printIncomingPrestudents(incomingCourses);
					}
					else
					{
						$("#fhcles").text("-");
						$("#incomingprestudents").html("<tr align='center'><td colspan='5'>Keine Incomings mit Kursen gefunden!</td></tr>");
					}
				},
				errorCallback: function()
				{
					$("#incomingprestudents").html("<tr align='center'><td colspan='5'>Fehler beim Holen der Incomings!</td></tr>");
				}
			}
		);
	},
	/**
	 * Gets courses for a single incoming.
	 * @param prestudentObj with lv info
	 * @param studiensemester
	 * @param uid of the incoming
	 **/
	getCoursesForIncoming: function(prestudentObj, studiensemester, uid)
	{
		if (MobilityOnlineIncomingCourses.uidsOfFetchedCourses.includes(uid))
		{
			MobilityOnlineIncomingCourses._printAllLvs(prestudentObj);
			return;
		}

		let lv_kuerzel = [];
		for (let lv of prestudentObj.lvs)
		{
			lv_kuerzel.push(lv['lehrveranstaltung']['lv_kuerzel_mo']);
		}

		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getCoursesForIncoming',
			{
				"lv_kuerzel": lv_kuerzel,
				"studiensemester": studiensemester,
				"uid": uid
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						MobilityOnlineIncomingCourses.uidsOfFetchedCourses.push(uid);
						let lvData = FHC_AjaxClient.getData(data);
						for (let oldLvIdx in prestudentObj.lvs)
						{
							for (let newLv of lvData.moLvs)
							{
								if (newLv.lehrveranstaltung.lehrveranstaltung_id_mo == prestudentObj.lvs[oldLvIdx].lehrveranstaltung.lehrveranstaltung_id_mo)
								{
									prestudentObj.lvs[oldLvIdx] = newLv;
								}
							}
						}
						prestudentObj.nonMoLvs = lvData.nonMoLvs;
						MobilityOnlineIncomingCourses._printAllLvs(prestudentObj);
					}
					else
					{
						MobilityOnlineIncomingCourses._toggleIncomingCoursesView();

						$("#molvs").html("<tr align='center'><td colspan='5'>Keine Kurse für den Incoming gefunden!</td></tr>");
						$("#allfhcles").html(MobilityOnlineIncomingCourses._getBackButtonHtml(false));
						MobilityOnlineIncomingCourses._resetCoursesWell();

						$("#backtoincomings").click(
							MobilityOnlineIncomingCourses._toggleIncomingCoursesView
						);
					}
				},
				errorCallback: function()
				{
					$("#incomingprestudents").html("<tr align='center'><td colspan='5'>Fehler beim Holen der Kurse für den Incoming!</td></tr>");
				}
			}
		);
	},
	/**
	 * Updates Lehreinheitassignments, i.e. adds or deletes direct assignments to a lehreinheit
	 * @param lehreinheitassignments array, each entry contains uid, lehreinheit_id and bool indicating if directly assigned
	 */
	updateLehreinheitAssignment: function(lehreinheitassignments)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/updateLehreinheitAssignment',
			{"lehreinheitassignments": lehreinheitassignments},
			{
				successCallback: function (data, textStatus, jqXHR)
				{
					let messageEl = $("#message");
					let msgText = "";
					if (FHC_AjaxClient.isError(data))
					{
						messageEl.removeClass("text-success");
						messageEl.addClass("text-danger");
						msgText = FHC_AjaxClient.getError(data);
					}
					else if (FHC_AjaxClient.isSuccess(data))
					{
						messageEl.removeClass("text-danger");
						messageEl.addClass("text-success");
						msgText = FHC_AjaxClient.getData(data);

						let lvIdElements = $("#allfhcles .lehrveranstaltunginput");
						let lvIds = [];

						lvIdElements.each(
							function()
							{
								let lehrveranstaltung_id = $(this).val();
								lvIds.push(lehrveranstaltung_id);
							}
						);

						let studiensemester = $("#studiensemester").val();

						MobilityOnlineIncomingCourses.refreshCourseAssignments(lvIds, lehreinheitassignments[0].uid, studiensemester)
					}

					messageEl.html(msgText);
				},
				errorCallback: function()
				{
					let messageEl = $("#message");
					messageEl.removeClass("text-success");
					messageEl.addClass("text-danger");
					$("#message").html("Error when changing Lehreinheit assignments!");
				}
			}
		)
	},
	/**
	 * Gets course assignments for a user, refreshes directlyAssigned fields in global assignments object
	 * Updates numbers in html views accordingly
	 * @param lvids array, contains courses for which data has to refreshed
	 * @param uid user for which lehreinheitassignments should be refreshed
	 * @param studiensemester
	 */
	refreshCourseAssignments: function(lvids, uid, studiensemester)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getFhcCourses',
			{
				"uid": uid,
				"lvids": lvids,
				"studiensemester": studiensemester
			},
			{
				successCallback: function (data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						let lvData = FHC_AjaxClient.getData(data);

						for (let prestudentObj of MobilityOnlineIncomingCourses.incomingCourses)
						{
							if (prestudentObj.uid === uid)
							{
								for (let lv of lvData)
								{
									for (let oldLv of prestudentObj.lvs)
									{
										if (oldLv != null && oldLv.lehrveranstaltung.lehrveranstaltung_id == lv.lehrveranstaltung.lehrveranstaltung_id)
										{
											oldLv.lehrveranstaltung.incomingsplaetze = lv.lehrveranstaltung.incomingsplaetze;
											oldLv.lehrveranstaltung.anz_incomings = lv.lehrveranstaltung.anz_incomings;
											oldLv.lehreinheiten = lv.lehreinheiten;
										}
									}

									for (let oldNonMoLv of prestudentObj.nonMoLvs)
									{
										if (oldNonMoLv != null && oldNonMoLv.lehrveranstaltung.lehrveranstaltung_id == lv.lehrveranstaltung.lehrveranstaltung_id)
										 {
											 oldNonMoLv.lehrveranstaltung.incomingsplaetze = lv.lehrveranstaltung.incomingsplaetze;
											 oldNonMoLv.lehrveranstaltung.anz_incomings = lv.lehrveranstaltung.anz_incomings;
											 oldNonMoLv.lehreinheiten = lv.lehreinheiten;
										 }
									}
								}
								MobilityOnlineIncomingCourses._printMoCourses(prestudentObj);
								MobilityOnlineIncomingCourses._printFhcCourses(prestudentObj);
								break;
							}
						}
						MobilityOnlineIncomingCourses._printIncomingPrestudents(MobilityOnlineIncomingCourses.incomingCourses);
					}
				},
				errorCallback: function()
				{
					FHC_DialogLib.alertError("Fehler beim Aktualisieren des Kurszuweisungen!");
				}
			}
		)

	},
	/**
	 * Prints initial prestudent table with list of synced prestudents
	 * @param incomingscourses
	 * @private
	 */
	_printIncomingPrestudents: function(incomingscourses)
	{
		$("#incomingprestudents").empty();

		let totalAssigned = 0, totalLvsInFhc = 0;

		for (let prestudentObj of incomingscourses)
		{
			let tablerowString = "<tr>";

			tablerowString += "<td>"+prestudentObj.nachname+", "+prestudentObj.vorname+"</td>" +
				"<td>"+prestudentObj.email+"</td>";

			let assignedCount = 0, notInMo = 0;
			let lvsInFhc = prestudentObj.lvs.length;

			for (let lv in prestudentObj.lvs)
			{
				let lvoOj = prestudentObj.lvs[lv];

				for (let le in lvoOj.lehreinheiten)
				{
					if (lvoOj.lehreinheiten[le].directlyAssigned === true)
					{
						assignedCount++;
						break;
					}
				}
			}

			for (let nomoLv in prestudentObj.nonMoLvs)
			{
				let noMoLvObj = prestudentObj.nonMoLvs[nomoLv];

				for (let le in noMoLvObj.lehreinheiten)
				{
					if (noMoLvObj.lehreinheiten[le].directlyAssigned === true)
					{
						notInMo++;
						break;
					}
				}
			}

			totalAssigned += assignedCount;
			totalLvsInFhc += lvsInFhc;

			tablerowString += "<td class='text-center'>" +
				"<span>"+assignedCount+"/" +
				+lvsInFhc+"</span>";

			if (assignedCount < lvsInFhc)
			{
				tablerowString += "&nbsp;<i class ='fa fa-exclamation text-danger'></i>";
			}
			else
			{
				tablerowString += "&nbsp;<i class ='fa fa-check text-success'></i>";
			}

			if (notInMo > 0)
				tablerowString += "<br /><span class='text-danger'>"+notInMo+" in FH-Complete, aber nicht in MobilityOnline</span>";

			tablerowString += "</td>";

			tablerowString +=
				"<td class='text-center'>" +
				"<button class='btn btn-default btn-sm' id='lezuw_"+prestudentObj.prestudent_id+"'>" +
				"<i class='fa fa-edit'></i>" +
				"</button>" +
				"</td>";

			tablerowString += "</tr>";

			$("#incomingprestudents").append(tablerowString);

			$("#lezuw_"+prestudentObj.prestudent_id).click(
				() => {
					MobilityOnlineIncomingCourses.getCoursesForIncoming(prestudentObj, $("#studiensemester").val(), prestudentObj.uid);
				}
			)
		}
		$("#totalCoursesAssigned").text(totalAssigned);
		$("#totalCoursesFhc").text(totalLvsInFhc);

		let headers = {headers: { 3: { sorter: false, filter: false}}};
		Tablesort.addTablesorter("incomingprestudentstbl", [[0, 0], [1, 0]], ["filter"], 2, headers);
	},
	/**
	 * Prints courses from in Mobility Online and courses in fhcomplete
	 * @param prestudentObj prestudent whose courses are displayed
	 * @private
	 */
	_printAllLvs: function(prestudentObj)
	{
		MobilityOnlineIncomingCourses._printMoCourses(prestudentObj);
		MobilityOnlineIncomingCourses._printFhcCourses(prestudentObj);
		MobilityOnlineIncomingCourses._toggleIncomingCoursesView();
		MobilityOnlineIncomingCourses._resetCoursesWell();
	},
	_printMoCourses: function(moApplication)
	{
		let totalAssigned = 0;

		let prestudentDataHtml = "<tr><td class='prestudentfieldname'>Vorname</td><td>"+moApplication.vorname+"</td>" +
			"<td class='prestudentfieldname'>Nachname</td><td class='prestudentfieldvalue'>"+moApplication.nachname+"</td></tr>" +
			"<tr><td class='prestudentfieldname'>E-Mail</td><td class='prestudentfieldvalue'>"+moApplication.email+"</td>" +
			"<td class='prestudentfieldname'>Telefon</td><td class='prestudentfieldvalue'>"+moApplication.phonenumber+"</td></tr>" +
			"<tr><td class='prestudentfieldname'>Studiengang</td><td class='prestudentfieldvalue'>"+moApplication.studiengang+"</td>" +
			"<td class='prestudentfieldname'>Aufenthalt</td><td class='prestudentfieldvalue'>"+MobilityOnlineIncomingCourses._formatDateGerman(moApplication.stayfrom) +
			" - " + MobilityOnlineIncomingCourses._formatDateGerman(moApplication.stayto) +
			"</td>" +
			"</tr>";

		$("#lvsprestudentdata").html(prestudentDataHtml);

		$("#molvs").empty();

		for (let lv in moApplication.lvs)
		{
			let assignedCount = 0;
			let status = "";
			let lvObj = moApplication.lvs[lv];

			for (let le in lvObj.lehreinheiten)
			{
				if (lvObj.lehreinheiten[le].directlyAssigned === true)
					assignedCount++;
			}

			let tablerowString = "<tr><td>"+lvObj.lehrveranstaltung.mobezeichnung+"</td>";

			let textclass = 'fa fa-check text-success';

			if (assignedCount <= 0)
				textclass = 'fa fa-exclamation text-danger';

			if ($.isNumeric(lvObj.lehrveranstaltung.lehrveranstaltung_id))
				status = "<i class='"+textclass+"' id='courselestatus_"+lvObj.lehrveranstaltung.lehrveranstaltung_id+"'></i>" +
					" <span id='courseleamount_"+lvObj.lehrveranstaltung.lehrveranstaltung_id+"'>"+assignedCount+" Einheit"+(assignedCount === 1 ? "" : "en")+"</span>" +
					" zugewiesen";
			else
			{
				status = "<i class='fa fa-exclamation text-danger' id='courselestatus_"+lvObj.lehrveranstaltung.lehrveranstaltung_id+"'></i> nicht in FHC";
			}

			tablerowString += "<td>"+ status +"</td>";
			tablerowString += "</tr>";

			$("#molvs").append(tablerowString);

			totalAssigned += assignedCount;
		}

		Tablesort.addTablesorter("molvstbl", [[0, 0], [1, 0]], ["filter"], 2);
	},
	_printFhcCourses: function(fhcPrestudent)
	{
		let fhcLvHtml = "";
		let numLvs = 0;
		let hasLes = false;

		fhcLvHtml += "<div id='fhcles'>";

		for (let fhcLvObj of fhcPrestudent.lvs)
		{
			if ($.isNumeric(fhcLvObj.lehrveranstaltung.lehrveranstaltung_id))
			{
				numLvs++;
				fhcLvHtml += MobilityOnlineIncomingCourses._getLehrveranstaltungHtml(fhcLvObj);
				if (fhcLvObj.lehreinheiten.length > 0)
					hasLes = true;
			}
		}
		fhcLvHtml += "</div>";

		fhcLvHtml += "<div id='fhconlyles'>";
		// Lvs which are not in Mobility Online, but in FH-Complete
		// (e.g. when course assignment gets deleted in MobilityOnline)
		if (fhcPrestudent.nonMoLvs.length > 0)
		{
			let first = true;
			for (let noMoLvobj of fhcPrestudent.nonMoLvs)
			{
				if ($.isNumeric(noMoLvobj.lehrveranstaltung.lehrveranstaltung_id))
				{
					numLvs++;

					let assigned = false;

					for (let lehreinheit in noMoLvobj.lehreinheiten)
					{
						if (noMoLvobj.lehreinheiten[lehreinheit].directlyAssigned)
						{
							assigned = true;
							break;
						}
					}
					if (assigned)
					{
						if (first)
							fhcLvHtml += "<hr><strong>In FH-Complete, but not in MobilityOnline:</strong><br /><br />";
						fhcLvHtml += MobilityOnlineIncomingCourses._getLehrveranstaltungHtml(noMoLvobj);
						first = false;
					}

					if (noMoLvobj.lehreinheiten.length > 0)
						hasLes = true;
				}
			}
		}
		fhcLvHtml += "</div>";

		fhcLvHtml += MobilityOnlineIncomingCourses._getBackButtonHtml(numLvs > 0 && hasLes);

		$("#allfhcles").html(fhcLvHtml);

		$("#backtoincomings").click(
			MobilityOnlineIncomingCourses._toggleIncomingCoursesView
		);

		$("#save").off("click");

		$("#save").click(
			function()
			{
				let uid = fhcPrestudent.uid;
				let lehreinheitAssignments = [];
				$(".lehreinheitinput").each(
					function()
					{
						let idStr = $(this).prop("id");
						let lehreinheit_id = idStr.substr(idStr.indexOf("_") + 1);
						lehreinheitAssignments.push(
							{
								"uid": uid,
								"lehreinheit_id": lehreinheit_id,
								"assigned": $(this).prop("checked")
							}
						);
					}
				);

				MobilityOnlineIncomingCourses.updateLehreinheitAssignment(lehreinheitAssignments);
			}
		);
	},
	_getLehrveranstaltungHtml: function(lehrveranstaltungobj)
	{
		let fhcLvHtml = "<div class='panel panel-default'>";
		fhcLvHtml += "<div class='panel-heading fhclvpanelheading'>";
		fhcLvHtml += "" + lehrveranstaltungobj.lehrveranstaltung.fhcbezeichnung +
			 " | ";

		for (let stg of lehrveranstaltungobj.studiengaenge)
		{
			fhcLvHtml += (" " + stg.kuerzel);
		}

		for (let sem of lehrveranstaltungobj.ausbildungssemester)
		{
			fhcLvHtml += " " + sem;
		}

		fhcLvHtml += " | ";

		let courseFull = (lehrveranstaltungobj.lehrveranstaltung.anz_incomings) > parseInt(lehrveranstaltungobj.lehrveranstaltung.incomingplaetze);

		if (courseFull)
			fhcLvHtml += "<span class='alert-danger'>";

		fhcLvHtml += lehrveranstaltungobj.lehrveranstaltung.anz_incomings + "/" +
			lehrveranstaltungobj.lehrveranstaltung.incomingplaetze;

		if (courseFull)
			fhcLvHtml += "</span>";

		fhcLvHtml += " incomings";

		fhcLvHtml += " <span class='pull-right'>lvid "+lehrveranstaltungobj.lehrveranstaltung.lehrveranstaltung_id+"</span>";

		fhcLvHtml += "</div><div class='panel-body fhclvpanel'>";

		fhcLvHtml += "<input type='hidden' class='lehrveranstaltunginput' value="+lehrveranstaltungobj.lehrveranstaltung.lehrveranstaltung_id+">";

		for (let lehreinheitObj of lehrveranstaltungobj.lehreinheiten)
		{
			fhcLvHtml += MobilityOnlineIncomingCourses._getLehreinheitHtml(lehrveranstaltungobj, lehreinheitObj);
		}
		fhcLvHtml += "</div></div>";

		return fhcLvHtml;
	},
	_getLehreinheitHtml: function(lehrveranstaltungObj, lehreinheitObj)
	{
		let fhcLesHtml = '';
		let checked = lehreinheitObj.directlyAssigned === true ? "checked": '';
		fhcLesHtml += "<div class='checkbox'><input type='checkbox' class='lehreinheitinput' id='lecheckbox_"+lehreinheitObj.lehreinheit_id+"' "+checked+">";
		fhcLesHtml += lehreinheitObj.lehrform_kurzbz;

		for (let legrObj of lehreinheitObj.lehreinheitgruppen)
		{
			if (legrObj.direktinskription === true)
				continue;

			if (legrObj.gruppe_kurzbz != null && typeof legrObj.gruppe_kurzbz == "string"
				&& legrObj.gruppe_kurzbz.length > 0)
			{
				fhcLesHtml += " "+legrObj.gruppe_kurzbz;
			}
			else
			{
				fhcLesHtml += " " + legrObj.studiengang_kuerzel;
				fhcLesHtml += (legrObj.semester == null ? "" : legrObj.semester);
				fhcLesHtml += (legrObj.verband == null ? "" : legrObj.verband);
				fhcLesHtml += (legrObj.gruppe == null ? "" : legrObj.gruppe);
			}
		}

		for (let lektorUid of lehreinheitObj.lektoren)
		{
			fhcLesHtml += " "+(lektorUid == null ? '' : lektorUid);
		}

		fhcLesHtml += " <span class='nowrap'>("+lehreinheitObj.anz_teilnehmer+ " participants)</span>";

		fhcLesHtml += "</div>";

		return fhcLesHtml;
	},
	_getBackButtonHtml: function(includeSaveBtn)
	{
		let html = "<hr>";

		html += "<div class='row'>";
		html += "<div class='col-xs-6 text-left'>" +
			"<button class='btn btn-default' id='backtoincomings'>" +
			"<i class='fa fa-arrow-left'></i> Zurück zu allen Incomings"+
			"</button>"+
			"</div>";

		if (includeSaveBtn)
			html += "<div class='col-xs-6 text-right'>" +
				"<button class='btn btn-default' id='save'>" +
				"<i class='glyphicon glyphicon-floppy-disk'></i> Speichern"+
				"</button>"+
				"</div>";

		html += "</div>";

		return html;
	},
	/**
	 * Shows hidden html views, hides non-hidden
	 * @private
	 */
	_toggleIncomingCoursesView: function()
	{
		$("#message").empty();

		let toToggle = [
			$("#syncIncomingInput"),
			$("#incomingprestudentsrow"),
			$("#coursesassignment"),
			$("#lvsprestudent")
		];

		for (let el of toToggle)
		{
			if (el.hasClass("hidden"))
				el.removeClass("hidden");
			else
				el.addClass("hidden");
		}
	},
	_resetCoursesWell: function()
	{
		$("#mocourseswell").css('top', '0');
		$(window).scrollTop(0);
	},
	/**
	 * Formats a date in format YYYY-mm-dd to dd.mm.YYYY
	 * @param date
	 * @returns {string}
	 */
	_formatDateGerman: function(date)
	{
		return date.substring(8, 10) + "." + date.substring(5, 7) + "." + date.substring(0, 4);
	}
};
