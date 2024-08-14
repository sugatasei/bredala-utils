<?php

namespace Bredala\Utils;

class File
{
    private string $filename;
    private $stream = null;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function __destruct()
    {
        $this->close();
    }

    // -------------------------------------------------------------------------

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Get if file exists
     *
     * @return boolean
     */
    public function isFile(): bool
    {
        return is_file($this->filename);
    }

    /**
     * Reads entire file into a string
     *
     * @return string|null
     */
    public function get(): ?string
    {
        if ($this->isFile()) {
            $this->close();
            $content = file_get_contents($this->filename);
            return $content === false ? null : $content;
        }

        return null;
    }

    /**
     * Reads entire json file and convert the content into an array
     *
     * @return string|null
     */
    public function json(): ?array
    {
        if (!($content = $this->get())) {
            return null;
        }

        $json = json_decode($content, true);
        return is_array($json) ? $json : null;
    }

    /**
     * Write a string to a file
     *
     * @param string $data
     * @return integer|null
     */
    public function put(string $data): ?int
    {
        $this->close();
        $res = file_put_contents($this->filename, $data);
        return $res === false ? null : $res;
    }

    /**
     * Delete file
     *
     * @return static
     */
    public function delete(): static
    {
        if ($this->isFile()) {
            $this->close();
            unlink($this->filename);
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Open & close
    // -------------------------------------------------------------------------

    /**
     * Is file open
     *
     * @return boolean
     */
    public function isOpen(): bool
    {
        return $this->stream ? true : false;
    }

    /**
     * Open file
     *
     * @param string $mode
     * @return boolean
     */
    public function open(string $mode = 'r+'): bool
    {
        $this->stream = fopen($this->filename, $mode) ?: null;
        return $this->isOpen();
    }

    /**
     * Close open file
     *
     * @return boolean
     */
    public function close(): bool
    {
        if ($this->isOpen() && fclose($this->stream)) {
            $this->stream = null;
        }

        return !$this->isOpen();
    }

    /**
     * @return ressource|null
     */
    public function stream()
    {
        return $this->stream;
    }

    // -------------------------------------------------------------------------
    // Reader
    // -------------------------------------------------------------------------

    /**
     * Tests for end-of-file on a file pointer
     *
     * @return bool
     */
    public function eof(): bool
    {
        if (!$this->isOpen()) {
            return true;
        }

        return feof($this->stream);
    }

    /**
     * Gets next line from file
     *
     * @return string|null
     */
    public function read(): ?string
    {
        if (!$this->isOpen()) {
            return null;
        }

        $res = fgets($this->stream);
        return $res === false ? null : $res;
    }

    /**
     * Get line from file and parse CSV fields
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape_char
     * @return array|null
     */
    public function readCsv(string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\'): ?array
    {
        if (!$this->isOpen()) {
            return null;
        }

        $res = fgetcsv($this->stream, 0, $delimiter, $enclosure, $escape_char);
        return $res === false ? null : $res;
    }

    // -------------------------------------------------------------------------
    // Writer
    // -------------------------------------------------------------------------

    /**
     * Append a string to a file
     *
     * @param string $text
     * @return integer|null
     */
    public function add(string $text): ?int
    {
        if (!$this->isOpen()) {
            return null;
        }

        $res = fwrite($this->stream, $text);
        return $res === false ? null : $res;
    }

    /**
     * Append a line to a file
     *
     * @param string $text
     * @return integer|null
     */
    public function line(string $text): ?int
    {
        return $this->add($text . "\n");
    }

    /**
     * Format line as CSV and write to file
     *
     * @param array $data
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     * @return integer|null
     */
    public function csv(array $data, string $delimiter = ",", string $enclosure = '"', string $escape = "\\"): ?int
    {
        if (!$this->isOpen()) {
            return null;
        }

        $res = fputcsv($this->stream, $data, $delimiter, $enclosure, $escape);

        return $res === false ? null : $res;
    }

    public static function unlink(string $file)
    {
        if (is_file($file)) {
            unlink($file);
        }
    }

    public static function deleteDirRecursive(string $dir): bool
    {
        $dir = rtrim($dir, '/') . '/';

        if (($handle = opendir($dir))) {
            while ($obj = readdir($handle)) {
                if ($obj != '.' && $obj != '..') {
                    if (is_dir($dir . $obj)) {
                        if (!self::deleteDirRecursive($dir . $obj)) {
                            return false;
                        }
                    } elseif (is_file($dir . $obj)) {
                        if (!unlink($dir . $obj)) {
                            return false;
                        }
                    }
                }
            }

            closedir($handle);

            if (!@rmdir($dir)) {
                return false;
            }
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
}
