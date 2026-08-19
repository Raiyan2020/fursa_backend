-- Run in phpMyAdmin BEFORE importing fursa_prod_mysql.sql
-- Fixes MySQL case-insensitive duplicate issues on email/username

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `users`;

ALTER TABLE `users`
  MODIFY `username` VARCHAR(255)
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_bin
  NULL;

ALTER TABLE `users`
  MODIFY `email` VARCHAR(255)
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_bin
  NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;
