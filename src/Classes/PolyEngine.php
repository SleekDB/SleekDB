<?php

namespace SleekDB\Classes;

use Exception;
use SleekDB\Cache;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\InvalidConfigurationException;

class PolyEngine
{

    protected $configurations = [
        "storeName" => "",
        "databasePath" => "",
        "documentSize" => 1000,
        "storeDirectory" => "",
        "dataDirectory" => "",
        "dataFilePath" => "",
        "cacheDirectory" => "",
        "primaryKey" => "_id"
    ];

    function __construct($storeName, $databasePath, $primaryKey = null, $folderPermissions = 0777, $documentSize = null)
    {
        $storeDirectory = $databasePath . $storeName . DIRECTORY_SEPARATOR;
        $this->configurations["storeName"] = $storeName;
        $this->configurations["databasePath"] = $databasePath;
        $this->configurations["storeDirectory"] = $storeDirectory;
        $this->configurations["dataDirectory"] = $storeDirectory . Engine::DATA_DIRECTORY;
        $this->configurations["dataFilePath"] = $this->configurations["dataDirectory"] . "data.sdb";
        $this->configurations["cacheDirectory"] = $storeDirectory . Engine::CACHE_DIRECTORY;

        if ($primaryKey) {
            if (!is_string($primaryKey)) {
                throw new InvalidConfigurationException("Primary key has to be a valid string");
            }
            $this->configurations["primaryKey"] = $primaryKey;
        }

        if ($documentSize) {
            if (!is_integer($documentSize)) {
                throw new InvalidConfigurationException("Document size must be a valid integer number");
            }
            $this->configurations["documentSize"] = $documentSize;
        }

        $this->bootstrap($folderPermissions);
    }

    public function getEngineName()
    {
        return Engine::POLY;
    }

    public function getStoreDirectory()
    {
        return $this->configurations["storeDirectory"];
    }

    public function getDatabasePath()
    {
        return $this->configurations["databasePath"];
    }

    public function getDataFilePath()
    {
        return $this->configurations["dataFilePath"];
    }

    public function getPrimaryKey(): string
    {
        return $this->configurations["primaryKey"];
    }

    public function getDocumentSize(): int
    {
        return $this->configurations["documentSize"];
    }

    /**
     * Bootstrap the store.
     * Create necessary directories and files if they do not exist.
     * 
     * @throws IOException
     */
    public function bootstrap($folderPermissions)
    {
        IoHelper::createFolder($this->getDatabasePath(), $folderPermissions);

        $storeName = $this->configurations["storeName"];
        // Prepare store name.
        IoHelper::normalizeDirectory($storeName);

        // Create the store directory.
        IoHelper::createFolder($this->configurations["storeDirectory"], $folderPermissions);

        // Create the cache directory.
        IoHelper::createFolder($this->configurations["cacheDirectory"], $folderPermissions);

        // Create the data directory.
        IoHelper::createFolder($this->configurations["dataDirectory"], $folderPermissions);

        // Create the data file.
        if (!file_exists($this->getDataFilePath())) {
            PolyIoHelper::writeContentToFile($this->getDataFilePath(), '');
        }
    }

    public function newDocument(array $storeData): array
    {
        $primaryKey = $this->getPrimaryKey();
        // Check if it has the primary key
        if (isset($storeData[$primaryKey])) {
            throw new IdNotAllowedException(
                "The \"$primaryKey\" index is reserved by SleekDB, please delete the $primaryKey key and try again"
            );
        }
        $id = $this->getUniqueUUID();

        // Prepare storable data
        $storableJSON = @json_encode($storeData);
        if ($storableJSON === false) {
            throw new JsonException('Unable to encode the data array, 
              please provide a valid PHP associative array');
        }

        PolyIoHelper::addRow($this->getDataFilePath(), $storableJSON, $id, $this->getDocumentSize());

        // Add the system ID with the store data array.
        $storeData[$primaryKey] = $id;

        return $storeData;
    }

    protected function getUniqueUUID()
    {
        // Generate 16 bytes (128 bits) of random data.
        $data = random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        if (!is_string($uuid)) {
            throw new IdNotAllowedException('Error generating UUID.');
        }
        return $uuid;
    }
}
