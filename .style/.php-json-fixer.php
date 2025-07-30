<?php

    declare(strict_types=1);

    if ($argc == 1) {
        $mode = '';
        $dir = '.';
    } elseif($argc == 2) {
        $mode = $argv[1];
        $dir = '.';
    } elseif($argc == 3) {
        $mode = $argv[1];
        $dir = $argv[2];
    } else {
        die('JSON Checker cannot be run with this set of parameters!');
    }

    if (!in_array($mode, ['', 'fix'])) {
        die('Unsupported mode "' . $mode . '"!');
    }

    $start = microtime(true);
    $invalidFiles = jsonStyleCheck($dir, $mode);
    $duration = microtime(true) - $start;

    foreach ($invalidFiles as $invalidFile) {
        if ($mode == 'fix') {
            writeStatus('FIXED', 'yellow', ' File: ', $invalidFile);
        } else {
            writeStatus('FIXIT', 'purple', ' File: ', $invalidFile);
        }
    }

    if (!empty($invalidFiles)) {
        echo PHP_EOL;
    }

    echo 'Checked all files in ' . number_format($duration, 3) . ' seconds, ' . number_format(memory_get_peak_usage() / 1024 / 1024, 3) . ' MB memory used' . PHP_EOL;

    if (!empty($invalidFiles)) {
        exit(1);
    }

    function jsonStyleCheck(string $dir, string $mode)
    {
        $ignore = ['./.vscode', './.idea', './.git', './libs/vendor'];
        $invalidFiles = [];
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && !in_array($dir, $ignore)) {
                if (is_dir($dir . '/' . $file)) {
                    $invalidFiles = array_merge($invalidFiles, jsonStyleCheck($dir . '/' . $file, $mode));
                } else {
                    if (fnmatch('*.json', $dir . '/' . $file)) {
                        $invalidFile = checkContentInFile($dir . '/' . $file, $mode);
                        if ($invalidFile !== false) {
                            $invalidFiles[] = $invalidFile;
                        }
                    }
                }
            }
        }
        return $invalidFiles;
    }

    function checkContentInFile(string $dir, string $mode)
    {
        $fileOriginal = file_get_contents($dir);

        // Normalize line endings
        $fileOriginal = str_replace("\r\n", "\n", $fileOriginal);
        $fileOriginal = str_replace("\r", "\n", $fileOriginal);

        // Ignore line break at the end of the file
        $fileOriginal = rtrim($fileOriginal, "\n");

        // Reformat JSON using PHP
        $fileCompare = json_encode(json_decode($fileOriginal), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        if ($fileOriginal == $fileCompare) {
            return false;
        }

        if ($mode == 'fix') {
            file_put_contents($dir, $fileCompare);
        }

        return $dir;
    }

    function writeline($output = '', $newline = true)
    {
        echo $newline ? ($output . PHP_EOL) : ($output);
    }

    function writeStatus($status, $color, $text, $param)
    {
        writeline('   ', false);
        echoStatus($status, $color);
        writeline(' ' . $text, false);
        writeline($param);
    }

    function echoStatus($status, $color = null)
    {
        // pre def escape sequences
        $ESCAPE = [
            'grey'   => "\033[90m",
            'red'    => "\033[91m",
            'green'  => "\033[92m",
            'yellow' => "\033[93m",
            'blue'   => "\033[94m",
            'purple' => "\033[95m",
            'cyan'   => "\033[96m",
            'white'  => "\033[97m",
        ];
        $ESC = '';
        if ($color != null) {
            $ESC = $ESCAPE[$color];
        }
        // Reset to orginal
        $CLI = "\033[0m";
        // only on Windows console
        if (!isWindows()) {
            $ESC = '';
            $CLI = '';
        }
        echo '[' . $ESC . $status . $CLI . ']';
    }

    function isWindows()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return true;
        } else {
            return false;
        }
    }

