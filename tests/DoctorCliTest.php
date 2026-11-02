<?php

namespace Tests;

use Amp\Process\Process;
use PHPUnit\Framework\TestCase;
use function Amp\ByteStream\buffer;

class DoctorCliTest extends TestCase
{
    public function testBrokenDatabaseStillProducesStructuredDoctorOutput(): void
    {
        $before = getenv('DB_CONNECTION');
        putenv('DB_CONNECTION=missing');
        try {
            $process = Process::start([PHP_BINARY, '-d', 'display_errors=stderr',
                '-d', 'auto_prepend_file=' . __DIR__ . '/bootstrap.php',
                dirname(__DIR__) . '/reactor', 'doctor', '--json'], dirname(__DIR__));
            $out = \Amp\async(fn () => buffer($process->getStdout()));
            $err = \Amp\async(fn () => buffer($process->getStderr()));
            $exit = $process->join(); $output = $out->await(); $err->await();
            self::assertSame(1, $exit);
            $checks = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $failed = array_column(array_filter($checks, fn ($check) => $check['status'] === 'fail'), 'name');
            self::assertContains('database_configuration', $failed);
            self::assertContains('async_database', $failed);
            self::assertStringNotContainsString('test-only', $output);
        } finally {
            if ($before === false) { putenv('DB_CONNECTION'); } else { putenv('DB_CONNECTION=' . $before); }
        }
    }
}
