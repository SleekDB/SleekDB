<?php

namespace SleekDB;

use Exception;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\Exceptions\InvalidPropertyAccessException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use SleekDB\Traits\IoHelperTrait;

// To provide usage without composer, we need to require all files.
if(false === class_exists("\Composer\Autoload\ClassLoader")) {
    foreach (glob(__DIR__ . '/Exceptions/*.php') as $exception) {
        require_once $exception;
    }
    foreach (glob(__DIR__ . '/Traits/*.php') as $traits) {
      require_once $traits;
    }
    foreach (glob(__DIR__ . '/*.php') as $class) {
        if (strpos($class, 'SleekDB.php') !== false || strpos($class, 'Store.php') !== false) {
          continue;
        }
        require_once $class;
    }
}

class Store
{

  use IoHelperTrait;

  protected $root = __DIR__;

  protected $storeName = "";
  protected $storePath = "";

  protected $dataDirectory = "";

  protected $useCache = true;
  protected $defaultCacheLifetime;
  protected $primaryKey = "_id";
  protected $timeout = 120;
  protected $searchOptions = [
    "minLength" => 2,
    "scoreKey" => "searchScore",
    "mode" => "or",
    "algorithm" => Query::SEARCH_ALGORITHM["hits"]
  ];

  /**
   * Store constructor.
   * @param string $storeName
   * @param string $dataDir
   * @param array $configuration
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  public function __construct(string $storeName, string $dataDir, array $configuration = [])
  {
    $storeName = trim($storeName);
    if (empty($storeName)) {
      throw new InvalidArgumentException('store name can not be empty');
    }
    $this->storeName = $storeName;

    $dataDir = trim($dataDir);
    if (empty($dataDir)) {
      throw new InvalidArgumentException('data directory can not be empty');
    }
    if (substr($dataDir, -1) !== '/') {
      $dataDir .= '/';
    }
    $this->dataDirectory = $dataDir;

    $this->setConfiguration($configuration);

    // boot store
    $this->createDataDirectory();
    $this->createStore();
  }

  /**
   * Change the destination of the store object.
   * @param string $storeName
   * @param string|null $dataDir If dataDir is empty, previous database directory path will be used.
   * @param array $configuration
   * @return Store
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidConfigurationException
   */
  public function changeStore(string $storeName, string $dataDir = null, array $configuration = []): Store
  {
    if(empty($dataDir)){
      $dataDir = $this->getDataDirectory();
    }
    $this->__construct($storeName, $dataDir, $configuration);
    return $this;
  }

  /**
   * @return string
   */
  public function getStoreName(): string
  {
    return $this->storeName;
  }

  /**
   * @return string
   */
  public function getDataDirectory(): string
  {
    return $this->dataDirectory;
  }

  /**
   * @param array $configuration
   * @throws InvalidConfigurationException
   */
  private function setConfiguration(array $configuration)
  {
    if(array_key_exists("auto_cache", $configuration)){
      $autoCache = $configuration["auto_cache"];
      if(!is_bool($configuration["auto_cache"])){
        throw new InvalidConfigurationException("auto_cache has to be boolean");
      }

      $this->useCache = $autoCache;
    }

    if(array_key_exists("cache_lifetime", $configuration)){
      $defaultCacheLifetime = $configuration["cache_lifetime"];
      if(!is_int($defaultCacheLifetime) && !is_null($defaultCacheLifetime)){
        throw new InvalidConfigurationException("cache_lifetime has to be null or int");
      }

      $this->defaultCacheLifetime = $defaultCacheLifetime;
    }

    // Set timeout.
    if (array_key_exists("timeout", $configuration)) {
      if (!is_int($configuration['timeout']) || $configuration['timeout'] <= 0){
        throw new InvalidConfigurationException("timeout has to an int > 0");
      }
      $this->timeout = $configuration["timeout"];
    }
    set_time_limit($this->timeout);

    if(array_key_exists("primary_key", $configuration)){
      $primaryKey = $configuration["primary_key"];
      if(!is_string($primaryKey)){
        throw new InvalidConfigurationException("primary key has to be a string");
      }
      $this->primaryKey = $primaryKey;
    }

    if(array_key_exists("search", $configuration)){
      $searchConfig = $configuration["search"];

      if(array_key_exists("min_length", $searchConfig)){
        $searchMinLength = $searchConfig["min_length"];
        if(!is_int($searchMinLength) || $searchMinLength <= 0){
          throw new InvalidConfigurationException("min length for searching has to be an int >= 0");
        }
        $this->searchOptions["minLength"] = $searchMinLength;
      }

      if(array_key_exists("mode", $searchConfig)){
        $searchMode = $searchConfig["mode"];
        if(!is_string($searchMode) || !in_array(strtolower(trim($searchMode)), ["and", "or"])){
          throw new InvalidConfigurationException("search mode can just be \"and\" or \"or\"");
        }
        $this->searchOptions["mode"] = strtolower(trim($searchMode));
      }

      if(array_key_exists("score_key", $searchConfig)){
        $searchScoreKey = $searchConfig["score_key"];
        if((!is_string($searchScoreKey) && !is_null($searchScoreKey))){
          throw new InvalidConfigurationException("search score key for search has to be a not empty string or null");
        }
        $this->searchOptions["scoreKey"] = $searchScoreKey;
      }

      if(array_key_exists("algorithm", $searchConfig)){
        $searchAlgorithm = $searchConfig["algorithm"];
        if(!in_array($searchAlgorithm, Query::SEARCH_ALGORITHM, true)){
          $searchAlgorithm = implode(', ', $searchAlgorithm);
          throw new InvalidConfigurationException("The search algorithm has to be one of the following integer values ($searchAlgorithm)");
        }
        $this->searchOptions["algorithm"] = $searchAlgorithm;
      }
    }
  }

