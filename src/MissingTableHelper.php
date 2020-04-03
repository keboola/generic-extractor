<?php

namespace Keboola\GenericExtractor;

use Keboola\Csv\CsvFile;
use Keboola\GenericExtractor\Exception\UserException;

class MissingTableHelper
{
    public static function checkConfigs($configs, $dataDir)
    {
        foreach ($configs as $config) {
            if ($config->getAttribute('mappings')) {
                foreach ($config->getAttribute('mappings') as $name => $mapping) {
                    if ($config->getAttribute('outputBucket')) {
                        $destinationBase = $dataDir . '/out/tables/' . $config->getAttribute('outputBucket') . '.';
                    } else {
                        $destinationBase = $dataDir . '/out/tables/';
                    }
                    self::fillMissingTableMapping(
                        $destinationBase,
                        $config->getAttribute('outputBucket'),
                        $config->getAttribute('incremental'),
                        $name,
                        $mapping
                    );
                }
            }
        }
    }

    private static function fillMissingTableMapping(
        $baseFileName,
        $outputBucket,
        $incremental,
        $name,
        $mapping,
        $parentKey = []
    ) {
        $columns = [];
        $primaryKey = [];
        foreach ($mapping as $itemName => $item) {
            if (!is_array($item)) {
                $columns[] = $item;
            } elseif (empty($item['type']) || (($item['type'] === 'column') || ($item['type'] === 'user'))) {
                $columns[] = $item['mapping']['destination'];
                if (!empty($item['mapping']['primaryKey'])) {
                    $primaryKey[] = $item['mapping']['destination'];
                }
            } elseif ($item['type'] === 'table') {
                self::fillMissingTableMapping(
                    $baseFileName,
                    $outputBucket,
                    $incremental,
                    $item['destination'],
                    $item['tableMapping'],
                    empty($item['parentKey']) ? ['destination' => $name . '_pk'] : $item['parentKey']
                );
            } else {
                throw new UserException(sprintf('Invalid mapping type "%s".', $item['type']));
            }
        }
        /* this is intentionally after to produce consistent results with generic, where parent key
            is appended to the end of the table */
        if ($parentKey) {
            $columns[] = $parentKey['destination'];
            if (!empty($parentKey['primaryKey'])) {
                $primaryKey[] = $parentKey['destination'];
            }
        }
        /* the condition for file existence is intentionally so far in checking, if it were any earlier, we would
            skip non-existent child mappings of an existent parent */
        if ($columns && !file_exists($baseFileName . $name)) {
            $csvFile = new CsvFile($baseFileName . $name);
            $csvFile->writeRow($columns);
            $manifest = [
                'incremental' => $incremental,
            ];
            if ($outputBucket) {
                $manifest['destination'] = 'in.c-' . $outputBucket . '.' . $name;
            }
            if ($primaryKey) {
                $manifest['primary_key'] = $primaryKey;
            }
            file_put_contents($baseFileName . $name . '.manifest', json_encode($manifest));
        }
    }
}
