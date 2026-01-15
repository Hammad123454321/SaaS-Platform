-- Migration: Add password_change_required column to users table
-- Date: 2026-01-15

-- Add password_change_required column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'users' 
        AND column_name = 'password_change_required'
    ) THEN
        ALTER TABLE users 
        ADD COLUMN password_change_required BOOLEAN NOT NULL DEFAULT FALSE;
        
        RAISE NOTICE 'Column password_change_required added to users table';
    ELSE
        RAISE NOTICE 'Column password_change_required already exists in users table';
    END IF;
END $$;

