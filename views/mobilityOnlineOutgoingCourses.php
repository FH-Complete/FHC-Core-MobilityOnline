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
								'public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineOutgoingCourses.js',
								'public/js/tablesort/tablesort.js'),
			'customCSSs' => array('public/css/sbadmin2/tablesort_bootstrap.css',
								'public/extensions/FHC-Core-MobilityOnline/css/MobilityOnline.css')
		)
	);
?>

<div id="wrapper">

	<?php echo $this->widgetlib->widget('NavigationWidget'); ?>

	<div id="page-wrapper">
		<div class="container-fluid">
			<div class="row">
				<div class="col-xs-12">
					<h3 class="page-header text-center">MobilityOnline Outgoing Kurssynchronisierung</h3>
				</div>
			</div>
			<?php $this->load->view('extensions/FHC-Core-MobilityOnline/subviews/selectionHeader.php'); ?>
			<div class="row">
				<?php $this->load->view('extensions/FHC-Core-MobilityOnline/subviews/syncOutput.php'); ?>
				<?php $this->load->view(
						'extensions/FHC-Core-MobilityOnline/subviews/applicationsTable.php',
						array(
							'applicationType' => 'Outgoing Kurse',
							'columnNames' => array(
								'Name', 'E-Mail', 'Kursbezeichnung', 'ECTS', 'Note', 'Lvnummer', 'KursID', 'Gesynct'
							)
						)
				); ?>
			</div>
		</div>
	</div>
</div>

<?php $this->load->view('templates/FHC-Footer'); ?>
