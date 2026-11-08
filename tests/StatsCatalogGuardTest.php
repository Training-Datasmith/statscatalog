<?php

class StatsCatalogGuardTest extends StatsCatalogTestCase
{
    private static $helperPath;

    public static function setUpBeforeClass()
    {
        $dir = sys_get_temp_dir() . '/statscatalog-guard-' . getmypid();
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        self::$helperPath = $dir . '/include-exit-check.php';
        file_put_contents(
            self::$helperPath,
            "<?php\ninclude \$argv[1];\necho \"SENTINEL_NOT_EXITED\\n\";\n"
        );
    }

    public function testModuleFileExitsWhenPsVersionMissing()
    {
        $target = $this->repoRoot . '/statscatalog.php';
        $this->runGuardChild($target, 'statscatalog.php without _PS_VERSION_');
    }

    public function testDirectoryIndexScriptsExit()
    {
        $paths = array(
            'index.php',
            'tests/index.php',
            'tests/phpstan/index.php',
            'translations/index.php',
            'upgrade/index.php',
        );
        foreach ($paths as $rel) {
            $this->runGuardChild($this->repoRoot . '/' . $rel, $rel);
        }
    }

    private function runGuardChild($absolutePath, $label)
    {
        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $cmd = escapeshellarg($php)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
            . escapeshellarg(self::$helperPath) . ' '
            . escapeshellarg($absolutePath);

        $descriptorSpec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $proc = proc_open($cmd, $descriptorSpec, $pipes);
        $this->assertInternalType('resource', $proc, $label);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($proc);

        $this->assertSame(0, $status, $label . ' exit status');
        $this->assertNotContains('SENTINEL_NOT_EXITED', $stdout, $label);
        $this->assertNoPhpDiagnostic($stdout, $label . ' stdout');
        $this->assertNoPhpDiagnostic($stderr, $label . ' stderr');
    }

    private function assertNoPhpDiagnostic($output, $label)
    {
        $pattern = '/(^|\n)(Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error):/';
        $this->assertNotRegExp($pattern, $output, $label);
    }
}
