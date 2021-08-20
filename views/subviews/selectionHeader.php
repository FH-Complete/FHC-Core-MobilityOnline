<div class="row text-center">
	<div class="col-xs-4 col-xs-offset-2 form-group">
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
	<div class="col-xs-4 form-group">
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
	<div class="col-xs-1">
		<label id="refreshBtnLabel">&nbsp;</label>
		<button class="btn btn-default" id="refreshBtn" title="MO Bewerbungen aktualisieren"><i class="fa fa-download"></i></button>
	</div>
</div>
