<?php
declare(strict_types=1);

use ElasticCompare\Document;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Forbidden403Exception;

require __DIR__ . '/../vendor/autoload.php';

$source = parseSourceIndex($argv);
$target = parseTargetIndex($argv);
$host = parseHost($argv);

$sortKeysForSequentialArrays = [
    'facets' => 'key'
];

#################### CONFIGURATION ####################

const SCROLL_TIME = '30s';

#################### DO NOT CHANGE ANYTHING BELOW THIS LINE ####################

$client = ClientBuilder::create()
    ->setHosts([$host])
    ->build();

$params = [
    'scroll' => SCROLL_TIME,          // how long between scroll requests. should be small!
    'size' => 10,                     // how many results *per shard* you want back
    'index' => $source['index'],
    'type' => $source['type'],
    'body' => [
        'query' => [
            'match_all' => new \stdClass()
        ]
    ]
];

$startTotalTime = microtime(true);

try {
    $response = $client->search($params);
} catch (Forbidden403Exception $exception) {
    $response = json_decode($exception->getMessage(), true);

    if (array_key_exists('message', $response)) {
        echo $response['message'] . PHP_EOL;
        exit();
    }
}

$documentsProcessedCount = 0;
$totalDocumentsToProcess = $response['hits']['total'];

while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
    $startTime = microtime(true);

    /** @var array $sourceDocuments */
    $sourceDocuments = $response['hits']['hits'];

    $sourceDocumentIds = [];
    $sourceDocumentMap = [];
    foreach ($sourceDocuments as $sourceDocument) {
        $sourceDocumentIds[] = $sourceDocument['_id'];
        $sourceDocumentMap[$sourceDocument['_id']] = $sourceDocument;
    }

    $result = $client->mget([
        'index' => $target['index'],
        'type' => $target['type'],
        'body' => ['ids' => $sourceDocumentIds]
    ]);

    /** @var array $targetDocuments */
    $targetDocuments = $result['docs'];
    foreach ($targetDocuments as $targetDocument) {
        // Check if exists.
        if (!$targetDocument['found']) {
            echo $source['index'] . '/' . $source['type'] . '/' . $targetDocument['_id'] . ' not found in ' . $target['index'] . '/' . $target['type'] . PHP_EOL;
            echo PHP_EOL;
            continue;
        }

        $documentId = $targetDocument['_id'];

        $documentCompare = Document::getInstance([
            'facets' => 'key',
            'stockClusterAvailabilityState' => 'stockClusterId',
            'promoIcons' => 'displayText',
            'secondChanceProducts' => 'secondChanceInformation.productId'
        ]);

        $sourceDocument = $sourceDocumentMap[$documentId]['_source'];

        try {
            $targetDiff = $documentCompare->diff(
                $sourceDocument,
                $targetDocument['_source']
            );
        } catch (\RuntimeException $e) {
            echo $e->getMessage() . PHP_EOL;
            exit;
        }

        if (!empty($sourceDocument) || !empty($targetDiff)) {
            $sourceDocUrl = $host . '/' . $source['index'] . '/' . $source['type'] . '/' . $documentId;
            $targetDocUrl = $host . '/' . $target['index'] . '/' . $target['type'] . '/' . $documentId;

            echo "DIFFERENCES FOR $documentId" . PHP_EOL;
            echo "($documentId) SOURCE DOCUMENT: $sourceDocUrl" . PHP_EOL;
            echo json_encode($sourceDocument) . PHP_EOL . PHP_EOL;
            echo "($documentId) TARGET DOCUMENT: $targetDocUrl" . PHP_EOL;
            echo json_encode($targetDiff) . PHP_EOL . PHP_EOL;
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

echo "Done ($timeDiffTotal seconds)" . PHP_EOL;

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
