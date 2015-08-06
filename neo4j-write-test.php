<?php

if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', __DIR__);
}

require_once APPLICATION_PATH.'/vendor/autoload.php';

use Neoxygen\NeoClient\ClientBuilder;

$config = require_once APPLICATION_PATH.'/local.php.dist';

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

$companyName = 'Company Name';

$client->createUniqueConstraint('Company', 'name');
$client->createUniqueConstraint('Person', 'email');
$client->sendCypherQuery('MERGE (c:Company { name: { name }}) RETURN c;', ['name' => $companyName]);


// method 1
$s = microtime(true);

$tx = $client->prepareTransaction();

for ($i = 1; $i < 1000000; $i++) {
    $q = 'MATCH (c:Company {name: {name}})
    MERGE (p:Person {email: {email}})
    MERGE (p)-[:WORKS_AT]->(c)';

    $p = [
        'name' => $companyName,
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

// method 2 using unwind
// delete db
for ($i= 1; $i < 1000; ++$i) {
    echo 'Deleting nodes in batch' . PHP_EOL;
    $client->sendCypherQuery('MATCH (n) WITH n LIMIT 1000 OPTIONAL MATCH (n)-[r]-() DELETE r,n');
}
$client->sendCypherQuery('MERGE (c:Company { name: { name }}) RETURN c;', ['name' => $companyName]);
$start = microtime(true);
$q = 'MATCH (c:Company {name:{name}})
UNWIND {persons} as person
MERGE (p:Person {email:person.email})
MERGE (p)-[:WORKS_AT]->(c)';
$p = ['name' => $companyName, 'persons' => []];
$txc = 1;
$tx = $client->createTransaction();
for ($i= 1; $i <= 1000000; ++$i) {
  $p['persons'][] = [
    'name' => $companyName,
    'email' => $i.'example.com'
  ];
  if ($i % 1000 === 0) {
    $s = microtime(true);
    $tx->pushQuery($q, $p);
    $e = microtime(true);
    $diff = $e - $s;
    echo PHP_EOL.sprintf('Write Tx #%d, of %d pushed in %f', $txc, count($p['persons']), $diff);
    ++$txc;
    $p['persons'] = [];
  }

  if ($i % 50000 === 0) {
    $tx->commit();
    $tx = $client->createTransaction();
  }
}
$end = microtime(true);
$elapsed = $end - $start;

echo PHP_EOL.'Done in '.$elapsed.' seconds.'.PHP_EOL;
