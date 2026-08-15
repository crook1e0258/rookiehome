-- 갤러리 게시판 이미지를 링크(URL) 형식으로 저장할 수 있도록 컬럼 길이를 늘리는 마이그레이션
-- phpMyAdmin에서 이 파일을 한 번만 가져오기(Import) 하세요.

SET NAMES utf8mb4;

ALTER TABLE `posts` MODIFY COLUMN `profile_image` VARCHAR(500) NULL;
