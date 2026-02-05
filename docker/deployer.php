<?php

$secret = $_GET['key'] ?? '';
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), 'DEPLOYER_SECRET=') === 0) {
            $deployerSecret = trim(substr($line, strlen('DEPLOYER_SECRET=')));
            break;
        }
    }
}
if (!isset($deployerSecret)) {
    $deployerSecret = '';
}

if ($secret === $deployerSecret) {
    system('git pull');
    system('docker-compose -f docker-compose.prod.yml down');
    system('docker-compose -f docker-compose.prod.yml up -d --build --force-recreate');
    system('docker-compose -f docker-compose.prod.yml exec app bash setup.sh');
    echo 'Deployment successful';
} else {
    http_response_code(401);
    echo 'Invalid secret';
    exit;
}
