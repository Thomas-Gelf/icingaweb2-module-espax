ALTER TABLE espax_notification
  DROP INDEX idx_ref,
  DROP INDEX idx_tan;

ALTER TABLE espax_notification
  ADD INDEX idx_reference(problem_reference, ts);

ALTER TABLE espax_notification_history
  ADD INDEX idx_reference(problem_reference, ts);

INSERT
  INTO espax_schema_migration (schema_version, migration_time)
  VALUES (5, NOW());
