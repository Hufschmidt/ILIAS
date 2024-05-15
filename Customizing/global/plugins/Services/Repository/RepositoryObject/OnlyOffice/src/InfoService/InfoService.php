<?php

namespace srag\Plugins\OnlyOffice\InfoService;

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;

/**
 * Class InfoService
 * Used to access information using OnlyOfficeTrait.
 * @author      Sophie Pfister
 */
class InfoService
{
    use OnlyOfficeTrait;

    public static function getOpenSetting(int $file_id): string {
        return self::onlyOffice()->objectSettings()->getObjectSettingsById($file_id)->getOpen();
    }

    public static final function getOnlyOfficeUrl(): string {
        return self::onlyOffice()->config()->getValue("onlyoffice_url");
    }

    public static final function getSecret(): string {
        return self::onlyOffice()->config()->getValue("onlyoffice_secret");
    }

    public static final function getNumberOfVersions(): int {
        return self::onlyOffice()->config()->getValue("number_of_versions");
    }

}