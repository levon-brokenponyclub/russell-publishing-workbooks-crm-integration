#!/bin/bash
# JSON to SQL converter script
# Usage: ./convert-json-to-sql.sh /path/to/orgs.json

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 /path/to/orgs.json"
    exit 1
fi

JSON_FILE="$1"

if [ ! -f "$JSON_FILE" ]; then
    echo "Error: File $JSON_FILE not found"
    exit 1
fi

echo "-- Generated SQL from $JSON_FILE"
echo "-- Generated on $(date)"
echo ""
echo "INSERT INTO wp_workbooks_employers (workbooks_id, name, created_at, updated_at) VALUES"

# Use python to parse JSON and generate SQL
python3 << EOF
import json
import sys

try:
    with open('$JSON_FILE', 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    if not isinstance(data, list):
        print("Error: JSON must be an array", file=sys.stderr)
        sys.exit(1)
    
    sql_values = []
    for i, org in enumerate(data):
        if 'id' in org and 'name' in org:
            org_id = org['id']
            org_name = org['name'].replace("'", "''")  # Escape single quotes
            
            # Only add if ID is numeric and positive
            if str(org_id).isdigit() and int(org_id) > 0:
                sql_values.append(f"({org_id}, '{org_name}', NOW(), NOW())")
    
    # Print all values
    for i, value in enumerate(sql_values):
        if i == len(sql_values) - 1:
            print(value)  # Last one without comma
        else:
            print(value + ",")
    
    print("ON DUPLICATE KEY UPDATE")
    print("    name = VALUES(name),")
    print("    updated_at = NOW();")
    print("")
    print(f"-- Total records: {len(sql_values)}")
    
except Exception as e:
    print(f"Error: {e}", file=sys.stderr)
    sys.exit(1)
EOF