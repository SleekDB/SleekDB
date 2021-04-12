<?php

namespace SleekDB;

use Closure;
use Exception;
use ReflectionFunction;
use SleekDB\Classes\IoHelper;
use SleekDB\Exceptions\IOException;

/**
 * Class Cache
 * Caching layer of SleekDB, handles everything regarding caching.
 */
class Cache
{

  const DEFAULT_CACHE_DIR = "cache/";
  const NO_LIFETIME_FILE_STRING = "no_lifetime";

  /**
   * Lifetime in seconds or deletion with deleteAll
   * @var int|null
   */
  protected $lifetime;

  protected $cachePath = "";

  protected $cacheDir = "";

  protected $tokenArray;

  /**
   * Cache constructor.
   * @param string $storePath
   * @param array $cacheTokenArray
   * @param int|null $cacheLifetime
   */
  public function __construct(string $storePath, array &$cacheTokenArray, $cacheLifetime)
  {
    // TODO make it possible to define custom cache directory.
//    $cacheDir = "";
//    $this->setCacheDir($cacheDir);

    $this->setCachePath($storePath);

    $this->setTokenArray($cacheTokenArray);

    $this->lifetime = $cacheLifetime;
  }

  /**
   * Retrieve the cache lifetime for current query.
   * @return int|null lifetime in seconds (int) or no lifetime with null
   */
  public function getLifetime()
  {
    return $this->lifetime;
  }

  /**
   * Retrieve the path to cache folder of current store.
   * @return string path to cache directory
   */
  public function getCachePath(): string
  {
    return $this->cachePath;
  }

  /**
   * Retrieve the cache token used as filename to store cache file.
   * @return string unique token for current query.
   */
  public function getToken(): string
  {
    $tokenArray = $this->getTokenArray();
    $tokenArray = self::convertClosuresToString($tokenArray);

    return md5(json_encode($tokenArray));
  }

  /**
   * Delete all cache files for current store.
   * @return bool
   */
  public function deleteAll(): bool
  {
    return IoHelper::deleteFiles(glob($this->getCachePath()."*"));
  }

  /**
   * Delete all cache files with no lifetime (null) in current store.
   * @return bool
   */
  public function deleteAllWithNoLifetime(): bool
  {
    $noLifetimeFileString = self::NO_LIFETIME_FILE_STRING;
    return IoHelper::deleteFiles(glob($this->getCachePath()."*.$noLifetimeFileString.json"));
  }

  /**
   * Save content for current query as a cache file.
   * @param array $content
   * @throws IOException if cache folder is not writable or saving failed.
   */
  public function set(array $content){
    $lifetime = $this->getLifetime();
    $cachePath = $this->getCachePath();
    $token = $this->getToken();

    $noLifetimeFileString = self::NO_LIFETIME_FILE_STRING;
    $cacheFile = $cachePath . $token . ".$noLifetimeFileString.json";

    if(is_int($lifetime)){
      $cacheFile = $cachePath . $token . ".$lifetime.json";
    }

    IoHelper::writeContentToFile($cacheFile, json_encode($content));
  }

  /**
   * Retrieve content of cache file.
   * @return array|null array on success, else null
   * @throws IOException if cache file is not readable or does not exist.
   */
  public function get(){
    $cachePath = $this->getCachePath();
    $token = $this->getToken();

    $cacheFile = null;

    IoHelper::checkRead($cachePath);

    $cacheFiles = glob($cachePath.$token."*.json");

    if($cacheFiles !== false && count($cacheFiles) > 0){
      $cacheFile = $cacheFiles[0];
    }

    if(!empty($cacheFile)){
      $cacheParts = explode(".", $cacheFile);
      if(count($cacheParts) >= 3){
        $lifetime = $cacheParts[count($cacheParts) - 2];
        if(is_numeric($lifetime)){
          if($lifetime === "0"){
            return json_decode(IoHelper::getFileContent($cacheFile), true);
          }
          $fileExpiredAfter = filemtime($cacheFile) + (int) $lifetime;
          if(time() <= $fileExpiredAfter){
            return json_decode(IoHelper::getFileContent($cacheFile), true);
          }
          IoHelper::deleteFile($cacheFile);
        } else if($lifetime === self::NO_LIFETIME_FILE_STRING){
            return json_decode(IoHelper::getFileContent($cacheFile), true);
        }
      }
    }
    return null;
  }

