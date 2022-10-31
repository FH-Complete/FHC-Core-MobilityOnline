<?php
	$this->load->view(
		'templates/FHC-Header',
		array(
			'title' => 'Mobility Online',
			'jquery3' => true,
			'jqueryui1' => true,
			'bootstrap3' => true,
			'fontawesome4' => true,
			'sbadmintemplate3' => true,
			'dialoglib' => true,
			'ajaxlib' => true,
			'tablesorter2' => true,
			'navigationwidget' => true,
			'customJSs' => array('public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineIncomingCourses.js',
								 'public/js/tablesort/tablesort.js'),
			'customCSSs' => array('public/extensions/FHC-Core-MobilityOnline/css/MobilityOnline.css',
								  'public/css/sbadmin2/tablesort_bootstrap.css')
		)
	);
?>

<body>
	<div id="wrapper">

		<?php echo $this->widgetlib->widget('NavigationWidget'); ?>

		<div id="page-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-xs-12">
						<h3 class="page-header text-center">MobilityOnline Incoming Kurszuweisung</h3>
					</div>
				</div>
				<div class="row text-center" id="syncIncomingInput">
					<div class="col-xs-2 col-xs-offset-2 form-group">
						<label>Studiensemester</label>
						<select class="form-control" name="studiensemester" id="studiensemester">
							<?php
							foreach ($semester as $sem):
								$selected = $sem->studiensemester_kurzbz === $currsemester[0]->studiensemester_kurzbz ? ' selected=""' : '';
								?>
								<option value="<?php echo $sem->studiensemester_kurzbz ?>"<?php echo $selected ?>>
									<?php echo $sem->studiensemester_kurzbz ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-xs-3 form-group">
						<label>Studiengang</label>
						<select class="form-control" name="studiengang_kz" id="studiengang_kz">
							<option value="" selected="selected">Studiengang wählen...</option>';
							<option value="all">Alle</option>
							<?php
							$typ = '';
							foreach ($studiengaenge as $studiengang):

								if ($typ != $studiengang->typ || $typ == '')
								{
									if ($typ != '')
										echo '</optgroup>';

									echo '<optgroup label = "'.($studiengang->typbezeichnung !== '' ? $studiengang->typbezeichnung : $studiengang).'">';
								}
								$typ = $studiengang->typ;
								?>
								<option value="<?php echo $studiengang->studiengang_kz ?>">
									<?php echo $studiengang->kuerzel . ' - ' . $studiengang->bezeichnung ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-xs-2" id="showSyncedIncomings" style="top: 25px">
						<button class="btn btn-default" id="showincomingsbtn">Synchronisierte Incomings anzeigen</button>
					</div>
				</div>
				<div class="row" id="incomingprestudentsrow">
					<div class="col-xs-12">
						<div class="well well-sm wellminheight">
							<div class="text-center">
								<h4>MobilityOnline Incomings</h4>
								<div id="nrapplicationstext">
									<span id="totalCoursesAssigned">0</span>/<span id="totalCoursesFhc">0</span> Kurse zugewiesen
								</div>
							</div>
							<div class="panel panel-body">
								<table class="table table-bordered table-condensed table-vertical-center" id="incomingprestudentstbl">
									<thead>
										<tr>
											<th class="text-center">Name</th>
											<th class="text-center">E-Mail</th>
											<th class="text-center">Kurse hinzugefügt</th>
											<th class="text-center">Kurse hinzufügen</th>
										</tr>
									</thead>
									<tbody id="incomingprestudents">
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<div class="row hidden" id="lvsprestudent">
					<div class="col-xs-12">
						<div class="panel panel-default">
							<table class="table table-condensed table-bordered">
								<tbody id="lvsprestudentdata">
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="row hidden" id="coursesassignment">
					<div class="col-xs-6">
						<div class="well well-sm" id="fhccourseswell">
							<h4 class="text-center">FH-Complete Kurse</h4>
							<div id="allfhcles" class="panel panel-body">
								-
							</div>
							<div id='message' class="text-center"></div>
						</div>
					</div>
					<div class="col-xs-6">
						<div class="well well-sm wellminheight" id="mocourseswell">
							<div class="text-center">
								<h4>MobilityOnline Kurse</h4>
							</div>
							<div class="panel panel-body" id="molvspnl">
								<div class="panel panel-default">
								<table class="table table-bordered table-condensed table-vertical-center" id="molvstbl">
									<thead>
									<tr>
										<th class="text-center">Kurs</th>
										<th class="text-center">Status</th>
									</tr>
									</thead>
									<tbody id="molvs">
									</tbody>
								</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
