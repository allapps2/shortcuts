<?php

namespace Shortcuts;

class ConsoleService
{
    const GREEN = "\033[92m";
    const GREEN_BG = "\033[30;42m";
    const YELLOW_BG = "\033[30;43m";
    const RED_BG = "\033[30;41m";
    const RED = "\033[91m";
    const WHITE = "\033[97m";
    const YELLOW = "\033[93m";
    private const COLOR_RESET = "\033[0m";

    const VERBOSE_COLOR = self::YELLOW;

    private bool $wasCwdDisplayed = false;
    private ?string $cwd = null;

    function __construct(
        private readonly array $env,
        public readonly array  $args,
        public readonly bool  $isVerboseMode
    ) {}

    /**
     * Outputs to STDOUT
     */
    static function echo(string $msg, string $color = null): void
    {
        echo (isset($color) ? self::composeEchoColored($msg, $color) : $msg) . "\n";
    }

    static function composeEchoColored(string $msg, string $color): string
    {
        return
            $color .
            str_replace("\n", self::COLOR_RESET . "\n" . $color, $msg) .
            self::COLOR_RESET;
    }

    function setCwd(string $cwd): void
    {
        if ($this->cwd !== $cwd) {
            $this->cwd = $cwd;
            $this->wasCwdDisplayed = false;
        }
    }

    private function _echoCommandInVerboseMode(string $command): void
    {
        if (!$this->wasCwdDisplayed) {
            self::echo('working dir: ' . ($this->cwd ?: getcwd()), self::VERBOSE_COLOR);
            $this->wasCwdDisplayed = true;
        }
        self::echo($command, self::VERBOSE_COLOR);
    }

    /**
     * Executes and returns output
     */
    function exec(string $command): false|string
    {
        if ($this->isVerboseMode) {
            $this->_echoCommandInVerboseMode($command);
        }
        $process = proc_open(
            $command,
            [1 => ["pipe", "w"], 2 => STDERR],
            $pipes,
            cwd: $this->cwd,
            env_vars: $this->env
        );
        if (!is_resource($process)) {
            self::echo("failed to execute: {$command}");
            return false;
        }
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        if ($output !== false) {
            $output = rtrim($output); // trim trailing newline
            if ($this->isVerboseMode) {
                self::echo($output);
            }
        }

        return $output;
    }

    /**
     * Executes and outputs to STDOUT
     */
    function execSTDOUT(string $command, bool $ignoreVerboseMode = false): bool
    {
        if ($this->isVerboseMode && !$ignoreVerboseMode) {
            $this->_echoCommandInVerboseMode($command);
        }

        $process = proc_open(
            $command,
            [1 => STDOUT, 2 => STDERR],
            $pipes,
            cwd: $this->cwd,
            env_vars: $this->env
        );
        if (!is_resource($process)) {
            self::echo("failed to execute: {$command}");
            return false;
        }
        while(($status = proc_get_status($process)) && $status['running']) {
            usleep(500000);
        }
        proc_close($process);

        return ($status['exitcode'] === 0);
    }
}
