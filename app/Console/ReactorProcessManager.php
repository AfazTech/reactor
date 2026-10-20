<?php

namespace App\Console;

use Reactor\Console\Support\BotProcessManager as BaseBotProcessManager;
use Reactor\Core\Config;
use Reactor\Core\Paths;

/**
 * Application-specific override of the framework's BotProcessManager.
 *
 * The framework version hardcodes "reactor.php" when spawning the
 * detached polling process, but this project ships a single entry
 * point named "reactor" (the legacy reactor.php file has been
 * removed). This subclass keeps the base class behaviour for stop /
 * PID lookup, and only overrides spawnDetached() so that the
 * background process is launched through "reactor".
 *
 * The constructor re-declares the promoted properties with the same
 * names as the parent so that the parent's private copies continue
 * to be initialised via parent::__construct().
 */
class ReactorProcessManager extends BaseBotProcessManager
{
    public function __construct(
        private Paths $paths,
        private Config $config,
    ) {
        parent::__construct($paths, $config);
    }

    public function spawnDetached(): bool
    {
        $reactorScript = $this->paths->base() . '/reactor';
        if (!file_exists($reactorScript)) {
            return false;
        }

        $logFile = $this->paths->logs() . '/bot-output.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $phpBinary = $this->config->getPhpBinary();
        if ($phpBinary === '' || !@is_executable($phpBinary)) {
            $phpBinary = PHP_BINARY;
        }

        $command = sprintf(
            'cd %s && nohup %s %s start >> %s 2>&1 &',
            escapeshellarg($this->paths->base()),
            escapeshellarg($phpBinary),
            escapeshellarg($reactorScript),
            escapeshellarg($logFile)
        );

        @shell_exec($command);

        return true;
    }
}
