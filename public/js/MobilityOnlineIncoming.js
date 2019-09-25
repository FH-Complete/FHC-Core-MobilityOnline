/**
 * javascript file for Mobility Online incoming sync
 */

$(document).ready(function()
	{
		MobilityOnlineIncoming.getIncoming($("#studiensemester").val(), $("#studiengang_kz").val());

		// get Incomings when Dropdown selected
		$("#studiensemester,#studiengang_kz").change(
			function()
			{
				var studiensemester = $("#studiensemester").val();
				var studiengang_kz = $("#studiengang_kz").val();
				$("#incomingsyncoutputheading").html("");
				$("#incomingsyncoutputtext").html("<div class='text-center'>-</div>");
				MobilityOnlineIncoming.getIncoming(studiensemester, studiengang_kz);
			}
		);

		//init sync
		$("#incomingsyncbtn").click(
			function()
			{
				var incomingelem = $("#incomings input[type=checkbox]:checked");
				var incomings = [];
				incomingelem.each(
					function()
					{
						for (var incoming in MobilityOnlineIncoming.incomings)
						{
							var moinc = MobilityOnlineIncoming.incomings[incoming];
							if (moinc.moid == $(this).val())
								incomings.push(moinc)
						}
					}
				);

				var syncIncomingsFunc = function (data) {
					if (FHC_AjaxClient.hasData(data))
					{
						var maxPostSize = FHC_AjaxClient.getData(data);
						if ($.isNumeric(maxPostSize))
						{
							maxPostSize = (parseInt(maxPostSize));
							$("#incomingsyncoutput div").empty();

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
		$("#selectallincomings").click(
			function()
			{
				var incomingelem = $("#incomings input[type=checkbox][name='incoming[]']");
				incomingelem.each(
					function()
					{
						$(this).prop('checked', true);
					}
				);
				MobilityOnlineIncoming._refreshIncomingNumber();
			}
		);

		//select incoming checkboxes which are not in FHC yet
		$("#selectnewincomings").click(
			function()
			{
				var incomingelem = $("#incomings tr");
				incomingelem.each(
					function()
					{
						var infhc = $(this).find("input.infhc").val();

						if (infhc === '0')
							$(this).find("input[type=checkbox][name='incoming[]']").prop('checked', true);
						else
							$(this).find("input[type=checkbox][name='incoming[]']").prop('checked', false);
					}
				);
				MobilityOnlineIncoming._refreshIncomingNumber();
			}
		);
	}
);

var MobilityOnlineIncoming = {
	incomings: null,
	getIncoming: function(studiensemester, studiengang_kz)
	{
		if (studiensemester == null || studiensemester === "" || studiengang_kz == null || (!$.isNumeric(studiengang_kz) && studiengang_kz !== "all"))
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
					$("#incomings").empty();

					if (FHC_AjaxClient.hasData(data))
					{
						var incomings = data.retval;
						MobilityOnlineIncoming.incomings = incomings;

						for (var incoming in incomings)
						{
							var incomingobj = incomings[incoming];
							var incomingdata = incomingobj.data;

							var person = incomingdata.person;
							var hasError = incomingobj.error;
							var chkbxstring, stgnotsettxt, errorclass, newicon;
							chkbxstring = stgnotsettxt = errorclass = "";

							// show errors in tooltip if sync not possible
							if (hasError)
							{
								errorclass = " class='inactive' data-toggle='tooltip' title='";
								var firstmsg = true;
								for (var i in incomingobj.errorMessages)
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
								chkbxstring = "<input type='checkbox' value='" + incomingobj.moid + "' name='incoming[]'>";
							}

							// courses from MobilityOnline
							var coursesstring = '';
							var firstcourse = true;

							for (var courseidx in incomingdata.mocourses)
							{
								var course = incomingdata.mocourses[courseidx];
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

							$("#incomings").append(
								"<tr" + errorclass + ">" +
								"<td class='text-center'>" + chkbxstring + "</td>" +
								"<td>" + person.nachname + ", " + person.vorname + "</td>" +
								"<td>" + incomingdata.kontaktmail.kontakt + "</td>" +
								"<td>" + incomingdata.pipelineStatusDescription + "</td>" +
								"<td>" + coursesstring + "</td>" +
								"<td class='text-center'>" + newicon + "</td>" +
								"</tr>"
							);

							$("#incomings input[type=checkbox][name='incoming[]']").change(
								MobilityOnlineIncoming._refreshIncomingNumber
							);
							MobilityOnlineIncoming._refreshIncomingNumber();
						}
						var headers = {headers: { 0: { sorter: false, filter: false}, 5: {sorter: false, filter: false} }};

						Tablesort.addTablesorter("incomingstbl", [[1, 0], [2, 0]], ["filter"], 2, headers);
					}
					else
					{
						$("#incomingsyncoutputtext").html("<div class='text-center'>No incomings found!</div>");
					}
				},
				errorCallback: function()
				{
					$("#incomingsyncoutputtext").html("<div class='text-center'>error occured while getting incomings!</div>");
				}
			}
		);
	},
	syncIncomings: function(incomings, studiensemester, maxPostSize)
	{
		var incomingJson = JSON.stringify(incomings);

		var postlength = incomingJson.length + 3.5 * incomings.length;

		if (postlength > maxPostSize)
		{
			var indexhalf = incomings.length / 2;
			var incomingsPartOne = incomings.splice(0, indexhalf);
			var incomingsPartTwo = incomings;//incomings.splice(indexhalf, incomings.length);
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
							$("#incomingsyncoutputtext").append(data.retval.syncoutput);

							if ($("#incomingsyncoutputheading").text().length > 0)
							{
								$("#noadd").text(parseInt($("#noadd").text()) + data.retval.added);
								$("#noupdate").text(parseInt($("#noupdate").text()) + data.retval.updated);
							}
							else
							{
								$("#incomingsyncoutputheading")
									.append("<br />MOBILITY ONLINE INCOMINGS SYNC FINISHED<br /><span id = 'noadd'>"+data.retval.added+"</span> added, <span id = 'noupdate'>"+data.retval.updated+"</span> updated</div>")
									.append("<br />-----------------------------------------------<br />");
							}
							MobilityOnlineIncoming.refreshIncomingsSyncStatus();
						}
					},
					errorCallback: function()
					{
						$("#incomingsyncoutputtext").html("<div class='text-center'>error occured while syncing!</div>");
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
		var moidsel = $("#incomings input[name='incoming[]']");
		var moids = [];

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
						for (var moid in data.retval)
						{
							var prestudent_id = data.retval[moid];
							var infhc = $.isNumeric(prestudent_id);

							// refresh JS array
							for (var incoming in MobilityOnlineIncoming.incomings)
							{
								var incomingobj = MobilityOnlineIncoming.incomings[incoming];

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
							var infhciconel = $("#infhcicon_" + moid);
							var infhcel = $("#infhc_" + moid);

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
	},
	_refreshIncomingNumber: function()
	{
		var length = $("#incomings input[type=checkbox][name='incoming[]']:checked").length;

		$("#noincomings").text(length);
	}
};
