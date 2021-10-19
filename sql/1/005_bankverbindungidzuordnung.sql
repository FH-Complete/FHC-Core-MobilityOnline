CREATE OR REPLACE FUNCTION extension_mobilityonline_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension.tbl_mo_bankverbindungidzuordnung
  (
    bankverbindung_id integer,
    mo_person_id integer,
    insertamum timestamp default now(),
    updateamum timestamp,
    constraint pk_tbl_mo_bankverbindungidzuordnung
    primary key (bankverbindung_id, mo_person_id)
  );

  COMMENT ON TABLE extension.tbl_mo_bankverbindungidzuordnung IS 'MobilityOnline application id zu bankverbindung_id Zuordnung';

  ALTER TABLE extension.tbl_mo_bankverbindungidzuordnung ADD CONSTRAINT fk_mobankverbindungidzuordnung_bankverbindung_id FOREIGN KEY (bankverbindung_id) REFERENCES public.tbl_bankverbindung(bankverbindung_id) ON UPDATE CASCADE ON DELETE RESTRICT;

  GRANT SELECT, INSERT, UPDATE, DELETE ON extension.tbl_mo_bankverbindungidzuordnung TO vilesci;

  SELECT 'Table added'::text;
$$
LANGUAGE 'sql';

SELECT
  CASE
  WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'extension' AND tablename  = 'tbl_mo_bankverbindungidzuordnung')
    THEN (SELECT 'success'::TEXT)
  ELSE (SELECT extension_mobilityonline_create_table())
END;

  DROP FUNCTION extension_mobilityonline_create_table();