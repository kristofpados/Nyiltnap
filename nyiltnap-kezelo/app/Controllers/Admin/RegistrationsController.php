<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Program;
use App\Models\Registration;
use App\Support\RegistrationFilters;

final class RegistrationsController extends AdminController
{
    private const LIST_PATH = '/admin/registrations';

    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $programs = Program::all();
        $filters = $this->filters($programs);

        $total = Registration::count($filters);
        $pages = $filters->clampPage($total);
        $rows = Registration::search($filters, $filters->perPage, $filters->offset());
        $grandTotal = $filters->hasActiveFilters() ? Registration::count(new RegistrationFilters()) : $total;

        $query = $filters->toQuery();
        $this->render('admin/registrations', [
            'title'      => 'Jelentkezések',
            'active'     => 'registrations',
            'admin'      => $admin,
            'programs'   => $programs,
            'filters'    => $filters,
            'rows'       => $rows,
            'total'      => $total,
            'grandTotal' => $grandTotal,
            'pages'      => $pages,
            'returnTo'   => self::LIST_PATH . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''),
        ]);
    }

    /** CSV of the currently filtered list (read-only GET, admin only). */
    public function export(): void
    {
        Auth::requireAdmin();
        $filters = $this->filters(Program::all());
        $rows = Registration::search($filters, null);

        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="jelentkezesek-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new HttpException(500);
        }
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly
        $this->csvRow($out, ['Azonosító', 'Teljes név', 'Jelenlegi iskola', 'Szakmák', 'Kollégium', 'Végzős', 'Beküldve']);
        foreach ($rows as $row) {
            $this->csvRow($out, [
                format_reference($row['reference_code']),
                $row['full_name'],
                $row['current_school'],
                implode(', ', array_column($row['programs'], 'name')),
                residency_label($row['residency_preference']),
                $row['is_graduating'] ? 'Igen' : 'Nem',
                (string) $row['created_at'],
            ]);
        }
        fclose($out);
    }

    /** Confirmation page — also the no-JavaScript fallback of the delete dialog. GET never deletes. */
    public function confirmDelete(string $id): void
    {
        $admin = Auth::requireAdmin();
        $registration = Registration::find((int) $id);
        if ($registration === null) {
            throw new HttpException(404, 'Ez a jelentkezés nem található — lehet, hogy időközben már törölték.');
        }

        $this->render('admin/delete', [
            'title'        => 'Jelentkezés törlése',
            'active'       => 'registrations',
            'admin'        => $admin,
            'registration' => $registration,
            'returnTo'     => safe_admin_path(Request::query('return'), self::LIST_PATH),
        ]);
    }

    public function delete(string $id): void
    {
        Auth::requireAdmin();
        Csrf::verify();

        $returnTo = safe_admin_path(Request::string($_POST, 'return'), self::LIST_PATH);
        $registration = Registration::find((int) $id);
        $result = Registration::delete((int) $id, Request::string($_POST, 'reference'));

        match ($result) {
            Registration::DELETED => Session::flash('success', sprintf(
                '%s jelentkezése (%s) törölve.',
                (string) ($registration['full_name'] ?? 'A'),
                format_reference((string) ($registration['reference_code'] ?? '')),
            )),
            Registration::NOT_FOUND => Session::flash('error', 'Ez a jelentkezés már nem létezik — valószínűleg időközben valaki törölte.'),
            default => Session::flash('error', 'A törlést nem hajtottuk végre, mert a kiválasztott jelentkezés adatai nem egyeznek. Töltsd újra a listát, és próbáld meg ismét.'),
        };

        Response::redirect($returnTo);
    }

    /** @param list<array{id: int}> $programs */
    private function filters(array $programs): RegistrationFilters
    {
        return RegistrationFilters::fromQuery(
            $_GET,
            array_map(static fn (array $p): int => $p['id'], $programs),
            (array) config('admin.per_page_options', [25, 50, 100]),
            (int) config('admin.default_per_page', 25),
        );
    }

    /**
     * Writes one CSV line. Cells that a spreadsheet would treat as a formula
     * get a leading apostrophe (CSV/formula injection protection).
     *
     * @param resource     $out
     * @param list<string> $cells
     */
    private function csvRow($out, array $cells): void
    {
        $safe = array_map(static function (string $cell): string {
            return $cell !== '' && str_contains("=+-@\t\r", $cell[0]) ? "'" . $cell : $cell;
        }, $cells);

        fputcsv($out, $safe, ';', '"', '');
    }
}
