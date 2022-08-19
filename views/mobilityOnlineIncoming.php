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
			'customJSs' => array('public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineApplicationsHelper.js',
								'public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineIncoming.js',
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
						<h3 class="page-header text-center">MobilityOnline Incomingsynchronisierung</h3>
					</div>
				</div>
				<?php $this->load->view('extensions/FHC-Core-MobilityOnline/subviews/selectionHeader.php'); ?>
				<div class="row">
					<?php $this->load->view('extensions/FHC-Core-MobilityOnline/subviews/syncOutput.php'); ?>
					<?php $this->load->view(
							'extensions/FHC-Core-MobilityOnline/subviews/applicationsTable.php',
							array(
								'applicationType' => 'Incomings',
								'columnNames' => array(
									'Name', 'E-Mail', 'Letzter Status', 'Kurse', 'In FHC'
								)
							)
					); ?>
				</div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
