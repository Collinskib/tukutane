-- Nipange Release 1 — additive migration.
-- Safe to run on the existing database: only adds tables/columns.

ALTER TABLE gatherings
    ADD COLUMN creator_id  INT UNSIGNED NULL AFTER creator,
    ADD COLUMN capacity    INT UNSIGNED NULL COMMENT 'NULL = unlimited',
    ADD COLUMN cover       VARCHAR(255) NULL COMMENT 'uploads/ filename',
    ADD COLUMN invite_only TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN featured    TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN status      ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    ADD INDEX idx_creator (creator_id),
    ADD INDEX idx_status (status, event_at);

ALTER TABLE rsvps
    ADD COLUMN user_id  INT UNSIGNED NULL AFTER session_key,
    ADD COLUMN reminded TINYINT(1) NOT NULL DEFAULT 0,
    ADD INDEX idx_user (user_id);

CREATE TABLE IF NOT EXISTS users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(190) NOT NULL UNIQUE,
    pass_hash    VARCHAR(255) NOT NULL,
    name         VARCHAR(60)  NOT NULL,
    photo        VARCHAR(255) NULL COMMENT 'uploads/ avatar filename',
    bio          VARCHAR(300) NOT NULL DEFAULT '',
    area         VARCHAR(60)  NOT NULL DEFAULT '',
    interests    VARCHAR(300) NOT NULL DEFAULT '' COMMENT 'comma-separated category names',
    role         ENUM('member','admin') NOT NULL DEFAULT 'member',
    status       ENUM('active','suspended') NOT NULL DEFAULT 'active',
    discoverable TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'show in People nearby',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_discover (discoverable, status),
    INDEX idx_area (area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS friendships (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    addressee_id INT UNSIGNED NOT NULL,
    status       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pair (requester_id, addressee_id),
    INDEX idx_addressee (addressee_id, status),
    INDEX idx_requester (requester_id, status),
    CONSTRAINT fk_fr_req FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_fr_add FOREIGN KEY (addressee_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocks (
    blocker_id INT UNSIGNED NOT NULL,
    blocked_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id),
    CONSTRAINT fk_bl_blocker FOREIGN KEY (blocker_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_bl_blocked FOREIGN KEY (blocked_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS conversations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_one        INT UNSIGNED NOT NULL COMMENT 'lower user id',
    user_two        INT UNSIGNED NOT NULL COMMENT 'higher user id',
    last_message_at DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pair (user_one, user_two),
    CONSTRAINT fk_cv_one FOREIGN KEY (user_one) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_cv_two FOREIGN KEY (user_two) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS conversation_state (
    conversation_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    muted           TINYINT(1) NOT NULL DEFAULT 0,
    typing_at       DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (conversation_id, user_id),
    CONSTRAINT fk_cs_conv FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id       INT UNSIGNED NOT NULL,
    body            VARCHAR(1000) NOT NULL,
    read_at         DATETIME NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conv (conversation_id, id),
    INDEX idx_unread (conversation_id, read_at),
    CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(30) NOT NULL COMMENT 'friend_request, friend_accepted, invite, rsvp, reminder, event_cancelled',
    body       VARCHAR(300) NOT NULL,
    link       VARCHAR(200) NOT NULL DEFAULT '',
    read_at    DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, read_at, id),
    CONSTRAINT fk_nt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS saves (
    user_id      INT UNSIGNED NOT NULL,
    gathering_id INT UNSIGNED NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, gathering_id),
    CONSTRAINT fk_sv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_sv_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invites (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gathering_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    inviter_id   INT UNSIGNED NOT NULL,
    status       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invite (gathering_id, user_id),
    CONSTRAINT fk_iv_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE,
    CONSTRAINT fk_iv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_iv_inviter FOREIGN KEY (inviter_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT UNSIGNED NOT NULL,
    target_type ENUM('user','gathering','message') NOT NULL,
    target_id   INT UNSIGNED NOT NULL,
    reason      VARCHAR(300) NOT NULL,
    status      ENUM('open','resolved') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    CONSTRAINT fk_rp_user FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token_hash),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin-managed vocabulary (replaces the PHP constants over time).
CREATE TABLE IF NOT EXISTS categories (
    name   VARCHAR(40) PRIMARY KEY,
    emoji  VARCHAR(8)  NOT NULL,
    color  CHAR(7)     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS areas (
    name VARCHAR(60) PRIMARY KEY,
    sort INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, emoji, color) VALUES
    ('Football','⚽','#dce9d3'), ('Nightlife','🍹','#f4d8d0'), ('Business','☕','#f0e0bb'),
    ('Faith','📖','#d9e8f0'), ('Wellness','🌿','#dce9d3'),
    ('Dance','🎵','#f5d9cb'), ('Hangouts','🎲','#f1e4ca')
ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), color = VALUES(color);

INSERT INTO areas (name, sort) VALUES
    ('Westlands',1), ('Kilimani',2), ('South B',3), ('Nairobi CBD',4), ('Ngong Road',5), ('Karen',6)
ON DUPLICATE KEY UPDATE sort = VALUES(sort);

-- Backfill: attribute existing session-created gatherings' RSVPs only when
-- a user later claims the session key (done in code on login, not here).

CREATE TABLE IF NOT EXISTS waitlists (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gathering_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    status       ENUM('waiting','offered','joined','expired','declined') NOT NULL DEFAULT 'waiting',
    offered_at   TIMESTAMP NULL DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wl (gathering_id, user_id),
    INDEX idx_wl (gathering_id, status, created_at),
    CONSTRAINT fk_wl_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE,
    CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============ Release 2: local interest groups ============

CREATE TABLE IF NOT EXISTS grps (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL,
    description VARCHAR(600) NOT NULL DEFAULT '',
    category    VARCHAR(40) NOT NULL,
    area        VARCHAR(60) NOT NULL,
    creator_id  INT UNSIGNED NOT NULL,
    join_policy ENUM('open','approval') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_grp_area (area),
    INDEX idx_grp_cat (category),
    CONSTRAINT fk_grp_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS grp_members (
    group_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    role       ENUM('admin','member') NOT NULL DEFAULT 'member',
    status     ENUM('active','pending','declined') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    INDEX idx_gm_user (user_id, status),
    CONSTRAINT fk_gm_grp FOREIGN KEY (group_id) REFERENCES grps (id) ON DELETE CASCADE,
    CONSTRAINT fk_gm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============ Release 2: event group chat ============

CREATE TABLE IF NOT EXISTS event_messages (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gathering_id INT UNSIGNED NOT NULL,
    sender_id    INT UNSIGNED NOT NULL,
    body         VARCHAR(1000) NOT NULL,
    pinned       TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_em (gathering_id, id),
    CONSTRAINT fk_em_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE,
    CONSTRAINT fk_em_user FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS event_chat_state (
    gathering_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    muted        TINYINT(1) NOT NULL DEFAULT 0,
    last_read_id INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (gathering_id, user_id),
    CONSTRAINT fk_ecs_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE,
    CONSTRAINT fk_ecs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- event chat messages are reportable too
ALTER TABLE reports MODIFY target_type ENUM('user','gathering','message','event_message') NOT NULL;

-- ============ Release 2: recurring gatherings ============

CREATE TABLE IF NOT EXISTS gathering_series (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    freq       ENUM('weekly','monthly') NOT NULL,
    creator_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gs_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gatherings
    ADD COLUMN series_id INT UNSIGNED NULL,
    ADD KEY idx_series (series_id),
    ADD CONSTRAINT fk_g_series FOREIGN KEY (series_id) REFERENCES gathering_series (id) ON DELETE SET NULL;

-- ============ Release 3: organizer follows + venue profiles ============

CREATE TABLE IF NOT EXISTS follows (
    follower_id INT UNSIGNED NOT NULL,
    followed_id INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    INDEX idx_fol_target (followed_id),
    CONSTRAINT fk_fol_a FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_fol_b FOREIGN KEY (followed_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS venues (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL,
    area        VARCHAR(60) NOT NULL,
    description VARCHAR(600) NOT NULL DEFAULT '',
    photo       VARCHAR(255) NULL,
    creator_id  INT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_v_area (area),
    INDEX idx_v_name (name),
    CONSTRAINT fk_v_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gatherings
    ADD COLUMN venue_id INT UNSIGNED NULL,
    ADD KEY idx_venue (venue_id),
    ADD CONSTRAINT fk_g_venue FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL;

-- chat attachments
ALTER TABLE messages
    ADD COLUMN attachment      VARCHAR(255) NULL,
    ADD COLUMN attachment_name VARCHAR(200) NULL,
    ADD COLUMN attachment_kind VARCHAR(10)  NULL;

-- "keep me logged in" cookies (includes/auth.php remember_*)
CREATE TABLE IF NOT EXISTS remember_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    selector   CHAR(16) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used  DATETIME NULL,
    expires_at DATETIME NOT NULL,
    INDEX (user_id),
    INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
