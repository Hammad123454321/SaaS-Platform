-- Migration script for subscriptions table
-- Run this directly in your PostgreSQL database if needed

-- Add missing columns to subscriptions table
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR;
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS amount INTEGER DEFAULT 0;
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS currency VARCHAR DEFAULT 'usd';
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS interval VARCHAR DEFAULT 'month';
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS current_period_start TIMESTAMP;
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS current_period_end TIMESTAMP;
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS modules JSONB DEFAULT '{}';

-- Verify columns were added
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'subscriptions'
ORDER BY ordinal_position;





