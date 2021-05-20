var MobilityOnlineApplicationsHelper = {
	setSelectAllApplicationsEvent: function()
	{
		//select all application checkboxes
		$("#selectallapplications").click(
			function()
			{
				let applicationelem = $("#applications input[type=checkbox][name='applications[]']");
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
				let applicationElem = $("#applications tr");
				applicationElem.each(
					function()
					{
						let infhc = $(this).find("input.infhc").val();

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
		let length = $("#applications input[type=checkbox][name='applications[]']:checked").length;

		$("#nrapplications").text(length);
	},
	resetSyncOutput: function()
	{
		$("#applicationsyncoutputheading").html("");
		$("#applicationsyncoutputtext").html("<div class='text-center'>-</div>");
	},
	getMessageHtml: function(text, msgtype)
	{
		let msg = text;

		if (msgtype == 'success')
			msg = "<i class='fa fa-check text-success'></i> "+msg+"<br />";
		else if (msgtype == 'error')
			msg = "<span class='text-danger'><i class='fa fa-times'></i> "+msg+"</span><br />";

		return msg;
	},
	writeSyncOutput: function(syncoutput)
	{
		for (let idx in syncoutput)
		{
			let message = syncoutput[idx];

			$("#applicationsyncoutputtext").append("<br />");

			if (message.type === 'error')
				$("#applicationsyncoutputtext").append(
					MobilityOnlineApplicationsHelper.getMessageHtml(message.text, "error")
				);
			else if (message.type === 'success')
				$("#applicationsyncoutputtext").append(
					MobilityOnlineApplicationsHelper.getMessageHtml(message.text, "success")
				);
			else
				$("#applicationsyncoutputtext").append(message.text);
		}
	}
}
