<?php

declare(strict_types=1);

namespace ElasticCompare;

class Document
{
    /** @var Document */
    private static $instance;

    /** @var array */
    private $sortKeysForSequentialArrays;

    /** @var bool */
    private $strict;

    /**
     * @param array $sortKeysForSequentialArrays
     * @param bool $strict
     */
    private function __construct(array $sortKeysForSequentialArrays,  bool $strict)
    {
        $this->sortKeysForSequentialArrays = $sortKeysForSequentialArrays;
        $this->strict = $strict;
    }

    /**
     * @param array $sortKeysForSequentialArrays
     * @param bool $strict
     * @return Document
     */
    public static function getInstance(array $sortKeysForSequentialArrays, bool $strict = true): Document
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        self::$instance = new Document($sortKeysForSequentialArrays, $strict);

        return self::getInstance($sortKeysForSequentialArrays, $strict);
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
                    $this->sortKeysForSequentialArrays[$key] ?? []
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
     * @param array $sortKey
     * @return array
     * @throws \RuntimeException
     */
    private function diffSequentialArray(array &$source, array $target, $sortKey = []): array
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

        if (\count($sortKey) === 0) {
            throw new \RuntimeException('Please define sort key for: ' . json_encode($target));
        }

        $this->sortSequentialArray($source, $sortKey);
        $this->sortSequentialArray($target, $sortKey);

        $targetMap = [];
        foreach ($target as $targetValue) {
            $targetSortValue = $this->getSortValueForSortKey($targetValue, $sortKey);

            if ($targetSortValue === null) {
                $targetMap[] = $targetValue;
            } else {
                $targetMap[$targetSortValue] = $targetValue;
            }
        }

        foreach ($source as &$sourceValue) {
            $sourceSortValue = $this->getSortValueForSortKey($sourceValue, $sortKey);

            $targetMap[$sourceSortValue] = $this->diff($sourceValue, $targetMap[$sourceSortValue] ?? []);
        }

        return array_values($targetMap);
    }

    /**
     * @param array $array
     * @param array $sortKeys
     * @return mixed|null
     */
    private function getSortValueForSortKey(array $array, array $sortKeys = [])
    {
        $combinedSortValue = null;

        foreach ($sortKeys as $sortKey) {
            $sortKeyParts = explode('.', $sortKey);

            $sortValueForKey = null;
            foreach($sortKeyParts as $key) {
                if ($sortValueForKey === null) {
                    if (!array_key_exists($key, $array)) {
                        break;
                    }

                    $sortValueForKey = $array[$key];

                    continue;
                }

                if (!array_key_exists($key, $sortValueForKey)) {
                    break;
                }

                $sortValueForKey = $sortValueForKey[$key];
            }

            $combinedSortValue .= $sortValueForKey;
        }

        return $combinedSortValue;
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private function isSameValue($a, $b = null): bool
    {
        return $this->strict ? $a === $b : $a == $b;
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
     * @param array $sortKey
     */
    private function sortSequentialArray(array &$array, array $sortKey = [])
    {
        usort($array, function ($a, $b) use ($sortKey) {
            if (!\is_array($a)) {
                return $a <=> $b;
            }

            $sortValueA = $this->getSortValueForSortKey($a, $sortKey);
            $sortValueB = $this->getSortValueForSortKey($b, $sortKey);

            if (!isset($sortValueA, $sortValueB)) {
                return 0;
            }

            return $sortValueA <=> $sortValueB;
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
