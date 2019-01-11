CREATE OR REPLACE FUNCTION extension_mobilityonline_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension.tbl_mo_lvidzuordnung
  (
    lehrveranstaltung_id integer,
    mo_lvid integer,
    studiensemester_kurzbz varchar(16),
    insertamum timestamp default now(),
    updateamum timestamp,
    constraint pk_tbl_mo_lvidzuordnung
    primary key (lehrveranstaltung_id, mo_lvid, studiensemester_kurzbz)
  );

  COMMENT ON TABLE extension.tbl_mo_lvidzuordnung IS 'MobilityOnline lv id Zuordnung';

  ALTER TABLE extension.tbl_mo_lvidzuordnung ADD CONSTRAINT fk_molvidzuordnung_studiensemester FOREIGN KEY (studiensemester_kurzbz) REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
  ALTER TABLE extension.tbl_mo_lvidzuordnung ADD CONSTRAINT fk_molvidzuordnung_lehrveranstaltung_id FOREIGN KEY (lehrveranstaltung_id) REFERENCES lehre.tbl_lehrveranstaltung(lehrveranstaltung_id) ON UPDATE CASCADE ON DELETE RESTRICT;

  GRANT SELECT, INSERT, UPDATE, DELETE ON extension.tbl_mo_lvidzuordnung TO vilesci;

  SELECT 'Table added'::text;
$$
LANGUAGE 'sql';

SELECT
  CASE
  WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'extension' AND tablename  = 'tbl_mo_lvidzuordnung')
    THEN (SELECT 'success'::TEXT)
  ELSE (SELECT extension_mobilityonline_create_table())
END;

  DROP FUNCTION extension_mobilityonline_create_table();