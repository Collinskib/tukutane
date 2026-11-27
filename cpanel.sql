-- Tukutane / Nipange — one-shot cPanel setup.
--
-- Run order:
--   1. cPanel > MySQL Databases: create database + user, add user to db
--      (CREATE DATABASE/USER can't run in phpMyAdmin on shared hosting)
--   2. phpMyAdmin > select the database > SQL tab > paste this file > Go

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

-- Sample gatherings from George's mockup.
-- event_at mirrors seed.php: days spread around the coming weekend,
-- Africa/Nairobi time. Safe to re-run — INSERT IGNORE skips on PK
-- conflict only, so delete sample rows first if you want fresh dates.
SET @untilSat = MOD(6 - (DAYOFWEEK(NOW()) - 1) + 7, 7);
SET @untilSat = IF(@untilSat = 0, 7, @untilSat);

INSERT INTO gatherings
    (title, category, area, venue, event_at, cost, audience, description, sample, going_base)
VALUES
    ('Matchday in Westlands', 'Football', 'Westlands', 'Sports café',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat - 1 DAY), INTERVAL 18 HOUR), 0, 'public',
     'Catch the match on a big screen with other football fans. Food and drinks are available for purchase.', 1, 12),
    ('Afrobeats dance social', 'Dance', 'Kilimani', 'Dance studio',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat DAY), INTERVAL 17 HOUR), 600, 'public',
     'Learn a few steps, then stay for a social dance session. Beginners welcome.', 1, 8),
    ('Coffee and business ideas', 'Business', 'Nairobi CBD', 'Coffee shop',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat DAY), INTERVAL 10 HOUR), 0, 'public',
     'Bring an idea or just come to listen and meet other aspiring founders.', 1, 6),
    ('Saturday Bible study', 'Faith', 'South B', 'Community hall',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat DAY), INTERVAL 9 HOUR), 0, 'public',
     'Read a short passage together and discuss it over tea.', 1, 5),
    ('Board game afternoon', 'Hangouts', 'Nairobi CBD', 'Common room',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat + 1 DAY), INTERVAL 14 HOUR), 0, 'public',
     'Meet new people over a few rounds of board games.', 1, 7),
    ('Cocktails and conversation', 'Nightlife', 'Kilimani', 'Rooftop lounge',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat + 1 DAY), INTERVAL 19 HOUR), 0, 'public',
     'An adults-only evening at a public lounge. Order and pay for your own drinks.', 1, 9),
    ('Morning walk and goal setting', 'Wellness', 'Karen', 'Garden entrance',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat + 2 DAY), INTERVAL 8 HOUR), 300, 'public',
     'Take a relaxed walk and share a personal goal for the week.', 1, 4),
    ('Board games with friends', 'Hangouts', 'Ngong Road', 'Game café',
     DATE_ADD(DATE_ADD(CURDATE(), INTERVAL @untilSat + 2 DAY), INTERVAL 15 HOUR), 500, 'public',
     'Bring a friend or join a table when you arrive.', 1, 6);
