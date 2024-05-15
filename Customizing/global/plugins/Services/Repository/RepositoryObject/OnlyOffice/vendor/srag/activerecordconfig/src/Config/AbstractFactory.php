<?php

namespace srag\ActiveRecordConfig\OnlyOffice\Config;

use srag\DIC\OnlyOffice\DICTrait;

/**
 * Class AbstractFactory
 *
 * @package srag\ActiveRecordConfig\OnlyOffice\Config
 */
abstract class AbstractFactory
{

    use DICTrait;

    /**
     * AbstractFactory constructor
     */
    protected function __construct()
    {

    }


    /**
     * @return Config
     */
    public function newInstance() : Config
    {
        $config = new Config();

        return $config;
    }
}
