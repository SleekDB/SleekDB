#!/usr/bin/env php
<?php

if ('cli' !== php_sapi_name()) {
    return;
} else {
    require_once('SleekCli.php');
    $ini_array = parse_ini_file("config.ini");
    $cli = new SleekCli($ini_array);
    $cli->check_args();
    exit;
}