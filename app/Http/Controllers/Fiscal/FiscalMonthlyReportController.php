<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Fiscal\FiscalMonthlyReportService;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalMonthlyReportController extends Controller
{
    public function __construct(private readonly FiscalMonthlyReportService $reports) {}

    public function index(Request $request, CurrentBusiness $currentBusiness, CurrentBranch $currentBranch): Response|StreamedResponse
    {
        $business = $currentBusiness->get();
        $branch = $currentBranch->get();
        abort_if($business === null || $branch === null, 404);

        $period = $this->period((string) $request->query('month', now()->format('Y-m')));
        $scope = $request->query('branch_scope') === 'all' && $request->user()?->canAccessAllActiveBranches() ? 'all' : 'current';
        $branchId = $scope === 'all' ? null : $branch->id;
        $report = $this->reports->build($business, $period['from'], $period['to'], $branchId);

        if ($request->query('export') === 'csv') {
            return $this->csv($report['purchase_records'], $period['month']);
        }

        return Inertia::render('Fiscal/VatDashboard', [
            'period' => ['month' => $period['month'], 'date_from' => $period['from']->toDateString(), 'date_to' => $period['to']->toDateString()],
            'branch_scope' => $scope,
            'current_branch' => ['id' => $branch->id, 'name' => $branch->name],
            'branches' => Branch::query()->forBusiness($business->id)->active()->when(! $request->user()?->canAccessAllActiveBranches(), fn ($query) => $query->whereIn('id', $request->user()?->branches()->select('branches.id')))->orderBy('name')->get(['id', 'name']),
            'report' => $report,
        ]);
    }

    /** @return array{month: string, from: CarbonImmutable, to: CarbonImmutable} */
    private function period(string $month): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? $month : now()->format('Y-m');
        $date = CarbonImmutable::createFromFormat('!Y-m', $month);
        if ($date === false || (int) $date->format('Y') < 2000) {
            $date = now()->toImmutable()->startOfMonth();
        }

        return ['month' => $date->format('Y-m'), 'from' => $date->startOfMonth(), 'to' => $date->endOfMonth()];
    }

    /** @param list<array<string, mixed>> $records */
    private function csv(array $records, string $month): StreamedResponse
    {
        return response()->streamDownload(function () use ($records): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Fecha', 'Sucursal', 'Proveedor', 'CUIT', 'Comprobante', 'Punto de venta', 'Numero', 'Neto', 'IVA', 'Exento', 'No gravado', 'Otros', 'Total']);
            foreach ($records as $record) {
                fputcsv($output, [$record['voucher_date'], $record['branch'], $record['supplier'], $record['supplier_cuit'], $record['document_type'], $record['point_of_sale'], $record['number'], $record['net_amount'], $record['vat_amount'], $record['exempt_amount'], $record['non_taxed_amount'], $record['other_taxes_amount'], $record['total_amount']]);
            }
            fclose($output);
        }, "iva-compras-{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
