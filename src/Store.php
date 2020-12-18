<?php

namespace SleekDB;

use SleekDB\Exceptions\InvalidDataException;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\Exceptions\InvalidStoreBootUpException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Store
{

  protected $root = __DIR__;

  protected $storeName = "";

  protected $storePath = "";

  protected $dataDirectory = "";

  protected $useCache = true;

  protected $defaultCacheLifetime = null;

  /**
   * Store constructor.
   * @param string $storeName
   * @param string $dataDir
   * @param array $configuration
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  function __construct(string $storeName, string $dataDir = "", array $configuration = [])
  {
    $storeName = trim($storeName);
    if (empty($storeName)) throw new InvalidArgumentException('Invalid store name was found');
    $this->storeName = $storeName;

    if (!is_array($configuration)) throw new InvalidConfigurationException('Invalid configurations was found.');
    $this->setConfiguration($configuration);

    $this->setDataDirectory($dataDir);
  }

  /**
   * @return string
   */
  public function getStoreName(): string
  {
    return $this->storeName;
  }

  /**
   * @param string $directory
   * @return Store
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  public function setDataDirectory(string $directory): Store
  {
    // Prepare the data directory.
    $dataDir = trim($directory);
    if(empty($dataDir)) return $this;

    // Handle directory path ending.
    if (substr($dataDir, -1) !== '/') $dataDir = $dataDir . '/';

    // Set the data directory.
    $this->dataDirectory = $dataDir;

    // boot store
    $this->createDataDirectory();
    $this->createStore();
    return $this;
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
      if(!is_null($defaultCacheLifetime) || !is_int($defaultCacheLifetime)){
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
  }

  /**
   * @return QueryBuilder
   */
  public function getQueryBuilder(): QueryBuilder
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
   * @throws InvalidDataException
   * @throws JsonException
   * @throws InvalidStoreBootUpException
   */
  public function insert(array $data): array
  {
    // Handle invalid data
    if (empty($data)) throw new InvalidDataException('No data found to insert in the store');
    // Make sure that the data is an array
    if (!is_array($data)) throw new InvalidDataException('Storable data must an array');
    $data = $this->writeInStore($data);
    // Check do we need to wipe the cache for this store.
    if($this->getUseCache() === true){
      $queryBuilder = $this->getQueryBuilder();
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
   * @throws InvalidDataException
   * @throws JsonException
   * @throws InvalidStoreBootUpException
   */
  public function insertMany(array $data): array
  {
    // Handle invalid data
    if (empty($data)) throw new InvalidDataException('No data found to insert in the store');
    // Make sure that the data is an array
    if (!is_array($data)) throw new InvalidDataException('Data must be an array in order to insert in the store');
    // All results.
    $results = [];
    foreach ($data as $key => $node) {
      $results[] = $this->writeInStore($node);
    }
    // Check do we need to wipe the cache for this store.
    if($this->getUseCache() === true){
      $queryBuilder = $this->getQueryBuilder();
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
    // Cast to array
    $storeData = (array) $storeData;
    // Check if it has _id key
    if (isset($storeData['_id'])) {
      throw new IdNotAllowedException(
        'The _id index is reserved by SleekDB, please delete the _id key and try again'
      );
    }
    $id = $this->getStoreId();
    // Add the system ID with the store data array.
    $storeData['_id'] = $id;
    // Prepare storable data
    $storableJSON = json_encode($storeData);
    if ($storableJSON === false) throw new JsonException('Unable to encode the data array, 
        please provide a valid PHP associative array');
    // Define the store path
    $dataPath = $this->getStorePath() . 'data/';

    $this->_checkWrite($dataPath);

    $storePath = $dataPath . $id . '.json';
    if (!file_put_contents($storePath, $storableJSON)) {
      throw new IOException("Unable to write the object file! Please check if PHP has write permission.");
    }
    return $storeData;
  }

  /**
   * Deletes a store and wipes all the data and cache it contains.
   * @return bool
   * @throws IOException
   */
  public function delete(): bool
  {
    $storePath = $this->getStorePath();
    $this->_checkWrite($storePath);
    $it = new RecursiveDirectoryIterator($storePath, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      $this->_checkWrite($file);
      if ($file->isDir()) rmdir($file->getRealPath());
      else unlink($file->getRealPath());
    }
    return rmdir($storePath);
  }

  /**
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  private function createDataDirectory()
  {
    // Check if data_directory is empty.
    if (empty($this->dataDirectory)) {
      throw new InvalidConfigurationException(
        '"data_directory" can not be empty.'
      );
    }
    // Check if the data_directory exists.
    if (!file_exists($this->dataDirectory)) {
      // The directory was not found, create one.
      if (!mkdir($this->dataDirectory, 0777, true)) {
        throw new IOException(
          'Unable to create the data directory at ' . $this->dataDirectory
        );
      }
    }
  }

  /**
   * @throws IOException
   */
  private function createStore()
  {
    $storeName = $this->storeName;
    // Prepare store name.
    if (substr($storeName, -1) !== '/') $storeName = $storeName . '/';
    // Store directory path.
    $this->storePath = $this->dataDirectory . $storeName;
    $storePath = $this->getStorePath();
    // Check if the store exists.
    if (!file_exists($storePath)) {
      // The directory was not found, create one with cache directory.
      if (!mkdir($storePath, 0777, true)) {
        throw new IOException("Unable to create the store path at \"$storePath\"");
      }
      // Create the cache directory.
      $cacheDirectory = $storePath . 'cache';
      if (!mkdir($cacheDirectory, 0777, true)) {
        throw new IOException("Unable to create the store's cache directory at \"$storePath\"");
      }
      // Create the data directory.
      $dataDirectory = $storePath . 'data';
      if (!mkdir($dataDirectory, 0777, true)) {
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
  public function getUseCache(): bool
  {
    return $this->useCache;
  }

  /**
   * @return null|int
   */
  public function getDefaultCacheLifetime()
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
      $fp = fopen($counterPath, 'r+');
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
      $this->_checkRead($counterPath);
      return (int) file_get_contents($counterPath);
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
   * @throws InvalidStoreBootUpException
   */
  public function _checkBootUp(){
    if( empty($this->getStorePath()) || empty($this->getDataDirectory()) ){
      throw new InvalidStoreBootUpException(
        "Store is not booted up properly. Please set a data directory. Invalid StorePath: \"{$this->getStorePath()}\""
      );
    }
  }

}