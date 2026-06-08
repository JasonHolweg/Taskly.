-- Taskly — Migration: privater Kalender-Abo-Token pro User (iCal-Feed)
SET NAMES utf8mb4;
ALTER TABLE users ADD COLUMN cal_token CHAR(32) UNIQUE AFTER friend_code;
