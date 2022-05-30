CREATE OR REPLACE FUNCTION extension_mobilityonline_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension.tbl_mo_akteidzuordnung
  (
    akte_id integer,
    mo_file_id integer,
    insertamum timestamp default now(),
    constraint pk_tbl_mo_akteidzuordnung
    primary key (akte_id, mo_file_id)
  );

  COMMENT ON TABLE extension.tbl_mo_akteidzuordnung IS 'MobilityOnline file id zu akte_id Zuordnung';

  ALTER TABLE extension.tbl_mo_akteidzuordnung ADD CONSTRAINT fk_moakteidzuordnung_akte_id FOREIGN KEY (akte_id) REFERENCES public.tbl_akte(akte_id) ON UPDATE CASCADE ON DELETE RESTRICT;

  GRANT SELECT, INSERT, UPDATE, DELETE ON extension.tbl_mo_akteidzuordnung TO vilesci;

  SELECT 'Table added'::text;
$$
LANGUAGE 'sql';

SELECT
  CASE
  WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'extension' AND tablename  = 'tbl_mo_akteidzuordnung')
    THEN (SELECT 'success'::TEXT)
  ELSE (SELECT extension_mobilityonline_create_table())
END;

  DROP FUNCTION extension_mobilityonline_create_table();
