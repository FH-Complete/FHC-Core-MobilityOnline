CREATE OR REPLACE FUNCTION extension_mobilityonline_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension.tbl_mo_zahlungidzuordnung
  (
    buchungsnr integer,
    mo_referenz_nr varchar(64),
    insertamum timestamp default now(),
    constraint pk_tbl_mo_zahlungidzuordnung
    primary key (buchungsnr, mo_referenz_nr)
  );

  COMMENT ON TABLE extension.tbl_mo_zahlungidzuordnung IS 'MobilityOnline Förderungsauszahlungen Zuordnung';

  ALTER TABLE extension.tbl_mo_zahlungidzuordnung ADD CONSTRAINT fk_mozahlungidzuordnung_konto FOREIGN KEY (buchungsnr) REFERENCES public.tbl_konto(buchungsnr) ON UPDATE CASCADE ON DELETE RESTRICT;

  GRANT SELECT, INSERT, UPDATE, DELETE ON extension.tbl_mo_zahlungidzuordnung TO vilesci;

  SELECT 'Table added'::text;
$$
LANGUAGE 'sql';

SELECT
  CASE
  WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'extension' AND tablename  = 'tbl_mo_zahlungidzuordnung')
    THEN (SELECT 'success'::TEXT)
  ELSE (SELECT extension_mobilityonline_create_table())
END;

  DROP FUNCTION extension_mobilityonline_create_table();