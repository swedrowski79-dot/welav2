<?php

declare(strict_types=1);

final class PipelineConfig
{
    public static function all(): array
    {
        static $config;

        if ($config !== null) {
            return $config;
        }

        $loaded = require dirname(__DIR__, 2) . '/config/pipeline.php';
        $pipeline = is_array($loaded['pipeline'] ?? null) ? $loaded['pipeline'] : [];
        $jobs = is_array($pipeline['jobs'] ?? null) ? $pipeline['jobs'] : [];

        if ($jobs === []) {
            throw new RuntimeException('Pipeline-Konfiguration enthaelt keine Jobs.');
        }

        $config = $pipeline;

        return $config;
    }

    public static function job(string $job): array
    {
        $jobs = self::jobs();
        $definition = $jobs[$job] ?? null;

        if (!is_array($definition)) {
            throw new InvalidArgumentException('Unbekannter Pipeline-Job: ' . $job);
        }

        return $definition;
    }

    public static function jobs(): array
    {
        return self::all()['jobs'];
    }

    public static function command(string $job, string $basePath = '/app'): string
    {
        $script = self::script($job);

        return sprintf('php %s/%s', rtrim($basePath, '/'), ltrim($script, '/'));
    }

    public static function script(string $job): string
    {
        $script = self::job($job)['script'] ?? null;

        if (!is_string($script) || trim($script) === '') {
            throw new RuntimeException('Pipeline-Job ohne Script: ' . $job);
        }

        return trim($script);
    }

    /**
     * @return list<string>
     */
    public static function fullPipelineSteps(): array
    {
        $steps = self::job('full_pipeline')['sequence'] ?? null;
        $normalized = [];

        foreach (is_array($steps) ? $steps : [] as $step) {
            if (!is_string($step) || $step === '') {
                continue;
            }

            if ($step === 'full_pipeline') {
                throw new RuntimeException('full_pipeline darf sich nicht selbst referenzieren.');
            }

            self::job($step);
            $normalized[] = $step;
        }

        if ($normalized === []) {
            throw new RuntimeException('Pipeline-Konfiguration enthaelt keine full_pipeline-Sequenz.');
        }

        return $normalized;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function sections(string $surface): array
    {
        $sections = self::all()['sections'] ?? [];
        $result = [];

        foreach (is_array($sections) ? $sections : [] as $section) {
            if (!is_array($section)) {
                continue;
            }

            $jobs = [];
            foreach (is_array($section['jobs'] ?? null) ? $section['jobs'] : [] as $job) {
                if (!is_string($job) || $job === '') {
                    continue;
                }

                $definition = self::job($job);
                if (!self::showsOnSurface($definition, $surface)) {
                    continue;
                }

                $jobs[] = array_merge(['name' => $job], $definition);
            }

            if ($jobs === []) {
                continue;
            }

            $result[] = [
                'title' => (string) ($section['title'] ?? ''),
                'description' => (string) ($section['description'] ?? ''),
                'jobs' => $jobs,
            ];
        }

        return $result;
    }

    public static function labelForRunType(string $runType): string
    {
        if ($runType === '') {
            return 'Pipeline';
        }

        $jobs = self::jobs();
        if (isset($jobs[$runType])) {
            $label = $jobs[$runType]['run_type_label'] ?? $jobs[$runType]['label'] ?? null;
            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        $aliases = self::all()['run_type_labels'] ?? [];
        $label = is_array($aliases) ? ($aliases[$runType] ?? null) : null;

        return is_string($label) && $label !== '' ? $label : $runType;
    }

    private static function showsOnSurface(array $definition, string $surface): bool
    {
        $key = match ($surface) {
            'pipeline' => 'show_in_pipeline',
            'sync_runs' => 'show_in_sync_runs',
            default => null,
        };

        if ($key === null) {
            return true;
        }

        return !array_key_exists($key, $definition) || (bool) $definition[$key];
    }
}
