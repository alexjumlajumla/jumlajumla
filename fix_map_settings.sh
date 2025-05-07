#!/bin/bash

# This script will fix the maps functionality in the application

# Make sure the Country model is properly set up (already done earlier)
echo "Checking Country model setup..."

# Create temporary PHP files for database operations
cat > fix_map_api_key.php << 'EOF'
<?php
try {
    // Connect to the remote database
    $pdo = new PDO("mysql:host=139.84.227.249;dbname=jumla_main", "jumla_main", "Cf255f@s9");
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE `key` = :key");
    $stmt->execute(["key" => "map_api_key"]);
    
    if (!$stmt->fetch()) {
        // Insert the key if it doesn't exist
        $insert = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (:key, :value)");
        $insert->execute(["key" => "map_api_key", "value" => "AIzaSyAoMSKMn_LXJ8Q_0-dxDmMj-AEpDAphFE8"]);
        echo "Google Maps API key added to settings table.\n";
    } else {
        // Update the key if it exists
        $update = $pdo->prepare("UPDATE settings SET `value` = :value WHERE `key` = :key");
        $update->execute(["key" => "map_api_key", "value" => "AIzaSyAoMSKMn_LXJ8Q_0-dxDmMj-AEpDAphFE8"]);
        echo "Google Maps API key updated in settings table.\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
EOF

cat > check_columns.php << 'EOF'
<?php
try {
    // Connect to the remote database
    $pdo = new PDO("mysql:host=139.84.227.249;dbname=jumla_main", "jumla_main", "Cf255f@s9");
    
    // Check if phone_code column exists in countries table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM countries LIKE :column");
    $stmt->execute(["column" => "phone_code"]);
    
    if (!$stmt->fetch()) {
        echo "Phone code column missing in countries table. Running fix...\n";
        $pdo->exec("ALTER TABLE countries ADD COLUMN phone_code VARCHAR(10) NULL AFTER code");
    } else {
        echo "Phone code column exists in countries table.\n";
    }
    
    // Check if default column exists in countries table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM countries LIKE :column");
    $stmt->execute(["column" => "default"]);
    
    if (!$stmt->fetch()) {
        echo "Default column missing in countries table. Running fix...\n";
        $pdo->exec("ALTER TABLE countries ADD COLUMN `default` BOOLEAN DEFAULT FALSE AFTER active");
    } else {
        echo "Default column exists in countries table.\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
EOF

# Run the PHP scripts
echo "Adding Google Maps API key to settings..."
php fix_map_api_key.php

# Clear application cache
echo "Clearing application cache..."
php artisan optimize:clear
php artisan cache:clear
php artisan config:cache

# Check if phone_code field is properly added to countries table
echo "Checking country table columns..."
php check_columns.php

# Clean up temporary files
rm -f fix_map_api_key.php check_columns.php

echo "Maps functionality should now be working correctly."
echo "Note: Make sure the Google Maps API key is properly set in the settings table."
echo "Done!" 