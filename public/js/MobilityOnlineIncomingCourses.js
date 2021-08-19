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
		let mocourseswell = $("#mocourseswell");
		mocourseswell.css('position', 'relative');

		$(window).on('scroll', function(event) {
			if ($("#fhcles").length > 0)
			{
				let scrollTop = $(window).scrollTop();
				let maxScrollHeight = $("#fhcles")[0].scrollHeight;
				let wellheight = $("#mocourseswell")[0].scrollHeight;
				if (wellheight < maxScrollHeight && scrollTop < maxScrollHeight)
				{
					let maxscreenheight = screen.height;
					let windowheight = $(window).height();
					let screenheightfactor = maxscreenheight / windowheight;
					let top = scrollTop / screenheightfactor;
					mocourseswell.css('top', top + 'px');
				}
			}
		});
	}
);

var MobilityOnlineIncomingCourses = {
	incomingCourses: null,// object for storing array with incomings and their courses
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
						let incomingcourses = FHC_AjaxClient.getData(data);
						MobilityOnlineIncomingCourses.incomingCourses = incomingcourses;
						MobilityOnlineIncomingCourses._printIncomingPrestudents(incomingcourses);
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
					let messageel = $("#message");
					let msgtext = "";
					if (FHC_AjaxClient.isError(data))
					{
						messageel.removeClass("text-success");
						messageel.addClass("text-danger");
						msgtext = FHC_AjaxClient.getError(data);
					}
					else if (FHC_AjaxClient.isSuccess(data))
					{
						messageel.removeClass("text-danger");
						messageel.addClass("text-success");
						msgtext = FHC_AjaxClient.getData(data);

						let lvidelements = $("#allfhcles .lehrveranstaltunginput");
						let lvids = [];

						lvidelements.each(
							function()
							{
								let lehrveranstaltung_id = $(this).val();
								lvids.push(lehrveranstaltung_id);
							}
						);

						let studiensemester = $("#studiensemester").val();

						MobilityOnlineIncomingCourses.refreshCourseAssignments(lvids, lehreinheitassignments[0].uid, studiensemester)
					}

					messageel.html(msgtext);
				},
				errorCallback: function()
				{
					let messageel = $("#message");
					messageel.removeClass("text-success");
					messageel.addClass("text-danger");
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
						let lvdata = FHC_AjaxClient.getData(data);

						for (let prestudent in MobilityOnlineIncomingCourses.incomingCourses)
						{
							let prestudentobj = MobilityOnlineIncomingCourses.incomingCourses[prestudent];

							if (prestudentobj.uid === uid)
							{
								for (let lvid in lvdata)
								{
									let lv = lvdata[lvid];

									for (let oldlvid in prestudentobj.lvs)
									{
										let oldlv = prestudentobj.lvs[oldlvid];
										if (oldlv != null && oldlv.lehrveranstaltung.lehrveranstaltung_id == lv.lehrveranstaltung.lehrveranstaltung_id)
										{
											oldlv.lehrveranstaltung.incomingsplaetze = lv.lehrveranstaltung.incomingsplaetze;
											oldlv.lehrveranstaltung.anz_incomings = lv.lehrveranstaltung.anz_incomings;
											oldlv.lehreinheiten = lv.lehreinheiten;
										}
									}

									for (let oldNonMoLvid in prestudentobj.nonMoLvs)
									{
										let oldNonMoLv = prestudentobj.nonMoLvs[oldNonMoLvid];
										if (oldNonMoLv != null && oldNonMoLv.lehrveranstaltung.lehrveranstaltung_id == lv.lehrveranstaltung.lehrveranstaltung_id)
										 {
											 oldNonMoLv.lehrveranstaltung.incomingsplaetze = lv.lehrveranstaltung.incomingsplaetze;
											 oldNonMoLv.lehrveranstaltung.anz_incomings = lv.lehrveranstaltung.anz_incomings;
											 oldNonMoLv.lehreinheiten = lv.lehreinheiten;
										 }
									}
								}
								MobilityOnlineIncomingCourses._printMoCourses(prestudentobj);
								MobilityOnlineIncomingCourses._printFhcCourses(prestudentobj);
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

		for (let person in incomingscourses)
		{
			let prestudentobj = incomingscourses[person];
			let tablerowstring = "<tr>";

			tablerowstring += "<td>"+prestudentobj.nachname+", "+prestudentobj.vorname+"</td>" +
				"<td>"+prestudentobj.email+"</td>";

			let assignedCount = 0, notInMo = 0;
			let lvsInFhc = prestudentobj.lvs.length;

			for (let lv in prestudentobj.lvs)
			{
				let lvobj = prestudentobj.lvs[lv];

				for (let le in lvobj.lehreinheiten)
				{
					if (lvobj.lehreinheiten[le].directlyAssigned === true)
					{
						assignedCount++;
						break;
					}
				}
			}

			for (let nomolv in prestudentobj.nonMoLvs)
			{
				let nomolvobj = prestudentobj.nonMoLvs[nomolv];

				for (let le in nomolvobj.lehreinheiten)
				{
					if (nomolvobj.lehreinheiten[le].directlyAssigned === true)
					{
						notInMo++;
						break;
					}
				}
			}

			totalAssigned += assignedCount;
			totalLvsInFhc += lvsInFhc;

			tablerowstring += "<td class='text-center'>" +
				"<span>"+assignedCount+"/" +
				+lvsInFhc+"</span>";

			if (assignedCount < lvsInFhc)
			{
				tablerowstring += "&nbsp;<i class ='fa fa-exclamation text-danger'></i>";
			}
			else
			{
				tablerowstring += "&nbsp;<i class ='fa fa-check text-success'></i>";
			}

			if (notInMo > 0)
				tablerowstring += "<br /><span class='text-danger'>"+notInMo+" in FH-Complete, aber nicht in MobilityOnline</span>";

			tablerowstring += "</td>";

			tablerowstring +=
				"<td class='text-center'>" +
				"<button class='btn btn-default btn-sm' id='lezuw_"+prestudentobj.prestudent_id+"'>" +
				"<i class='fa fa-edit'></i>" +
				"</button>" +
				"</td>";

			tablerowstring += "</tr>";

			$("#incomingprestudents").append(tablerowstring);

			$("#lezuw_"+prestudentobj.prestudent_id).click(
				prestudentobj,
				MobilityOnlineIncomingCourses._printLvs
			)
		}
		$("#totalCoursesAssigned").text(totalAssigned);
		$("#totalCoursesFhc").text(totalLvsInFhc);

		let headers = {headers: { 3: { sorter: false, filter: false}}};
		Tablesort.addTablesorter("incomingprestudentstbl", [[0, 0], [1, 0]], ["filter"], 2, headers);
	},
	/**
	 * Prints courses from in Mobility Online and courses in fhcomplete
	 * @param prestudentobj prestudent whose courses are displayed
	 * @private
	 */
	_printLvs: function(prestudentobj)
	{
		MobilityOnlineIncomingCourses._printMoCourses(prestudentobj.data);
		MobilityOnlineIncomingCourses._printFhcCourses(prestudentobj.data);
		MobilityOnlineIncomingCourses._toggleIncomingCoursesView();
		$("#mocourseswell").css('top', '0');
		$(window).scrollTop(0);
	},
	_printMoCourses: function(moapplication)
	{
		let totalAssigned = 0;

		let prestudentdatahtml = "<tr><td class='prestudentfieldname'>Vorname</td><td>"+moapplication.vorname+"</td>" +
			"<td class='prestudentfieldname'>Nachname</td><td class='prestudentfieldvalue'>"+moapplication.nachname+"</td></tr>" +
			"<tr><td class='prestudentfieldname'>E-Mail</td><td class='prestudentfieldvalue'>"+moapplication.email+"</td>" +
			"<td class='prestudentfieldname'>Telefon</td><td class='prestudentfieldvalue'>"+moapplication.phonenumber+"</td></tr>" +
			"<tr><td class='prestudentfieldname'>Studiengang</td><td class='prestudentfieldvalue'>"+moapplication.studiengang+"</td>" +
			"<td class='prestudentfieldname'>Aufenthalt</td><td class='prestudentfieldvalue'>"+MobilityOnlineIncomingCourses._formatDateGerman(moapplication.stayfrom) +
			" - " + MobilityOnlineIncomingCourses._formatDateGerman(moapplication.stayto) +
			"</td>" +
			"</tr>";

		$("#lvsprestudentdata").html(prestudentdatahtml);

		$("#molvs").empty();

		for (let lv in moapplication.lvs)
		{
			let assignedCount = 0;
			let status = "";
			let lvobj = moapplication.lvs[lv];

			for (let le in lvobj.lehreinheiten)
			{
				if (lvobj.lehreinheiten[le].directlyAssigned === true)
					assignedCount++;
			}

			let tablerowstring = "<tr><td>"+lvobj.lehrveranstaltung.mobezeichnung+"</td>";

			let textclass = 'fa fa-check text-success';

			if (assignedCount <= 0)
				textclass = 'fa fa-exclamation text-danger';

			if ($.isNumeric(lvobj.lehrveranstaltung.lehrveranstaltung_id))
				status = "<i class='"+textclass+"' id='courselestatus_"+lvobj.lehrveranstaltung.lehrveranstaltung_id+"'></i>" +
					" <span id='courseleamount_"+lvobj.lehrveranstaltung.lehrveranstaltung_id+"'>"+assignedCount+" Einheit"+(assignedCount === 1 ? "" : "en")+"</span>" +
					" zugewiesen";
			else
			{
				status = "<i class='fa fa-exclamation text-danger' id='courselestatus_"+lvobj.lehrveranstaltung.lehrveranstaltung_id+"'></i> nicht in FHC";
			}

			tablerowstring += "<td>"+ status +"</td>";
			tablerowstring += "</tr>";

			$("#molvs").append(tablerowstring);

			totalAssigned += assignedCount;
		}

		Tablesort.addTablesorter("molvstbl", [[0, 0], [1, 0]], ["filter"], 2);
	},
	_printFhcCourses: function(fhcprestudent)
	{
		let fhclvhtml = "";
		let numLvs = 0;
		let hasLes = false;

		fhclvhtml += "<div id='fhcles'>";

		for (let fhclv in fhcprestudent.lvs)
		{
			let fhclvobj = fhcprestudent.lvs[fhclv];
			if ($.isNumeric(fhclvobj.lehrveranstaltung.lehrveranstaltung_id))
			{
				numLvs++;
				fhclvhtml += MobilityOnlineIncomingCourses._getLehrveranstaltungHtml(fhclvobj);
				if (fhclvobj.lehreinheiten.length > 0)
					hasLes = true;
			}
		}
		fhclvhtml += "</div>";

		fhclvhtml += "<div id='fhconlyles'>";
		// Lvs which are not in Mobility Online, but in FH-Complete
		// (e.g. when course assignment gets deleted in MobilityOnline)
		if (fhcprestudent.nonMoLvs.length > 0)
		{
			let first = true;
			for (let nomolv in fhcprestudent.nonMoLvs)
			{
				let nonmolvobj = fhcprestudent.nonMoLvs[nomolv];
				if ($.isNumeric(nonmolvobj.lehrveranstaltung.lehrveranstaltung_id))
				{
					numLvs++;

					let assigned = false;

					for (let lehreinheit in nonmolvobj.lehreinheiten)
					{
						if (nonmolvobj.lehreinheiten[lehreinheit].directlyAssigned)
						{
							assigned = true;
							break;
						}
					}
					if (assigned)
					{
						if (first)
							fhclvhtml += "<hr><strong>In FH-Complete, but not in MobilityOnline:</strong><br /><br />";
						fhclvhtml += MobilityOnlineIncomingCourses._getLehrveranstaltungHtml(nonmolvobj);
						first = false;
					}

					if (nonmolvobj.lehreinheiten.length > 0)
						hasLes = true;
				}
			}
		}
		fhclvhtml += "</div>";

		fhclvhtml += "<hr>";

		fhclvhtml += "<div class='row'>";
		fhclvhtml += "<div class='col-xs-6 text-left'>" +
			"<button class='btn btn-default' id='backtoincomings'>" +
			"<i class='fa fa-arrow-left'></i> Zurück zu allen Incomings"+
			"</button>"+
			"</div>";

		if (numLvs > 0 && hasLes)
			fhclvhtml += "<div class='col-xs-6 text-right'>" +
				"<button class='btn btn-default' id='save'>" +
				"<i class='glyphicon glyphicon-floppy-disk'></i> Speichern"+
				"</button>"+
				"</div>";

		fhclvhtml += "</div>";

		$("#allfhcles").html(fhclvhtml);

		$("#backtoincomings").click(
			MobilityOnlineIncomingCourses._toggleIncomingCoursesView
		);

		$("#save").off("click");

		$("#save").click(
			function()
			{
				let uid = fhcprestudent.uid;
				let lehreinheitassignments = [];
				$(".lehreinheitinput").each(
					function()
					{
						let idstr = $(this).prop("id");
						let lehreinheit_id = idstr.substr(idstr.indexOf("_") + 1);
						lehreinheitassignments.push(
							{
								"uid": uid,
								"lehreinheit_id": lehreinheit_id,
								"assigned": $(this).prop("checked")
							}
						);
					}
				);

				MobilityOnlineIncomingCourses.updateLehreinheitAssignment(lehreinheitassignments);
			}
		);
	},
	_getLehrveranstaltungHtml: function(lehrveranstaltungobj)
	{
		let fhclvhtml = "<div class='panel panel-default'>";
		fhclvhtml += "<div class='panel-heading fhclvpanelheading'>";
		fhclvhtml += "" + lehrveranstaltungobj.lehrveranstaltung.fhcbezeichnung +
			 " | ";

		for (let stg in lehrveranstaltungobj.studiengaenge)
		{
			let stgobj = lehrveranstaltungobj.studiengaenge[stg];
			fhclvhtml += (" " + stgobj.kuerzel);
		}

		for (let sem in lehrveranstaltungobj.ausbildungssemester)
		{
			let semobj = lehrveranstaltungobj.ausbildungssemester[sem];
			fhclvhtml += " " + semobj;
		}

		fhclvhtml += " | ";

		let coursefull = (lehrveranstaltungobj.lehrveranstaltung.anz_incomings) > parseInt(lehrveranstaltungobj.lehrveranstaltung.incomingplaetze);

		if (coursefull)
			fhclvhtml += "<span class='alert-danger'>";

		fhclvhtml += lehrveranstaltungobj.lehrveranstaltung.anz_incomings + "/" +
			lehrveranstaltungobj.lehrveranstaltung.incomingplaetze;

		if (coursefull)
			fhclvhtml += "</span>";

		fhclvhtml += " incomings";

		fhclvhtml += " <span class='pull-right'>lvid "+lehrveranstaltungobj.lehrveranstaltung.lehrveranstaltung_id+"</span>";

		fhclvhtml += "</div><div class='panel-body fhclvpanel'>";

		fhclvhtml += "<input type='hidden' class='lehrveranstaltunginput' value="+lehrveranstaltungobj.lehrveranstaltung.lehrveranstaltung_id+">";

		for (let le in lehrveranstaltungobj.lehreinheiten)
		{
			let lehreinheitobj = lehrveranstaltungobj.lehreinheiten[le];
			fhclvhtml += MobilityOnlineIncomingCourses._getLehreinheitHtml(lehrveranstaltungobj, lehreinheitobj);
		}
		fhclvhtml += "</div></div>";

		return fhclvhtml;
	},
	_getLehreinheitHtml: function(lehrveranstaltungobj, lehreinheitobj)
	{
		let fhcleshtml = '';
		let checked = lehreinheitobj.directlyAssigned === true ? "checked": '';
		fhcleshtml += "<div class='checkbox'><input type='checkbox' class='lehreinheitinput' id='lecheckbox_"+lehreinheitobj.lehreinheit_id+"' "+checked+">";
		fhcleshtml += lehreinheitobj.lehrform_kurzbz;

		for (let legr in lehreinheitobj.lehreinheitgruppen)
		{
			let legrobj = lehreinheitobj.lehreinheitgruppen[legr];
			if (legrobj.direktinskription === true)
				continue;

			if (legrobj.gruppe_kurzbz != null && typeof legrobj.gruppe_kurzbz == "string"
				&& legrobj.gruppe_kurzbz.length > 0)
			{
				fhcleshtml += " "+legrobj.gruppe_kurzbz;
			}
			else
			{
				fhcleshtml += " " + legrobj.studiengang_kuerzel;
				fhcleshtml += (legrobj.semester == null ? "" : legrobj.semester);
				fhcleshtml += (legrobj.verband == null ? "" : legrobj.verband);
				fhcleshtml += (legrobj.gruppe == null ? "" : legrobj.gruppe);
			}
		}

		for (let lektor in lehreinheitobj.lektoren)
		{
			let lektoruid = lehreinheitobj.lektoren[lektor];
			fhcleshtml += " "+(lektoruid == null ? '' : lektoruid);
		}

		fhcleshtml += " <span class='nowrap'>("+lehreinheitobj.anz_teilnehmer+ " participants)</span>";

		fhcleshtml += "</div>";

		return fhcleshtml;
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

		for (let element in toToggle)
		{
			let el = toToggle[element];

			if (el.hasClass("hidden"))
				el.removeClass("hidden");
			else
				el.addClass("hidden");
		}
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
