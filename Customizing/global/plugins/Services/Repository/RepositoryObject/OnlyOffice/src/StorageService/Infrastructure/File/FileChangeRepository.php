<?php

namespace srag\Plugins\OnlyOffice\StorageService\Infrastructure\File;

use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileChange;

/**
 * interface FileChangeRepository
 * @package srag\Plugins\OnlyOffice\StorageService\Infrastructure\File
 * @author  Sophie Pfiser <sophie@fluxlabs.ch>
 */
interface FileChangeRepository
{

    const DEFAULT_SERVER_VERSION = '6.3.1';

    /**
     * @param UUID   $file_uuid
     * @param int    $version
     * @param string $changesObjectString
     * @param string $serverVersion
     * @param string $changesUrl
     * @return mixed
     */
    public function create(
        UUID $file_uuid,
        int $version,
        string $changesObjectString,
        string $serverVersion,
        string $changesUrl
    );

    public function getAllChanges(string $uuid): array;

    public function getChange(string $uuid, int $version) : FileChange;

}