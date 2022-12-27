<?php

namespace SleekDB\Classes;

use Exception;
use \SleekDB\Cache;
use \SleekDB\Classes\Engine;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\InvalidConfigurationException;

class MonoEngine
{

    protected $configurations = [
        "storeName" => "",
        "databasePath" => "",
        "storeDirectory" => "",
        "dataDirectory" => "",
        "cacheDirectory" => "",
        "counterFilePath" => "",
        "primaryKey" => "_id"
    ];

    public function __construct($storeName, $databasePath, $primaryKey = null, $folderPermissions = 0777)
    {
        $storeDirectory = $databasePath . $storeName . DIRECTORY_SEPARATOR;
        $this->configurations["storeName"] = $storeName;
        $this->configurations["databasePath"] = $databasePath;
        $this->configurations["storeDirectory"] = $storeDirectory;
        $this->configurations["dataDirectory"] = $storeDirectory . Engine::DATA_DIRECTORY;
        $this->configurations["cacheDirectory"] = $storeDirectory . Engine::CACHE_DIRECTORY;
        $this->configurations["counterFilePath"] = $storeDirectory . "_cnt.sdb";

        if ($primaryKey) {
            if (!is_string($primaryKey)) {
                throw new InvalidConfigurationException("Primary key has to be a valid string");
            }
            $this->configurations["primaryKey"] = $primaryKey;
        }

        $this->bootstrap($folderPermissions);
    }

    public function getEngineName()
    {
        return Engine::MONO;
    }

    public function getDatabasePath()
    {
        return $this->configurations["databasePath"];
    }

    public function getStoreDirectory()
    {
        return $this->configurations["storeDirectory"];
    }

    public function getDataDirectory()
    {
        return $this->configurations["dataDirectory"];
    }

    public function getCacheDirectory()
    {
        return $this->configurations["cacheDirectory"];
    }

    public function getCounterFilePath()
    {
        return $this->configurations["counterFilePath"];
    }

    /**
     * Get the name of the field used as the primary key.
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->configurations["primaryKey"];
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
        $id = $this->increaseCounterAndGetNextId();
        // Add the system ID with the store data array.
        $storeData[$primaryKey] = $id;
        // Prepare storable data
        $storableJSON = @json_encode($storeData);
        if ($storableJSON === false) {
            throw new JsonException('Unable to encode the data array, 
              please provide a valid PHP associative array');
        }

        IoHelper::writeContentToFile($this->documentPath($id), $storableJSON);

        return $storeData;
    }

    private function documentPath($id)
    {
        return $this->getDataDirectory() . $id . ".json";
    }

    /**
     * Increments the store wide unique store object ID and returns it.
     * @return int
     * @throws IOException
     * @throws JsonException
     */
    public function increaseCounterAndGetNextId(): int
    {
        $counterPath = $this->configurations["counterFilePath"];

        if (!file_exists($counterPath)) {
            throw new IOException("File $counterPath does not exist.");
        }

        $dataDirectory = $this->configurations["dataDirectory"];

        return (int) IoHelper::updateFileContent($counterPath, function ($counter) use ($dataDirectory) {
            $newCounter = ((int) $counter) + 1;

            while (file_exists($dataDirectory . "$newCounter.json") === true) {
                $newCounter++;
            }
            return (string)$newCounter;
        });
    }

    /**
     * Delete store with all its data and cache.
     * @return bool
     * @throws IOException
     */
    public function deleteStore(): bool
    {
        return IoHelper::deleteFolder($this->configurations["storeDirectory"]);
    }

    /**
     * Return the last created store object ID.
     * @return int
     * @throws IOException
     */
    public function getLastInsertedId(): int
    {
        return (int) IoHelper::getFileContent($this->configurations["counterFilePath"]);
    }

    /**
     * Retrieve one document by its primary key. Very fast because it finds the document by its file path.
     * @param int|string $id
     * @return array|null
     * @throws InvalidArgumentException
     */
    public function findById($id)
    {

        $id = $this->checkAndStripId($id);

        try {
            $content = IoHelper::getFileContent($this->documentPath($id));
        } catch (Exception $exception) {
            return null;
        }

        return @json_decode($content, true);
    }

