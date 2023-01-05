<?php

namespace SleekDB\Classes;

use Closure;
use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;

class PolyIoHelper extends IoHelper
{
    public static function addRow(string $filePath, string $content, string $uniqueId, int $documentSize)
    {

        self::checkWrite($filePath);

        // Open the file in write mode
        $file = fopen($filePath, 'a');

        // Lock the file for exclusive access
        if (flock($file, LOCK_EX) === false) {
            throw new IOException('Unable to lock file');
        }

        $preparedRow = $uniqueId . "|" . str_pad(trim($content), $documentSize) . PHP_EOL;

        // Write the row to the file
        fwrite($file, $preparedRow);

        // Unlock the file
        flock($file, LOCK_UN);

        // Close the file
        fclose($file);
    }
}
