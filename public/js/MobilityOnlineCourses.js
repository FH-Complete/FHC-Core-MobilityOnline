/**
 * javascript file for Mobility Online courses sync
 */
const FULL_URL = FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + "/"+FHC_JS_DATA_STORAGE_OBJECT.called_path;

$(document).ready(function()
	{
		MobilityOnlineCourses.getLvs($("#studiensemester").val());

		// change displayed lvs when Studiensemester selected
		$("#studiensemester").change(
			function()
			{
				var studiensemester = $(this).val();
				MobilityOnlineCourses.getLvs(studiensemester);
			}
		);

		//collapse/expand lvlist
		$("#lvhead").click(
			function()
			{
				var lvsel = $("#lvs");
				if (lvsel.hasClass("hidden"))
				{
					lvsel.removeClass("hidden");
					$("#arrowtoggle").html("<i class='fa fa-chevron-down'></i>");
				}
				else
				{
					lvsel.addClass("hidden");
					$("#arrowtoggle").html("<i class='fa fa-chevron-right'></i>");
				}
			}
		);

		//init sync
		$("#lvsyncbtn").click(
			function()
			{
				MobilityOnlineCourses.syncLvs($("#studiensemester").val());
			}
		);
	}
);

var MobilityOnlineCourses = {
	getLvs: function(studiensemester)
	{
		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getLvsJson',
			{"studiensemester": studiensemester},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.isSuccess(data))
					{
						var lvcount = data.retval.length;
						$("#lvcount").text(lvcount);
						$("#lvs").empty();
						for (var i in data.retval)
						{
							var lv = data.retval[i];
							$("#lvs").append("<p>" + lv.studiengang_kuerzel + " " + lv.orgform_kurzbz + " - "
								+ lv.lv_bezeichnung + " " + lv.lehrform_kurzbz + " - " + lv.lehrveranstaltung_id + "</p>");
						}
					}
					else
					{
							$("#lvs").html("<p>" + (data.retval ? data.retval : "error when getting courses") + "</p>");
					}
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					FHC_DialogLib.alertError("error when getting courses!");
				}
			}
		);
	},
	syncLvs: function(studiensemester)
	{
		FHC_AjaxClient.showVeil();
		$(".fhc-ajaxclient-veil").append("<div class='veil-text'>Synchronising...</div>");

		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/syncLvs',
			{"studiensemester": studiensemester},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						var syncdata = data.retval;
						$("#lvsyncoutput").html(syncdata.syncoutput);
						var infotext = "Sync completed. " + syncdata.added + " added,<br />" + syncdata.updated +
							" updated, " + syncdata.deleted + " deleted,<br />" +
							"<span "+(syncdata.errors > 0 ? "class='text-danger'" : "") + ">" + syncdata.errors + " errors</span>";
						FHC_DialogLib.alertInfo(infotext);
					}
					FHC_AjaxClient.hideVeil();
				},
				errorCallback: function(jqXHR, textStatus, errorThrown)
				{
					$("#lvsyncoutput").html("<div class='text-center'>error occured while syncing!</div>");
					FHC_AjaxClient.hideVeil();
				}
			}
		);

	}
};
