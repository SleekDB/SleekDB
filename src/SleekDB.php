<?php

namespace SleekDB;

use Closure;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidConfigurationException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;

if(false === class_exists("\Composer\Autoload\ClassLoader")){
    require_once __DIR__.'/Store.php';
}

/**
 * Class SleekDB
 * @package SleekDB
 * @deprecated since version 2.0, use SleekDB\Store instead.
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
  public function __construct(string $storeName, string $dataDir, array $configuration = []){
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
  public function init(string $storeName, string $dataDir, array $conf = []){
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
  public static function store(string $storeName, string $dataDir, array $configuration = []): SleekDB
  {
    return new SleekDB($storeName, $dataDir, $configuration);
  }

  /**
   * Execute Query and get Results
   * @return array
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function fetch(): array
  {
    return $this->getQuery()->fetch();
  }

  /**
   * Check if data is found
   * @return bool
   * @throws InvalidArgumentException
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
   * @throws IOException
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
   * @throws InvalidArgumentException
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
   * @throws InvalidArgumentException
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
   */
  public function update(array $updatable): bool
  {
    return $this->getQuery()->update($updatable);
  }

  /**
   * Deletes matched store objects.
   * @param int $returnOption
   * @return bool|array|int
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function delete(int $returnOption = Query::DELETE_RETURN_BOOL){
    return $this->getQuery()->delete($returnOption);
  }

  /**
   * Deletes a store and wipes all the data and cache it contains.
   * @return bool
   * @throws IOException
   */
  public function deleteStore(): bool
  {
    return $this->getStore()->deleteStore();
  }

  /**
   * This method would make a unique token for the current query.
   * We would use this hash token as the id/name of the cache file.
   * @return string
   */
  public function getCacheToken(): string
  {
    return $this->getQueryBuilder()->getQuery()->getCache()->getToken();
  }

  /**
   * Select specific fields
   * @param string[] $fieldNames
   * @return SleekDB
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
   * @param string|array|mixed ...$conditions (string fieldName, string condition, mixed value) OR (array(array(string fieldName, string condition, mixed value)[, array(...)]))
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function where(...$conditions): SleekDB
  {
    foreach ($conditions as $key => $arg) {
      if ($key > 0) {
        throw new InvalidArgumentException("Allowed: (string fieldName, string condition, mixed value) OR (array(array(string fieldName, string condition, mixed value)[, array(...)]))");
      }
      if (is_array($arg)) {
        // parameters given as arrays for multiple "where" with "and" between each condition
        $this->setQueryBuilder($this->getQueryBuilder()->where($arg));
        break;
      }
      if (count($conditions) === 3) {
        // parameters given as (string fieldName, string condition, mixed value) for a single "where"
        $this->setQueryBuilder($this->getQueryBuilder()->where($conditions));
        break;
      }
    }

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
   * @param string|array|mixed ...$conditions (string fieldName, string condition, mixed value) OR array(array(string fieldName, string condition, mixed value) [, array(...)])
   * @return SleekDB
   * @throws InvalidArgumentException
   */
  public function orWhere(...$conditions): SleekDB
  {
    foreach ($conditions as $key => $arg) {
      if ($key > 0) {
        throw new InvalidArgumentException("Allowed: (string fieldName, string condition, mixed value) OR array(array(string fieldName, string condition, mixed value) [, array(...)])");
      }
      if (is_array($arg)) {
        // parameters given as arrays for an "or where" with "and" between each condition
        $this->setQueryBuilder($this->getQueryBuilder()->orWhere($arg));
        break;
      }
      if (count($conditions) === 3) {
        // parameters given as (string fieldName, string condition, mixed value) for a single "or where"
        $this->setQueryBuilder($this->getQueryBuilder()->orWhere($conditions));
        break;
      }
    }

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
    $this->setQueryBuilder($this->getQueryBuilder()->orderBy([$orderBy => $order]));
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
   * @param Closure $joinedStore
   * @param string $dataPropertyName
   * @return SleekDB
   */
  public function join(Closure $joinedStore, string $dataPropertyName): SleekDB
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
   */
  public function deleteCache(): SleekDB
  {
    $this->getQueryBuilder()->getQuery()->getCache()->delete();
    return $this;
  }

  /**
   * Delete all cache files for current store.
   * @return SleekDB
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
   * @throws InvalidArgumentException
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
   */
  public function getQuery(): Query
  {
    $query = $this->getQueryBuilder()->getQuery();
    $this->resetQueryBuilder();
    return $query;
  }

  /**
   * @return Cache
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
    if($this->shouldKeepConditions === true) {
      return;
    }
    $this->setQueryBuilder($this->getStore()->createQueryBuilder());
  }


  /**
   * Retrieve all documents.
   * @return array
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function findAll(): array
  {
    return $this->getStore()->findAll();
  }

  /**
   * Retrieve one document by its _id. Very fast because it finds the document by its file path.
   * @param int $id
   * @return array|null
   * @throws InvalidArgumentException
   */
  public function findById(int $id){
    return $this->getStore()->findById($id);
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
    return $this->getStore()->findBy($criteria, $orderBy, $limit, $offset);
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
    return $this->getStore()->findOneBy($criteria);
  }

  /**
   * Update one or multiple documents.
   * @param array $updatable true if all documents could be updated and false if one document did not exist
   * @return bool
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function updateBy(array $updatable): bool
  {
    return $this->getStore()->update($updatable);
  }

  /**
   * Delete one or multiple documents.
   * @param $criteria
   * @param int $returnOption
   * @return array|bool|int
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function deleteBy($criteria, $returnOption = Query::DELETE_RETURN_BOOL){
    return $this->getStore()->deleteBy($criteria, $returnOption);
  }

  /**
   * Delete one document by its _id. Very fast because it deletes the document by its file path.
   * @param $id
   * @return bool true if document does not exist or deletion was successful, false otherwise
   * @throws IOException
   */
  public function deleteById($id): bool
  {
    return $this->getStore()->deleteById($id);
  }

  /**
   * Add a where statement that is nested. ( $x or ($y and $z) )
   * @param array $conditions
   * @return $this
   * @throws InvalidArgumentException
   * @deprecated since version 2.3, use where and orWhere instead.
   */
  public function nestedWhere(array $conditions): SleekDB
  {
    $this->setQueryBuilder($this->getQueryBuilder()->nestedWhere($conditions));
    return $this;
  }

}
