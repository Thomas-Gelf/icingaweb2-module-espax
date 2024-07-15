ALTER TABLE espax_notification_history
  DROP INDEX idx_ref,
  DROP INDEX idx_tan;

INSERT
  INTO espax_schema_migration (schema_version, migration_time)
  VALUES (4, NOW());
