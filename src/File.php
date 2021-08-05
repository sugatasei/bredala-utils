<?php

namespace Bredala\Utils;

class File
{
    protected $filename;
    protected $handle;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function read()
    {
        $this->close();
        $this->handle = fopen($this->file, 'r');
    }

    public function write()
    {
        $this->close();
        $this->handle = fopen($this->file, 'w');
    }

    public function append()
    {
        $this->close();
        $this->handle = fopen($this->file, 'a');
    }

    public function close()
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function copy(string $to)
    {
        $this->close();
        copy($this->filename, $to);

        return $this;
    }

    public function move(string $to)
    {
        $this->close();
        rename($this->filename, $to);
        $this->filename = $to;

        return $this;
    }

    public function delete()
    {
        $this->close();
        unlink($this->filename);
        $this->filename = null;
    }
}
