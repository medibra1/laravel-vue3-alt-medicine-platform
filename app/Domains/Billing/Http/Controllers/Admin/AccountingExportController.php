<?php

namespace App\Domains\Billing\Http\Controllers\Admin;

use App\Domains\Billing\Services\AccountingJournalBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingExportController extends Controller
{
    /**
     * CSV (semicolon-separated, UTF-8 with BOM so Excel opens it as is),
     * streamed row by row.
     */
    public function __invoke(Request $request, AccountingJournalBuilder $journal): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isGlobalAdmin() || $user->can('accounting.export'), 403);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ]);

        // A manager only ever exports their active center.
        $centerId = $user->isGlobalAdmin() ? ($validated['center_id'] ?? null) : getPermissionsTeamId();
        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);

        return response()->streamDownload(function () use ($journal, $centerId, $from, $to) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, AccountingJournalBuilder::HEADER, ';');

            foreach ($journal->rows($centerId, $from, $to) as $row) {
                fputcsv($output, $row, ';');
            }

            fclose($output);
        }, "journal-comptable-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
