<?php

class Normalizer
{
    private array $config;

    public function __construct(array $normalizeConfig)
    {
        $this->config = $normalizeConfig['normalize'];
    }

    public function normalize(string $entity, array $row): array
    {
        if (!isset($this->config[$entity])) {
            throw new RuntimeException("Normalize config for {$entity} not found.");
        }

        $entityConfig = $this->config[$entity];
        $result = [];

        foreach ($entityConfig['fields'] as $target => $source) {
            $result[$target] = $row[$source] ?? null;
        }

        if (!empty($entityConfig['calculated'])) {
            foreach ($entityConfig['calculated'] as $target => $resolver) {
                $result[$target] = $this->resolveCalculated($resolver, $result, $row);
            }
        }

        return $result;
    }

    private function resolveCalculated(string $resolver, array $normalized, array $raw)
    {
        switch ($resolver) {
            case 'calc:normalize_language_code':
                $value = strtoupper(trim((string)($normalized['language_code'] ?? $raw['language'] ?? $raw['Sprache'] ?? '')));
                $map = ['DE' => 'de', 'EN' => 'en', 'FR' => 'fr', 'NL' => 'nl'];
                return $map[$value] ?? strtolower($value);

            case 'calc:product_type_from_variant_flag':
                $flag = trim((string)($normalized['variant_flag'] ?? ''));
                if ($flag === 'Master') {
                    return 'master';
                }
                if ($flag === '') {
                    return 'standard';
                }
                return 'slave';

            case 'calc:is_master':
                return trim((string)($normalized['variant_flag'] ?? '')) === 'Master' ? 1 : 0;

            case 'calc:is_slave':
                $flag = trim((string)($normalized['variant_flag'] ?? ''));
                return ($flag !== '' && strcasecmp($flag, 'Master') !== 0) ? 1 : 0;

            case 'calc:is_standard':
                return trim((string)($normalized['variant_flag'] ?? '')) === '' ? 1 : 0;

            case 'calc:master_sku':
                $flag = trim((string)($normalized['variant_flag'] ?? ''));
                return ($flag !== '' && strcasecmp($flag, 'Master') !== 0) ? $flag : null;

            default:
                throw new RuntimeException("Unknown calculated resolver: {$resolver}");
        }
    }
}
