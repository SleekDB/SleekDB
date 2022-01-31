<?php

namespace SleekDB;

use Exception;
use SleekDB\Classes\IoHelper;
use SleekDB\Classes\NestedHelper;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;

// To provide usage without composer, we need to require all files.
if(false === class_exists("\Composer\Autoload\ClassLoader")) {
    foreach (glob(__DIR__ . '/Exceptions/*.php') as $exception) {
        require_once $exception;
    }
    foreach (glob(__DIR__ . '/Classes/*.php') as $traits) {
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

  protected $root = __DIR__;

  protected $storeName = "";
  protected $storePath = "";

  protected $databasePath = "";

  protected $useCache = true;
  protected $defaultCacheLifetime;
  protected $primaryKey = "_id";
  protected $timeout = false;
  protected $searchOptions = [
    "minLength" => 2,
    "scoreKey" => "searchScore",
    "mode" => "or",
    "algorithm" => Query::SEARCH_ALGORITHM["hits"]
  ];

  const dataDirectory = "data/";

  /**
   * Store constructor.
   * @param string $storeName
   * @param string $databasePath
   * @param array $configuration
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  public function __construct(string $storeName, string $databasePath, array $configuration = [])
  {
    $storeName = trim($storeName);
    if (empty($storeName)) {
      throw new InvalidArgumentException('store name can not be empty');
    }
    $this->storeName = $storeName;

    $databasePath = trim($databasePath);
    if (empty($databasePath)) {
      throw new InvalidArgumentException('data directory can not be empty');
    }

    IoHelper::normalizeDirectory($databasePath);
    $this->databasePath = $databasePath;

    $this->setConfiguration($configuration);

    // boot store
    $this->createDatabasePath();
    $this->createStore();
  }

  /**
   * Change the destination of the store object.
   * @param string $storeName
   * @param string|null $databasePath If empty, previous database path will be used.
   * @param array $configuration
   * @return Store
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidConfigurationException
   */
  public function changeStore(string $storeName, string $databasePath = null, array $configuration = []): Store
  {
    if(empty($databasePath)){
      $databasePath = $this->getDatabasePath();
    }
    $this->__construct($storeName, $databasePath, $configuration);
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
  public function getDatabasePath(): string
  {
    return $this->databasePath;
  }

  /**
   * @return QueryBuilder
   */
  public function createQueryBuilder(): QueryBuilder
  {
    return new QueryBuilder($this);
  }

  /**
   * Insert a new document to the store.
   * It is stored as a plaintext JSON document.
   * @param array $data
   * @return array inserted document
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
   * Insert multiple documents to the store.
   * They are stored as plaintext JSON documents.
   * @param array $data
   * @return array inserted documents
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
   * Delete store with all its data and cache.
   * @return bool
   * @throws IOException
   */
  public function deleteStore(): bool
  {
    $storePath = $this->getStorePath();
    return IoHelper::deleteFolder($storePath);
  }


  /**
   * Return the last created store object ID.
   * @return int
   * @throws IOException
   */
  public function getLastInsertedId(): int
  {
    $counterPath = $this->getStorePath() . '_cnt.sdb';

    return (int) IoHelper::getFileContent($counterPath);
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
   * @param array|null $orderBy array($fieldName => $order). $order can be "asc" or "desc"
   * @param int|null $limit the amount of data record to limit
   * @param int|null $offset the amount of data record to skip
   * @return array
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function findAll(array $orderBy = null, int $limit = null, int $offset = null): array
  {
    $qb = $this->createQueryBuilder();
    if(!is_null($orderBy)){
      $qb->orderBy($orderBy);
    }
    if(!is_null($limit)){
      $qb->limit($limit);
    }
    if(!is_null($offset)){
      $qb->skip($offset);
    }
    return $qb->getQuery()->fetch();
  }

  /**
   * Retrieve one document by its primary key. Very fast because it finds the document by its file path.
   * @param int|string $id
   * @return array|null
   * @throws InvalidArgumentException
   */
  public function findById($id){

    $id = $this->checkAndStripId($id);

    $filePath = $this->getDataPath() . "$id.json";

    try{
      $content = IoHelper::getFileContent($filePath);
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
   */
  public function findOneBy(array $criteria)
  {
    $qb = $this->createQueryBuilder();

    $qb->where($criteria);

    $result = $qb->getQuery()->first();

    return (!empty($result))? $result : null;

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
  public function updateOrInsert(array $data, bool $autoGenerateIdOnInsert = true): array
  {
    $primaryKey = $this->getPrimaryKey();

    if(empty($data)) {
      throw new InvalidArgumentException("No document to update or insert.");
    }

//    // we can use this check to determine if multiple documents are given
//    // because documents have to have at least the primary key.
//    if(array_keys($data) !== range(0, (count($data) - 1))){
//      $data = [ $data ];
//    }

    if(!array_key_exists($primaryKey, $data)) {
//        $documentString = var_export($document, true);
//        throw new InvalidArgumentException("Documents have to have the primary key \"$primaryKey\". Got data: $documentString");
      $data[$primaryKey] = $this->increaseCounterAndGetNextId();
    } else {
      $data[$primaryKey] = $this->checkAndStripId($data[$primaryKey]);
      if($autoGenerateIdOnInsert && $this->findById($data[$primaryKey]) === null){
        $data[$primaryKey] = $this->increaseCounterAndGetNextId();
      }
    }

    // One document to update or insert

    // save to access file with primary key value because we secured it above
    $storePath = $this->getDataPath() . "$data[$primaryKey].json";
    IoHelper::writeContentToFile($storePath, json_encode($data));

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

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
  public function updateOrInsertMany(array $data, bool $autoGenerateIdOnInsert = true): array
  {
    $primaryKey = $this->getPrimaryKey();

    if(empty($data)) {
      throw new InvalidArgumentException("No documents to update or insert.");
    }

//    // we can use this check to determine if multiple documents are given
//    // because documents have to have at least the primary key.
//    if(array_keys($data) !== range(0, (count($data) - 1))){
//      $data = [ $data ];
//    }

    // Check if all documents have the primary key before updating or inserting any
    foreach ($data as $key => $document){
      if(!is_array($document)) {
        throw new InvalidArgumentException('Documents have to be an arrays.');
      }
      if(!array_key_exists($primaryKey, $document)) {
//        $documentString = var_export($document, true);
//        throw new InvalidArgumentException("Documents have to have the primary key \"$primaryKey\". Got data: $documentString");
        $document[$primaryKey] = $this->increaseCounterAndGetNextId();
      } else {
        $document[$primaryKey] = $this->checkAndStripId($document[$primaryKey]);
        if($autoGenerateIdOnInsert && $this->findById($document[$primaryKey]) === null){
          $document[$primaryKey] = $this->increaseCounterAndGetNextId();
        }
      }
      // after the stripping and checking we apply it back
      $data[$key] = $document;
    }

    // One or multiple documents to update or insert
    foreach ($data as $document) {
      // save to access file with primary key value because we secured it above
      $storePath = $this->getDataPath() . "$document[$primaryKey].json";
      IoHelper::writeContentToFile($storePath, json_encode($document));
    }

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

    return $data;
  }


  /**
   * Update one or multiple documents.
   * @param array $updatable
   * @return bool true if all documents could be updated and false if one document did not exist
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
    foreach ($updatable as $key => $document){
      if(!is_array($document)) {
        throw new InvalidArgumentException('Documents have to be an arrays.');
      }
      if(!array_key_exists($primaryKey, $document)) {
        throw new InvalidArgumentException("Documents have to have the primary key \"$primaryKey\".");
      }

      $document[$primaryKey] = $this->checkAndStripId($document[$primaryKey]);
      // after the stripping and checking we apply it back to the updatable array.
      $updatable[$key] = $document;

      $storePath = $this->getDataPath() . "$document[$primaryKey].json";

      if (!file_exists($storePath)) {
        return false;
      }
    }

    // One or multiple documents to update
    foreach ($updatable as $document) {
      // save to access file with primary key value because we secured it above
      $storePath = $this->getDataPath() . "$document[$primaryKey].json";
      IoHelper::writeContentToFile($storePath, json_encode($document));
    }

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

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
  public function updateById($id, array $updatable)
  {

    $id = $this->checkAndStripId($id);

    $filePath = $this->getDataPath() . "$id.json";

    $primaryKey = $this->getPrimaryKey();

    if(array_key_exists($primaryKey, $updatable)) {
      throw new InvalidArgumentException("You can not update the primary key \"$primaryKey\" of documents.");
    }

    if(!file_exists($filePath)){
      return false;
    }

    $content = IoHelper::updateFileContent($filePath, function($content) use ($filePath, $updatable){
      $content = @json_decode($content, true);
      if(!is_array($content)){
        throw new JsonException("Could not decode content of \"$filePath\" with json_decode.");
      }
      foreach ($updatable as $key => $value){
        NestedHelper::updateNestedValue($key, $content, $value);
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
   */
  public function deleteBy(array $criteria, int $returnOption = Query::DELETE_RETURN_BOOL){

    $query = $this->createQueryBuilder()->where($criteria)->getQuery();

    $query->getCache()->deleteAllWithNoLifetime();

    return $query->delete($returnOption);
  }

  /**
   * Delete one document by its primary key. Very fast because it deletes the document by its file path.
   * @param int|string $id
   * @return bool true if document does not exist or deletion was successful, false otherwise
   * @throws InvalidArgumentException
   */
  public function deleteById($id): bool
  {

    $id = $this->checkAndStripId($id);

    $filePath = $this->getDataPath() . "$id.json";

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

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
  public function removeFieldsById($id, array $fieldsToRemove)
  {
    $id = $this->checkAndStripId($id);
    $filePath = $this->getDataPath() . "$id.json";
    $primaryKey = $this->getPrimaryKey();

    if(in_array($primaryKey, $fieldsToRemove, false)) {
      throw new InvalidArgumentException("You can not remove the primary key \"$primaryKey\" of documents.");
    }
    if(!file_exists($filePath)){
      return false;
    }

    $content = IoHelper::updateFileContent($filePath, function($content) use ($filePath, $fieldsToRemove){
      $content = @json_decode($content, true);
      if(!is_array($content)){
        throw new JsonException("Could not decode content of \"$filePath\" with json_decode.");
      }
      foreach ($fieldsToRemove as $fieldToRemove){
        NestedHelper::removeNestedField($content, $fieldToRemove);
      }
      return $content;
    });

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

    return json_decode($content, true);
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
   * Get the name of the field used as the primary key.
   * @return string
   */
  public function getPrimaryKey(): string
  {
    return $this->primaryKey;
  }

  /**
   * Returns the amount of documents in the store.
   * @return int
   * @throws IOException
   */
  public function count(): int
  {
    if($this->_getUseCache() === true){
      $cacheTokenArray = ["count" => true];
      $cache = new Cache($this->getStorePath(), $cacheTokenArray, null);
      $cacheValue = $cache->get();
      if(is_array($cacheValue) && array_key_exists("count", $cacheValue)){
        return $cacheValue["count"];
      }
    }
    $value = [
      "count" => IoHelper::countFolderContent($this->getDataPath())
    ];
    if(isset($cache)) {
      $cache->set($value);
    }
    return $value["count"];
  }

  /**
   * Returns the search options of the store.
   * @return array
   */
  public function _getSearchOptions(): array
  {
    return $this->searchOptions;
  }

  /**
   * Returns if caching is enabled store wide.
   * @return bool
   */
  public function _getUseCache(): bool
  {
    return $this->useCache;
  }

  /**
   * Returns the store wide default cache lifetime.
   * @return null|int
   */
  public function _getDefaultCacheLifetime()
  {
    return $this->defaultCacheLifetime;
  }

  /**
   * @return string
   * @deprecated since version 2.7, use getDatabasePath instead.
   */
  public function getDataDirectory(): string
  {
    // TODO remove with version 3.0
    return $this->databasePath;
  }

  /**
   * @throws IOException
   */
  private function createDatabasePath()
  {
    $databasePath = $this->getDatabasePath();
    IoHelper::createFolder($databasePath);
  }

  /**
   * @throws IOException
   */
  private function createStore()
  {
    $storeName = $this->getStoreName();
    // Prepare store name.
    IoHelper::normalizeDirectory($storeName);
    // Store directory path.
    $this->storePath = $this->getDatabasePath() . $storeName;
    $storePath = $this->getStorePath();
    IoHelper::createFolder($storePath);

    // Create the cache directory.
    $cacheDirectory = $storePath . 'cache';
    IoHelper::createFolder($cacheDirectory);

    // Create the data directory.
    IoHelper::createFolder($storePath . self::dataDirectory);

    // Create the store counter file.
    $counterFile = $storePath . '_cnt.sdb';
    if(!file_exists($counterFile)){
      IoHelper::writeContentToFile($counterFile, '0');
    }
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

    // TODO remove timeout on major update
    // Set timeout.
    if (array_key_exists("timeout", $configuration)) {
      if ((!is_int($configuration['timeout']) || $configuration['timeout'] <= 0) && !($configuration['timeout'] === false)){
        throw new InvalidConfigurationException("timeout has to be an int > 0 or false");
      }
      $this->timeout = $configuration["timeout"];
    }
    if($this->timeout !== false){
      $message = 'The "timeout" configuration is deprecated and will be removed with the next major update.' .
        ' Set the "timeout" configuration to false and if needed use the set_timeout_limit() function in your own code.';
      trigger_error($message, E_USER_DEPRECATED);
      set_time_limit($this->timeout);
    }

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
    $id = $this->increaseCounterAndGetNextId();
    // Add the system ID with the store data array.
    $storeData[$primaryKey] = $id;
    // Prepare storable data
    $storableJSON = @json_encode($storeData);
    if ($storableJSON === false) {
      throw new JsonException('Unable to encode the data array, 
        please provide a valid PHP associative array');
    }
    // Define the store path
    $filePath = $this->getDataPath()."$id.json";

    IoHelper::writeContentToFile($filePath, $storableJSON);

    return $storeData;
  }

  /**
   * Increments the store wide unique store object ID and returns it.
   * @return int
   * @throws IOException
   * @throws JsonException
   */
  private function increaseCounterAndGetNextId(): int
  {
    $counterPath = $this->getStorePath() . '_cnt.sdb';

    if (!file_exists($counterPath)) {
      throw new IOException("File $counterPath does not exist.");
    }

    $dataPath = $this->getDataPath();

    return (int) IoHelper::updateFileContent($counterPath, function ($counter) use ($dataPath){
      $newCounter = ((int) $counter) + 1;

      while(file_exists($dataPath."$newCounter.json") === true){
        $newCounter++;
      }
      return (string)$newCounter;
    });
  }


  /**
   * @param string|int $id
   * @return int
   * @throws InvalidArgumentException
   */
  private function checkAndStripId($id): int
  {
    if(!is_string($id) && !is_int($id)){
      throw new InvalidArgumentException("The id of the document has to be an integer or string");
    }

    if(is_string($id)){
      $id = IoHelper::secureStringForFileAccess($id);
    }

    if(!is_numeric($id)){
      throw new InvalidArgumentException("The id of the document has to be numeric");
    }

    return (int) $id;
  }

  /**
   * @return string
   */
  private function getDataPath(): string
  {
    return $this->getStorePath() . self::dataDirectory;
  }

}