  /**
   * @return QueryBuilder
   */
  public function createQueryBuilder(): QueryBuilder
  {
    return new QueryBuilder($this);
  }

  /**
   * Creates a new object in the store.
   * It is stored as a plaintext JSON document.
   * @param array $data
   * @return array
   * @throws IOException
   * @throws IdNotAllowedException
   * @throws InvalidArgumentException
   * @throws JsonException
   */
  public function insert(array $data): array
  {
    // Handle invalid data
    if (empty($data)) {
      throw new InvalidArgumentException('No data found to insert in the store');
    }

    $data = $this->writeNewDocumentToStore($data);

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

    return $data;
  }

  /**
   * Creates multiple objects in the store.
   * @param array $data
   * @return array
   * @throws IOException
   * @throws IdNotAllowedException
   * @throws InvalidArgumentException
   * @throws JsonException
   */
  public function insertMany(array $data): array
  {
    // Handle invalid data
    if (empty($data)) {
      throw new InvalidArgumentException('No data found to insert in the store');
    }
    // All results.
    $results = [];
    foreach ($data as $document) {
      $results[] = $this->writeNewDocumentToStore($document);
    }
    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();
    return $results;
  }

  /**
   * Writes an object in a store.
   * @param array $storeData
   * @return array
   * @throws IOException
   * @throws IdNotAllowedException
   * @throws JsonException
   */
  private function writeNewDocumentToStore(array $storeData): array
  {
    $primaryKey = $this->getPrimaryKey();
    // Check if it has the primary key
    if (isset($storeData[$primaryKey])) {
      throw new IdNotAllowedException(
        "The \"$primaryKey\" index is reserved by SleekDB, please delete the $primaryKey key and try again"
      );
    }
    $id = $this->getStoreId();
    // Add the system ID with the store data array.
    $storeData[$primaryKey] = $id;
    // Prepare storable data
    $storableJSON = @json_encode($storeData);
    if ($storableJSON === false) {
      throw new JsonException('Unable to encode the data array, 
        please provide a valid PHP associative array');
    }
    // Define the store path
    $filePath = $this->getStorePath() . "data/$id.json";

    self::writeContentToFile($filePath, $storableJSON);

    return $storeData;
  }

  /**
   * Delete store with all its data and cache.
   * @return bool
   * @throws IOException
   */
  public function deleteStore(): bool
  {
    $storePath = $this->getStorePath();
    return self::deleteFolder($storePath);
  }

  /**
   * @throws IOException
   */
  private function createDataDirectory()
  {
    $dataDir = $this->getDataDirectory();
    self::createFolder($dataDir);
  }

  /**
   * @throws IOException
   */
  private function createStore()
  {
    $storeName = $this->getStoreName();
    // Prepare store name.
    if (substr($storeName, -1) !== '/') {
      $storeName .= '/';
    }
    // Store directory path.
    $this->storePath = $this->getDataDirectory() . $storeName;
    $storePath = $this->getStorePath();
    self::createFolder($storePath);

    // Create the cache directory.
    $cacheDirectory = $storePath . 'cache';
    self::createFolder($cacheDirectory);

    // Create the data directory.
    $dataDirectory = $storePath . 'data';
    self::createFolder($dataDirectory);

    // Create the store counter file.
    $counterFile = $storePath . '_cnt.sdb';
    if(!file_exists($counterFile)){
      self::writeContentToFile($counterFile, '0');
    }
  }

  /**
   * @return bool
   */
  public function _getUseCache(): bool
  {
    return $this->useCache;
  }

  /**
   * @return null|int
   */
  public function _getDefaultCacheLifetime()
  {
    return $this->defaultCacheLifetime;
  }

  /**
   * Increments the store wide unique store object ID and returns it.
   * @return int
   * @throws IOException
   * @throws JsonException
   */
  private function getStoreId(): int
  {
    $counterPath = $this->getStorePath() . '_cnt.sdb';

    if (!file_exists($counterPath)) {
      throw new IOException("File $counterPath does not exist.");
    }

    return (int) self::updateFileContent($counterPath, function ($counter){
      return (string)(((int) $counter) + 1);
    });
  }

  /**
   * Return the last created store object ID.
   * @return int
   * @throws IOException
   */
  public function getLastInsertedId(): int
  {
    $counterPath = $this->getStorePath() . '_cnt.sdb';

    return (int) self::getFileContent($counterPath);
  }

  /**
   * @return string
   */
  public function getStorePath(): string
  {
    return $this->storePath;
  }

  /**
   * Retrieve all documents.
   * @return array
   * @throws InvalidPropertyAccessException
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function findAll(): array
  {
    return $this->createQueryBuilder()->getQuery()->fetch();
  }

  /**
   * Retrieve one document by its primary key. Very fast because it finds the document by its file path.
   * @param int $id
   * @return array|null
   */
  public function findById(int $id){

    $filePath = $this->getStorePath() . "data/$id.json";

    try{
      $content = self::getFileContent($filePath);
    } catch (Exception $exception){
      return null;
    }

    return @json_decode($content, true);
  }

  /**
   * Retrieve one or multiple documents.
   * @param array $criteria
   * @param array $orderBy
   * @param int $limit
   * @param int $offset
   * @return array
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   */
  public function findBy(array $criteria, array $orderBy = null, int $limit = null, int $offset = null): array
  {
    $qb = $this->createQueryBuilder();

    $qb->where($criteria);

    if($orderBy !== null) {
      $qb->orderBy($orderBy);
    }

    if($limit !== null) {
      $qb->limit($limit);
    }

    if($offset !== null) {
      $qb->skip($offset);
    }

    return $qb->getQuery()->fetch();
  }

  /**
   * Retrieve one document.
   * @param array $criteria
   * @return array|null single document or NULL if no document can be found
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   */
  public function findOneBy(array $criteria)
  {
    $qb = $this->createQueryBuilder();

    $qb->where($criteria);

    $result = $qb->getQuery()->first();

    return (!empty($result))? $result : null;

  }

  /**
   * Update one or multiple documents.
   * @param array $updatable true if all documents could be updated and false if one document did not exist
   * @return bool
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function update(array $updatable): bool
  {
    $primaryKey = $this->getPrimaryKey();

    if(empty($updatable)) {
      throw new InvalidArgumentException("No documents to update.");
    }

    // we can use this check to determine if multiple documents are given
    // because documents have to have at least the primary key.
    if(array_keys($updatable) !== range(0, (count($updatable) - 1))){
      $updatable = [ $updatable ];
    }

    // Check if all documents exist and have the primary key before updating any
    foreach ($updatable as $document){
      if(!is_array($document)) {
        throw new InvalidArgumentException('Documents have to be an arrays.');
      }
      if(!array_key_exists($primaryKey, $document)) {
        throw new InvalidArgumentException("Documents have to have the primary key \"$primaryKey\".");
      }

      $storePath = $this->getStorePath() . "data/$document[$primaryKey].json";

      if (!file_exists($storePath)) {
        return false;
      }
    }

    // One or multiple documents to update
    foreach ($updatable as $document) {
      $storePath = $this->getStorePath() . "data/$document[$primaryKey].json";
      self::writeContentToFile($storePath, json_encode($document));
    }

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

    return true;
  }

  /**
   * Update properties of one document.
   * @param int $id
   * @param array $updatable
   * @return array|false Updated document or false if document does not exist.
   * @throws IOException If document could not be read or written.
   * @throws InvalidArgumentException If one key to update is primary key.
   * @throws JsonException If content of document file could not be decoded.
   */
  public function updateById(int $id, array $updatable)
  {
    $filePath = $this->getStorePath() . "data/$id.json";

    $primaryKey = $this->getPrimaryKey();

    if(array_key_exists($primaryKey, $updatable)) {
      throw new InvalidArgumentException("You can not update the primary key \"$primaryKey\" of documents.");
    }

    if(!file_exists($filePath)){
      return false;
    }

    $updateNestedValue = static function (array $keysArray, $oldData, $newValue, int $originalKeySize) use (&$updateNestedValue){
      if(empty($keysArray)){
        return $newValue;
      }
      $currentKey = $keysArray[0];
      $result[$currentKey] = $oldData;
      if(!is_array($oldData) || !array_key_exists($currentKey, $oldData)){
        $result[$currentKey] = $updateNestedValue(array_slice($keysArray, 1), $oldData, $newValue, $originalKeySize);
        if(count($keysArray) !== $originalKeySize){
          return $result;
        }
      }
      foreach ($oldData as $key => $item){
        if($key !== $currentKey){
          $result[$key] = $oldData[$key];
        } else {
          $result[$currentKey] = $updateNestedValue(array_slice($keysArray, 1), $oldData[$currentKey], $newValue, $originalKeySize);
        }
      }
      return $result;
    };

    $content = self::updateFileContent($filePath, function($content) use ($filePath, $updatable, &$updateNestedValue){
      $content = @json_decode($content, true);
      if(!is_array($content)){
        throw new JsonException("Could not decode content of \"$filePath\" with json_decode.");
      }
      foreach ($updatable as $key => $value){
        $fieldNameArray = explode(".", $key);
        if(count($fieldNameArray) > 1){
          if(array_key_exists($fieldNameArray[0], $content)){
            $oldData = $content[$fieldNameArray[0]];
            $fieldNameArraySliced = array_slice($fieldNameArray, 1);
            $value = $updateNestedValue($fieldNameArraySliced, $oldData, $value, count($fieldNameArraySliced));
          } else {
            $oldData = $content;
            $value = $updateNestedValue($fieldNameArray, $oldData, $value, count($fieldNameArray));
            $content = $value;
            continue;
          }
        }
        $content[$fieldNameArray[0]] = $value;
      }

      return json_encode($content);
    });

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

    return json_decode($content, true);
  }

  /**
   * Delete one or multiple documents.
   * @param array $criteria
   * @param int $returnOption
   * @return array|bool|int
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   */
  public function deleteBy(array $criteria, int $returnOption = Query::DELETE_RETURN_BOOL){

    $query = $this->createQueryBuilder()->where($criteria)->getQuery();

    $query->getCache()->deleteAllWithNoLifetime();

    return $query->delete($returnOption);
  }

  /**
   * Delete one document by its primary key. Very fast because it deletes the document by its file path.
   * @param int $id
   * @return bool true if document does not exist or deletion was successful, false otherwise
   * @throws IOException
   */
  public function deleteById(int $id): bool
  {

    $filePath = $this->getStorePath() . "data/$id.json";

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

    return (!file_exists($filePath) || true === @unlink($filePath));
  }

  /**
   * Do a fulltext like search against one or multiple fields.
   * @param array $fields
   * @param string $query
   * @param array|null $orderBy
   * @param int|null $limit
   * @param int|null $offset
   * @return array
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   */
  public function search(array $fields, string $query, array $orderBy = null, int $limit = null, int $offset = null): array
  {

    $qb = $this->createQueryBuilder();

    $qb->search($fields, $query);

    if($orderBy !== null) {
      $qb->orderBy($orderBy);
    }

    if($limit !== null) {
      $qb->limit($limit);
    }

    if($offset !== null) {
      $qb->skip($offset);
    }

    return $qb->getQuery()->fetch();
  }

  /**
   * @return string
   */
  public function getPrimaryKey(): string
  {
    return $this->primaryKey;
  }

  /**
   * @return array
   */
  public function _getSearchOptions(): array
  {
    return $this->searchOptions;
  }

}
