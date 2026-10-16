<?php
$db = new PDO('sqlite:' . __DIR__ . '/storage/turso_staging.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach (['santri', 'users', 'app_settings'] as $table) {
    $count = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
    echo $table . ': ' . $count . " baris\n";
}
