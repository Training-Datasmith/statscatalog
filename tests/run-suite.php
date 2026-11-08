<?php

$expectedFiles = array(
    'StatsCatalogGuardTest.php',
    'StatsCatalogHookTest.php',
    'StatsCatalogModuleMetaTest.php',
    'StatsCatalogQueryTest.php',
);
$expectedTestCount = 28;

if (strpos(PHP_VERSION, '5.6.40') !== 0) {
    fwrite(STDERR, 'PHP 5.6.40 is required, got ' . PHP_VERSION . "\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);
$testsDir = $repoRoot . '/tests';
$found = glob($testsDir . '/*Test.php');
sort($found);
$foundNames = array();
foreach ($found as $path) {
    $foundNames[] = basename($path);
}
$expectedPaths = array();
foreach ($expectedFiles as $name) {
    $expectedPaths[] = $testsDir . '/' . $name;
}
if ($foundNames !== $expectedFiles) {
    fwrite(STDERR, "Test file list drift\n");
    fwrite(STDERR, 'Expected: ' . implode(', ', $expectedFiles) . "\n");
    fwrite(STDERR, 'Found: ' . implode(', ', $foundNames) . "\n");
    exit(1);
}

$methodCount = 0;
foreach ($found as $file) {
    $code = file_get_contents($file);
    if (preg_match_all('/\bfunction\s+test\w*\s*\(/', $code, $matches)) {
        $methodCount += count($matches[0]);
    }
}
if ($methodCount !== $expectedTestCount) {
    fwrite(STDERR, "Expected {$expectedTestCount} test methods, found {$methodCount}\n");
    exit(1);
}

$phar = getenv('PHPUNIT_PHAR');
if (!$phar || !is_readable($phar)) {
    fwrite(STDERR, "PHPUNIT_PHAR must point to a readable phpunit phar\n");
    exit(1);
}

$diagnosticPattern = '/(Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error):/';
$phpIni = '-d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1';

function run_phpunit_once($phar, $phpIni, $bootstrap, $files, $label)
{
    global $diagnosticPattern, $expectedTestCount;

    $tmpDir = sys_get_temp_dir() . '/statscatalog-phpunit-' . substr(sha1(uniqid('', true)), 0, 16);
    if (!mkdir($tmpDir, 0700, true)) {
        fwrite(STDERR, "Cannot create temp dir\n");
        exit(1);
    }

    $xmlPath = $tmpDir . '/phpunit.xml';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<phpunit bootstrap="' . htmlspecialchars($bootstrap, ENT_QUOTES, 'UTF-8') . '" colors="true">' . "\n";
    $xml .= '  <testsuite name="module-tests">' . "\n";
    foreach ($files as $file) {
        $xml .= '    <file>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</file>' . "\n";
    }
    $xml .= "  </testsuite>\n</phpunit>\n";
    file_put_contents($xmlPath, $xml);

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . $phpIni . ' '
        . escapeshellarg($phar) . ' --configuration ' . escapeshellarg($xmlPath);

    $descriptorSpec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $repoRoot = dirname(__DIR__);
    $proc = proc_open($cmd, $descriptorSpec, $pipes, $repoRoot);
    if (!is_resource($proc)) {
        fwrite(STDERR, "Failed to start PHPUnit for {$label}\n");
        exit(1);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($proc);

  $combined = $stdout . "\n" . $stderr;
    if (preg_match($diagnosticPattern, $combined)) {
        fwrite(STDERR, "PHP diagnostic in PHPUnit output ({$label})\n");
        fwrite(STDERR, $combined);
        exit(1);
    }
    if (preg_match('/\b(Skipped|Risky|Incomplete)\b/', $combined)) {
        fwrite(STDERR, "Undesired PHPUnit status in {$label}\n");
        fwrite(STDERR, $combined);
        exit(1);
    }
    if (preg_match('/\b(FAILURES!|ERRORS!)\b/', $combined)) {
        fwrite(STDERR, "PHPUnit failed ({$label})\n");
        fwrite(STDERR, $combined);
        exit(1);
    }
    if (!preg_match('/^OK \(' . (int) $expectedTestCount . ' tests, \d+ assertions\)$/m', $combined)) {
        fwrite(STDERR, "Missing exact OK summary for {$label}\n");
        fwrite(STDERR, $combined);
        exit(1);
    }
    if ($status !== 0) {
        fwrite(STDERR, "PHPUnit exit status {$status} for {$label}\n");
        fwrite(STDERR, $combined);
        exit(1);
    }

    echo "=== {$label} ===\n";
    echo trim($combined) . "\n";

    $rm = 'rm -rf ' . escapeshellarg($tmpDir);
    exec($rm);
}

$bootstrap = $testsDir . '/bootstrap.php';
run_phpunit_once($phar, $phpIni, $bootstrap, $expectedPaths, 'ordered');

$shuffled = $expectedPaths;
srand(20261108);
shuffle($shuffled);
run_phpunit_once($phar, $phpIni, $bootstrap, $shuffled, 'shuffled');

exit(0);
