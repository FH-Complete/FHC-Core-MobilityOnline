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
			'customJSs' => array('public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineIncoming.js',
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
					<div class="col-lg-12">
						<h3 class="page-header text-center">MobilityOnline Incoming Synchronisation</h3>
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
						<div class="well well-sm wellminheight">
							<h4>synchronisation output:</h4>
							<div id="syncoutput" class="panel panel-body">
								-
							</div>
						</div>
					</div>
					<div class="col-xs-7">
						<div class="well well-sm wellminheight">
							<div class="text-center">
								<h4><span id="lvhead">&nbsp;MobilityOnline Incomings</span></h4>
								<div id="noincomingstext">
									<span id="noincomings">0</span> Incomings selected
								</div>
								<button class="btn btn-default" id="syncbtn"><i class="fa fa-refresh"></i>&nbsp;Synchronise Incomings</button>
							</div>
							<br />
							<div class="panel panel-body">
								<div class="text-center">
									<a id="selectallincomings"><i class="fa fa-check"></i>&nbsp;select all</a>
									&nbsp;&nbsp;
									<a id="selectnewincomings"><i class="fa fa-check"></i>&nbsp;select new (not in FHC)</a>
								</div>
								<br />
								<table class="table table-bordered table-condensed" id="incomingstbl">
									<thead>
										<tr>
											<th></th>
											<th class="text-center">Name</th>
											<th class="text-center">E-Mail</th>
											<th class="text-center">Last Status</th>
											<th class="text-center">in FHC</th>
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
