-- Manual JSON Import Script for wp_workbooks_employers
-- Replace 'your_database_name' with your actual database name

USE your_database_name;

-- First, ensure the table has the correct structure
CREATE TABLE IF NOT EXISTS wp_workbooks_employers (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    workbooks_id bigint(20) unsigned DEFAULT NULL,
    name varchar(255) NOT NULL DEFAULT '',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY workbooks_id (workbooks_id),
    KEY name (name)
);

-- Add missing columns if they don't exist (run these one by one)
-- ALTER TABLE wp_workbooks_employers ADD COLUMN workbooks_id bigint(20) unsigned DEFAULT NULL AFTER id;
-- ALTER TABLE wp_workbooks_employers ADD UNIQUE KEY workbooks_id (workbooks_id);
-- ALTER TABLE wp_workbooks_employers ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;
-- ALTER TABLE wp_workbooks_employers ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Clear existing data (OPTIONAL - remove this if you want to keep existing data)
-- TRUNCATE TABLE wp_workbooks_employers;

-- OPTION 1: Load from JSON file directly (if MySQL supports JSON_TABLE - MySQL 8.0+)
-- You'll need to upload your orgs.json file to MySQL server directory first
/*
INSERT INTO wp_workbooks_employers (workbooks_id, name, created_at, updated_at)
SELECT 
    CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.id')) AS UNSIGNED) as workbooks_id,
    JSON_UNQUOTE(JSON_EXTRACT(data, '$.name')) as name,
    NOW() as created_at,
    NOW() as updated_at
FROM (
    SELECT JSON_EXTRACT(json_data, CONCAT('$[', idx, ']')) as data
    FROM (
        SELECT @row_number := @row_number + 1 as idx, 
               LOAD_FILE('/path/to/your/orgs.json') as json_data
        FROM (SELECT @row_number := -1) r
        CROSS JOIN information_schema.tables 
        LIMIT 10000  -- Adjust based on your data size
    ) numbered
    WHERE JSON_EXTRACT(json_data, CONCAT('$[', idx, ']')) IS NOT NULL
) extracted
WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.id')) IS NOT NULL 
  AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.name')) IS NOT NULL
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    updated_at = NOW();
*/

-- OPTION 2: Manual INSERT statements (more compatible)
-- You can generate these from your JSON file using a simple script

-- Sample INSERT statements (replace with your actual data):
INSERT INTO wp_workbooks_employers (workbooks_id, name, created_at, updated_at) VALUES
(4253895, 'AE Solutions (Pty) Ltd', NOW(), NOW()),
(4104933, 'Sample Company', NOW(), NOW()),
(3312234, 'Academic of Pharmacy and Food Analysis of Putra Indonesia Malang', NOW(), NOW()),
(3533725, 'Academic staff', NOW(), NOW()),
(283807, 'Academy of Applied Pharmaceutical Sciences Inc (AAPS)', NOW(), NOW())
-- Add more records here...
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    updated_at = NOW();

-- OPTION 3: Create a temporary staging table and bulk import
CREATE TEMPORARY TABLE temp_orgs (
    id_text VARCHAR(255),
    name_text TEXT
);

-- Load your data into temp table first (you can use LOAD DATA INFILE or INSERT statements)
-- Then clean and insert:
/*
INSERT INTO wp_workbooks_employers (workbooks_id, name, created_at, updated_at)
SELECT 
    CAST(id_text AS UNSIGNED) as workbooks_id,
    TRIM(name_text) as name,
    NOW() as created_at,
    NOW() as updated_at
FROM temp_orgs 
WHERE id_text REGEXP '^[0-9]+$'  -- Only numeric IDs
  AND TRIM(name_text) != ''      -- Non-empty names
  AND CAST(id_text AS UNSIGNED) > 0  -- Valid positive numbers
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    updated_at = NOW();
*/

-- Check your results
SELECT COUNT(*) as total_records FROM wp_workbooks_employers;
SELECT * FROM wp_workbooks_employers ORDER BY workbooks_id LIMIT 10;