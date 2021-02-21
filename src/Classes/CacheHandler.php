<?php


namespace SleekDB\Classes;


use SleekDB\Cache;
use SleekDB\Exceptions\IOException;
use SleekDB\QueryBuilder;

/**
 * Class CacheHandler
 * Bridge between Query and Cache
 */
class CacheHandler
{
  /**
   * @var Cache
   */
  protected $cache;

  protected $cacheTokenArray;
  protected $regenerateCache;
  protected $useCache;

  /**
   * CacheHandler constructor.
   * @param string $storePath
   * @param QueryBuilder $queryBuilder
   */
  public function __construct(string $storePath, QueryBuilder $queryBuilder)
  {
    $this->cacheTokenArray = $queryBuilder->_getCacheTokenArray();

    $queryBuilderProperties = $queryBuilder->_getConditionProperties();
    $this->useCache = $queryBuilderProperties["useCache"];
    $this->regenerateCache = $queryBuilderProperties["regenerateCache"];

    $this->cache = new Cache($storePath, $this->_getCacheTokenArray(), $queryBuilderProperties["cacheLifetime"]);
  }

  /**
   * @return Cache
   */
  public function getCache(): Cache
  {
    return $this->cache;
  }

  /**
   * Get results from cache
   * @return array|null
   * @throws IOException
   */
  public function getCacheContent($getOneDocument)
  {
    if($this->getUseCache() !== true){
      return null;
    }

    $this->updateCacheTokenArray(['oneDocument' => $getOneDocument]);

    if($this->regenerateCache === true) {
      $this->getCache()->delete();
    }

    $cacheResults = $this->getCache()->get();
    if(is_array($cacheResults)) {
      return $cacheResults;
    }

    return null;
  }

  /**
   * Add content to cache
   * @param array $results
   * @throws IOException
   */
  public function setCacheContent(array $results)
  {
    if($this->getUseCache() === true){
      $this->getCache()->set($results);
    }
  }

  /**
   * Delete all cache files that have no lifetime.
   * @return bool
   */
  public function deleteAllWithNoLifetime(): bool
  {
    return $this->getCache()->deleteAllWithNoLifetime();
  }

  /**
   * Returns a reference to the array used for cache token generation
   * @return array
   */
  public function &_getCacheTokenArray(): array
  {
    return $this->cacheTokenArray;
  }

  /**
   * @param array $tokenUpdate
   */
  private function updateCacheTokenArray(array $tokenUpdate)
  {
    if(empty($tokenUpdate)) {
      return;
    }
    $cacheTokenArray = $this->_getCacheTokenArray();
    foreach ($tokenUpdate as $key => $value){
      $cacheTokenArray[$key] = $value;
    }
    $this->cacheTokenArray = $cacheTokenArray;
  }

  /**
   * Status if cache is used or not
   * @return bool
   */
  private function getUseCache(): bool
  {
    return $this->useCache;
  }

}