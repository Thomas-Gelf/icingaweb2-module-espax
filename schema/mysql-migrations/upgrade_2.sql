ALTER TABLE espax_packet_trace
  CHANGE COLUMN assigned_reference problem_reference VARCHAR(32) NULL DEFAULT NULL;
ALTER TABLE espax_packet_trace
  CHANGE COLUMN assigned_tan server_tan VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE espax_packet_trace DROP INDEX IF EXISTS idx_tan;

INSERT
  INTO espax_schema_migration (schema_version, migration_time)
  VALUES (2, NOW());
