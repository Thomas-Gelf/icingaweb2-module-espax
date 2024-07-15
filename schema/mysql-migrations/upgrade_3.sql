CREATE TABLE espax_notification_history (
    ts BIGINT UNSIGNED NOT NULL, -- receive time in milliseconds, last number contains node reference
    ts_sent BIGINT UNSIGNED NULL DEFAULT NULL, -- we shipped out packet
    ts_confirmed BIGINT UNSIGNED NULL DEFAULT NULL, -- ESPA-X-Server confirmed receipt
    ts_accepted BIGINT UNSIGNED NULL DEFAULT NULL, -- by final destination
    ts_failed BIGINT UNSIGNED NULL DEFAULT NULL,
    node_uuid VARBINARY(16) NOT NULL,
    destination VARCHAR(128) NOT NULL, -- strClipName is 24 characters
    message VARCHAR(160) NOT NULL,     -- strDisplayText is 160 characters
    problem_tan VARCHAR(20) NULL DEFAULT NULL,
    problem_reference VARCHAR(32) NOT NULL,
    problem_reference_implementation VARCHAR(128) NOT NULL, -- fully qualified class name
    accepted_by VARCHAR(128) DEFAULT NULL, -- SS-NETW-NAME (strClipName) is 24 characters
    problem_reference_details TEXT NOT NULL, -- json-encoded key/value object
    -- state... ??
    error_message TEXT NULL DEFAULT NULL,
    PRIMARY KEY (ts),
    UNIQUE KEY idx_ref (node_uuid, problem_reference),
    UNIQUE KEY idx_tan (problem_tan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT
  INTO espax_schema_migration (schema_version, migration_time)
  VALUES (3, NOW());
