<?php
declare(strict_types=1);

use Elasticsearch\ClientBuilder;

require __DIR__ . '/../vendor/autoload.php';

$source = parseSourceIndex($argv);
$target = parseTargetIndex($argv);
$host = parseHost($argv);

#################### CONFIGURATION ####################

const SCROLL_TIME = '30s';

$host = 'http://localhost:9500';

#################### DO NOT CHANGE ANYTHING BELOW THIS LINE ####################

$client = ClientBuilder::create()
    ->setHosts([$host])
    ->build();

$params = [
    'scroll' => SCROLL_TIME,          // how long between scroll requests. should be small!
    'size' => 2000,                     // how many results *per shard* you want back
    'index' => $source['index'],
    'type' => $source['type'],
    'body' => [
        'query' => [
            'match_all' => new \stdClass()
        ]
    ]
];

$startTotalTime = microtime(true);

$response = $client->search($params);

if (array_key_exists('message', $response)) {
    echo $response['message'] . PHP_EOL;
    exit();
}

$documentsProcessedCount = 0;
$totalDocumentsToProcess = $response['hits']['total'];

while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
    $startTime = microtime(true);

    /** @var array $sourceDocuments */
    $sourceDocuments = $response['hits']['hits'];

    $documentIds = [];
    $documentMap = [];
    foreach ($sourceDocuments as $document) {
        $documentId = $document['_source']['id'];

        $documentIds[] = $documentId;
        $documentMap[$documentId] = $document;
    }

    $result = $client->mget([
        'index' => $target['index'],
        'type' => $target['type'],
        'body' => ['ids' => $documentIds]
    ]);

    /** @var array $targetDocuments */
    $targetDocuments = $result['docs'];
    foreach ($targetDocuments as $document) {
        // Check if exists.
        if (!$document['found']) {
            echo $document['_id'] . " not found in " . $target['index'] . '/' . $target['type'] . PHP_EOL;
            echo PHP_EOL;
            continue;
        }

        $documentId = $document['_source']['id'];

        // Check documents are equal.
        if ($document['_source'] !== $documentMap[$documentId]['_source']) {
            echo "$documentId is not the same in source and target" . PHP_EOL;
            echo json_encode($document['_source']) . PHP_EOL;
            echo json_encode($documentMap[$documentId]['_source']) . PHP_EOL;
            echo PHP_EOL;

            continue;
        }
    }

    $endTime = microtime(true);
    $timeDiff = $endTime - $startTime;

    $documentsProcessedCount += count($sourceDocuments);
    echo "$documentsProcessedCount of $totalDocumentsToProcess compared. ($timeDiff seconds)" . PHP_EOL;

    // Execute a Scroll request and repeat
    $scroll_id = $response['_scroll_id'];
    $response = $client->scroll([
        'scroll_id' => $scroll_id,
        'scroll' => SCROLL_TIME
    ]);
}

$endTotalTime = microtime(true);
$timeDiffTotal = $endTotalTime - $startTotalTime;

echo "Done ($timeDiff seconds)" . PHP_EOL;

function parseSourceIndex(array $argv): array
{
    if (!isset($argv[1])) {
        echo 'Please supply name of the source index.' . PHP_EOL;
        exit();
    }

    if (!isset($argv[2])) {
        echo 'Please supply name of the source type.' . PHP_EOL;
        exit();
    }

    return [
        'index' => $argv[1],
        'type' => $argv[2]
    ];
}

function parseTargetIndex(array $argv): array
{
    if (!isset($argv[3])) {
        echo 'Please supply name of the target index.' . PHP_EOL;
        exit();
    }

    if (!isset($argv[4])) {
        echo 'Please supply name of the target type.' . PHP_EOL;
        exit();
    }

    return [
        'index' => $argv[3],
        'type' => $argv[4]
    ];
}

function parseHost(array $argv): string
{
    if (isset($argv[5])) {
        return $argv[5];
    }

    return 'http://localhost:9200';
}
