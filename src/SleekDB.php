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

if(false === class_exists("\Composer\Autoload\ClassLoader")){
    require_once __DIR__.'/Store.php';
}

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
    $this->setStore(new Store($storeName, $dataDir, $conf));
    $this->setQueryBuilder($this->getStore()->createQueryBuilder());
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
    return $this->getQuery()->fetch();
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
    return $this->getQuery()->exists();
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
    return $this->getQuery()->first();
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
    return $this->getStore()->insert($storeData);
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
    return $this->getStore()->insertMany($storeData);
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
    return $this->getQuery()->update($updatable);
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
    return $this->getQuery()->delete($returnRecordsCount);
  }

  /**
   * Deletes a store and wipes all the data and cache it contains.
   * @return bool
   * @throws IOException
   */
  public function deleteStore(): bool
  {
    return $this->getStore()->delete();
  }

  /**
   * This method would make a unique token for the current query.
   * We would use this hash token as the id/name of the cache file.
   * @return string
   */
  public function getCacheToken(): string
  {
    return $this->getQueryBuilder()->getCacheToken();
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
    $this->setQueryBuilder($this->getQueryBuilder()->setDataDirectory($directory));
    return $this;
  }

  /**
   * @return string
   */
  public function getDataDirectory(): string
  {
    return $this->getQueryBuilder()->getDataDirectory();
  }

  /**
   * Select specific fields
   * @param string[] $fieldNames
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function select(array $fieldNames): SleekDB
  {
    $this->setQueryBuilder($this->getQueryBuilder()->select($fieldNames));
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
    $this->setQueryBuilder($this->getQueryBuilder()->except($fieldNames));
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
    $this->setQueryBuilder($this->getQueryBuilder()->where($fieldName, $condition, $value));
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
    $this->setQueryBuilder($this->getQueryBuilder()->in($fieldName, $values));
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
    $this->setQueryBuilder($this->getQueryBuilder()->notIn($fieldName, $values));
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
    $this->setQueryBuilder($this->getQueryBuilder()->orWhere(...$conditions));
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
    $this->setQueryBuilder($this->getQueryBuilder()->skip($skip));
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
    $this->setQueryBuilder($this->getQueryBuilder()->limit($limit));
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
    $this->setQueryBuilder($this->getQueryBuilder()->orderBy($order, $orderBy));
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
    $this->setQueryBuilder($this->getQueryBuilder()->search($field, $keyword));
    return $this;
  }

  /**
   * @param callable $joinedStore
   * @param string $dataPropertyName
   * @return SleekDB
   */
  public function join(callable $joinedStore, string $dataPropertyName): SleekDB
  {
    $this->setQueryBuilder($this->getQueryBuilder()->join($joinedStore, $dataPropertyName));
    return $this;
  }

  /**
   * Re-generate the cache for the query.
   * @return SleekDB
   */
  public function makeCache(): SleekDB
  {
    $this->setQueryBuilder($this->getQueryBuilder()->regenerateCache());
    return $this;
  }

  /**
   * Disable cache for the query.
   * @return SleekDB
   */
  public function disableCache(): SleekDB
  {
    $this->setQueryBuilder($this->getQueryBuilder()->disableCache());
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
    $this->setQueryBuilder($this->getQueryBuilder()->useCache($lifetime));
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
    $this->getQueryBuilder()->getQuery()->getCache()->delete();
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
    $this->getQueryBuilder()->getQuery()->getCache()->deleteAll();
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
    $this->setQueryBuilder($this->getQueryBuilder()->distinct($fields));
    return $this;
  }

  /**
   * @return QueryBuilder
   */
  public function getQueryBuilder(): QueryBuilder
  {
    return $this->queryBuilder;
  }

  /**
   * @param QueryBuilder $queryBuilder
   */
  private function setQueryBuilder(QueryBuilder $queryBuilder){
      $this->queryBuilder = $queryBuilder;
  }

  /**
   * @return Query
   * @throws InvalidStoreBootUpException
   */
  public function getQuery(): Query
  {
    $query = $this->getQueryBuilder()->getQuery();
    $this->resetQueryBuilder();
    return $query;
  }

  /**
   * @return Cache
   * @throws InvalidStoreBootUpException
   */
  public function getCache(): Cache
  {
    // we do not want to reset the QueryBuilder
    return $this->getQueryBuilder()->getQuery()->getCache();
  }


  /**
   * @param Store $store
   */
  private function setStore(Store $store){
      $this->store = $store;
  }

  /**
   * @return Store
   */
  public function getStore(): Store
  {
    return $this->store;
  }

  /**
   * Handle shouldKeepConditions and reset queryBuilder accordingly
   */
  private function resetQueryBuilder(){
    if($this->shouldKeepConditions === true) return;
    $this->setQueryBuilder($this->getStore()->createQueryBuilder());
  }
}