  /**
   * Delete cache file/s for current query.
   * @return bool
   */
  public function delete(): bool
  {
    return IoHelper::deleteFiles(glob($this->getCachePath().$this->getToken()."*.json"));
  }

  /**
   * @param string $storePath
   * @return Cache
   */
  private function setCachePath(string $storePath): Cache
  {
    $cachePath = "";
    $cacheDir = $this->getCacheDir();

    if(!empty($storePath)){
      IoHelper::normalizeDirectory($storePath);
      $cachePath = $storePath . $cacheDir;
    }

    $this->cachePath = $cachePath;

    return $this;
  }

  /**
   * Set the cache token array used for cache token string generation.
   * @param array $tokenArray
   * @return Cache
   */
  private function setTokenArray(array &$tokenArray): Cache
  {
    $this->tokenArray = &$tokenArray;
    return $this;
  }

  /**
   * Retrieve the cache token array.
   * @return array
   */
  private function getTokenArray(): array
  {
    return $this->tokenArray;
  }

  /**
   * Convert one or multiple closures to string. If array provided, recursively.
   * @param mixed $data
   * @return mixed
   */
  private static function convertClosuresToString($data){
    if(!is_array($data)){
      if($data instanceof \Closure){
        return self::getClosureAsString($data);
      }
      return $data;
    }
    foreach ($data as $key => $token){
      if(is_array($token)){
        $data[$key] = self::convertClosuresToString($token);
      } else if($token instanceof \Closure){
        $data[$key] = self::getClosureAsString($token);
      }
    }
    return $data;
  }

  /**
   * Retrieve a string representation of a closure that can be used to differentiate between closures
   * when generating the cache token string.
   * @param Closure $closure
   * @return false|string string representation of closure or false on failure.
   */
  private static function getClosureAsString(Closure $closure)
  {
    try{
      $reflectionFunction = new ReflectionFunction($closure); // get reflection object
    } catch (Exception $exception){
      return false;
    }
    $filePath = $reflectionFunction->getFileName();  // absolute path of php file containing function
    $startLine = $reflectionFunction->getStartLine(); // start line of function
    $endLine = $reflectionFunction->getEndLine(); // end line of function
    $lineSeparator = PHP_EOL; // line separator "\n"

    $staticVariables = $reflectionFunction->getStaticVariables();
    $staticVariables = var_export($staticVariables, true);

    if($filePath === false || $startLine === false || $endLine === false){
      return false;
    }

    $startEndDifference = $endLine - $startLine;

    $startLine--; // -1 to use it with the array representation of the file

    if($startLine < 0 || $startEndDifference < 0){
      return false;
    }

    // get content of file containing function
    $fp = fopen($filePath, 'rb');
    $fileContent = "";
    if(flock($fp, LOCK_SH)){
      $fileContent = @stream_get_contents($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    if(empty($fileContent)){
      return false;
    }

    // separate the file into an array containing every line as one element
    $fileContentArray = explode($lineSeparator, $fileContent);
    if(count($fileContentArray) < $endLine){
      return false;
    }

    // return the part of the file containing the function as a string.
    $functionString = implode("", array_slice($fileContentArray, $startLine, $startEndDifference + 1));
    $functionString .= "|staticScopeVariables:".$staticVariables;
    return $functionString;
  }

  /**
   * Set the cache directory name.
   * @param string $cacheDir
   * @return Cache
   */
  private function setCacheDir(string $cacheDir): Cache
  {
    IoHelper::normalizeDirectory($cacheDir);
    $this->cacheDir = $cacheDir;
    return $this;
  }

  /**
   * Retrieve the cache directory name or the default cache directory name if empty.
   * @return string
   */
  private function getCacheDir(): string
  {
    return (!empty($this->cacheDir)) ? $this->cacheDir : self::DEFAULT_CACHE_DIR;
  }
}