/**
 * javascript file for Mobility Online incoming sync
 */

$(document).ready(function()
	{
		MobilityOnlineOutgoing.getOutgoing($("#studiensemester").val(), $("#studiengang_kz").val());

		let getOutgoingFunc = function()
		{
			let studiensemester = $("#studiensemester").val();
			let studiengang_kz = $("#studiengang_kz").val();
			MobilityOnlineApplicationsHelper.resetSyncOutput();
			MobilityOnlineOutgoing.getOutgoing(studiensemester, studiengang_kz);
		}

		// get Outgoings when Dropdown selected
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
				let outgoingelem = $("#applications input[type=checkbox]:checked");
				let outgoings = [];
				outgoingelem.each(
					function()
					{
						outgoings.push(MobilityOnlineOutgoing._findOutgoingByMoid($(this).val())[0]);
					}
				);

				$("#applicationsyncoutput div").empty();

				MobilityOnlineOutgoing.syncOutgoings(outgoings, $("#studiensemester").val());
			}
		);

		//select all incoming checkboxes
		MobilityOnlineApplicationsHelper.setSelectAllApplicationsEvent();
		//select incoming application which are not in FHC yet
		MobilityOnlineApplicationsHelper.setSelectNewApplicationsEvent();
	}
);

var MobilityOnlineOutgoing = {
	outgoings: null,
	getOutgoing: function(studiensemester, studiengang_kz)
	{
		if (studiensemester == null || studiensemester === "" || studiengang_kz == null
			|| (!$.isNumeric(studiengang_kz) && studiengang_kz !== "all"))
			return;

		FHC_AjaxClient.ajaxCallGet(
			FHC_JS_DATA_STORAGE_OBJECT.called_path+'/getOutgoingJson',
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
						MobilityOnlineOutgoing.outgoings = outgoings;

						for (let outgoing in outgoings)
						{
							let outgoingobj = outgoings[outgoing];
							let outgoingdata = outgoingobj.data;

							let person = outgoingdata.person;
							let hasError = outgoingobj.error;
							let chkbxstring, stgnotsettxt, errorclass, newicon;
							chkbxstring = stgnotsettxt = errorclass = "";
							let moid = outgoingobj.moid;
							let gerdatevon = MobilityOnlineOutgoing._convertDateToGerman(outgoingdata.bisio.von);
							let gerdatebis = MobilityOnlineOutgoing._convertDateToGerman(outgoingdata.bisio.bis);
							let student_uid = outgoingdata.bisio.student_uid;
							let vorname = person.vorname;
							let nachname = person.nachname;

							// show errors in tooltip if sync not possible
							if (hasError)
							{
								errorclass = " class='inactive' data-toggle='tooltip' title='";
								let firstmsg = true;
								for (let i in outgoingobj.errorMessages)
								{
									if (!firstmsg)
										errorclass += ', ';
									errorclass += outgoingobj.errorMessages[i];
									firstmsg = false;
								}
								errorclass += "'";
							}
							else
							{
								chkbxstring = "<input type='checkbox' value='" + moid + "' name='applications[]'>";
							}

							// courses from MobilityOnline
/*							var coursesstring = '';
							var firstcourse = true;

							for (var courseidx in incomingdata.mocourses)
							{
								var course = incomingdata.mocourses[courseidx];
								if (!firstcourse)
									coursesstring += ' | ';
								coursesstring += course.number + ': ' + course.name;
								firstcourse = false;
							}*/

							if (outgoingobj.infhc)
							{
								newicon = "<i id='infhcicon_"+moid+"' class='fa fa-check'></i><input type='hidden' id='infhc_"+moid+"' class='infhc' value='1'>";
							}
							else
							{
								newicon = "<i id='infhcicon_"+moid+"' class='fa fa-times'></i><input type='hidden' id='infhc_"+moid+"' class='infhc' value='0'>";
							}

							$("#applications").append(
								"<tr id='applicationsrow_"+moid+"'" + errorclass + ">" +
								"<td class='text-center' id='checkboxcell_"+moid+"'>" + chkbxstring + "</td>" +
								"<td>" + nachname + ", " + vorname + "</td>" +
								"<td>" + student_uid + "</td>" +
								"<td>" + outgoingdata.kontaktmail.kontakt + "</td>" +
								"<td class='text-center'>" + gerdatevon + "</td>" +
								"<td class='text-center'>" + gerdatebis + "</td>" +
								"<td class='text-center'>" + moid + "</td>" +
								"<td class='text-center' id='infhciconcell_"+outgoingobj.moid+"'>" + newicon + "</td>" +
								"</tr>"
							);

							$("#applications input[type=checkbox][name='applications[]']").change(
								MobilityOnlineApplicationsHelper.refreshApplicationsNumber
							);
							MobilityOnlineApplicationsHelper.refreshApplicationsNumber();

							if (outgoingobj.existingBisios && outgoingobj.existingBisios.length > 0)
							{
								let existingBisios = outgoingobj.existingBisios;
								let applicationsrowEl = $("#applicationsrow_"+outgoingobj.moid);
								applicationsrowEl.addClass("clickableApplicationsrow");
								applicationsrowEl.click(
									function()
									{
										let checkedFound = false;

										let bisiosHtml = "<div class='text-center'>";
										let linkBtnHtml = "<div class='text-center'><button class='btn btn-default linkBisioBtn'>" +
											"<i class='fa fa-link'></i>&nbsp;Link</button></div>";
										bisiosHtml += linkBtnHtml+"<br />";

										bisiosHtml += "<div class='radio'>" +
												"<label><input type='radio' name='bisiocheck' id='addNewBisio' value='null'>&nbsp;Add new mobility</label>" +
											"</div>";

										for (let idx in existingBisios)
										{
											let bisio = existingBisios[idx];
											let bisio_von = MobilityOnlineOutgoing._convertDateToGerman(bisio.von);
											let bisio_bis = MobilityOnlineOutgoing._convertDateToGerman(bisio.bis);

											let checked = '';
											if (!checkedFound && bisio_von === gerdatevon && bisio_bis === gerdatebis)
											{
												checked = ' checked';
												checkedFound = true;
											}

											bisiosHtml += "<table class='table-bordered table-condensed table-bisiolink'>";
											bisiosHtml += "<tr>";
											bisiosHtml += "<td colspan='2'>";
											bisiosHtml += "<input type='radio' name='bisiocheck' value='fhcbisio_"+bisio.bisio_id+"'"+(checked ? ' checked' : '')+">";
											bisiosHtml += "</td>";
											bisiosHtml += "</tr>";
											bisiosHtml += "<tr>";
											bisiosHtml += "<td>Von</td>";
											bisiosHtml += "<td>" + bisio_von + "</td>";
											bisiosHtml += "</tr>";
											bisiosHtml += "<tr>";
											bisiosHtml += "<td>Bis</td>";
											bisiosHtml += "<td>" + bisio_bis + "</td>";
											bisiosHtml += "</tr>";
											bisiosHtml += "<tr>";
											bisiosHtml += "<td>Mobilit&auml;tsprogramm</td>";
											bisiosHtml += "<td>" + (bisio.mobilitaetsprogramm != null ? bisio.mobilitaetsprogramm : "") + "</td>";
											bisiosHtml += "</tr>"
											bisiosHtml += "<tr>";
											bisiosHtml += "<td>Zweck</td>";
											bisiosHtml += "<td>" + (bisio.zweck != null ? bisio.zweck : "") + "</td>";
											bisiosHtml += "</tr>"
											bisiosHtml += "<tr>";
											bisiosHtml += "<td>Nation</td>";
											bisiosHtml += "<td>" + (bisio.nation != null ? bisio.nation : "") + "</td>";
											bisiosHtml += "</tr>"
											bisiosHtml += "<tr>";
											bisiosHtml += "<td>Universit&auml;t</td>";
											bisiosHtml += "<td>" + (bisio.nation != null ? bisio.universitaet : "") + "</td>";
											bisiosHtml += "</tr>";
											bisiosHtml += "</table>";
											bisiosHtml += "<br />"
										}

										bisiosHtml += linkBtnHtml;

										bisiosHtml += "</div>";

										$("#applications td").css("background-color", ""); // reset color of other clicked rows
										$("#applicationsrow_"+moid+" td").css("background-color", "#f5f5f5"); // color should stay after click

										$("#applicationsyncoutputheading").html(
											'<h4>Select correct fhcomplete mobility to link for '+student_uid+', '+vorname+' '+nachname+'</h4>'
										)

										$("#applicationsyncoutputtext").html(
											bisiosHtml
										)

										if (!checkedFound)
											$("#addNewBisio").prop("checked", true);

										$(".linkBisioBtn").click(
											function()
											{
												let bisio_id_with_prefix = $('input[name=bisiocheck]:checked').val();

												// if null, outgoing should be newly added, no existing outgoing in fhc is selected
												let bisio_id = bisio_id_with_prefix === 'null' ? null : bisio_id_with_prefix.substr(bisio_id_with_prefix.indexOf('_') + 1);
												MobilityOnlineOutgoing.linkBisio(moid, bisio_id);
											}
										)
									}
								)
							}
						}
						let tablesortParams = {headers: { 0: { sorter: false, filter: false}, 4: {sorter: "shortDate"}, 5: {sorter: "shortDate"},
													7: {sorter: false, filter: false} }, dateFormat: "ddmmyyyy"};

						Tablesort.addTablesorter("applicationstbl", [[1, 0], [2, 0], [3, 0], [4, 0]], ["filter"], 2, tablesortParams);
					}
					else
					{
						$("#applicationsyncoutputtext").html("<div class='text-center'>No outgoings found!</div>");
					}
				},
				errorCallback: function()
				{
					$("#applicationsyncoutputtext").html("<div class='text-center'>error occured while getting outgoings!</div>");
				}
			}
		);
	},
	syncOutgoings: function(outgoings, studiensemester)
	{
		FHC_AjaxClient.ajaxCallPost(
			FHC_JS_DATA_STORAGE_OBJECT.called_path + '/syncOutgoings',
			{
				"outgoings": JSON.stringify(outgoings),
				"studiensemester": studiensemester
			},
			{
				successCallback: function(data, textStatus, jqXHR)
				{
					if (FHC_AjaxClient.hasData(data))
					{
						let syncres = FHC_AjaxClient.getData(data);

						$("#applications td").css("background-color", ""); // remove background color of applications table

						MobilityOnlineApplicationsHelper.writeSyncOutput(syncres.syncoutput);

						$("#applicationsyncoutputtext").append(data.retval.syncoutput);

						if ($("#applicationsyncoutputheading").text().length > 0)
						{
							$("#nradd").text(parseInt($("#nradd").text()) + syncres.added.length);
							$("#nrupdate").text(parseInt($("#nrupdate").text()) + syncres.updated.length);
						}
						else
						{
							$("#applicationsyncoutputheading")
								.append("<br />MOBILITY ONLINE OUTGOING SYNC FINISHED<br />"+
									"<span id = 'nradd'>" +syncres.added.length + "</span> added, "+
									"<span id = 'nrupdate'>" + syncres.updated.length + "</span> updated</div>")
								.append("<br />-----------------------------------------------<br />");
						}
						MobilityOnlineOutgoing.refreshOutgoingsSyncStatus(syncres.added.concat(syncres.updated));
					}
				},
				errorCallback: function()
				{
					$("#applicationsyncoutputtext").html(
						MobilityOnlineApplicationsHelper.getMessageHtml("error occured while syncing!", "error")
					);
				}
			}
		);
	},
	linkBisio: function(moid, bisio_id)
	{
		let initOutgoingSync = function(moid)
		{
			MobilityOnlineOutgoing._blackInApplicationRow(moid);

			let outgoingToSync = MobilityOnlineOutgoing._findOutgoingByMoid(moid);
			MobilityOnlineOutgoing.syncOutgoings(outgoingToSync, $("#studiensemester").val());
		}

		if (bisio_id == null)
		{
			$("#applicationsyncoutputtext").html("");
			initOutgoingSync(moid);
		}
		else
		{
			FHC_AjaxClient.ajaxCallPost(
				FHC_JS_DATA_STORAGE_OBJECT.called_path + '/linkBisio',
				{
					"moid": moid,
					"bisio_id": bisio_id
				},
				{
					successCallback: function(data, textStatus, jqXHR)
					{
						if (FHC_AjaxClient.hasData(data))
						{
							let insertedMapping = FHC_AjaxClient.getData(data)
							let insertedMoid = insertedMapping.mo_applicationid;
							$("#applicationsyncoutputtext").html(
								MobilityOnlineApplicationsHelper.getMessageHtml("successfully linked applicationid " + insertedMoid, "success")
							);
							initOutgoingSync(insertedMoid);
						}
					},
					errorCallback: function()
					{
						$("#applicationsyncoutputtext").html(MobilityOnlineApplicationsHelper.getMessageHtml("error occured while linking mobility!", "error"));
					}
				}
			);
		}
	},
	/**
	 * Refreshes status (infhc, not in fhc) of outgoings
	 */
	refreshOutgoingsSyncStatus: function(synced_moids)
	{
		for (let idx in synced_moids)
		{
			let moid = synced_moids[idx];

			// refresh JS array
			for (let outgoing in MobilityOnlineOutgoing.outgoings)
			{
				let outgoingsobj = MobilityOnlineOutgoing.outgoings[outgoing];

				if (outgoingsobj.moid === parseInt(moid))
				{
					outgoingsobj.infhc = true;
					break;
				}
			}

			// refresh Outgoings Table "in FHC" field
			let infhciconel = $("#infhcicon_" + moid);
			let infhcel = $("#infhc_" + moid);

			infhciconel.removeClass();
			infhcel.val("1");
			infhciconel.addClass("fa fa-check");
		}
	},
	_findOutgoingByMoid(moid)
	{
		let outgoingFound = [];
		for (let outgoing in MobilityOnlineOutgoing.outgoings)
		{
			let moinc = MobilityOnlineOutgoing.outgoings[outgoing];
			if (moinc.moid == moid)
			{
				outgoingFound.push(moinc);
				break;
			}
		}

		return outgoingFound;
	},
	_blackInApplicationRow: function(moid)
	{
		$("#applicationsyncoutputheading").html('');
		let applicationsrowEl = $("#applicationsrow_"+moid);
		applicationsrowEl.css("color", "black");
		applicationsrowEl.off("click"); // row not clickable anymore
		applicationsrowEl.removeClass("clickableApplicationsrow"); // row not clickable anymore
		applicationsrowEl.removeAttr("title"); // remove tooltip

		let chkboxElement = $("<input type='checkbox' value='" + moid + "' name='applications[]'>");

		// reassign outgoing number event to new checkbox
		chkboxElement.change(
			MobilityOnlineApplicationsHelper.refreshApplicationsNumber
		);
		$("#checkboxcell_"+moid).append(chkboxElement);
	},
	_padDatePart(datePart)
	{
		return ('' + datePart).length < 2 ? '0'+datePart : datePart;
	},
	_convertDateToGerman(date)
	{
		date = new Date(date);
		return MobilityOnlineOutgoing._padDatePart(date.getDate())
		+"."+MobilityOnlineOutgoing._padDatePart(date.getMonth()+1)
		+"."+date.getFullYear();
	}
};