    /**
     * @param string|int $id
     * @return int
     * @throws InvalidArgumentException
     */
    public function checkAndStripId($id): int
    {
        if (!is_string($id) && !is_int($id)) {
            throw new InvalidArgumentException("The id of the document has to be an integer or string");
        }

        if (is_string($id)) {
            $id = IoHelper::secureStringForFileAccess($id);
        }

        if (!is_numeric($id)) {
            throw new InvalidArgumentException("The id of the document has to be numeric");
        }

        return (int) $id;
    }

    /**
     * Update or insert one document.
     * @param array $data
     * @param bool $autoGenerateIdOnInsert
     * @return array updated / inserted document
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function updateOrInsert(array $data, bool $autoGenerateIdOnInsert, $qb): array
    {

        if (empty($data)) {
            throw new InvalidArgumentException("No document to update or insert.");
        }

        if (!array_key_exists($this->getPrimaryKey(), $data)) {
            $data[$this->getPrimaryKey()] = $this->engine->increaseCounterAndGetNextId();
        } else {
            $data[$this->getPrimaryKey()] = $this->checkAndStripId($data[$this->getPrimaryKey()]);
            if ($autoGenerateIdOnInsert && $this->findById($data[$this->getPrimaryKey()]) === null) {
                $data[$this->getPrimaryKey()] = $this->engine->increaseCounterAndGetNextId();
            }
        }

        IoHelper::writeContentToFile($this->documentPath($data[$this->getPrimaryKey()]), json_encode($data));
        $qb->getQuery()->getCache()->deleteAllWithNoLifetime();

        return $data;
    }


    /**
     * Update or insert multiple documents.
     * @param array $data
     * @param bool $autoGenerateIdOnInsert
     * @return array updated / inserted documents
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function updateOrInsertMany(array $data, bool $autoGenerateIdOnInsert, $qb): array
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No documents to update or insert.");
        }

        // Check if all documents have the primary key before updating or inserting any
        foreach ($data as $key => $document) {
            if (!is_array($document)) {
                throw new InvalidArgumentException('Documents have to be an arrays.');
            }
            if (!array_key_exists($this->getPrimaryKey(), $document)) {
                $document[$this->getPrimaryKey()] = $this->engine->increaseCounterAndGetNextId();
            } else {
                $document[$this->getPrimaryKey()] = $this->checkAndStripId($document[$this->getPrimaryKey()]);
                if ($autoGenerateIdOnInsert && $this->findById($document[$this->getPrimaryKey()]) === null) {
                    $document[$this->getPrimaryKey()] = $this->engine->increaseCounterAndGetNextId();
                }
            }
            // after the stripping and checking we apply it back
            $data[$key] = $document;
        }

        // One or multiple documents to update or insert
        foreach ($data as $document) {
            // save to access file with primary key value because we secured it above
            IoHelper::writeContentToFile($this->documentPath($document[$this->getPrimaryKey()]), json_encode($document));
        }

        $qb->getQuery()->getCache()->deleteAllWithNoLifetime();

        return $data;
    }

    /**
     * Update one or multiple documents.
     * @param array $updatable
     * @return bool true if all documents could be updated and false if one document did not exist
     * @throws IOException
     * @throws InvalidArgumentException
     */
    public function update(array $updatable, $qb): bool
    {
        if (empty($updatable)) {
            throw new InvalidArgumentException("No documents to update.");
        }

        // we can use this check to determine if multiple documents are given
        // because documents have to have at least the primary key.
        if (array_keys($updatable) !== range(0, (count($updatable) - 1))) {
            $updatable = [$updatable];
        }

        // Check if all documents exist and have the primary key before updating any
        foreach ($updatable as $key => $document) {
            if (!is_array($document)) {
                throw new InvalidArgumentException('Documents have to be an arrays.');
            }
            if (!array_key_exists($this->getPrimaryKey(), $document)) {
                throw new InvalidArgumentException("Documents have to have the primary key \"$this->getPrimaryKey()\".");
            }

            $document[$this->getPrimaryKey()] = $this->checkAndStripId($document[$this->getPrimaryKey()]);
            // after the stripping and checking we apply it back to the updatable array.
            $updatable[$key] = $document;

            if (!file_exists($this->documentPath($document[$this->getPrimaryKey()]))) {
                return false;
            }
        }

        // One or multiple documents to update
        foreach ($updatable as $document) {
            // save to access file with primary key value because we secured it above
            IoHelper::writeContentToFile($this->documentPath($document[$this->getPrimaryKey()]), json_encode($document));
        }

        $qb->getQuery()->getCache()->deleteAllWithNoLifetime();

        return true;
    }

