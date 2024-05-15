<?php

namespace srag\CustomInputGUIs\OnlyOffice;

/**
 * Trait CustomInputGUIsTrait
 *
 * @package srag\CustomInputGUIs\OnlyOffice
 */
trait CustomInputGUIsTrait
{

    /**
     * @return CustomInputGUIs
     */
    protected static final function customInputGUIs() : CustomInputGUIs
    {
        return CustomInputGUIs::getInstance();
    }
}
