<?php

declare(strict_types=1);

chdir(__DIR__);
chdir('..');

require_once './Services/Cron/classes/class.ilCronStartUp.php';

if ($_SERVER['argc'] < 4) {
    echo "Usage: cron.php [--jobid <id>] [--username] username [--passsword] password [--client] client\n";
    exit(1);
}

$rest_index = 1;
$options = getopt('u:p:c:j:', ['username:', 'password:', 'client:', 'jobid:'], $rest_index);

$login    = array_key_exists('username', $options) ? $options['username'] : $_SERVER['argv'][$rest_index + 0];
$password = array_key_exists('password', $options) ? $options['password'] : $_SERVER['argv'][$rest_index + 1];
$client   = array_key_exists('client', $options)   ? $options['client']   : $_SERVER['argv'][$rest_index + 2];
$job_id   = array_key_exists('jobid', $options)    ? $options['jobid']    : null;

if (!$login || !$password || !$client) {
    echo "Usage: cron.php [--jobid <id>] [--username] username [--passsword] password [--client] client\n";
    exit(1);
}

$cron = new ilCronStartUp(
    $client,
    $login,
    $password
);

try {
    global $DIC;

    $cron->authenticate();

    $strictCronManager = new ilStrictCliCronManager(
        $DIC->cron()->manager()
    );


    if ($job_id) {
        $strictCronManager->runJobManual($job_id, $DIC->user());
    } else {
        $strictCronManager->runActiveJobs($DIC->user());
    }

    $cron->logout();
} catch (Exception $e) {
    $cron->logout();

    echo $e->getMessage() . "\n";

    if (defined('DEVMODE') && DEVMODE) {
        echo $e->getTraceAsString() . "\n";
    }

    exit(1);
}
