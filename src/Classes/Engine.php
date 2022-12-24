<?php

namespace SleekDB\Classes;

/**
 * Class Engine
 * @package SleekDB\Classes
 * @since 3.0
 * 
 * Defination of the available engines for SleekDB.
 * The engine is used to define the data storage type.
 */

class Engine
{
    /**
     * const POLY
     * 
     * The SleekDB Poly engine is the default engine for version 3 and higher. 
     * It is introduced in v3 to support single file based data storage.
     * This is the recommended engine of choice.
     */
    const POLY = "poly";

    /**
     * const MONO
     * 
     * The SleekDB Mono engine is the default engine for version 2 and lower.
     * This is the legacy engine of SleekDB that usage multiple files for data storage.
     * This engine is optional and only suggested to handle old data from SleekDB v2 or lower.
     */
    const MONO = "mono";
}