    /**
     * Update properties of one document.
     * @param int|string $id
     * @param array $updatable
     * @return array|false Updated document or false if document does not exist.
     * @throws IOException If document could not be read or written.
     * @throws InvalidArgumentException If one key to update is primary key or $id is not int or string.
     * @throws JsonException If content of document file could not be decoded.
     */
    public function updateById($id, array $updatable, $qb)
    {

        $id = $this->checkAndStripId($id);
        $filePath = $this->documentPath($id);

        if (array_key_exists($this->getPrimaryKey(), $updatable)) {
            throw new InvalidArgumentException("You can not update the primary key \"$this->getPrimaryKey()\" of documents.");
        }

        if (!file_exists($filePath)) {
            return false;
        }

        $content = IoHelper::updateFileContent($filePath, function ($content) use ($filePath, $updatable) {
            $content = @json_decode($content, true);
            if (!is_array($content)) {
                throw new JsonException("Could not decode content of \"$filePath\" with json_decode.");
            }
            foreach ($updatable as $key => $value) {
                NestedHelper::updateNestedValue($key, $content, $value);
            }
            return json_encode($content);
        });

        $qb->getQuery()->getCache()->deleteAllWithNoLifetime();

        return json_decode($content, true);
    }

    /**
     * Delete one or multiple documents.
     * @param array $criteria
     * @param int $returnOption
     * @return array|bool|int
     * @throws IOException
     * @throws InvalidArgumentException
     */
    public function deleteBy(array $criteria, int $returnOption, $qb)
    {
        $query = $qb->where($criteria)->getQuery();
        $query->getCache()->deleteAllWithNoLifetime();
        return $query->delete($returnOption);
    }

    /**
     * Delete one document by its primary key. Very fast because it deletes the document by its file path.
     * @param int|string $id
     * @return bool true if document does not exist or deletion was successful, false otherwise
     * @throws InvalidArgumentException
     */
    public function deleteById($id, $qb): bool
    {
        $id = $this->checkAndStripId($id);
        $filePath = $this->documentPath($id);
        $qb->getQuery()->getCache()->deleteAllWithNoLifetime();
        return (!file_exists($filePath) || true === @unlink($filePath));
    }

    /**
     * Remove fields from one document by its primary key.
     * @param int|string $id
     * @param array $fieldsToRemove
     * @return false|array
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function removeFieldsById($id, array $fieldsToRemove, $qb)
    {
        $id = $this->checkAndStripId($id);
        $filePath = $this->documentPath($id);

        if (in_array($this->getPrimaryKey(), $fieldsToRemove, false)) {
            throw new InvalidArgumentException("You can not remove the primary key \"$this->getPrimaryKey()\" of documents.");
        }
        if (!file_exists($filePath)) {
            return false;
        }

        $content = IoHelper::updateFileContent($filePath, function ($content) use ($filePath, $fieldsToRemove) {
            $content = @json_decode($content, true);
            if (!is_array($content)) {
                throw new JsonException("Could not decode content of \"$filePath\" with json_decode.");
            }
            foreach ($fieldsToRemove as $fieldToRemove) {
                NestedHelper::removeNestedField($content, $fieldToRemove);
            }
            return $content;
        });

        $qb->getQuery()->getCache()->deleteAllWithNoLifetime();

        return json_decode($content, true);
    }

    /**
     * Returns the amount of documents in the store.
     * @return int
     * @throws IOException
     */
    public function count($useCache): int
    {
        if ($useCache === true) {
            $cacheTokenArray = ["count" => true];
            $cache = new Cache($this->configurations["storeDirectory"], $cacheTokenArray, null);
            $cacheValue = $cache->get();
            if (is_array($cacheValue) && array_key_exists("count", $cacheValue)) {
                return $cacheValue["count"];
            }
        }
        $value = [
            "count" => IoHelper::countFolderContent($this->configurations["dataDirectory"])
        ];
        if (isset($cache)) {
            $cache->set($value);
        }
        return $value["count"];
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

        // Create the store counter file.
        if (!file_exists($this->configurations["counterFilePath"])) {
            IoHelper::writeContentToFile($this->configurations["counterFilePath"], '0');
        }
    }
}
