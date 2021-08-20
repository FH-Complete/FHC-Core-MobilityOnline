CREATE OR REPLACE FUNCTION extension_mobilityonline_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension.tbl_mo_bisioidzuordnung
  (
    bisio_id integer,
    mo_applicationid integer,
    insertamum timestamp default now(),
    updateamum timestamp,
    constraint pk_tbl_mo_bisioidzuordnung
    primary key (bisio_id, mo_applicationid)
  );

  COMMENT ON TABLE extension.tbl_mo_bisioidzuordnung IS 'MobilityOnline application id zu bisio_id Zuordnung';

  ALTER TABLE extension.tbl_mo_bisioidzuordnung ADD CONSTRAINT fk_mobisioidzuordnung_prestudent_id FOREIGN KEY (bisio_id) REFERENCES bis.tbl_bisio(bisio_id) ON UPDATE CASCADE ON DELETE RESTRICT;

  GRANT SELECT, INSERT, UPDATE, DELETE ON extension.tbl_mo_bisioidzuordnung TO vilesci;

  SELECT 'Table added'::text;
$$
LANGUAGE 'sql';

SELECT
  CASE
  WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'extension' AND tablename  = 'tbl_mo_bisioidzuordnung')
    THEN (SELECT 'success'::TEXT)
  ELSE (SELECT extension_mobilityonline_create_table())
END;

DROP FUNCTION extension_mobilityonline_create_table();