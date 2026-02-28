-- ============================================================
-- OSRS Client Portal — Database Schema
-- Run this file against your MySQL server to create the DB.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `osrs_portal`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `osrs_portal`;

-- -----------------------------------------------------------
-- USERS TABLE
-- Stores credentials, HWID binding, and role assignments.
-- -----------------------------------------------------------
CREATE TABLE `users` (
  `user_id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(32)     NOT NULL,
  `email`           VARCHAR(255)    NOT NULL,
  `password_hash`   VARCHAR(255)    NOT NULL,
  `hwid`            VARCHAR(128)    DEFAULT NULL,
  `hwid_updated_at` DATETIME        DEFAULT NULL,
  `role`            ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- SUBSCRIPTIONS TABLE
-- One active row per user; checked by the desktop client API.
-- -----------------------------------------------------------
CREATE TABLE `subscriptions` (
  `sub_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `status`     ENUM('active','expired','banned') NOT NULL DEFAULT 'active',
  `expires_at` DATETIME     NOT NULL,
  PRIMARY KEY (`sub_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_sub_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- SCRIPTS TABLE
-- Catalogue of OSRS automation scripts available to users.
-- -----------------------------------------------------------
CREATE TABLE `scripts` (
  `script_id`   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(120)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `version`     VARCHAR(20)   NOT NULL DEFAULT '1.0.0',
  `category`    VARCHAR(60)   NOT NULL DEFAULT 'Skilling',
  `is_premium`  TINYINT(1)    NOT NULL DEFAULT 0,
  `author_id`   INT UNSIGNED  NOT NULL,
  PRIMARY KEY (`script_id`),
  KEY `idx_author` (`author_id`),
  CONSTRAINT `fk_script_author`
    FOREIGN KEY (`author_id`) REFERENCES `users`(`user_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- LOGS TABLE
-- Immutable audit trail: logins, HWID resets, bans, API calls.
-- -----------------------------------------------------------
CREATE TABLE `logs` (
  `log_id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  DEFAULT NULL,
  `action`     VARCHAR(60)   NOT NULL,
  `ip_address` VARCHAR(45)   NOT NULL,
  `timestamp`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_log_user` (`user_id`),
  CONSTRAINT `fk_log_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- Seed: default admin account  (password: admin123)
-- Change this immediately after first login.
-- -----------------------------------------------------------
INSERT INTO `users` (`username`,`email`,`password_hash`,`role`)
VALUES (
  'admin',
  'admin@osrs-portal.local',
  '$2y$10$eImiTXuWVxfM37uY4JANjQ1HzP0eMBN9d7BbYzwTiGMWIC7eUkK2i',
  'admin'
);

-- -----------------------------------------------------------
-- ALTER: Add forum-related columns to existing users table
-- -----------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN `signature`  TEXT          DEFAULT NULL AFTER `role`,
  ADD COLUMN `post_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `signature`;

-- -----------------------------------------------------------
-- FORUM CATEGORIES
-- Top-level groupings displayed on the forum index.
-- -----------------------------------------------------------
CREATE TABLE `forum_categories` (
  `category_id` INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `sort_order`  INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- FORUM BOARDS
-- Each board belongs to a category.
-- Cached counters avoid expensive aggregation on index load.
-- -----------------------------------------------------------
CREATE TABLE `forum_boards` (
  `board_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`  INT UNSIGNED NOT NULL,
  `name`         VARCHAR(120) NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `sort_order`   INT          NOT NULL DEFAULT 0,
  `thread_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `post_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `last_post_id` INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`board_id`),
  CONSTRAINT `fk_board_category`
    FOREIGN KEY (`category_id`) REFERENCES `forum_categories`(`category_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- FORUM THREADS
-- Cached reply_count, views, last_post_at enable fast sorting
-- without JOINing or sub-querying the posts table.
-- -----------------------------------------------------------
CREATE TABLE `forum_threads` (
  `thread_id`    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `board_id`     INT UNSIGNED  NOT NULL,
  `user_id`      INT UNSIGNED  NOT NULL,
  `title`        VARCHAR(200)  NOT NULL,
  `is_sticky`    TINYINT(1)    NOT NULL DEFAULT 0,
  `is_locked`    TINYINT(1)    NOT NULL DEFAULT 0,
  `is_deleted`   TINYINT(1)    NOT NULL DEFAULT 0,
  `reply_count`  INT UNSIGNED  NOT NULL DEFAULT 0,
  `views`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `last_post_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`thread_id`),
  CONSTRAINT `fk_thread_board`
    FOREIGN KEY (`board_id`) REFERENCES `forum_boards`(`board_id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_thread_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- FORUM POSTS
-- is_deleted enables soft-deletion without breaking FKs.
-- -----------------------------------------------------------
CREATE TABLE `forum_posts` (
  `post_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `body`       TEXT         NOT NULL,
  `is_deleted` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`),
  CONSTRAINT `fk_post_thread`
    FOREIGN KEY (`thread_id`) REFERENCES `forum_threads`(`thread_id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_post_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------
-- EXPLICIT INDEXES for heavily-queried columns
-- -----------------------------------------------------------

/* Board listing sorted within a category */
CREATE INDEX idx_board_cat_sort
  ON `forum_boards` (`category_id`, `sort_order`);

/* Thread listing: board-scoped, non-deleted, stickies first,
   then ordered by most-recent activity.
   MySQL 8.0+ supports DESC in index definitions. */
CREATE INDEX idx_thread_listing
  ON `forum_threads` (`board_id`, `is_deleted`, `is_sticky` DESC, `last_post_at` DESC);

/* Fast lookup of threads by author */
CREATE INDEX idx_thread_user
  ON `forum_threads` (`user_id`);

/* Post listing: thread-scoped, non-deleted, chronological */
CREATE INDEX idx_post_thread_chrono
  ON `forum_posts` (`thread_id`, `is_deleted`, `created_at` ASC);

/* Fast lookup of posts by author (profile pages, etc.) */
CREATE INDEX idx_post_user
  ON `forum_posts` (`user_id`);

-- -----------------------------------------------------------
-- SEED DATA: Default categories and boards
-- -----------------------------------------------------------
INSERT INTO `forum_categories` (`name`, `description`, `sort_order`) VALUES
  ('General',            'News, announcements, and community chat',       1),
  ('Scripts & Botting',  'Script discussion, requests, and development',  2),
  ('Support',            'Get help with the client and your account',     3);

INSERT INTO `forum_boards` (`category_id`, `name`, `description`, `sort_order`) VALUES
  (1, 'Announcements',       'Official updates and patch notes',                1),
  (1, 'General Discussion',  'Talk about anything OSRS related',               2),
  (1, 'Introductions',       'Say hello to the community',                     3),
  (2, 'Script Releases',     'New and updated script releases',                1),
  (2, 'Script Requests',     'Request a script to be developed',               2),
  (2, 'Scripting Help',      'Get help writing your own scripts',              3),
  (3, 'Client Support',      'Troubleshoot client issues',                     1),
  (3, 'Account & Billing',   'Subscription and account questions',             2);