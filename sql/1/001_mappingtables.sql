CREATE OR REPLACE FUNCTION extension_budget_create_table () RETURNS TEXT AS $$
	CREATE TABLE extension
  (
    lvid integer,
    mo_lvid integer unique not null,
    studiensemester_kurzbz varchar(16),
    insertamum timestamp default now(),
    updateamum timestamp default now(),
    constraint pk_tbl_mo_idzuordnung
    primary key (lvid, studiensemester_kurzbz)
  );

  ALTER TABLE extension.tbl_mo_idzuordnung ADD CONSTRAINT fk_moidzuordnung_studiensemester FOREIGN KEY (studiensemester_kurzbz) REFERENCES public.tbl_studiensemester(studiensemester_kurzbz) ON UPDATE CASCADE ON DELETE RESTRICT;
  SELECT 'Table added'::text;
$$
LANGUAGE 'sql';
  DROP FUNCTION extension_budget_create_table();