<?php

namespace SleekDB;

use SleekDB\Exceptions\IOException;

class Cache
{

  const DEFAULT_CACHE_DIR = "cache/";
  const NO_LIFETIME_FILE_STRING = "no_lifetime";

  /**
   * Lifetime in seconds or deletion with deleteAll
   * @var int|null
   */
  protected $lifetime = null;

  protected $cachePath = "";

  protected $cacheDir = "";

  protected $tokenArray;

  /**
   * Cache constructor.
   * @param Query $query
   * @param string $storePath
   * @param string $cacheDir
   */
  public function __construct(Query $query, string $storePath, string $cacheDir = "")
  {
    $this->setCacheDir($cacheDir);

    $this->setCachePath($storePath);

    $this->setTokenArray($query->_getCacheTokenArray());
  }

  /**
   * @param int|null $lifetime
   * @return $this
   */
  public function setLifetime($lifetime): Cache
  {
    $this->lifetime = $lifetime;
    return $this;
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

      if(substr($storePath, -1) !== "/") $storePath .= "/";

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
    return md5(json_encode($this->getTokenArray()));
  }

  /**
   * @param string $cacheDir
   * @return Cache
   */
  private function setCacheDir(string $cacheDir): Cache
  {
    if(!empty($cacheDir) && substr($cacheDir, -1) !== "/") $cacheDir .= "/";
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
   * @throws IOException
   */
  public function deleteAll(){
    $this->_delete(glob($this->getCachePath()."*"));
  }

  /**
   * Delete all cache files with no lifetime in current store.
   * @throws IOException
   */
  public function deleteAllWithNoLifetime(){
    $noLifetimeFileString = self::NO_LIFETIME_FILE_STRING;
    $this->_delete(glob($this->getCachePath()."*.$noLifetimeFileString.json"));
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

    $this->_checkWrite($cachePath);

    $noLifetimeFileString = self::NO_LIFETIME_FILE_STRING;
    $cacheFile = $cachePath . $token . ".$noLifetimeFileString.json";

    if(is_int($lifetime)){
      $cacheFile = $cachePath . $token . ".$lifetime.json";
    }

    file_put_contents($cacheFile, json_encode($content));
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
      $this->_checkRead($cacheFile);
      $cacheParts = explode(".", $cacheFile);

      if(count($cacheParts) >= 3){
        $lifetime = $cacheParts[count($cacheParts) - 2];
        if(is_numeric($lifetime)){
          if($lifetime === "0"){
              return json_decode(file_get_contents($cacheFile), true);
          } else {
            $fileExpiredAfter = filemtime($cacheFile) + (int) $lifetime;
            if(time() <= $fileExpiredAfter){
              return json_decode(file_get_contents($cacheFile), true);
            }
            $this->_delete([$cacheFile]);
          }
        } else if($lifetime === self::NO_LIFETIME_FILE_STRING){
            return json_decode(file_get_contents($cacheFile), true);
        }
      }
    }

    return null;
  }

  /**
   * Delete cache file/s for current query.
   * @throws IOException
   */
  public function delete()
  {
    $this->_delete(glob($this->getCachePath().$this->getToken()."*.json"));
  }

  /**
   * @param array $cacheFiles
   * @throws IOException
   */
  private function _delete(array $cacheFiles){
    foreach ($cacheFiles as $cacheFile){
      $this->_checkWrite($cacheFile);
    }
    array_map("unlink", $cacheFiles);
  }


  /**
   * @param string $path
   * @throws IOException
   */
  private function _checkWrite(string $path)
  {
    // Check if PHP has write permission
    if (!is_writable($path)) {
      throw new IOException(
        "Cache directory or file is not writable at \"$path\". Please change permission."
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
      throw new IOException(
        "Cache directory or file is not readable at \"$path\". Please change permission."
      );
    }
  }

}