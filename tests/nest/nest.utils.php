<?php
  /**
   * A list of methods for the Nest testing framework for SleekDB.
   */
  trait NestUtils {
    function print_danger($msg) {
      echo "\e[0;31;40m".$msg."\e[0m\n";
    }
    function print_warning($msg) {
      echo "\e[1;34;40m".$msg."\e[0m\n";
    }
    function print_success($msg) {
      echo "\e[0;32;40m".$msg."\e[0m\n";
    }
    function print_default($msg) {
      echo "\e[0;36;40m".$msg."\e[0m\n";
    }
  }