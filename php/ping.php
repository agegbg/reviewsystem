<?php
// ping.php – debuggar om rätt version av session.php ligger på servern
header('Content-Type: text/plain; charset=utf-8');

$target = __DIR__ . '/session.php';

echo "Server time: " . date('Y-m-d H:i:s') . "\n";
echo "Target: $target\n";

if (file_exists($target)) {
    echo "session.php exists\n";
    echo "mtime:  " . date('Y-m-d H:i:s', filemtime($target)) . "\n";
    echo "size:   " . filesize($target) . " bytes\n";
    echo "sha1:   " . sha1_file($target) . "\n";
} else {
    echo "session.php NOT FOUND next to ping.php\n";
}

// OPcache-info (om tillåtet på hosten)
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    $enabled = $st && !empty($st['opcache_enabled']);
    echo "OPcache enabled: " . ($enabled ? "yes" : "no") . "\n";

    if ($enabled && isset($st['scripts'][$target])) {
        $s = $st['scripts'][$target];
        echo "OPcache timestamp: " . date('Y-m-d H:i:s', $s['timestamp']) . "\n";
        echo "OPcache hits: " . ($s['hits'] ?? 0) . "\n";
    }
} else {
    echo "OPcache status not available\n";
}
