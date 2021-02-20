<?php

namespace SleekDB;

use Closure;
use Exception;
use ReflectionFunction;
use SleekDB\Classes\IoHelper;
use SleekDB\Exceptions\IOException;

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
    $cacheDir = "";
    $this->setCacheDir($cacheDir);

    $this->setCachePath($storePath);

    $this->setTokenArray($cacheTokenArray);

    $this->lifetime = $cacheLifetime;
  }

  /**
   * @return int|null
   */
  public function getLifetime()
  {
    return $this->lifetime;
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

      if(substr($storePath, -1) !== "/") {
        $storePath .= "/";
      }

      $cachePath = $storePath . $cacheDir;

    }

    $this->cachePath = $cachePath;

    return $this;
  }

  /**
   * @return string path to cache directory
   */
  public function getCachePath(): string
  {
    return $this->cachePath;
  }

  /**
   * @param array $tokenArray
   * @return Cache
   */
  private function setTokenArray(array &$tokenArray): Cache
  {
    $this->tokenArray = &$tokenArray;
    return $this;
  }

  /**
   * @return array
   */
  private function getTokenArray(): array
  {
    return $this->tokenArray;
  }

  /**
   * @return string
   */
  public function getToken(): string
  {

    $tokenArray = $this->getTokenArray();

    // hash the join sub-queries instead of the function reference to generate the cache token
    if(array_key_exists("listOfJoins", $tokenArray)){
      $listOfJoins = $tokenArray["listOfJoins"];
      foreach ($listOfJoins as $key => $join){
        if(array_key_exists("joinFunction", $join)){
          $joinFunction = $join["joinFunction"];
          $joinFunctionString = self::getClosureAsString($joinFunction);
          if($joinFunctionString === false){
            continue;
          }
          $tokenArray["listOfJoins"][$key] = $joinFunctionString;
        }
      }
    }

    return md5(json_encode($tokenArray));
  }

  /**
   * @param Closure $closure
   * @return false|string
   */
  private static function getClosureAsString(Closure $closure)
  {
    try{
      $reflectionFunction = new ReflectionFunction($closure); // get reflection object
    } catch (Exception $exception){
      return false;
    }
    $filePath = $reflectionFunction->getFileName();  // absolute path of php file containing function
    $startLine = $reflectionFunction->getStartLine();
    $endLine = $reflectionFunction->getEndLine();
    $lineSeparator = PHP_EOL;

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

    $fileContentArray = explode($lineSeparator, $fileContent);

    if(count($fileContentArray) < $endLine){
      return false;
    }

    return implode("", array_slice($fileContentArray, $startLine, $startEndDifference + 1));
  }

  /**
   * @param string $cacheDir
   * @return Cache
   */
  private function setCacheDir(string $cacheDir): Cache
  {
    if(!empty($cacheDir) && substr($cacheDir, -1) !== "/") {
      $cacheDir .= "/";
    }
    $this->cacheDir = $cacheDir;
    return $this;
  }

  /**
   * @return string
   */
  private function getCacheDir(): string
  {
    return (!empty($this->cacheDir)) ? $this->cacheDir : self::DEFAULT_CACHE_DIR;
  }

  /**
   * Delete all cache files for current store.
   */
  public function deleteAll(): bool
  {
    return IoHelper::deleteFiles(glob($this->getCachePath()."*"));
  }

  /**
   * Delete all cache files with no lifetime in current store.
   */
  public function deleteAllWithNoLifetime(): bool
  {
    $noLifetimeFileString = self::NO_LIFETIME_FILE_STRING;
    return IoHelper::deleteFiles(glob($this->getCachePath()."*.$noLifetimeFileString.json"));
  }

  /**
   * Cache content for current query
   * @param array $content
   * @throws IOException
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
   * @return array|null array on success, else null
   * @throws IOException
   */
  public function get(){
    $cachePath = $this->getCachePath();
    $token = $this->getToken();

    $cacheFile = null;

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
}