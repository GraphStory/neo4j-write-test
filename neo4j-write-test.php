<?php

if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', __DIR__);
}

require_once APPLICATION_PATH.'/vendor/autoload.php';

use Neoxygen\NeoClient\ClientBuilder;

$config = require_once APPLICATION_PATH.'/local.php';

$client = ClientBuilder::create()->addConnection(
    $config['neo4j']['alias'],
    $config['neo4j']['scheme'],
    $config['neo4j']['host'],
    $config['neo4j']['port'],
    $config['neo4j']['authMode'],
    $config['neo4j']['authUser'],
    $config['neo4j']['authPassword']
)
->setDefaultTimeout($config['neo4j']['defaultTimeout'])
->setAutoFormatResponse($config['neo4j']['autoFormatResponse'])
->build();

$client->createUniqueConstraint('Company', 'name');
$client->createUniqueConstraint('Person', 'email');

$s = microtime(true);

$tx = $client->prepareTransaction();

for ($i = 1; $i < 100000; $i++) {
    $q = 'MATCH (c:Company {name: {name}})
    MERGE (p:Person {email: {email}})
    MERGE (p)-[:WORKS_AT]->(c)';

    $p = [
        'name' => 'Company Name',
        'email' => sprintf('%d@example.com', $i),
    ];

    $tx->pushQuery($q, $p);

    if ($i % 1500 === 0) {
        $b = microtime(true);
        $tx->commit();
        $f = microtime(true);
        $el = $f - $b;
        $tx = $client->prepareTransaction();
        echo PHP_EOL.sprintf('Transaction of 1000 commited in %f seconds, Total : %d', $el, $i).PHP_EOL;
    }
}

$e = microtime(true);
$elapsed = $e - $s;

echo PHP_EOL.'Done in '.$elapsed.' seconds.'.PHP_EOL;
