<?php
  /**
   * A list of methods for the Nest testing framework for SleekDB.
   * 
   */
  trait NestUtils {

    function print_danger($msg) {
      if( PHP_OS != 'WINNT'  ) {
        echo "\033[31m$msg \033[0m\n";
      } else {
        return $msg . "\n";
      }
    }

    function print_warning($msg) {
      if( PHP_OS != 'WINNT'  ) {
        echo "\033[33m$msg \033[0m\n";
      } else {
        return $msg . "\n";
      }
    }

    function print_success($msg) {
      if( PHP_OS != 'WINNT'  ) {
        echo "\033[32m$msg \033[0m\n";
      } else {
        return $msg . "\n";
      }
    }

    function print_default($msg) {
      echo $msg . "\n";
    }

    

  }