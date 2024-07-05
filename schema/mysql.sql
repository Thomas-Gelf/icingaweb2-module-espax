SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

CREATE TABLE espax_connection (
    node_uuid VARBINARY(16) NOT NULL,
    connection_name VARCHAR(128) NOT NULL,
    state ENUM ('connecting', 'login', 'ready', 'disabled') NOT NULL,
    session_id VARCHAR(32) NOT NULL,
    ts_last_refresh BIGINT UNSIGNED NOT NULL,
    last_error_message TEXT NULL DEFAULT NULL,
    PRIMARY KEY (node_uuid, connection_name),
    INDEX idx_name (connection_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE espax_notification (
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

ALTER TABLE espax_notification ADD COLUMN

CREATE TABLE espax_packet_trace (
    ts BIGINT UNSIGNED NOT NULL, -- milliseconds, last number contains node reference
    direction ENUM('inbound', 'outbound') NOT NULL,
    node_uuid VARBINARY(16) NOT NULL,
    session_id VARCHAR(32) NULL DEFAULT NULL,
    server_tan VARCHAR(20) NULL DEFAULT NULL,
    problem_reference VARCHAR(32) NULL DEFAULT NULL,
    root_element VARCHAR(64) NULL DEFAULT NULL, -- NULL only if unable to detect
    packet_trace MEDIUMTEXT DEFAULT NULL,
    PRIMARY KEY (ts),
    INDEX idx_reference(problem_reference, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE espax_schema_migration (
    schema_version SMALLINT UNSIGNED NOT NULL,
    migration_time DATETIME NOT NULL,
    PRIMARY KEY(schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT
    INTO espax_schema_migration (schema_version, migration_time)
    VALUES (2, NOW());
