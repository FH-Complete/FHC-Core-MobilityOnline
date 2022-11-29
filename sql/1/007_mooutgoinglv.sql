CREATE OR REPLACE FUNCTION extension_mobilityonline_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension.tbl_mo_outgoing_lv
	(
		outgoing_lehrveranstaltung_id integer,
		mo_lvid integer NOT NULL,
		lv_nr_gast varchar(64) NOT NULL,
		lv_bez_gast varchar(128),
		lv_semesterstunden_gast numeric(5,2),
		ects_punkte_gast numeric(5,2),
		note_local_gast varchar(32) NOT NULL,
		angerechnet boolean default NULL,
		bisio_id integer NOT NULL,
		insertamum timestamp without time zone default now(),
		insertvon varchar(32),
		updateamum timestamp without time zone,
		updatevon varchar(32)
	);

	ALTER TABLE extension.tbl_mo_outgoing_lv ADD CONSTRAINT pk_mo_outgoing_lv PRIMARY KEY (outgoing_lehrveranstaltung_id);
	ALTER TABLE extension.tbl_mo_outgoing_lv ADD CONSTRAINT uk_mo_outgoing_lv_mo_lvid UNIQUE (mo_lvid);
	ALTER TABLE extension.tbl_mo_outgoing_lv ADD CONSTRAINT fk_mo_outgoing_lv_bisio_id FOREIGN KEY (bisio_id) REFERENCES bis.tbl_bisio (bisio_id) ON DELETE RESTRICT ON UPDATE CASCADE;
	-- ALTER TABLE extension.tbl_mo_outgoing_lv ADD CONSTRAINT uk_mo_outgoing_lv_mo_lvid_bisio_id UNIQUE (mo_lvid, bisio_id);

	CREATE SEQUENCE extension.seq_mo_outgoing_lv_outgoing_lehrveranstaltung_id
		START WITH 1
		INCREMENT BY 1
		NO MAXVALUE
		NO MINVALUE
		CACHE 1;

	ALTER TABLE extension.tbl_mo_outgoing_lv ALTER COLUMN outgoing_lehrveranstaltung_id SET DEFAULT nextval('extension.seq_mo_outgoing_lv_outgoing_lehrveranstaltung_id');

	GRANT SELECT, UPDATE, INSERT, DELETE ON extension.tbl_mo_outgoing_lv TO vilesci;
	GRANT SELECT, UPDATE, INSERT ON extension.tbl_mo_outgoing_lv TO web;
	GRANT SELECT, UPDATE ON extension.seq_mo_outgoing_lv_outgoing_lehrveranstaltung_id TO vilesci;
	GRANT SELECT, UPDATE ON extension.seq_mo_outgoing_lv_outgoing_lehrveranstaltung_id TO web;

	COMMENT ON TABLE extension.tbl_mo_outgoing_lv IS 'MobilityOnline Outgoing Lehrveranstaltungen';

	SELECT 'Table added'::text;
$$
LANGUAGE 'sql';

SELECT
	CASE
		WHEN (SELECT true::BOOLEAN FROM pg_catalog.pg_tables WHERE schemaname = 'extension' AND tablename  = 'tbl_mo_outgoing_lv')
		THEN (SELECT 'success'::TEXT)
		ELSE (SELECT extension_mobilityonline_create_table())
	END;

DROP FUNCTION extension_mobilityonline_create_table();
