/**
 * javascript file for Mobility Online incoming sync
 */

$(document).ready(function()
	{
		// set expand all Zahlungen link
		$("#optionsPanel").append('&nbsp;&nbsp;<a id="showAllZahlungen"><i class="fa fa-money"></i>&nbsp;Alle Zahlungen anzeigen</a>');

		// get outgoings
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
						let applicationsRowHtml = "";

						// loop for building table rows of application table
						for (let outgoing in outgoings)
						{
							let outgoingobj = outgoings[outgoing];
							let outgoingdata = outgoingobj.data;

							let person = outgoingdata.person;
							let hasError = outgoingobj.error;
							let chkbxString, stgnotsettxt, errorClass, newicon;
							chkbxString = stgnotsettxt = errorClass = "";
							let moid = outgoingobj.moid;
							let student_uid = outgoingdata.bisio.student_uid;
							let vorname = person.vorname;
							let nachname = person.nachname;
							let gerdateVon = MobilityOnlineOutgoing._formatDateGerman(outgoingdata.bisio.von);
							let gerdateBis = MobilityOnlineOutgoing._formatDateGerman(outgoingdata.bisio.bis);
							let zahlungen = outgoingdata.zahlungen;

							// show errors in tooltip if sync not possible
							if (hasError)
							{
								errorClass = " class='inactive' data-toggle='tooltip' title='";
								let firstMsg = true;
								for (let i in outgoingobj.errorMessages)
								{
									if (!firstMsg)
										errorClass += ', ';
									errorClass += outgoingobj.errorMessages[i];
									firstMsg = false;
								}
								errorClass += "'";
							}
							else
							{
								chkbxString = "<input type='checkbox' value='" + moid + "' name='applications[]'>";
							}

							if (outgoingobj.infhc)
							{
								newicon = "<i id='infhcicon_"+moid+"' class='fa fa-check'></i><input type='hidden' id='infhc_"+moid+"' class='infhc' value='1'>";
							}
							else
							{
								newicon = "<i id='infhcicon_"+moid+"' class='fa fa-times'></i><input type='hidden' id='infhc_"+moid+"' class='infhc' value='0'>";
							}

							// show expandable payment number
							let paymentNumberString = zahlungen.length > 0 ?
								"<button id='paymentNo_"+moid+"' type='button' class='btn btn-default btn-xs paymentNo'>" +
								"<i class='fa fa-caret-right'></i> "+zahlungen.length+"</button>" : zahlungen.length;

							// render right hand table with MO data
							applicationsRowHtml =
								"<tr id='applicationsrow_"+moid+"'" + errorClass + ">" +
								"<td class='text-center' id='checkboxcell_"+moid+"'>" + chkbxString + "</td>" +
								"<td>" + nachname + ", " + vorname + "</td>" +
								"<td>" + student_uid + "</td>" +
								"<td>" + outgoingdata.kontaktmail.kontakt + "</td>" +
								"<td class='text-center'>" + gerdateVon + "</td>" +
								"<td class='text-center'>" + gerdateBis + "</td>" +
								"<td class='text-center'>" + paymentNumberString + "</td>" +
								"<td class='text-center'>" + moid + "</td>" +
								"<td class='text-center' id='infhciconcell_"+outgoingobj.moid+"'>" + newicon + "</td>" +
								"</tr>";

							$("#applicationstbl").append(applicationsRowHtml);

							// setting events

							// show payments for one bisio event
							$("#paymentNo_"+moid).click(
								function(e)
								{
									// if existing bisio, do not show bisio in left box
									e.stopPropagation();

									if (zahlungen.length > 0)
									{
										if ($(".zahlungrow_" + moid).length > 0)
										{
											$(".zahlungrow_" + moid).remove();
											$("#paymentNo_"+moid+" i").removeClass('fa-caret-down').addClass('fa-caret-right');
										}
										else
										{
											MobilityOnlineOutgoing._showZahlungen(moid, zahlungen);
										}
									}
								}
							);

							// show existing bisios in left box
							MobilityOnlineOutgoing._setExistingBisiosEvents(outgoingobj);

							// number of applications selected via checkboxes
							$("#applications input[type=checkbox][name='applications[]']").change(
								MobilityOnlineApplicationsHelper.refreshApplicationsNumber
							);
							MobilityOnlineApplicationsHelper.refreshApplicationsNumber();
						}

						// add tablesorter to right hand MO table
						let tablesortParams = {headers: { 0: { sorter: false, filter: false}, 4: {sorter: "shortDate"}, 5: {sorter: "shortDate"},
													8: {sorter: false, filter: false} }, dateFormat: "ddmmyyyy"};

						Tablesort.addTablesorter("applicationstbl", [[1, 0], [2, 0], [3, 0], [4, 0]], ["filter"], 2, tablesortParams);

						// show payments for all bisios event
						$("#showAllZahlungen").click(
							function()
							{
								for (let outgoing in outgoings)
								{
									let outgoingobj = outgoings[outgoing];
									let zahlungen = outgoingobj.data.zahlungen;

									MobilityOnlineOutgoing._showZahlungen(outgoingobj.moid, zahlungen);
								}
							}
						);

						// bind to sort event
						$("#applicationstbl").bind("sortBegin filterStart",function(e, table) {
							// remove zahlung rows on resort and refilter
							$(".zlgRow").remove();
							$(".paymentNo i").removeClass('fa-caret-down').addClass('fa-caret-right');
						});
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
								.append("<br />MOBILITY ONLINE OUTGOING SYNC ENDE<br />"+
									"<span id = 'nradd'>" +syncres.added.length + "</span> hinzugefügt, "+
									"<span id = 'nrupdate'>" + syncres.updated.length + "</span> aktualisiert</div>")
								.append("<br />-----------------------------------------------<br />");
						}
						MobilityOnlineOutgoing.refreshOutgoingsSyncStatus(syncres.added.concat(syncres.updated));
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
								MobilityOnlineApplicationsHelper.getMessageHtml("Applicationid " + insertedMoid + " erfolgreich verlinkt", "success")
							);
							initOutgoingSync(insertedMoid);
						}
					},
					errorCallback: function()
					{
						$("#applicationsyncoutputtext").html(MobilityOnlineApplicationsHelper.getMessageHtml("Fehler beim Verlinken der Mobilität!", "error"));
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

			// refresh zahlungen infhc flags too
			let zlginfhciconel = $(".zlgInFhc_"+moid);
			let zlginfhcel = $("#infhc_" + moid);

			zlginfhciconel.removeClass();
			zlginfhcel.val("1");
			zlginfhciconel.addClass("fa fa-check");
		}
	},
	_showZahlungen: function(moid, zahlungen)
	{
		if (!$(".zahlungrow_" + moid).length)
		{
			for (let zlg in zahlungen)
			{
				let zahlung = zahlungen[zlg];
				let buchungsinfo = zahlung.buchungsinfo;

				let mo_referenz_nr = buchungsinfo.mo_referenz_nr;

				if (buchungsinfo.infhc)
				{
					newicon = "<i id='zlgInFhcicon_"+moid+"' class='fa fa-check zlgInFhc_"+moid+"'></i><input type='hidden' id='infhc_"+moid+"' class='infhc' value='1'>";
				}
				else
				{
					newicon = "<i id='zlgInFhcicon_"+moid+"' class='fa fa-times zlgInFhc_"+moid+"'></i><input type='hidden' id='infhc_"+moid+"' class='infhc' value='0'>";
				}

				$("#paymentNo_"+moid+" i").removeClass('fa-caret-right').addClass('fa-caret-down');

				$("#applicationsrow_" + moid).after(
					"<tr class='zlgRow zahlungrow_" + moid + "'>" +
					"<td><i class='fa fa-arrow-right'</td>" +
					"<td colspan='7'>" +
					"<b>Referenznr:</b> " + mo_referenz_nr +
					" | <b>Zahlungsgrund:</b> " + buchungsinfo.mo_zahlungsgrund +
					" | <b>Betrag:</b> " + MobilityOnlineOutgoing._formatDecimalGerman(zahlung.konto.betrag) +
					"</td>" +
					"<td class='text-center'>" + newicon + "</td>" +
					"</tr>"
				);
			}
		}
	},
	_setExistingBisiosEvents: function(outgoingobj)
	{
		// handle linking of existing bisios
		if (outgoingobj.existingBisios && outgoingobj.existingBisios.length > 0)
		{
			let existingBisios = outgoingobj.existingBisios;
			let applicationsrowEl = $("#applicationsrow_"+outgoingobj.moid);
			let moDateVon = outgoingobj.data.bisio.von;
			let moDateBis = outgoingobj.data.bisio.bis;
			let person = outgoingobj.data.person;
			applicationsrowEl.addClass("clickableApplicationsrow");
			applicationsrowEl.click(
				function()
				{
					let checkedFound = false;

					let bisiosHtml = "<div class='text-center'>";
					let linkBtnHtml = "<div class='text-center'><button class='btn btn-default linkBisioBtn'>" +
						"<i class='fa fa-link'></i>&nbsp;Verlinken</button></div>";
					bisiosHtml += linkBtnHtml+"<br />";

					bisiosHtml += "<div class='radio'>" +
						"<label><input type='radio' name='bisiocheck' id='addNewBisio' value='null'>&nbsp;Neue Mobilität hinzufügen</label>" +
						"</div>";

					for (let idx in existingBisios)
					{
						let bisio = existingBisios[idx];

						let checked = '';
						if (!checkedFound && bisio.von === moDateVon && bisio.bis === moDateBis)
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
						bisiosHtml += "<td>" + MobilityOnlineOutgoing._formatDateGerman(bisio.von) + "</td>";
						bisiosHtml += "</tr>";
						bisiosHtml += "<tr>";
						bisiosHtml += "<td>Bis</td>";
						bisiosHtml += "<td>" + MobilityOnlineOutgoing._formatDateGerman(bisio.bis) + "</td>";
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
					$("#applicationsrow_"+outgoingobj.moid+" td").css("background-color", "#f5f5f5"); // color should stay after click

					$("#applicationsyncoutputheading").html(
						'<h4>Richtige fhcomplete Mobilität zum Verlinken für '+outgoingobj.data.bisio.student_uid+', '+person.vorname+' '
							+person.nachname+' auswählen</h4>'
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
							MobilityOnlineOutgoing.linkBisio(outgoingobj.moid, bisio_id);
						}
					)
				}
			)
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
	_formatDateGerman(date)
	{
		date = new Date(date);
		return MobilityOnlineOutgoing._padDatePart(date.getDate())
		+"."+MobilityOnlineOutgoing._padDatePart(date.getMonth()+1)
		+"."+date.getFullYear();
	},
	/**
	 * Formats a numeric value as a float with two decimals
	 * @param sum
	 * @returns {string}
	 */
	_formatDecimalGerman: function(sum)
	{
		var dec = null;

		if(sum === null)
			dec = parseFloat(0).toFixed(2).replace(".", ",");
		else if(sum === '')
		{
			dec = ''
		}
		else
		{
			dec = parseFloat(sum).toFixed(2);

			dec = dec.split('.');
			var dec1 = dec[0];
			var dec2 = ',' + dec[1];
			var rgx = /(\d+)(\d{3})/;
			while (rgx.test(dec1)) {
				dec1 = dec1.replace(rgx, '$1' + '.' + '$2');
			}
			dec = dec1 + dec2;
		}
		return dec;
	}
};
