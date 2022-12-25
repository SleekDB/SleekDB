<?php

namespace SleekDB\Classes;

use \SleekDB\Store;
use \SleekDB\Classes\Engine;

class MonoEngine
{

    protected $dataDirectory = "";
    protected $cacheDirectory = "";
    protected $counterFilePath = "";

    public function __construct($storeName)
    {
        // Store::getStoreName(); // (to get the current store or database name)
        // $this->dataDirectory = $dataDirectory;
        // $this->cacheDirectory = $cacheDirectory;
        // $this->counterFilePath = $this->dataDirectory . "/counter.json";
    }

    public static function getEngineName()
    {
        return Engine::MONO;
    }

    public function getDataDirectory()
    {
        return $this->dataDirectory;
    }

    public function getCacheDirectory()
    {
        return $this->cacheDirectory;
    }

    public function getCounterFilePath()
    {
        return $this->counterFilePath;
    }
}
