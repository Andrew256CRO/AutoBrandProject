<?php
$hour = (int) date('H');

// Ruleaza doar intre 12:00 si 18:00
if ($hour >= 12 && $hour < 18) {
    require_once __DIR__ . '/scraper.php';
} else {
    echo "Outside schedule (12:00-18:00). Current hour: " . $hour . ":00\n";
}
?>