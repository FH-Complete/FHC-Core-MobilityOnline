/**
 * javascript file for Mobility Online incoming sync
 */
const FULL_URL = FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + "/"+FHC_JS_DATA_STORAGE_OBJECT.called_path;

$(document).ready(function()
	{
		MobilityOnlineIncoming.getIncoming($("#studiensemester").val());

		// change displayed lvs when Studiensemester selected
		$("#studiensemester").change(
			function()
			{
				var studiensemester = $(this).val();
				$("#incomingsyncoutput").text('-');
				MobilityOnlineIncoming.getIncoming(studiensemester);
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

				MobilityOnlineIncoming.syncIncomings(incomings, $("#studiensemester").val());
			}
		);

		//select all incoming checkboxes link
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

		//select incoming checkboxes which are not in fhcomplete db yet
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
	getIncoming: function(studiensemester)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getIncomingJson',
			{"studiensemester": studiensemester},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						$("#incomings").empty();
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
								var first = true;
								for (var i in incomingobj.errorMessages)
								{
									var coma = '';
									if (!first)
										coma = ', ';
									errorclass += coma + incomingobj.errorMessages[i];
									first = false;
								}
								errorclass += "'";
							}
							else
							{
								chkbxstring = "<input type='checkbox' value='" + incomingobj.moid + "' name='incoming[]'>";
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
								"<td class='text-center'>" + newicon + "</td>" +
								"</tr>"
							);

							$("#incomings input[type=checkbox][name='incoming[]']").change(
								MobilityOnlineIncoming._refreshIncomingNumber
							);
							MobilityOnlineIncoming._refreshIncomingNumber();
						}
						var headers = {headers: { 0: { sorter: false, filter: false}, 4: {sorter: false, filter: false} }};

						Tablesort.addTablesorter("incomingstbl", [[1, 0], [2, 0]], ["filter"], 2, headers);
					}
					else
					{
						$("#incomingsyncoutput").html("<div class='text-center'>No incomings found!</div>");
					}
				},
				errorCallback: function()
				{
					$("#incomingsyncoutput").html("<div class='text-center'>error occured while getting incomings!</div>");
				}
			}
		);
	},
	syncIncomings: function(incomings, studiensemester)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/syncIncomings',
			{	"incomings": JSON.stringify(incomings),
				"studiensemester": studiensemester
			},
			{
				successCallback: function (data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						$("#incomingsyncoutput").html(data.retval);
						MobilityOnlineIncoming.refreshInFhcColumn();
					}
				},
				errorCallback: function()
				{
					$("#incomingsyncoutput").html("<div class='text-center'>error occured while syncing!</div>");
				}
			}
		);
	},
	refreshInFhcColumn: function()
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
				successCallback: function (data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						for (var incoming in data.retval)
						{
							var incomingobj = data.retval[incoming];
							var infhciconel = $("#infhcicon_" + incoming);
							var infhcel = $("#infhc_" + incoming);

							infhciconel.removeClass();
							if (incomingobj === true)
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
	_refreshIncomingNumber: function()
	{
		var length = $("#incomings input[type=checkbox][name='incoming[]']:checked").length;

		$("#noincomings").text(length);
	}
};
