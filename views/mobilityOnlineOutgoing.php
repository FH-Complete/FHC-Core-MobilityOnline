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
			'dialoglib' => true,
			'ajaxlib' => true,
			'tablesorter' => true,
			'navigationwidget' => true,
			'customJSs' => array('public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineApplicationsHelper.js',
								'public/extensions/FHC-Core-MobilityOnline/js/MobilityOnlineOutgoing.js',
								'public/js/tablesort/tablesort.js'),
			'customCSSs' => array('public/css/sbadmin2/tablesort_bootstrap.css',
								'public/extensions/FHC-Core-MobilityOnline/css/MobilityOnline.css')
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
						<h3 class="page-header text-center">MobilityOnline Outgoingsynchronisierung</h3>
					</div>
				</div>
				<?php $this->load->view('extensions/FHC-Core-MobilityOnline/subviews/selectionHeader.php'); ?>
				<div class="row">
					<?php $this->load->view('extensions/FHC-Core-MobilityOnline/subviews/syncOutput.php'); ?>
					<?php $this->load->view(
							'extensions/FHC-Core-MobilityOnline/subviews/applicationsTable.php',
							array(
								'applicationType' => 'Outgoings',
								'columnnames' => array(
									'Name', 'Uid', 'E-Mail', 'Aufh.von', 'Aufh.bis', 'Auszlg.', 'ID', 'Gesynct'
								)
							)
					); ?>
				</div>
			</div>
		</div>
	</div>
</body>

<?php $this->load->view('templates/FHC-Footer'); ?>
