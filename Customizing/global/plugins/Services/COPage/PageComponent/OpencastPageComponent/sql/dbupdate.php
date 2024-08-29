<#1>
<?php
$fields = [
    'name' => [
        'notnull' => '1',
        'type' => 'text',
        'length' => '100',

    ],
    'value' => [
        'type' => 'text',
        'length' => '2048',
    ],

];
/**
 * @var $ilDB \ilDBInterface
 */
if (!$ilDB->tableExists('copg_pgcp_ocpc_config')) {
    $ilDB->createTable('copg_pgcp_ocpc_config', $fields);
    $ilDB->addPrimaryKey('copg_pgcp_ocpc_config', ['name']);
    $ilDB->insert(
        'copg_pgcp_ocpc_config',
        [
                'value' => ['text', '268'],
                'name' => ['text', 'default_width']
        ]
    );
    $ilDB->insert(
        'copg_pgcp_ocpc_config',
        [
            'value' => ['text', '150'],
            'name' => ['text', 'default_height']
        ]
    );
}

?>
<#2>
<?php
$fields = [
    'id' => [
        'type' => 'integer',
        'length' => '8',

    ],
    'usr_id' => [
        'notnull' => '1',
        'type' => 'integer',
        'length' => '8',

    ],
    'event_id' => [
        'notnull' => '1',
        'type' => 'text',
        'length' => '128',

    ],
    'token' => [
        'notnull' => '1',
        'type' => 'text',
        'length' => '128',

    ],
    'valid_until_unix' => [
        'notnull' => '1',
        'type' => 'integer',
        'length' => '8',

    ],

];
if (!$ilDB->tableExists('ocpc_token')) {
    $ilDB->createTable('ocpc_token', $fields);
    $ilDB->addPrimaryKey('ocpc_token', ['id']);

    if (!$ilDB->sequenceExists('ocpc_token')) {
        $ilDB->createSequence('ocpc_token');
    }
}
?>
