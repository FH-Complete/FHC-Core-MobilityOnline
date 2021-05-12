var MobilityOnlineApplicationsHelper = {
	setSelectAllApplicationsEvent: function()
	{
		//select all application checkboxes
		$("#selectallapplications").click(
			function()
			{
				var applicationelem = $("#applications input[type=checkbox][name='applications[]']");
				applicationelem.each(
					function()
					{
						$(this).prop('checked', true);
					}
				);
				MobilityOnlineApplicationsHelper.refreshApplicationsNumber();
			}
		);
	},
	setSelectNewApplicationsEvent: function()
	{
		//select applications which are not in FHC yet
		$("#selectnewapplications").click(
			function()
			{
				var incomingelem = $("#incomings tr");
				incomingelem.each(
					function()
					{
						var infhc = $(this).find("input.infhc").val();

						if (infhc === '0')
							$(this).find("input[type=checkbox][name='applications[]']").prop('checked', true);
						else
							$(this).find("input[type=checkbox][name='applications[]']").prop('checked', false);
					}
				);
				MobilityOnlineApplicationsHelper.refreshApplicationsNumber();
			}
		);
	},
	refreshApplicationsNumber: function()
	{
		var length = $("#applications input[type=checkbox][name='applications[]']:checked").length;

		$("#nrapplications").text(length);
	},
	resetSyncOutput: function()
	{
		$("#applicationsyncoutputheading").html("");
		$("#applicationsyncoutputtext").html("<div class='text-center'>-</div>");
	}
}
