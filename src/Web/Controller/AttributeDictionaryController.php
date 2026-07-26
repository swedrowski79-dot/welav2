<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Web\Core\Controller;
use App\Web\Core\Paginator;
use App\Web\Core\Request;
use App\Web\Core\Response;
use App\Web\Repository\AttributeDictionaryRepository;
use App\Web\Repository\ExtraConnection;
use RuntimeException;

final class AttributeDictionaryController extends Controller
{
    public function index(Request $request): string
    {
        $repository = new AttributeDictionaryRepository(ExtraConnection::make());
        $search = $request->string('q');
        $status = $this->filterValue($request->string('status', '1'), ['', '1', '0'], '1');
        $page = max(1, $request->int('page', 1));
        $perPage = $this->perPage($request);
        $paginator = new Paginator($page, $perPage, $repository->countRows($search, $status));

        return $this->render('attribute-dictionary/index', [
            'pageTitle' => 'Attribut-Dictionary',
            'pageSubtitle' => 'Zentrale Attribut-Begriffe aus afs_extras mit Pflege fuer Uebersetzungen und is_active.',
            'metrics' => $repository->metrics(),
            'rows' => $repository->paginatedRows($search, $status, $paginator),
            'editableColumns' => $repository->editableColumns(),
            'search' => $search,
            'statusFilter' => $status,
            'paginator' => $paginator,
            'currentPath' => $request->path(),
        ]);
    }

    public function update(Request $request): string
    {
        $repository = new AttributeDictionaryRepository(ExtraConnection::make());
        $id = $request->postString('id');
        $field = $request->postString('field');
        $value = (string) $request->post('value', '');

        if ($id === '' || $field === '') {
            Response::json(['ok' => false, 'message' => 'Datensatz und Feld sind erforderlich.'], 422);

            return '';
        }

        try {
            $row = $repository->updateField($id, $field, $value);
        } catch (RuntimeException $exception) {
            $status = $exception->getMessage() === 'Record not found.' ? 404 : 422;
            Response::json(['ok' => false, 'message' => $exception->getMessage()], $status);

            return '';
        }

        Response::json([
            'ok' => true,
            'message' => 'Feld gespeichert.',
            'value' => $row[$field] ?? null,
            'isNull' => !array_key_exists($field, $row) || $row[$field] === null,
        ]);

        return '';
    }

    private function filterValue(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
