CREATE OR REPLACE FUNCTION extension_mobilityonline_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension.tbl_mo_appidzuordnung
  (
    prestudent_id integer,
    mo_applicationid integer,
    studiensemester_kurzbz varchar(16),
    insertamum timestamp default now(),
    updateamum timestamp,
    constraint pk_tbl_mo_appidzuordnung
    primary key (prestudent_id, mo_applicationid, studiensemester_kurzbz)
  );

  COMMENT ON TABLE extension.tbl_mo_appidzuordnung IS 'MobilityOnline application id zu prestudent_id Zuordnung';

  ALTER TABLE extension.tbl_mo_appidzuordnung ADD CONSTRAINT fk_moappidzuordnung_studiensemester FOREIGN KEY (studiensemester_kurzbz) REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
  ALTER TABLE extension.tbl_mo_appidzuordnung ADD CONSTRAINT fk_moappidzuordnung_prestudent_id FOREIGN KEY (prestudent_id) REFERENCES public.tbl_prestudent(prestudent_id) ON UPDATE CASCADE ON DELETE RESTRICT;

  GRANT SELECT, INSERT, UPDATE, DELETE ON extension.tbl_mo_appidzuordnung TO vilesci;

  SELECT 'Table added'::text;
$$
LANGUAGE 'sql';

SELECT
  CASE
  WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'extension' AND tablename  = 'tbl_mo_appidzuordnung')
    THEN (SELECT 'success'::TEXT)
  ELSE (SELECT extension_mobilityonline_create_table())
END;

  DROP FUNCTION extension_mobilityonline_create_table();