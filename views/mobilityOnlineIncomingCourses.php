<?php
	$this->load->view(
		'templates/FHC-Header',
		array(
			'title' => 'Mobility Online',
			'jquery' => true,
			'jqueryui' => true,
			'bootstrap' => true,
			'fontawesome' => true,
			'sbadmintemplate' => true,
			'ajaxlib' => true,
			'tablesorter' => true,
			'navigationwidget' => true,
			'customJSs' => array('public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineIncomingCourses.js',
								 'public/js/tablesort/tablesort.js'),
			'customCSSs' => array('public/extensions/FHC-Core-MobilityOnline/css/MobilityOnline.css')
		)
	);
?>

<body>
	<div id="wrapper">

		<?php echo $this->widgetlib->widget('NavigationWidget'); ?>

		<div id="page-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-lg-12">
						<h3 class="page-header text-center">MobilityOnline Incoming Courses Assignment</h3>
					</div>
				</div>
				<div class="row text-center">
					<div class="col-xs-4 col-xs-offset-4 form-group">
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
				</div>
				<div class="row">
					<div class="col-xs-5 text-center">
						<div class="well well-sm" id="leftwell">
							<h4>FH-Complete courses</h4>
							<div id="fhcles" class="panel panel-body">
								-
							</div>
						</div>
					</div>
					<div class="col-xs-7">
						<div class="well well-sm wellminheight">
							<div class="text-center">
								<h4><span id="lvhead">&nbsp;MobilityOnline Incoming Courses</span></h4>
								<div id="noincomingstext">
									<span id="nrteachingunits">0</span> teaching units assigned
								</div>
							</div>
							<div class="panel panel-body">
								<table class="table table-bordered table-condensed table-vertical-center" id="incomingstbl">
									<thead>
										<tr>
											<th class="text-center">Assign teaching units</th>
											<th class="text-center">Name</th>
											<th class="text-center">E-Mail</th>
											<th class="text-center">Elected Courses</th>
											<th class="text-center">Status</th>
										</tr>
									</thead>
									<tbody id="incomings">
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
