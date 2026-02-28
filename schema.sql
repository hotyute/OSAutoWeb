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