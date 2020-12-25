<?php

namespace SleekDB;

use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\InvalidDataException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\IndexNotFoundException;
use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\Exceptions\InvalidPropertyAccessException;
use SleekDB\Exceptions\InvalidStoreBootUpException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;

// To provide usage without composer, we need to require all files.
// Usage without composer is deprecated since 1.6.
foreach (glob(__DIR__ . '/Exceptions/*.php') as $exception){
  require_once $exception;
}
foreach (glob(__DIR__ . '/*.php') as $class){
  if(strpos($class, 'SleekDB.php') !== false) continue;
  require_once $class;
}

/**
 * @deprecated since version 1.6, use SleekDB\Store instead.
 */
class SleekDB
{

  /**
   * @var QueryBuilder
   */
  protected $queryBuilder;

  /**
   * @var Store
   */
  protected $store;

  private $shouldKeepConditions = false;

  /**
   * SleekDB constructor.
   * @param string $storeName
   * @param string $dataDir
   * @param array $configuration
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  function __construct(string $storeName, string $dataDir = "", array $configuration = []){
    $this->init($storeName, $dataDir, $configuration);
  }

  /**
   * Initialize the SleekDB instance.
   * @param string $storeName
   * @param string $dataDir
   * @param array $conf
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  public function init(string $storeName, string $dataDir = "", array $conf = []){
    $this->store = new Store($storeName, $dataDir, $conf);
    $this->queryBuilder = $this->store->getQueryBuilder();
  }

  /**
   * Initialize the store.
   * @param string $storeName
   * @param string $dataDir
   * @param array $configuration
   * @return SleekDB
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  public static function store(string $storeName, string $dataDir = "", array $configuration = []): SleekDB
  {
    return new SleekDB($storeName, $dataDir, $configuration);
  }

  /**
   * Execute Query and get Results
   * @return array
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws IndexNotFoundException
   * @throws InvalidDataException
   * @throws InvalidPropertyAccessException
   * @throws InvalidStoreBootUpException
   * @throws InvalidConfigurationException
   */
  public function fetch(): array
  {
    $results = $this->queryBuilder->getQuery()->fetch();
    $this->resetQueryBuilder();
    return $results;
  }

  /**
   * Check if data is found
   * @return bool
   * @throws InvalidArgumentException
   * @throws IndexNotFoundException
   * @throws InvalidPropertyAccessException
   * @throws InvalidStoreBootUpException
   * @throws IOException
   */
  public function exists(): bool
  {
    $results = $this->queryBuilder->getQuery()->exists();
    $this->resetQueryBuilder();
    return $results;
  }

  /**
   * Return the first document.
   * @return array
   * @throws InvalidArgumentException
   * @throws IndexNotFoundException
   * @throws InvalidDataException
   * @throws InvalidPropertyAccessException
   * @throws InvalidStoreBootUpException
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  public function first(): array
  {
    $results = $this->queryBuilder->getQuery()->first();
    $this->resetQueryBuilder();
    return $results;
  }

  /**
   * Creates a new object in the store.
   * It is stored as a plaintext JSON document.
   * @param array $storeData
   * @return array
   * @throws IOException
   * @throws IdNotAllowedException
   * @throws InvalidStoreBootUpException
   * @throws InvalidDataException
   * @throws JsonException
   */
  public function insert(array $storeData): array
  {
    return $this->store->insert($storeData);
  }

  /**
   * Creates multiple objects in the store.
   * @param array $storeData
   * @return array
   * @throws IOException
   * @throws IdNotAllowedException
   * @throws InvalidStoreBootUpException
   * @throws InvalidDataException
   * @throws JsonException
   */
  public function insertMany(array $storeData): array
  {
    return $this->store->insertMany($storeData);
  }

  /**
   * Update one or multiple documents, based on current query
   * @param array $updatable
   * @return bool
   * @throws InvalidArgumentException
   * @throws IOException
   * @throws IndexNotFoundException
   * @throws InvalidPropertyAccessException
   * @throws InvalidStoreBootUpException
   */
  public function update(array $updatable): bool
  {
    $results = $this->queryBuilder->getQuery()->update($updatable);
    $this->resetQueryBuilder();
    return $results;
  }

  /**
   * Deletes matched store objects.
   * @param bool $returnRecordsCount
   * @return bool|int
   * @throws InvalidArgumentException
   * @throws InvalidPropertyAccessException
   * @throws InvalidStoreBootUpException
   * @throws IOException
   * @throws IndexNotFoundException
   */
  public function delete(bool $returnRecordsCount = false){
    $results = $this->queryBuilder->getQuery()->delete($returnRecordsCount);
    $this->resetQueryBuilder();
    return $results;
  }

  /**
   * Deletes a store and wipes all the data and cache it contains.
   * @return bool
   * @throws IOException
   */
  public function deleteStore(): bool
  {
    return $this->store->delete();
  }

