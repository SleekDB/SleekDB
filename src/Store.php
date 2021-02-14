<?php

namespace SleekDB;

use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\Exceptions\InvalidPropertyAccessException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

// To provide usage without composer, we need to require all files.
if(false === class_exists("\Composer\Autoload\ClassLoader")) {
    foreach (glob(__DIR__ . '/Exceptions/*.php') as $exception) {
        require_once $exception;
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

  protected $dataDirectory = "";

  protected $useCache = true;
  protected $defaultCacheLifetime;
  protected $primaryKey = "_id";

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
   * @param $storeName
   * @param $dataDir
   * @param array $configuration
   * @throws IOException
   * @throws InvalidArgumentException
   * @throws InvalidConfigurationException
   */
  public function changeStore($storeName, $dataDir, $configuration = [])
  {
    $this->__construct($storeName, $dataDir, $configuration);
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
    $timeout = 120;
    if (array_key_exists("timeout", $configuration)) {
      if (!is_int($configuration['timeout']) || $configuration['timeout'] <= 0){
        throw new InvalidConfigurationException("timeout has to an int > 0");
      }
      $timeout = $configuration["timeout"];
    }
    set_time_limit($timeout);

    if(array_key_exists("primary_key", $configuration)){
      $primaryKey = $configuration["primary_key"];
      if(!is_string($primaryKey)){
        throw new InvalidConfigurationException("primary key has to be a string");
      }
      $this->primaryKey = $primaryKey;
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
    $data = $this->writeInStore($data);
    // Check do we need to wipe the cache for this store.
    if($this->_getUseCache() === true){
      $queryBuilder = $this->createQueryBuilder();
      $cache = $queryBuilder->getQuery()->getCache();
      $cache->deleteAllWithNoLifetime();
    }
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
    foreach ($data as $key => $node) {
      $results[] = $this->writeInStore($node);
    }
    // Check do we need to wipe the cache for this store.
    if($this->_getUseCache() === true){
      $queryBuilder = $this->createQueryBuilder();
      $cache = $queryBuilder->getQuery()->getCache();
      $cache->deleteAllWithNoLifetime();
    }
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
  private function writeInStore(array $storeData): array
  {
    $primaryKey = $this->primaryKey;
    // Check if it has the primary key
    if (isset($storeData[$primaryKey])) {
      throw new IdNotAllowedException(
        "The $primaryKey index is reserved by SleekDB, please delete the $primaryKey key and try again"
      );
    }
    $id = $this->getStoreId();
    // Add the system ID with the store data array.
    $storeData[$primaryKey] = $id;
    // Prepare storable data
    $storableJSON = json_encode($storeData);
    if ($storableJSON === false) {
      throw new JsonException('Unable to encode the data array, 
        please provide a valid PHP associative array');
    }
    // Define the store path
    $dataPath = $this->getStorePath() . 'data/';

    $this->_checkWrite($dataPath);

    $storePath = $dataPath . $id . '.json';
    if (!file_put_contents($storePath, $storableJSON)) {
      throw new IOException("Unable to write the object file! Please check if PHP has write permission. Location: \"$storePath\"");
    }
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
    $this->_checkWrite($storePath);
    $it = new RecursiveDirectoryIterator($storePath, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      $this->_checkWrite($file);
      if ($file->isDir()) {
        rmdir($file->getRealPath());
      } else {
        unlink($file->getRealPath());
      }
    }
    return rmdir($storePath);
  }

  /**
   * @throws IOException
   */
  private function createDataDirectory()
  {
    $dataDir = $this->getDataDirectory();
    // Check if the data_directory exists or create one.
    if (!file_exists($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
      throw new IOException(
        'Unable to create the data directory at ' . $this->getDataDirectory()
      );
    }
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
    // Check if the store exists.
    if (!file_exists($storePath)) {
      // The directory was not found, create one with cache directory.
      if (!mkdir($storePath, 0777, true) && !is_dir($storePath)) {
        throw new IOException("Unable to create the store path at \"$storePath\"");
      }
      // Create the cache directory.
      $cacheDirectory = $storePath . 'cache';
      if (!mkdir($cacheDirectory, 0777, true) && !is_dir($cacheDirectory)) {
        throw new IOException("Unable to create the store's cache directory at \"$storePath\"");
      }
      // Create the data directory.
      $dataDirectory = $storePath . 'data';
      if (!mkdir($dataDirectory, 0777, true) && !is_dir($dataDirectory)) {
        throw new IOException("Unable to create the store's data directory at \"$dataDirectory\"");
      }
      // Create the store counter file.
      $counterFile = $storePath . '_cnt.sdb';
      if (!file_put_contents($counterFile, '0')) {
        throw new IOException("Unable to create the system counter for the store at \"$counterFile\"");
      }
    }

  }

  /**
   * @param string $path
   * @throws IOException
   */
  private function _checkWrite(string $path)
  {
    // Check if PHP has write permission
    if (!is_writable($path)) {
      $storeName = $this->getStoreName();
      throw new IOException(
        "Directory or file of store \"$storeName\" is not writable at \"$path\". Please change permission."
      );
    }
  }

  /**
   * @param string $path
   * @throws IOException
   */
  private function _checkRead(string $path)
  {
    // Check if PHP has read permission
    if (!is_readable($path)) {
      $storeName = $this->getStoreName();
      throw new IOException(
        "Directory or file of store \"$storeName\" is not readable at \"$path\". Please change permission."
      );
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
   */
  private function getStoreId(): int
  {
    $counter = 1; // default (first) id
    $counterPath = $this->getStorePath() . '_cnt.sdb';
    if (file_exists($counterPath)) {
      $this->_checkRead($counterPath);
      $fp = fopen($counterPath, 'rb+');
      for ($retries = 10; $retries > 0; $retries--) {
        flock($fp, LOCK_UN);
        if (flock($fp, LOCK_EX) === false) {
          sleep(1);
        } else {
          $counter = (int) fgets($fp);
          $counter++;
          rewind($fp);
          fwrite($fp, (string) $counter);
          break;
        }
      }
      flock($fp, LOCK_UN);
      fclose($fp);
    }
    return $counter;
  }

  /**
   * Return the last created store object ID.
   * @return int
   * @throws IOException
   */
  public function getLastInsertedId(): int
  {
    $counterPath = $this->getStorePath() . '_cnt.sdb';
    if (file_exists($counterPath)) {
      $content = 0;
      $this->_checkRead($counterPath);

      $fp = fopen($counterPath, 'rb');
      if(flock($fp, LOCK_SH)){
        $content = stream_get_contents($fp);
      }
      flock($fp, LOCK_UN);
      fclose($fp);

      return (int) $content;
    }
    return 0;
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
   * @throws IOException
   */
  public function findById(int $id){

    $filePath = $this->getStorePath() . "data/$id.json";

    if(!file_exists($filePath)) {
      return null;
    }

    $this->_checkRead($filePath);

    // retrieve file content
    $content = false;
    $fp = fopen($filePath, 'rb');
    if(flock($fp, LOCK_SH)){
      $content = stream_get_contents($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    if($content === false) {
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
    $primaryKey = $this->primaryKey;

    if(empty($updatable)) {
      throw new InvalidArgumentException("No documents to update.");
    }

    $multipleDocuments = array_keys($updatable) === range(0, (count($updatable) - 1));

    // Check if all documents exist before updating any
    foreach ($updatable as $document){
      if($multipleDocuments === false){
        $document = $updatable;
      }

      if(!is_array($document)) {
        throw new InvalidArgumentException('Documents have to be arrays.');
      }
      if(!array_key_exists($primaryKey, $document)) {
        throw new InvalidArgumentException("Documents have to have \"$primaryKey\".");
      }
      $id = $document[$primaryKey];
      $storePath = $this->getStorePath() . "data/$id.json";

      if (!file_exists($storePath)) {
        return false;
      }

      if($multipleDocuments === false) {
        break;
      }
    }

    // One or multiple documents to update
    foreach ($updatable as $document)
    {
      if($multipleDocuments === false){
        $document = $updatable;
      }

      $id = $document[$primaryKey];
      $storePath = $this->getStorePath() . "data/$id.json";

      $this->_checkWrite($storePath);
      // Wait until it's unlocked, then update data.
      if(file_put_contents($storePath, json_encode($document), LOCK_EX) === false){
        throw new IOException("Could not update document with $primaryKey \"$id\". Please check permissions at: $storePath");
      }

      if($multipleDocuments === false) {
        break;
      }
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

    $primaryKey = $this->primaryKey;

    if(array_key_exists($primaryKey, $updatable)) {
      throw new InvalidArgumentException("You can not update the primary key \"$primaryKey\" of documents.");
    }

    if(!file_exists($filePath)){
      return false;
    }

    $this->_checkRead($filePath);

    // retrieve file content
    $content = false;
    $fp = fopen($filePath, 'rb');
    if(flock($fp, LOCK_SH)){
      $content = stream_get_contents($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    if($content === false) {
      throw new IOException("Could not get content of document to update with $primaryKey \"$id\". Please check permissions at: $filePath");
    }

    $content = @json_decode($content, true);

    if(!is_array($content)){
      throw new JsonException("Could not decode content of \"$filePath\" with json_decode.");
    }

    foreach ($updatable as $key => $value){
      $content[$key] = $value;
    }

    $this->_checkWrite($filePath);
    // Wait until it's unlocked, then update data.
    if(file_put_contents($filePath, json_encode($content), LOCK_EX) === false){
      throw new IOException("Could not update document with $primaryKey \"$id\". Please check permissions at: $filePath");
    }

    $this->createQueryBuilder()->getQuery()->getCache()->deleteAllWithNoLifetime();

    return $content;
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
   * @return string
   */
  public function getPrimaryKey(): string
  {
    return $this->primaryKey;
  }

}
