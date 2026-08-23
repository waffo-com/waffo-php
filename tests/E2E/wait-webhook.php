<?php

declare(strict_types=1);

$eventType = $argv[1] ?? '';
$matchKey = $argv[2] ?? '';
$matchValue = $argv[3] ?? '';
$expectedKey = $argv[4] ?? '';
$expectedValue = $argv[5] ?? '';
$artifactDirectory = getenv('WAFFO_E2E_ARTIFACT_DIR');
if ($eventType === '' || !is_string($artifactDirectory) || $artifactDirectory === '') {
    throw new RuntimeException('Usage: wait-webhook.php EVENT_TYPE [MATCH_KEY MATCH_VALUE [EXPECTED_KEY EXPECTED_VALUE]]');
}
$eventsPath = rtrim($artifactDirectory, DIRECTORY_SEPARATOR) . '/events.ndjson';
$deadline = microtime(true) + 120;
$matched = null;
do {
    $lines = is_file($eventsPath) ? file($eventsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    foreach (array_reverse($lines ?: []) as $line) {
        $event = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        if (($event['case'] ?? null) !== 'webhook' || ($event['phase'] ?? null) !== $eventType) {
            continue;
        }
        $details = is_array($event['details'] ?? null) ? $event['details'] : [];
        $notification = is_array($details['notification'] ?? null) ? $details['notification'] : [];
        $result = is_array($notification['result'] ?? null) ? $notification['result'] : [];
        if ($matchKey !== '' && (string) ($result[$matchKey] ?? '') !== $matchValue) {
            continue;
        }
        if ($expectedKey !== '' && (string) ($result[$expectedKey] ?? '') !== $expectedValue) {
            continue;
        }
        if (($details['handlerSuccess'] ?? false) !== true
            || ($details['responseBody'] ?? null) !== '{"message":"success"}'
            || ($details['signedResponsePresent'] ?? false) !== true) {
            throw new RuntimeException("Webhook {$eventType} did not produce a signed success response");
        }
        $matched = $event;
        break 2;
    }
    usleep(1_000_000);
} while (microtime(true) < $deadline);

if (!is_array($matched)) {
    throw new RuntimeException("Timed out waiting for matching {$eventType}");
}
fwrite(STDOUT, json_encode([
    'eventType' => $eventType,
    'matchKey' => $matchKey,
    'matchValue' => $matchValue,
    'expectedKey' => $expectedKey,
    'expectedValue' => $expectedValue,
    'handlerSuccess' => true,
    'signedResponsePresent' => true,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