  /**
   * This method would make a unique token for the current query.
   * We would use this hash token as the id/name of the cache file.
   * @return string
   */
  public function getCacheToken(): string
  {
    return $this->queryBuilder->getCacheToken();
  }

  /**
   * Set DataDirectory for current query.
   * @param string $directory
   * @return SleekDB
   * @throws IOException
   * @throws InvalidConfigurationException
   */
  public function setDataDirectory(string $directory): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->setDataDirectory($directory);
    return $this;
  }

  /**
   * @return string
   */
  public function getDataDirectory(): string
  {
    return $this->queryBuilder->getDataDirectory();
  }

  /**
   * Select specific fields
   * @param string[] $fieldNames
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function select(array $fieldNames): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->select($fieldNames);
    return $this;
  }

  /**
   * Exclude specific fields
   * @param string[] $fieldNames
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function except(array $fieldNames): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->except($fieldNames);
    return $this;
  }

  /**
   * Add conditions to filter data.
   * @param string $fieldName
   * @param string $condition
   * @param mixed $value
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function where(string $fieldName, string $condition, $value): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->where($fieldName, $condition, $value);
    return $this;
  }

  /**
   * Add "in" condition to filter data.
   * @param string $fieldName
   * @param array $values
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function in(string $fieldName, array $values = []): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->in($fieldName, $values);
    return $this;
  }

  /**
   * Add "not in" condition to filter data.
   * @param string $fieldName
   * @param array $values
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function notIn(string $fieldName, array $values = []): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->notIn($fieldName, $values);
    return $this;
  }

  /**
   * Add or-where conditions to filter data.
   * @param string|array|mixed ...$conditions (string fieldName, string condition, mixed value) OR ([string fieldName, string condition, mixed value],...)
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function orWhere(...$conditions): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->orWhere(...$conditions);
    return $this;
  }

  /**
   * Set the amount of data record to skip.
   * @param int $skip
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function skip(int $skip = 0): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->skip($skip);
    return $this;
  }

  /**
   * Set the amount of data record to limit.
   * @param int $limit
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function limit(int $limit = 0): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->limit($limit);
    return $this;
  }

  /**
   * Set the sort order.
   * @param string $order "asc" or "desc"
   * @param string $orderBy
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function orderBy(string $order, string $orderBy = '_id'): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->orderBy($order, $orderBy);
    return $this;
  }

  /**
   * Do a fulltext like search against more than one field.
   * @param string|array $field one fieldName or multiple fieldNames as an array
   * @param string $keyword
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function search($field, string $keyword): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->search($field, $keyword);
    return $this;
  }

  /**
   * @param callable $joinedStore
   * @param string $dataPropertyName
   * @return SleekDB
   */
  public function join(callable $joinedStore, string $dataPropertyName): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->join($joinedStore, $dataPropertyName);
    return $this;
  }

  /**
   * Re-generate the cache for the query.
   * @return SleekDB
   */
  public function makeCache(): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->regenerateCache();
    return $this;
  }

  /**
   * Disable cache for the query.
   * @return SleekDB
   */
  public function disableCache(): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->disableCache();
    return $this;
  }

  /**
   * Use caching for current query
   * @param int|null $lifetime time to live as int in seconds or null to regenerate cache on every insert, update and delete
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function useCache(int $lifetime = null): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->useCache($lifetime);
    return $this;
  }

  /**
   * Delete cache file/s for current query.
   * @return SleekDB
   * @throws IOException
   * @throws InvalidStoreBootUpException
   */
  public function deleteCache(): SleekDB
  {
    $this->queryBuilder->getQuery()->getCache()->delete();
    return $this;
  }

  /**
   * Delete all cache files for current store.
   * @return SleekDB
   * @throws IOException
   * @throws InvalidStoreBootUpException
   */
  public function deleteAllCache(): SleekDB
  {
    $this->queryBuilder->getQuery()->getCache()->deleteAll();
    return $this;
  }

  /**
   * Keep the active query conditions.
   * @return SleekDB
   */
  public function keepConditions(): SleekDB
  {
    $this->shouldKeepConditions = true;
    return $this;
  }

  /**
   * Return distinct values.
   * @param array|string $fields
   * @return SleekDB
   * @throws InvalidDataException
   */
  public function distinct($fields = []): SleekDB
  {
    $this->queryBuilder = $this->queryBuilder->distinct($fields);
    return $this;
  }

  /**
   * @return Query
   * @throws InvalidStoreBootUpException
   */
  public function getQuery(): Query
  {
    $query = $this->queryBuilder->getQuery();
    $this->resetQueryBuilder();
    return $query;
  }

  /**
   * Handle shouldKeepConditions and reset queryBuilder accordingly
   */
  private function resetQueryBuilder(){
    if($this->shouldKeepConditions === true) return;
    $this->queryBuilder = $this->store->getQueryBuilder();
  }
}
