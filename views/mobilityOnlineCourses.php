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
			'navigationwidget' => true,
			'customJSs' => array('public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineCourses.js'),
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
					<div class="col-xs-12">
						<h3 class="page-header text-center">MobilityOnline Kurssynchronisierung</h3>
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
					<div class="col-xs-5">
						<div class="well well-sm wellminheight">
							<div class="text-center">
								<h4>FH-Complete Kurse</h4>
								<br />
								<button class="btn btn-default" id="lvsyncbtn"><i class="fa fa-refresh"></i>&nbsp;Kurse synchronisieren</button>
								<br />
								<h4>
									<span id="lvhead">
										<span id="arrowtoggle"><i class="fa fa-chevron-right"></i>&nbsp;</span>
										<span id="lvcount"><?php echo count($lvs) ?></span>&nbsp;Kurse mit Incomingplätzen
									</span>
								</h4>
							</div>
							<div id="lvs" class="panel panel-body hidden">
							</div>
						</div>
					</div>
					<div class="col-xs-7">
						<div class="well well-sm wellminheight">
							<h4 class="text-center">Synchronisierungsoutput:</h4>
							<div id="lvsyncoutput" class="panel panel-body">
								<div class="text-center">-</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>

