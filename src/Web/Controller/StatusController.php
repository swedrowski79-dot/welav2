<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Request;
use App\Web\Repository\SourceStatusRepository;
use App\Web\Repository\StageConnection;
use App\Web\Repository\StatusRepository;

final class StatusController extends Controller
{
    public function __invoke(Request $request): string
    {
        $statusRepository = new StatusRepository(StageConnection::make());
        $sourceRepository = new SourceStatusRepository();
        $config = \web_config('sources');
        $maskedConfig = [];

        foreach ($config['sources'] as $sourceName => $sourceConfig) {
            $maskedConfig[$sourceName] = $sourceConfig;
            if (isset($maskedConfig[$sourceName]['connection']['password'])) {
                $maskedConfig[$sourceName]['connection']['password'] = '********';
            }
        }

        return $this->render('status/index', [
            'pageTitle' => 'Konfiguration & Status',
            'pageSubtitle' => 'Verbindungsstatus, zentrale ENV-Konfiguration und Tabellenmengen.',
            'sources' => $sourceRepository->statuses(),
            'stageCounts' => $statusRepository->tableCounts(),
            'config' => $maskedConfig,
            'currentPath' => $request->path(),
        ]);
    }
}
