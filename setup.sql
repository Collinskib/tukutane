-- Tukutane one-shot setup.
-- Run:  sudo mysql < setup.sql
-- Creates the database, the app user, and all tables.

CREATE DATABASE IF NOT EXISTS tukutane
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'tukutane'@'localhost'
    IDENTIFIED BY 'tukutane_pass';

GRANT ALL PRIVILEGES ON tukutane.* TO 'tukutane'@'localhost';
FLUSH PRIVILEGES;

USE tukutane;

CREATE TABLE IF NOT EXISTS gatherings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(80)  NOT NULL,
    category    VARCHAR(40)  NOT NULL,
    area        VARCHAR(60)  NOT NULL,
    venue       VARCHAR(80)  NOT NULL,
    event_at    DATETIME     NOT NULL COMMENT 'Africa/Nairobi local time',
    cost        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'KSh, 0 = free',
    audience    ENUM('public','friends') NOT NULL DEFAULT 'public',
    description VARCHAR(300) NOT NULL,
    sample      TINYINT(1)   NOT NULL DEFAULT 0,
    creator     CHAR(32)     NULL COMMENT 'session key of the host',
    going_base  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'seeded attendee count',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_feed (event_at, audience),
    INDEX idx_area (area),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rsvps (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gathering_id INT UNSIGNED NOT NULL,
    session_key  CHAR(32)     NOT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rsvp (gathering_id, session_key),
    CONSTRAINT fk_rsvp_gathering FOREIGN KEY (gathering_id)
        REFERENCES gatherings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
