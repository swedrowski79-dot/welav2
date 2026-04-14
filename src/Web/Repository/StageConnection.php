<?php

declare(strict_types=1);

namespace App\Web\Repository;

final class StageConnection
{
    public static function make(): \PDO
    {
        $sources = \web_config('sources');

        return \ConnectionFactory::create($sources['sources']['stage']);
    }
}
