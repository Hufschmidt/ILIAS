<?php

namespace srag\DIC\OnlyOffice\DIC;

use ILIAS\DI\Container;
use srag\DIC\OnlyOffice\Database\DatabaseDetector;
use srag\DIC\OnlyOffice\Database\DatabaseInterface;

/**
 * Class AbstractDIC
 *
 * @package srag\DIC\OnlyOffice\DIC
 */
abstract class AbstractDIC implements DICInterface
{

    /**
     * @var Container
     */
    protected $dic;


    /**
     * @inheritDoc
     */
    public function __construct(Container &$dic)
    {
        $this->dic = &$dic;
    }


    /**
     * @inheritDoc
     */
    public function database() : DatabaseInterface
    {
        return DatabaseDetector::getInstance($this->databaseCore());
    }
}
