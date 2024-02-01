<#1>
<?php
$fields = array(
	'role_id_students' => array(
		'type' => 'integer',
		'length' => 8,
		'notnull' => false
	),
    	'role_id_staff' => array(
		'type' => 'integer',
		'length' => 8,
		'notnull' => false
	)
);

$ilDB->createTable("cron_umruser_config", $fields);
?>