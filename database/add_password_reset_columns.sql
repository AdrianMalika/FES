-- Add password reset token columns to users table (for secure operator activation)
-- Run once. Skip if you get "Duplicate column" errors (columns already exist).

ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD COLUMN password_reset_expires DATETIME DEFAULT NULL;
