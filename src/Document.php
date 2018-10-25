<?php

declare(strict_types=1);

namespace ElasticCompare;

class Document
{
    /** @var Document */
    private static $instance;

    /** @var array */
    private $sortKeysForSequentialArrays;

    /**
     * @param array $sortKeysForSequentialArrays
     */
    private function __construct(array $sortKeysForSequentialArrays)
    {
        $this->sortKeysForSequentialArrays = $sortKeysForSequentialArrays;
    }

    /**
     * @param array $sortKeysForSequentialArrays
     * @return Document
     */
    public static function getInstance(array $sortKeysForSequentialArrays): Document
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        self::$instance = new Document($sortKeysForSequentialArrays);

        return self::getInstance($sortKeysForSequentialArrays);
    }

    /**
     * @param array $source
     * @param array $target
     * @return array
     * @throws \RuntimeException
     */
    public function diff(array &$source, array $target): array
    {
        foreach ($source as $key => &$sourceValue) {
            if ((\is_bool($sourceValue) || \is_string($sourceValue) || \is_scalar($sourceValue)) &&
                $this->isSameValue($sourceValue, $target[$key] ?? null
                )) {
                unset($source[$key], $target[$key]);
            }

            if (\is_array($sourceValue) && ($target[$key] ?? false) && $this->isAssociativeArray($sourceValue)) {
                $target[$key] = $this->diff($sourceValue, $target[$key]);
            }

            if (\is_array($sourceValue) && ($target[$key] ?? false) && !$this->isAssociativeArray($sourceValue)) {
                $targetValue = $target[$key];

                $target[$key] = $this->cleanupSequentialArrayForDiff($this->diffSequentialArray(
                    $sourceValue,
                    $targetValue,
                    $this->sortKeysForSequentialArrays[$key] ?? null
                ));
                $source[$key] = $this->cleanupSequentialArrayForDiff($sourceValue);
            }
        }
        unset($sourceValue);

        ksort($source);
        ksort($target);

        return $this->cleanupDiff($source, $target);
    }

    /**
     * @param array $source
     * @param array $target
     * @param string|null $sortKey
     * @return array
     * @throws \RuntimeException
     */
    private function diffSequentialArray(array &$source, array $target, string $sortKey = null): array
    {
        if (isset($source[0]) && (\is_bool($source[0]) || \is_string($source[0]) || \is_scalar($source[0]))) {
            $this->sortSequentialArray($source);
            $this->sortSequentialArray($target);

            foreach ($source as $key => &$sourceValue) {
                if ($this->isSameValue($sourceValue, $target[$key] ?? null)) {
                    unset($source[$key], $target[$key]);
                }
            }

            return $target;
        }

        if ($sortKey === null) {
            throw new \RuntimeException('Please define sort key for: ' . json_encode($target));
        }

        $this->sortSequentialArray($source, $sortKey);
        $this->sortSequentialArray($target, $sortKey);

        $targetMap = [];
        foreach ($target as $key => $targetValue) {
            if (!isset($targetValue[$sortKey])) {
                var_dump($targetValue);
                continue;
            }

            $targetMap[$targetValue[$sortKey]] = $targetValue;
        }

        foreach ($source as $key => &$sourceValue) {
            $keyValue = $sourceValue[$sortKey];

            $diff = $this->diff($sourceValue, $targetMap[$keyValue] ?? []);

            $targetMap[$keyValue] = $diff;
        }

        return array_values($targetMap);
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private function isSameValue($a, $b = null): bool
    {
        return $a === $b;
    }

    /**
     * @param array $array
     * @return bool
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, \count($array) - 1);
    }

    /**
     * @param array $array
     * @param string $sortKey
     */
    private function sortSequentialArray(array &$array, string $sortKey = null)
    {
        usort($array, function ($a, $b) use ($sortKey) {
            if (!\is_array($a)) {
                return $a <=> $b;
            }

            if (!isset($a[$sortKey], $b[$sortKey])) {
                return 0;
            }

            return $a[$sortKey] <=> $b[$sortKey];
        });
    }

    /**
     * @param array $array
     * @return array
     */
    private function cleanupSequentialArrayForDiff(array $array): array
    {
        return array_values(array_filter($array, function ($value) {
            return !(\is_array($value) && empty($value));
        }));
    }

    /**
     * @param array $source
     * @param array $target
     * @return array
     */
    private function cleanupDiff(array &$source, array $target): array
    {
        foreach ($source as $key => &$sourceValue) {
            if (\is_array($sourceValue) && array_key_exists($key, $target) && \is_array($target[$key])) {
                if (empty($sourceValue)) {
                    unset($source[$key]);
                }

                if (empty($target[$key])) {
                    unset($target[$key]);
                }
            }
        }

        return $target;
    }
}
