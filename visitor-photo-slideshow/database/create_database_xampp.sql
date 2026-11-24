-- Run this in phpMyAdmin > SQL to create the application database.
-- This does not create a MySQL user; the supplied XAMPP .env uses root with
-- an empty password, which is the common default for a local XAMPP install.
CREATE DATABASE IF NOT EXISTS visitor_photo_slideshow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
