<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Html;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\StageConnection;

final class CronController extends Controller
{
    public function index(Request $request): string
    {
        $config = \web_config('cron');
        $service = new \CronScheduleService(
            StageConnection::make(),
            is_array($config['schedule'] ?? null) ? $config['schedule'] : []
        );

        return $this->render('cron/index', [
            'pageTitle' => 'Cron-Zeitplan',
            'pageSubtitle' => 'Automatische Pipeline-Laeufe aktivieren und das Intervall einstellen.',
            'settings' => $service->settings(),
            'steps' => is_array($config['steps'] ?? null) ? array_keys($config['steps']) : [],
            'minimumInterval' => $service->minimumInterval(),
            'maximumInterval' => $service->maximumInterval(),
            'saved' => $request->query('saved') === '1',
            'errorMessage' => $request->string('error'),
            'currentPath' => '/cron',
        ]);
    }

    public function save(Request $request): void
    {
        try {
            $rawInterval = $request->postString('interval_minutes');

            if ($rawInterval === '' || filter_var($rawInterval, FILTER_VALIDATE_INT) === false) {
                throw new \InvalidArgumentException('Bitte ein gueltiges Intervall in Minuten eingeben.');
            }

            $config = \web_config('cron');
            $service = new \CronScheduleService(
                StageConnection::make(),
                is_array($config['schedule'] ?? null) ? $config['schedule'] : []
            );
            $service->updateSettings(
                $request->hasPost('enabled'),
                (int) $rawInterval
            );

            Response::redirect(Html::buildUrl('/cron', ['saved' => 1]));
        } catch (\Throwable $exception) {
            Response::redirect(Html::buildUrl('/cron', ['error' => $exception->getMessage()]));
        }
    }
}
