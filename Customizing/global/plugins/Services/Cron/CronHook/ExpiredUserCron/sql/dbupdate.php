<#1>
<?php
$fields = array(
	'days_ago' => array(
		'type' => 'integer',
		'length' => 8,
		'notnull' => false
	),
	'role_id' => array(
	'type' => 'integer',
	'length' => 8,
	'notnull' => false
	)
);

$ilDB->createTable("cron_exuser_config", $fields);
?>