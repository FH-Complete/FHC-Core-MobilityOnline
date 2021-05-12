<div class="col-xs-8">
	<div class="well well-sm wellminheight">
		<div class="text-center">
			<h4><span id="lvhead">&nbsp;MobilityOnline <?php echo $applicationType ?></span></h4>
			<div id="nrapplicationstext">
				<span id="nrapplications">0</span> <?php echo $applicationType ?> selected
			</div>
			<button class="btn btn-default" id="applicationsyncbtn"><i class="fa fa-refresh"></i>&nbsp;Synchronise <?php echo $applicationType ?></button>
		</div>
		<br />
		<div class="panel panel-body">
			<div class="text-center">
				<a id="selectallapplications"><i class="fa fa-check"></i>&nbsp;select all</a>
				&nbsp;&nbsp;
				<a id="selectnewapplications"><i class="fa fa-check"></i>&nbsp;select new (not in FHC)</a>
			</div>
			<br />
			<table class="table table-bordered table-condensed" id="applicationstbl">
				<thead>
				<tr>
					<th></th>
					<?php foreach ($columnnames as $columnname): ?>
						<th class="text-center"><?php echo $columnname ?></th>
					<?php endforeach; ?>
				</tr>
				</thead>
				<tbody id="applications">
				</tbody>
			</table>
		</div>
	</div>
</div>
