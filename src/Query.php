<?php

namespace SleekDB;

use SleekDB\Classes\CacheHandler;
use SleekDB\Classes\DocumentFinder;
use SleekDB\Classes\DocumentUpdater;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;

/**
 * Class Query
 * Query execution object of SleekDB.
 */
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

  /**
   * @var DocumentFinder
   */
  protected $documentFinder;

  /**
   * @var DocumentUpdater
   */
  protected $documentUpdater;

  /**
   * Query constructor.
   * @param QueryBuilder $queryBuilder
   */
  public function __construct(QueryBuilder $queryBuilder)
  {
    $store = $queryBuilder->_getStore();
    $primaryKey = $store->getPrimaryKey();

    $this->cacheHandler = new CacheHandler($store->getStorePath(), $queryBuilder);
    $this->documentFinder = new DocumentFinder($store->getStorePath(), $queryBuilder->_getConditionProperties(), $primaryKey);
    $this->documentUpdater = new DocumentUpdater($store->getStorePath(), $primaryKey);
  }

  /**
   * Execute query and get results.
   * @return array
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function fetch(): array
  {
    return $this->getResults();
  }

  /**
   * Check if data is found.
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
   * Update parts of one or multiple documents based on current query.
   * @param array $updatable
   * @param bool $returnUpdatedDocuments
   * @return array|bool
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function update(array $updatable, bool $returnUpdatedDocuments = false){

    if(empty($updatable)){
      throw new InvalidArgumentException("You have to define what you want to update.");
    }

    $results = $this->documentFinder->findDocuments(false, false);

    $this->getCacheHandler()->deleteAllWithNoLifetime();

    return $this->documentUpdater->updateResults($results, $updatable, $returnUpdatedDocuments);
  }

  /**
   * Delete one or multiple documents based on current query.
   * @param int $returnOption
   * @return bool|array|int
   * @throws InvalidArgumentException
   * @throws IOException
   */
  public function delete(int $returnOption = self::DELETE_RETURN_BOOL){
    $results = $this->documentFinder->findDocuments(false, false);

    $this->getCacheHandler()->deleteAllWithNoLifetime();

    return $this->documentUpdater->deleteResults($results, $returnOption);
  }

  /**
   * Remove fields of one or multiple documents based on current query.
   * @param array $fieldsToRemove
   * @return array|false
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function removeFields(array $fieldsToRemove)
  {
    if(empty($fieldsToRemove)){
      throw new InvalidArgumentException("You have to define what fields you want to remove.");
    }
    $results = $this->documentFinder->findDocuments(false, false);

    $this->getCacheHandler()->deleteAllWithNoLifetime();

    return $this->documentUpdater->removeFields($results, $fieldsToRemove);
  }

  /**
   * Retrieve Cache object.
   * @return Cache
   */
  public function getCache(): Cache
  {
    return $this->getCacheHandler()->getCache();
  }

  /**
   * Retrieve the results from either the cache or store.
   * @param bool $getOneDocument
   * @return array
   * @throws IOException
   * @throws InvalidArgumentException
   */
  private function getResults(bool $getOneDocument = false): array
  {

    $results = $this->getCacheHandler()->getCacheContent($getOneDocument);

    if($results !== null) {
      return $results;
    }

    $results = $this->documentFinder->findDocuments($getOneDocument, true);

    if ($getOneDocument === true && count($results) > 0) {
      list($item) = $results;
      $results = $item;
    }

    $this->getCacheHandler()->setCacheContent($results);

    return $results;
  }

  /**
   * Retrieve the caching layer bridge.
   * @return CacheHandler
   */
  private function getCacheHandler(): CacheHandler
  {
    return $this->cacheHandler;
  }
}