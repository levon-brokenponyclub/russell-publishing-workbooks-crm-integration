<?php
/**
 * Debug script to check JSON data structure
 */

// Read the orgs.json file to analyze the data
$json_file = '/Users/levongravett/Downloads/orgs.json';

if (file_exists($json_file)) {
    $json_content = file_get_contents($json_file);
    $organizations = json_decode($json_content, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($organizations)) {
        echo "Total organizations: " . count($organizations) . "\n";
        echo "\nFirst 10 records:\n";
        
        for ($i = 0; $i < min(10, count($organizations)); $i++) {
            $org = $organizations[$i];
            echo "Index $i:\n";
            echo "  Raw ID: " . var_export($org['id'], true) . " (type: " . gettype($org['id']) . ")\n";
            echo "  intval(ID): " . intval($org['id']) . "\n";
            echo "  is_numeric(ID): " . (is_numeric($org['id']) ? 'true' : 'false') . "\n";
            echo "  Name: " . $org['name'] . "\n";
            echo "  ---\n";
        }
        
        // Check for problematic IDs
        echo "\nChecking for problematic IDs (first 100 records):\n";
        $problematic = 0;
        for ($i = 0; $i < min(100, count($organizations)); $i++) {
            $org = $organizations[$i];
            $id_converted = is_numeric($org['id']) ? intval($org['id']) : 0;
            if ($id_converted <= 0) {
                echo "Problematic ID at index $i: " . var_export($org['id'], true) . "\n";
                $problematic++;
            }
        }
        echo "Found $problematic problematic IDs in first 100 records\n";
        
    } else {
        echo "JSON decode error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "JSON file not found at: $json_file\n";
}