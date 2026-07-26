<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Repository\AttributeDictionaryRepository;
use App\Web\Core\Controller;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\ExtraConnection;
use App\Web\Repository\StageConnection;
use App\Web\Repository\TranslationOverviewRepository;

final class TranslationController extends Controller
{
    public function index(Request $request): string
    {
        $repository = new TranslationOverviewRepository(StageConnection::make());
        $dictionaryRepository = new AttributeDictionaryRepository(ExtraConnection::make());
        $filters = [
            'entity_type' => $this->filterValue($request->string('entity_type'), ['product', 'category', 'attribute'], 'product'),
            'coverage' => $this->filterValue($request->string('coverage'), ['translated', 'missing', 'orphan'], 'translated'),
            'language_code' => $this->filterValue($request->string('language_code'), ['', 'de', 'en', 'fr', 'nl'], ''),
        ];
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $isAttributeView = $filters['entity_type'] === 'attribute';
        $totalRows = $isAttributeView
            ? $dictionaryRepository->countDetailRows($filters)
            : $repository->countDetailRows($filters);
        $paginator = new Paginator($page, $perPage, $totalRows);
        $summary = $repository->summary();
        $summary = array_merge($summary, $dictionaryRepository->summary());
        $detailRows = $isAttributeView
            ? $dictionaryRepository->paginatedDetailRows($filters, $paginator)
            : $repository->paginatedDetailRows($filters, $paginator);

        return $this->render('translations/index', [
            'pageTitle' => 'Translations',
            'pageSubtitle' => 'Uebersicht ueber vorhandene und fehlende Uebersetzungen fuer Produkte, Kategorien und Attribute.',
            'summary' => $summary,
            'filters' => $filters,
            'paginator' => $paginator,
            'detailRows' => $detailRows,
            'dictionaryEditableColumns' => $dictionaryRepository->editableColumns(),
            'bulkMessage' => $request->string('bulk_message'),
            'bulkDeleted' => $request->string('bulk_deleted'),
            'currentPath' => $request->path(),
        ]);
    }

    public function delete(Request $request): string
    {
        $repository = new TranslationOverviewRepository(StageConnection::make());
        $dictionaryRepository = new AttributeDictionaryRepository(ExtraConnection::make());
        $filters = [
            'entity_type' => $this->filterValue($request->postString('entity_type'), ['product', 'category', 'attribute'], 'product'),
            'coverage' => $this->filterValue($request->postString('coverage'), ['translated', 'missing', 'orphan'], 'translated'),
            'language_code' => $this->filterValue($request->postString('language_code'), ['', 'de', 'en', 'fr', 'nl'], ''),
        ];
        $page = max(1, (int) $request->post('page', 1));
        $perPage = max(1, (int) $request->post('per_page', 20));

        $selectedIds = $request->post('selected_ids', []);
        $selectedIds = is_array($selectedIds) ? $selectedIds : [];

        $deletedRows = 0;
        $message = 'Keine Zeilen ausgewaehlt.';

        if ($selectedIds !== []) {
            if ($filters['coverage'] === 'missing') {
                $message = 'In der Sicht ohne Uebersetzung gibt es keine loeschbaren Datensaetze.';
            } elseif ($filters['entity_type'] === 'attribute') {
                $deletedRows = $dictionaryRepository->deleteRows($selectedIds);
                $message = $deletedRows > 0
                    ? $deletedRows . ' Attribut-Begriffe geloescht.'
                    : 'Keine Attribut-Begriffe geloescht.';
            } else {
                $deletedRows = $repository->deleteDetailRows($filters, $selectedIds);
                $message = $deletedRows > 0
                    ? $deletedRows . ' Uebersetzungszeilen geloescht.'
                    : 'Keine Uebersetzungszeilen geloescht.';
            }
        }

        $query = http_build_query([
            'entity_type' => $filters['entity_type'],
            'coverage' => $filters['coverage'],
            'language_code' => $filters['language_code'],
            'page' => $page,
            'per_page' => $perPage,
            'bulk_deleted' => (string) $deletedRows,
            'bulk_message' => $message,
        ]);

        Response::redirect('/translations?' . $query . '#detail-table');

        return '';
    }

    private function filterValue(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
