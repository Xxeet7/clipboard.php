<?php

declare(strict_types=1);

/**
 * Clipboard - perform clipboard operations in PHP
 * Homepage: https://github.com/asvvvad/clipboard
 * Made by ASVVVAD (https://asvvad.eu.org)
 * Forked and maintained by Xxeet7 (https://github.com/Xxeet7/clipboard.php)
 */
final class Clipboard
{
    /**
     * @var bool
     */
    private $unsupported = false;

    /**
     * @var string
     */
    private $copyCmdArgs;

    /**
     * @var string
     */
    private $pasteCmdArgs;

    /**
     * Decide which method to use based on OS and availability
     */
    public function __construct()
    {
        switch (PHP_OS_FAMILY) {
            case 'Solaris': case 'BSD': case 'Linux':
                if (getenv('WAYLAND_DISPLAY') !== false) {
                    if ($this->lookPath('wl-paste') and $this->lookPath('wl-copy')) {
                        $this->pasteCmdArgs = 'wl-paste --no-newline';
                        $this->copyCmdArgs = 'wl-copy';
                        break;
                    } // fall back to xclip or xsel
                }
                if ($this->lookPath('xclip')) {
                    $this->pasteCmdArgs = 'xclip -out -selection clipboard';
                    $this->copyCmdArgs = 'xclip -in -selection clipboard';
                } elseif ($this->lookPath('xsel')) {
                    $this->pasteCmdArgs = 'xsel --output --clipboard';
                    $this->copyCmdArgs = 'xsel --input --clipboard';
                } elseif ($this->isWSL()) {
                    $this->copyCmdArgs = 'clip.exe';
                    $this->pasteCmdArgs = 'powershell.exe -Command Get-Clipboard';
                } elseif ($this->lookPath('termux-clipboard-get') and $this->lookPath('termux-clipboard-get')) {
                    $this->pasteCmdArgs = 'termux-clipboard-get';
                    $this->copyCmdArgs = 'termux-clipboard-set';
                } else {
                    $this->unsupported = true;
                }
                break;
            case 'Darwin':
                $this->pasteCmdArgs = 'pbpaste';
                $this->copyCmdArgs = 'pbcopy';
                break;
            case 'Windows':
                // needs: https://www.c3scripts.com/tutorials/msdos/paste.html#exe
                if ($this->lookPath('paste.exe')) {
                    $this->pasteCmdArgs = 'paste';
                } else {
                    // https://github.com/Microsoft/WSL/issues/1069
                    // slower
                    $this->pasteCmdArgs = 'powershell.exe -Command Get-Clipboard';
                }
                $this->copyCmdArgs = 'clip';
                break;
            default:
                $this->unsupported = true;
                break;
        }
    }

    /**
     * Read clipboard content
     */
    public function readAll(): string
    {
        return exec($this->pasteCmdArgs);
    }

    /**
     * Write string to clipboard
     */
    public function writeAll($text): bool
    {
        $process = popen($this->copyCmdArgs, 'wb');

        if (is_resource($process)) {
            $w = fwrite($process, (string) $text);
            pclose($process);
        }

        return $text === $w;
    }

    public function isUnsupported(): bool
    {
        return $this->unsupported;
    }

    private function isWSL(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }
        $release = mb_strtolower(php_uname('r'));

        return mb_strpos($release, 'microsoft') !== false || mb_strpos($release, 'wsl') !== false;
    }

    /**
     * Look in PATH for an excutable
     *
     * @param  string  $excutable  what to look for
     */
    private function lookPath(string $excutable): bool
    {
        foreach (explode(PATH_SEPARATOR, getenv('PATH')) as $dir) {
            if (is_dir($dir) and in_array($excutable, scandir($dir))) {
                return true;
            }
        }

        return false;
    }
}
