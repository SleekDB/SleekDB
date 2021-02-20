<?php

namespace SleekDB;

use SleekDB\Classes\CacheHandler;
use SleekDB\Classes\DocumentFinder;
use SleekDB\Classes\DocumentUpdater;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;

class Query
{

  const DELETE_RETURN_BOOL = 1;
  const DELETE_RETURN_RESULTS = 2;
  const DELETE_RETURN_COUNT = 3;

  const SEARCH_ALGORITHM = [
    "hits" => 1,
    "hits_prioritize" => 2,
    "prioritize" => 3,
    "prioritize_position" => 4,
  ];

  /**
   * @var CacheHandler|null
   */
  protected $cacheHandler;

  protected $storePath;
  protected $queryBuilderProperties;
  protected $primaryKey;

  /**
   * Query constructor.
   * @param QueryBuilder $queryBuilder
   */
  public function __construct(QueryBuilder $queryBuilder)
  {
    $store = $queryBuilder->_getStore();

    $this->storePath = $store->getStorePath();
    $this->primaryKey = $store->getPrimaryKey();
    $this->queryBuilderProperties = $queryBuilder->_getConditionProperties();

    $this->cacheHandler = new CacheHandler($store->getStorePath(), $queryBuilder);
  }


  /**
   * Execute Query and get Results
   * @return array
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function fetch(): array
  {
    return $this->getResults();
  }

  /**
   * Check if data is found
   * @return bool
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function exists(): bool
  {
    // Return boolean on data exists check.
    return !empty($this->first());
  }

  /**
   * Return the first document.
   * @return array empty array or single document
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function first(): array
  {
    return $this->getResults(true);
  }

  /**
   * Update one or multiple documents, based on current query
   * @param array $updatable
   * @param bool $returnUpdatedDocuments
   * @return array|bool
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function update(array $updatable, bool $returnUpdatedDocuments = false){

    $results = DocumentFinder::findStoreDocuments(
      false,
      false,
      $this->queryBuilderProperties,
      $this->getDataPath(),
      $this->primaryKey
    );

    $this->getCacheHandler()->deleteAllWithNoLifetime();

    return DocumentUpdater::updateResults(
      $results,
      $updatable,
      $returnUpdatedDocuments,
      $this->primaryKey,
      $this->getDataPath()
    );
  }

  /**
   * Deletes matched store objects.
   * @param int $returnOption
   * @return bool|array|int
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function delete(int $returnOption = self::DELETE_RETURN_BOOL){
    $results = DocumentFinder::findStoreDocuments(
      false,
      false,
      $this->queryBuilderProperties,
      $this->getDataPath(),
      $this->primaryKey
    );

    $this->getCacheHandler()->deleteAllWithNoLifetime();

    return DocumentUpdater::deleteResults($results, $returnOption, $this->primaryKey, $this->getDataPath());
  }

  /**
   * @param bool $getOneDocument
   * @param bool $reduceAndJoinPossible
   * @return array
   * @throws IOException
   * @throws InvalidArgumentException
   */
  private function getResults(bool $getOneDocument = false, bool $reduceAndJoinPossible = true): array
  {

    $results = $this->getCacheHandler()->getCacheContent($getOneDocument);

    if($results !== null) {
      return $results;
    }

    $results = DocumentFinder::findStoreDocuments(
      $getOneDocument,
      $reduceAndJoinPossible,
      $this->queryBuilderProperties,
      $this->getDataPath(),
      $this->primaryKey
    );

    if ($getOneDocument === true && count($results) > 0) {
      list($item) = $results;
      $results = $item;
    }

    $this->getCacheHandler()->setCacheContent($results);

    return $results;
  }

  /**
   * @return Cache
   */
  public function getCache(): Cache
  {
    return $this->getCacheHandler()->getCache();
  }

  /**
   * @return string
   */
  private function getDataPath(): string
  {
    return $this->storePath . "data/";
  }

  /**
   * @return CacheHandler
   */
  private function getCacheHandler(): CacheHandler
  {
    return $this->cacheHandler;
  }
}