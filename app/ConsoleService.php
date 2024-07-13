<?php

namespace Shortcuts;

class ConsoleService
{
    private const GREEN = "\033[92m";
    private const GREEN_BG = "\033[30;42m";
    private const YELLOW_BG = "\033[30;43m";
    private const RED_BG = "\033[30;41m";
    private const RED = "\033[91m";
    private const WHITE = "\033[97m";
    private const YELLOW = "\033[93m";
    private const COLOR_RESET = "\033[0m";

    private bool $wasCwdDisplayed = false;
    private ?string $cwd = null;

    function __construct(
        private readonly array $env,
        public readonly array $args,
        private readonly bool  $isVerboseMode
    ) {}

    static function echo(string $msg, string $color = null): void
    {
        echo (
            isset($color)
            ? (
                $color .
                str_replace("\n", self::COLOR_RESET . "\n" . $color, $msg) .
                self::COLOR_RESET
            )
            : $msg
        ) . "\n";
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
            self::echo('working dir: ' . ($this->cwd ?: getcwd()), self::YELLOW);
            $this->wasCwdDisplayed = true;
        }
        self::echo($command, self::YELLOW);
    }

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

    function execSTDOUT(string $command, bool $echoCommand = false): bool
    {
        if ($this->isVerboseMode) {
            $this->_echoCommandInVerboseMode($command);
        } elseif ($echoCommand) {
            self::echo($command);
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
