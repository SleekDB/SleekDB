<?php

namespace SleekDB\Classes;

use Closure;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;

/**
 * Class IoHelper
 * Helper to handle file input/ output.
 */
class IoHelper {

  /**
   * @param string $path
   * @throws IOException
   */
  public static function checkWrite(string $path)
  {
    if(file_exists($path) === false){
      $path = dirname($path);
    }
    // Check if PHP has write permission
    if (!is_writable($path)) {
      throw new IOException(
        "Directory or file is not writable at \"$path\". Please change permission."
      );
    }
  }

  /**
   * @param string $path
   * @throws IOException
   */
  public static function checkRead(string $path)
  {
    // Check if PHP has read permission
    if (!is_readable($path)) {
      throw new IOException(
        "Directory or file is not readable at \"$path\". Please change permission."
      );
    }
  }

    /**
     * @param string $filePath
     * @return string
     * @throws IOException
     */
  public static function getFileContent(string $filePath): string
  {

    self::checkRead($filePath);

    if(!file_exists($filePath)) {
      throw new IOException("File does not exist: $filePath");
    }

    $content = false;
    $fp = fopen($filePath, 'rb');
    if(flock($fp, LOCK_SH)){
      $content = stream_get_contents($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    if($content === false) {
      throw new IOException("Could not retrieve the content of a file. Please check permissions at: $filePath");
    }

    return $content;
  }

  /**
   * @param string $filePath
   * @param string $content
   * @throws IOException
   */
  public static function writeContentToFile(string $filePath, string $content){

    self::checkWrite($filePath);

    // Wait until it's unlocked, then write.
    if(file_put_contents($filePath, $content, LOCK_EX) === false){
      throw new IOException("Could not write content to file. Please check permissions at: $filePath");
    }
  }

  /**
   * @param string $folderPath
   * @return bool
   * @throws IOException
   */
  public static function deleteFolder(string $folderPath): bool
  {
    self::checkWrite($folderPath);
    $it = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      self::checkWrite($file);
      if ($file->isDir()) {
        rmdir($file->getRealPath());
      } else {
        unlink($file->getRealPath());
      }
    }
    return rmdir($folderPath);
  }

  /**
   * @param string $folderPath
   * @param int $chmod
   * @throws IOException
   */
  public static function createFolder(string $folderPath, int $chmod){
    // We don't need to create a folder if it already exists.
    if(file_exists($folderPath) === true){
      return;
    }
    self::checkWrite($folderPath);
    // Check if the data_directory exists or create one.
    if (!file_exists($folderPath) && !mkdir($folderPath, $chmod, true) && !is_dir($folderPath)) {
      throw new IOException(
        'Unable to create the a directory at ' . $folderPath
      );
    }
  }

  /**
   * @param string $filePath
   * @param Closure $updateContentFunction Has to return a string or an array that will be encoded to json.
   * @return string
   * @throws IOException
   * @throws JsonException
   */
  public static function updateFileContent(string $filePath, Closure $updateContentFunction): string
  {
    self::checkRead($filePath);
    self::checkWrite($filePath);

    $content = false;

    $fp = fopen($filePath, 'rb');
    if(flock($fp, LOCK_SH)){
      $content = stream_get_contents($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);

    if($content === false){
      throw new IOException("Could not get shared lock for file: $filePath");
    }

    $content = $updateContentFunction($content);

    if(!is_string($content)){
      $encodedContent = json_encode($content);
      if($encodedContent === false){
        $content = (!is_object($content) && !is_array($content) && !is_null($content)) ? $content : gettype($content);
        throw new JsonException("Could not encode content with json_encode. Content: \"$content\".");
      }
      $content = $encodedContent;
    }


    if(file_put_contents($filePath, $content, LOCK_EX) === false){
      throw new IOException("Could not write content to file. Please check permissions at: $filePath");
    }


    return $content;
  }

  /**
   * @param string $filePath
   * @return bool
   */
  public static function deleteFile(string $filePath): bool
  {

    if(false === file_exists($filePath)){
      return true;
    }
    try{
      self::checkWrite($filePath);
    }catch(Exception $exception){
      return false;
    }

    return (@unlink($filePath) && !file_exists($filePath));
  }

  /**
   * @param array $filePaths
   * @return bool
   */
  public static function deleteFiles(array $filePaths): bool
  {
    foreach ($filePaths as $filePath){
      // if a file does not exist, we do not need to delete it.
      if(true === file_exists($filePath)){
        try{
          self::checkWrite($filePath);
          if(false === @unlink($filePath) || file_exists($filePath)){
            return false;
          }
        } catch (Exception $exception){
          // TODO trigger a warning or exception
          return false;
        }
      }
    }
    return true;
  }

  /**
   * Strip string for secure file access.
   * @param string $string
   * @return string
   */
  public static function secureStringForFileAccess(string $string): string
  {
    return (str_replace(array(".", "/", "\\"), "", $string));
  }

  /**
   * Appends a slash ("/") to the given directory path if there is none.
   * @param string $directory
   */
  public static function normalizeDirectory(string &$directory){
    if(!empty($directory) && substr($directory, -1) !== "/") {
      $directory .= "/";
    }
  }

  /**
   * Returns the amount of files in folder.
   * @param string $folder
   * @return int
   * @throws IOException
   */
  public static function countFolderContent(string $folder): int
  {
    self::checkRead($folder);
    $fi = new \FilesystemIterator($folder, \FilesystemIterator::SKIP_DOTS);
    return iterator_count($fi);
  }
}