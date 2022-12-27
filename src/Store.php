<?php

namespace SleekDB;

use \SleekDB\Classes\Engine;
use SleekDB\Classes\IoHelper;
use SleekDB\Classes\MonoEngine;
use SleekDB\Classes\PolyEngine;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\InvalidConfigurationException;

// To provide usage without composer, we need to require all files.
if (false === class_exists("\Composer\Autoload\ClassLoader")) {
  foreach (glob(__DIR__ . '/Exceptions/*.php') as $exception) {
    require_once $exception;
  }
  foreach (glob(__DIR__ . '/Classes/*.php') as $traits) {
    require_once $traits;
  }
  foreach (glob(__DIR__ . '/*.php') as $class) {
    if (strpos($class, 'Store.php') !== false) {
      continue;
    }
    require_once $class;
  }
}

class Store
{
  protected $databasePath = "";
  protected $useCache = true;
  protected $engineName = Engine::POLY;
  protected $engine = null;
  protected $defaultCacheLifetime;
  protected $searchOptions = [
    "minLength" => 2,
    "scoreKey" => "searchScore",
    "mode" => "or",
    "algorithm" => Query::SEARCH_ALGORITHM["hits"]
  ];

  /**
   * Store constructor.
   * 
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

    $databasePath = trim($databasePath);
    if (empty($databasePath)) {
      throw new InvalidArgumentException('Store root directory (Database Path) can not be empty');
    }

    IoHelper::normalizeDirectory($databasePath);

    $this->setConfiguration($configuration);

    // Engine should be based on config later.
    $primaryKey = array_key_exists("primary_key", $configuration) ? $configuration["primary_key"] : null;
    $folderPermissions = array_key_exists("folder_permissions", $configuration) ? $configuration["folder_permissions"] : 0777;
    $this->engine = new MonoEngine($storeName, $databasePath, $primaryKey, $folderPermissions);
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
    if (empty($databasePath)) {
      $databasePath = $this->getEngine()->getDatabasePath();
    }
    $this->__construct($storeName, $databasePath, $configuration);
    return $this;
  }

  /**
   * @return MonoEngine | PolyEngine
   */
  public function getEngine(): MonoEngine | PolyEngine
  {
    return $this->engine;
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
    return $this->getEngine()->deleteStore();
  }


  /**
   * Return the last created store object ID.
   * @return int
   * @throws IOException
   */
  public function getLastInsertedId(): int
  {
    return $this->getEngine()->getLastInsertedId();
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
    if (!is_null($orderBy)) {
      $qb->orderBy($orderBy);
    }
    if (!is_null($limit)) {
      $qb->limit($limit);
    }
    if (!is_null($offset)) {
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
  public function findById($id)
  {
    return $this->getEngine()->findById($id);
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

    if ($orderBy !== null) {
      $qb->orderBy($orderBy);
    }
    if ($limit !== null) {
      $qb->limit($limit);
    }
    if ($offset !== null) {
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

    return (!empty($result)) ? $result : null;
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
    return $this->getEngine()->updateOrInsert($data, $autoGenerateIdOnInsert, $this->createQueryBuilder());
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
    return $this->getEngine()->updateOrInsertMany($data, $autoGenerateIdOnInsert, $this->createQueryBuilder());
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
    return $this->getEngine()->update($updatable, $this->createQueryBuilder());
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
    return $this->getEngine()->updateById($id, $updatable, $this->createQueryBuilder());
  }

  /**
   * Delete one or multiple documents.
   * @param array $criteria
   * @param int $returnOption
   * @return array|bool|int
   * @throws IOException
   * @throws InvalidArgumentException
   */
  public function deleteBy(array $criteria, int $returnOption = Query::DELETE_RETURN_BOOL)
  {
    return $this->getEngine()->deleteBy($criteria, $returnOption, $this->createQueryBuilder());
  }

  /**
   * Delete one document by its primary key. Very fast because it deletes the document by its file path.
   * @param int|string $id
   * @return bool true if document does not exist or deletion was successful, false otherwise
   * @throws InvalidArgumentException
   */
  public function deleteById($id): bool
  {
    return $this->getEngine()->deleteById($id, $this->createQueryBuilder());
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
    return $this->getEngine()->removeFieldsById($id, $fieldsToRemove, $this->createQueryBuilder());
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

    if ($orderBy !== null) {
      $qb->orderBy($orderBy);
    }

    if ($limit !== null) {
      $qb->limit($limit);
    }

    if ($offset !== null) {
      $qb->skip($offset);
    }

    return $qb->getQuery()->fetch();
  }

  /**
   * Returns the amount of documents in the store.
   * @return int
   * @throws IOException
   */
  public function count(): int
  {
    return $this->getEngine()->count($this->useCache);
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
   * @param array $configuration
   * @throws InvalidConfigurationException
   */
  private function setConfiguration(array $configuration)
  {
    if (array_key_exists("auto_cache", $configuration)) {
      $autoCache = $configuration["auto_cache"];
      if (!is_bool($configuration["auto_cache"])) {
        throw new InvalidConfigurationException("auto_cache has to be boolean");
      }

      $this->useCache = $autoCache;
    }

    if (array_key_exists("cache_lifetime", $configuration)) {
      $defaultCacheLifetime = $configuration["cache_lifetime"];
      if (!is_int($defaultCacheLifetime) && !is_null($defaultCacheLifetime)) {
        throw new InvalidConfigurationException("cache_lifetime has to be null or int");
      }

      $this->defaultCacheLifetime = $defaultCacheLifetime;
    }

    if (array_key_exists("search", $configuration)) {
      $searchConfig = $configuration["search"];

      if (array_key_exists("min_length", $searchConfig)) {
        $searchMinLength = $searchConfig["min_length"];
        if (!is_int($searchMinLength) || $searchMinLength <= 0) {
          throw new InvalidConfigurationException("min length for searching has to be an int >= 0");
        }
        $this->searchOptions["minLength"] = $searchMinLength;
      }

      if (array_key_exists("mode", $searchConfig)) {
        $searchMode = $searchConfig["mode"];
        if (!is_string($searchMode) || !in_array(strtolower(trim($searchMode)), ["and", "or"])) {
          throw new InvalidConfigurationException("search mode can just be \"and\" or \"or\"");
        }
        $this->searchOptions["mode"] = strtolower(trim($searchMode));
      }

      if (array_key_exists("score_key", $searchConfig)) {
        $searchScoreKey = $searchConfig["score_key"];
        if ((!is_string($searchScoreKey) && !is_null($searchScoreKey))) {
          throw new InvalidConfigurationException("search score key for search has to be a not empty string or null");
        }
        $this->searchOptions["scoreKey"] = $searchScoreKey;
      }

      if (array_key_exists("algorithm", $searchConfig)) {
        $searchAlgorithm = $searchConfig["algorithm"];
        if (!in_array($searchAlgorithm, Query::SEARCH_ALGORITHM, true)) {
          $searchAlgorithm = implode(', ', $searchAlgorithm);
          throw new InvalidConfigurationException("The search algorithm has to be one of the following integer values ($searchAlgorithm)");
        }
        $this->searchOptions["algorithm"] = $searchAlgorithm;
      }
    }

    if (array_key_exists("engine", $configuration)) {
      $engine = $configuration["engine"];
      if (!is_string($engine)) {
        throw new InvalidConfigurationException("Engine has to be a string");
      }
      $this->engineName = $engine;
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
    return $this->getEngine()->newDocument($storeData);
  }
}
