/**
 * javascript file for Mobility Online incoming courses sync and Lehreinheitassignment
 */
$(document).ready(function()
	{
		MobilityOnlineIncomingCourses.getIncomingCourses($("#studiensemester").val());

		// change displayed courses when Studiensemester selected
		$("#studiensemester").change(
			function()
			{
				var studiensemester = $(this).val();
				$("#syncoutput").text('-');
				MobilityOnlineIncomingCourses.getIncomingCourses(studiensemester);
			}
		);

		// make left box follow on scroll
		var leftwell = $("#leftwell"), originalY = leftwell.offset().top;

		var topMargin = 10;

		leftwell.css('position', 'relative');

		$(window).on('scroll', function(event) {
			var scrollTop = $(window).scrollTop();

			leftwell.stop(false, false).animate({
				top: scrollTop < originalY
					? 0
					: scrollTop - originalY + topMargin
			}, 100);
		});

	}
);

var MobilityOnlineIncomingCourses = {
	getIncomingCourses: function(studiensemester)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getIncomingCoursesJson',
			{"studiensemester": studiensemester},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						$("#incomings").empty();
						var incomingcourses = data.retval;
						MobilityOnlineIncomingCourses._printMoCourses(incomingcourses);
					}
					else
					{
						$("#fhcles").text("-");
						$("#incomings").html("<tr align='center'><td colspan='5'>No incomings with courses found!</td></tr>");
					}
				}
			}
		);
	},
	changeLehreinheitAssignment: function(lehreinheitassignments, studiensemester)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/changeLehreinheitAssignment',
			{"lehreinheitassignments": lehreinheitassignments},
			{
				successCallback: function (data, textStatus, jqXHR)
				{
					$("#message").removeClass();
					if (FHC_AjaxClient.isSuccess(data))
					{
						$("#message").addClass("text-success");
					}
					else
					{
						$("#message").addClass("text-danger");
					}

					$("#message").text(data.retval);

					MobilityOnlineIncomingCourses.getIncomingCourses(studiensemester);
				}
			}
		)
	},
	_printMoCourses: function(incomingscourses)
	{
		var totalAssigned = 0;

		for (var person in incomingscourses)
		{
			var prestudentobj = incomingscourses[person];
			var rowspan = prestudentobj.lvs.length;
			var tablerowstring = "";

			tablerowstring += "<tr>" +
				"<td class='text-center' rowspan='"+rowspan+"'>" +
				"<button class='btn btn-default' id='lezuw_"+prestudentobj.prestudent_id+"'>" +
				"<i class='fa fa-chevron-left'></i>" +
				"</button>" +
				"</td>";
			tablerowstring += "<td rowspan='"+rowspan+"'>"+prestudentobj.nachname+", "+prestudentobj.vorname+"</td>" +
				"<td rowspan='"+rowspan+"'>"+prestudentobj.email+"</td>";


			for (var lv in prestudentobj.lvs)
			{
				var lvobj = prestudentobj.lvs[lv];

				tablerowstring += "<td>"+lvobj.lehrveranstaltung.mobezeichnung+"</td>";
				var status = "";
				var assignedCount = 0;

				for (var le in lvobj.lehreinheiten)
				{
					if (lvobj.lehreinheiten[le].directlyAssigned === true)
						assignedCount++;
				}

				if (assignedCount > 0)
					status = "<i class='fa fa-check text-success'></i> "+assignedCount+" unit"+(assignedCount > 1 ? "s" : "")+" assigned";
				else if ($.isNumeric(lvobj.lehrveranstaltung.lehrveranstaltung_id))
				{
					status = "<i class='fa fa-times text-danger'></i> no unit assigned";
				}
				else
				{
					status = "<i class='fa fa-times text-danger'></i> not in FHC";
				}

				tablerowstring += "<td>"+ status +"</td>";
				tablerowstring += "</tr>";

				totalAssigned += assignedCount;
			}

			$("#incomings").append(tablerowstring);

			$("#lezuw_"+prestudentobj.prestudent_id).click(
				prestudentobj,
				MobilityOnlineIncomingCourses._printFhcCourses
			)
		}
		$("#nrteachingunits").text(totalAssigned);
	},
	_printFhcCourses: function(prestudentobj)
	{
		var fhcprestudent = prestudentobj.data;
		var fhclvhtml = "<h4>"+fhcprestudent.vorname+" "+fhcprestudent.nachname+"</h4><hr>";
		var numLvs = 0;

		for (var fhclv in fhcprestudent.lvs)
		{
			var fhclvobj = fhcprestudent.lvs[fhclv];
			if ($.isNumeric(fhclvobj.lehrveranstaltung.lehrveranstaltung_id))
			{
				numLvs++;
				fhclvhtml += MobilityOnlineIncomingCourses._getLehrveranstaltungHtml(fhclvobj);
			}
		}

		// Lvs which are not in Mobility Online, but in FH-Complete
		// (e.g. when course assignment gets deleted in MobilityOnline)
		if (fhcprestudent.nonMoLvs.length > 0)
		{
			fhclvhtml += "<hr>Not in Mobility Online:<br /><br />";

			for (var nomolv in fhcprestudent.nonMoLvs)
			{
				var nonmolvobj = fhcprestudent.nonMoLvs[nomolv];
				if ($.isNumeric(nonmolvobj.lehrveranstaltung.lehrveranstaltung_id))
				{
					numLvs++;
					fhclvhtml += MobilityOnlineIncomingCourses._getLehrveranstaltungHtml(nonmolvobj);
				}
			}
		}

		if (numLvs > 0)
			fhclvhtml += "<hr><input type='button' class='btn btn-default' id='save' value='Save'>";

		fhclvhtml += "<br /><br /><span id='message'></span>";

		$("#fhcles").html(fhclvhtml);

		$("#save").off("click");

		$("#save").click(
			function()
			{
				var uid = fhcprestudent.uid;
				var lehreinheitassignments = [];
				$(".lehreinheitinput").each(
					function()
					{
						lehreinheitassignments.push(
							{
								"uid": uid,
								"lehreinheit_id": $(this).prop("id"),
								"assigned": $(this).prop("checked")
							}
						);
					}
				);

				MobilityOnlineIncomingCourses.changeLehreinheitAssignment(lehreinheitassignments, fhcprestudent.studiensemester_kurzbz);
			}
		);
	}
	,
	_getLehrveranstaltungHtml: function(lehrveranstaltungobj)
	{
		var fhclvhtml = "<div class='well well-sm'>";
		fhclvhtml += "<h4>" + lehrveranstaltungobj.lehrveranstaltung.fhcbezeichnung + "</h4>";

		for (var le in lehrveranstaltungobj.lehreinheiten)
		{
			var lehreinheitobj = lehrveranstaltungobj.lehreinheiten[le];
			fhclvhtml += MobilityOnlineIncomingCourses._getLehreinheitHtml(lehrveranstaltungobj, lehreinheitobj);
		}

		fhclvhtml += "</div>";

		return fhclvhtml;
	},
	_getLehreinheitHtml: function(lehrveranstaltungobj, lehreinheitobj)
	{
		var fhcleshtml = '';
		var checked = lehreinheitobj.directlyAssigned === true ? "checked": '';
		fhcleshtml += "<div class='checkbox'><input type='checkbox' class='lehreinheitinput' id="+lehreinheitobj.lehreinheit_id+" "+checked+">";
		fhcleshtml += lehreinheitobj.lehrform_kurzbz + " " + lehrveranstaltungobj.studiengang.kuerzel;
		for (var legr in lehreinheitobj.lehreinheitgruppen)
		{
			var legrobj = lehreinheitobj.lehreinheitgruppen[legr];
			fhcleshtml += " "+legrobj .semester;
			fhcleshtml += (legrobj.verband == null ? "" : legrobj.verband);
			fhcleshtml += (legrobj.gruppe == null ? "" : legrobj.gruppe);
		}
		for (var lektor in lehreinheitobj.lektoren)
		{
			var lektoruid = lehreinheitobj.lektoren[lektor];
			fhcleshtml += " "+(lektoruid == null ? '' : lektoruid)+"</div>";
		}
		return fhcleshtml;
	}
};
