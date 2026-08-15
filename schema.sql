-- 루키홈 DB 스키마
-- phpMyAdmin에서 이 파일을 통째로 가져오기(Import) 하면 테이블이 생성됩니다.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `members` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nickname` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `level` INT NOT NULL DEFAULT 2,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_nickname` (`nickname`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `boards` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(30) NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(200) NOT NULL DEFAULT '',
  `board_type` VARCHAR(20) NOT NULL DEFAULT 'list',
  `posts_per_page` INT NOT NULL DEFAULT 15,
  `use_password` TINYINT(1) NOT NULL DEFAULT 0,
  `board_password` VARCHAR(255) NULL,
  `use_categories` TINYINT(1) NOT NULL DEFAULT 0,
  `categories` VARCHAR(500) NOT NULL DEFAULT '',
  `list_level` INT NOT NULL DEFAULT 1,
  `read_level` INT NOT NULL DEFAULT 1,
  `write_level` INT NOT NULL DEFAULT 2,
  `comment_level` INT NOT NULL DEFAULT 2,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_boards_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `menus` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(30) NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `board_id` INT NOT NULL,
  `member_id` INT NULL,
  `guest_name` VARCHAR(20) NULL,
  `guest_password` VARCHAR(255) NULL,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `category` VARCHAR(20) NOT NULL DEFAULT '',
  `profile_image` VARCHAR(500) NULL,
  `name_en` VARCHAR(30) NULL,
  `name_ko` VARCHAR(30) NULL,
  `tags` VARCHAR(200) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posts_member` (`member_id`),
  KEY `idx_posts_board` (`board_id`),
  CONSTRAINT `fk_posts_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `post_id` INT NOT NULL,
  `member_id` INT NULL,
  `guest_name` VARCHAR(20) NULL,
  `guest_password` VARCHAR(255) NULL,
  `content` VARCHAR(1000) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comments_post` (`post_id`),
  KEY `idx_comments_member` (`member_id`),
  CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('site_title', '루키홈'),
  ('admin_email', 'admin@example.com'),
  ('hero_handle', 'WELCOME TO MY HOME'),
  ('hero_year', '2026'),
  ('site_public', '0'),
  ('registration_open', '0')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
