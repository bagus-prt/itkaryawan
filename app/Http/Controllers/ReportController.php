<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ReportController extends Controller
{
    private const REPORTS = [
        'stock' => 'Stok Inventaris',
        'transactions' => 'Transaksi Inventaris',
        'leave' => 'Rekap Cuti Karyawan',
        'ptpp' => 'PTPP',
        'complaints' => 'Keluhan SPBU',
    ];

    private const LEAVE_STATUSES = [
        'diajukan' => 'Menunggu review',
        'disetujui' => 'Disetujui',
        'perlu_revisi' => 'Perlu revisi',
        'ditolak' => 'Ditolak',
    ];

    public function __invoke(Request $request)
    {
        abort_unless($request->user()->role?->name === 'admin', 403);

        $report = $request->validate(['report' => ['nullable', Rule::in(array_keys(self::REPORTS))]])['report'] ?? 'stock';
        [$query, $columns, $fields, $labels] = $this->{$report}();
        $rules = [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', Rule::in([10, 20, 50])],
            'format' => ['nullable', Rule::in(['print', 'csv'])],
            'sort' => ['nullable', Rule::in(array_keys($columns))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];

        foreach ($fields as $key => $field) {
            $rules[$key] = match ($field['type']) {
                'date' => ['nullable', 'date_format:Y-m-d'],
                'text' => ['nullable', 'string', 'max:100'],
                default => ['nullable', Rule::in(array_keys($field['options']))],
            };
        }

        if (isset($fields['end']) && $request->filled('start')) {
            $rules['end'][] = 'after_or_equal:start';
        }

        $validated = $request->validate($rules);
        $filters = array_filter(
            array_intersect_key($validated, $fields + ['search' => true]),
            fn ($value) => $value !== null && $value !== '',
        );
        $this->applyFilters($query, $report, $filters);

        $query = DB::query()->fromSub($query, 'report_rows');
        $sort = $validated['sort'] ?? array_key_first($columns);
        $direction = $validated['direction'] ?? (in_array($sort, ['date', 'updated']) ? 'desc' : 'asc');
        $query->orderBy($sort, $direction)->orderBy('row_id');

        $title = 'Laporan '.self::REPORTS[$report];
        $now = now();
        $period = $this->periodLabel($report, $filters, $now);
        $activeFilters = collect($filters)->except(['start', 'end'])->map(function ($value, $key) use ($fields) {
            $field = $fields[$key] ?? ['label' => 'Pencarian'];

            return $field['label'].': '.($field['options'][$value] ?? $value);
        })->values()->all();
        $format = $validated['format'] ?? null;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($query, $columns, $labels): void {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, array_values($columns), escape: '');
                foreach ($query->cursor() as $row) {
                    $cells = array_map(function ($value) {
                        return is_string($value) && preg_match('/^(?:\s*[=+\-@]|[\t\r\n])/u', $value) ? "'".$value : $value;
                    }, $this->row($row, $columns, $labels));
                    fputcsv($output, $cells, escape: '');
                }
                fclose($output);
            }, $report.'-'.$now->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($format === 'print') {
            return response()->view('reports.print', [
                'title' => $title,
                'period' => $period,
                'activeFilters' => $activeFilters,
                'columns' => $columns,
                'rows' => $query->cursor()->map(fn ($row) => $this->row($row, $columns, $labels)),
                'landscape' => count($columns) > 6,
            ]);
        }

        $export = ['report' => $report, ...$filters, 'sort' => $sort, 'direction' => $direction];

        return Inertia::render('Reports/Index', [
            'reports' => self::REPORTS,
            'report' => $report,
            'title' => $title,
            'period' => $period,
            'columns' => $columns,
            'fields' => $fields,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'printUrl' => route('reports.index', $export + ['format' => 'print']),
            'csvUrl' => route('reports.index', $export + ['format' => 'csv']),
            'rows' => $query->paginate((int) ($validated['per_page'] ?? 10))->withQueryString()
                ->through(fn ($row) => $this->row($row, $columns, $labels)),
        ]);
    }

    private function stock(): array
    {
        return [
            DB::table('inventory_items as i')->leftJoin('inventory_categories as c', 'c.id', '=', 'i.category_id')
                ->where('i.is_active', true)
                ->selectRaw('i.id as row_id, i.name, c.name as category, i.stock_on_hand as stock, i.unit'),
            ['name' => 'Nama barang', 'category' => 'Kategori', 'stock' => 'Stok', 'unit' => 'Satuan'],
            [
                'category' => $this->selectField('Kategori', DB::table('inventory_categories')->orderBy('name')->pluck('name', 'id')->all()),
                'stock_status' => $this->selectField('Kondisi stok', ['available' => 'Aman', 'low' => 'Menipis', 'out' => 'Habis']),
            ],
            [],
        ];
    }

    private function transactions(): array
    {
        $types = ['stock_in' => 'Barang masuk', 'stock_out' => 'Barang keluar', 'loan' => 'Peminjaman', 'return' => 'Pengembalian'];
        $returns = DB::table('inventory_transaction_items as ri')
            ->join('inventory_transactions as rt', 'rt.id', '=', 'ri.inventory_transaction_id')
            ->where('rt.type', 'return')->whereNotNull('ri.related_loan_item_id')
            ->selectRaw('ri.related_loan_item_id, sum(ri.quantity) as quantity, max(rt.transaction_at) as returned_at')
            ->groupBy('ri.related_loan_item_id');

        return [
            DB::table('inventory_transaction_items as ti')->join('inventory_transactions as t', 't.id', '=', 'ti.inventory_transaction_id')
                ->join('inventory_items as i', 'i.id', '=', 'ti.inventory_item_id')
                ->leftJoin('inventory_transaction_items as li', 'li.id', '=', 'ti.related_loan_item_id')
                ->leftJoin('inventory_transactions as lt', 'lt.id', '=', 'li.inventory_transaction_id')
                ->leftJoinSub($returns, 'r', 'r.related_loan_item_id', '=', 'ti.id')
                ->selectRaw("ti.id as row_id, t.transaction_at as date, t.type, i.name as item, ti.quantity, i.unit,
                    coalesce(nullif(trim(t.borrower_name), ''), nullif(trim(lt.borrower_name), '')) as borrower,
                    case when t.type = 'return' then t.transaction_at when t.type = 'loan' and r.quantity >= ti.quantity then r.returned_at end as returned_at"),
            ['date' => 'Tanggal', 'type' => 'Jenis', 'item' => 'Barang', 'quantity' => 'Jumlah', 'unit' => 'Satuan', 'borrower' => 'Peminjam / Penerima', 'returned_at' => 'Tanggal pengembalian'],
            $this->dates() + [
                'type' => $this->selectField('Jenis transaksi', $types),
                'item_kind' => $this->selectField('Jenis barang', ['loanable' => 'Barang pinjaman', 'consumable' => 'Barang habis pakai']),
                'return_status' => $this->selectField('Pengembalian', ['open' => 'Belum kembali penuh', 'closed' => 'Sudah kembali penuh']),
            ],
            ['type' => $types],
        ];
    }

    private function leave(): array
    {
        $duration = DB::connection()->getDriverName() === 'sqlite'
            ? 'cast(julianday(d.end_date) - julianday(d.start_date) as integer) + 1'
            : 'datediff(d.end_date, d.start_date) + 1';

        return [
            DB::table('document_requests as d')->where('d.type', 'cuti')
                ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
                ->selectRaw("d.id as row_id, coalesce(nullif(trim(d.employee_name), ''), u.name) as employee,
                    coalesce(nullif(trim(d.department), ''), 'Belum tercatat') as department,
                    {$duration} as duration, d.start_date, d.end_date"),
            ['employee' => 'Nama', 'department' => 'Bagian', 'duration' => 'Lama cuti (hari)', 'start_date' => 'Tanggal mulai', 'end_date' => 'Tanggal selesai'],
            $this->dates() + [
                'date_basis' => $this->selectField('Dasar tanggal', ['submitted' => 'Tanggal pengajuan', 'leave_period' => 'Periode cuti']),
                'department' => ['label' => 'Bagian', 'type' => 'text'],
                'status' => $this->selectField('Status', self::LEAVE_STATUSES),
            ],
            [],
        ];
    }

    private function ptpp(): array
    {
        $approval = ['menunggu_persetujuan' => 'Menunggu persetujuan', 'disetujui' => 'Disetujui', 'perlu_revisi' => 'Perlu revisi'];
        $progress = ['unfinished' => 'Belum selesai', 'finished' => 'Selesai'];

        return [
            DB::table('ptpp_requests as p')
                ->selectRaw("p.id as row_id, p.created_at as date, p.ptpp_number as code, p.from_function, p.to_function, p.description,
                    case when p.progress_percent = 100 then 'finished' else 'unfinished' end as progress"),
            ['date' => 'Tanggal', 'code' => 'Nomor PTPP', 'from_function' => 'Dari fungsi', 'to_function' => 'Tujuan', 'description' => 'Ketidaksesuaian atau Potensi yang Ditemukan', 'progress' => 'Progres'],
            $this->dates() + [
                'function' => ['label' => 'Fungsi', 'type' => 'text'],
                'approval' => $this->selectField('Status persetujuan', $approval),
                'progress' => $this->selectField('Progres', $progress),
            ],
            ['progress' => $progress],
        ];
    }

    private function complaints(): array
    {
        return [
            DB::table('complaints as c')->leftJoin('fuel_stations as s', 's.id', '=', 'c.fuel_station_id')
                ->selectRaw('c.id as row_id, coalesce(c.complaint_date, c.incident_at, c.created_at) as date, coalesce(c.station_code, s.code) as code, coalesce(c.station_address, s.address) as address, c.category, c.description'),
            ['date' => 'Tanggal', 'code' => 'Kode SPBU', 'address' => 'Lokasi', 'category' => 'Kategori', 'description' => 'Keluhan'],
            $this->dates() + [
                'category' => $this->selectField('Kategori', DB::table('complaints')->distinct()->orderBy('category')->pluck('category', 'category')->all()),
            ],
            [],
        ];
    }

    private function applyFilters(Builder $query, string $report, array $filters): void
    {
        $searchColumns = match ($report) {
            'stock' => ['i.code', 'i.name'],
            'transactions' => ['t.transaction_code', 'i.code', 'i.name', 't.borrower_name', 'lt.borrower_name'],
            'leave' => ['d.employee_name', 'u.name', 'd.department'],
            'ptpp' => ['p.ptpp_number', 'p.title', 'p.description'],
            'complaints' => ['c.station_code', 'c.station_address', 's.code', 's.address', 'c.description'],
        };
        if (isset($filters['search'])) {
            $query->where(function (Builder $search) use ($searchColumns, $filters): void {
                foreach ($searchColumns as $column) {
                    $search->orWhere($column, 'like', '%'.$filters['search'].'%');
                }
            });
        }

        $leavePeriod = $report === 'leave' && ($filters['date_basis'] ?? null) === 'leave_period';
        $date = match ($report) {
            'transactions' => 't.transaction_at',
            'leave' => 'd.created_at',
            'ptpp' => 'p.created_at',
            'complaints' => DB::raw('coalesce(c.complaint_date, c.incident_at, c.created_at)'),
            default => null,
        };
        if ($date && isset($filters['start'])) {
            $query->where($leavePeriod ? 'd.end_date' : $date, '>=', $filters['start']);
        }
        if ($date && isset($filters['end'])) {
            $query->where($leavePeriod ? 'd.start_date' : $date, '<', Carbon::parse($filters['end'])->addDay()->toDateString());
        }

        foreach (match ($report) {
            'stock' => ['category' => 'i.category_id'],
            'transactions' => ['type' => 't.type'],
            'leave' => ['status' => 'd.status'],
            'ptpp' => ['approval' => 'p.approval_status'],
            'complaints' => ['category' => 'c.category'],
        } as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }

        if (isset($filters['stock_status'])) {
            match ($filters['stock_status']) {
                'out' => $query->where('i.stock_on_hand', 0),
                'low' => $query->where('i.stock_on_hand', '>', 0)->whereColumn('i.stock_on_hand', '<=', 'i.minimum_stock'),
                'available' => $query->whereColumn('i.stock_on_hand', '>', 'i.minimum_stock'),
            };
        }
        if (isset($filters['return_status'])) {
            $query->where('t.type', 'loan')->whereRaw(
                'ti.quantity '.($filters['return_status'] === 'open' ? '>' : '<=').' coalesce(r.quantity, 0)',
            );
        }
        if (isset($filters['item_kind'])) {
            $query->where('i.is_borrowable', $filters['item_kind'] === 'loanable');
        }
        if (isset($filters['progress'])) {
            $query->where('p.progress_percent', $filters['progress'] === 'finished' ? '=' : '<>', 100);
        }
        if (isset($filters['department'])) {
            $query->where('d.department', 'like', '%'.$filters['department'].'%');
        }
        if (isset($filters['function'])) {
            $function = '%'.$filters['function'].'%';
            $query->where(fn (Builder $part) => $part->where('p.from_function', 'like', $function)->orWhere('p.to_function', 'like', $function));
        }
    }

    private function row(object $row, array $columns, array $labels): array
    {
        $cells = [];
        foreach (array_keys($columns) as $column) {
            $value = $row->{$column};
            $cells[$column] = $value !== null && in_array($column, ['date', 'start_date', 'end_date', 'returned_at'], true)
                ? Carbon::parse($value)->format('d/m/Y')
                : ($labels[$column][$value] ?? $value);
        }

        return $cells;
    }

    private function periodLabel(string $report, array $filters, Carbon $now): string
    {
        if ($report === 'stock') {
            return 'Posisi stok per '.$now->locale('id')->translatedFormat('j F Y');
        }
        $start = isset($filters['start']) ? Carbon::parse($filters['start'])->locale('id')->translatedFormat('j F Y') : null;
        $end = isset($filters['end']) ? Carbon::parse($filters['end'])->locale('id')->translatedFormat('j F Y') : null;

        return match (true) {
            $start !== null && $end !== null => 'Periode '.$start.' – '.$end,
            $start !== null => 'Sejak '.$start,
            $end !== null => 'Sampai '.$end,
            default => 'Semua periode',
        };
    }

    private function dates(): array
    {
        return [
            'start' => ['label' => 'Dari tanggal', 'type' => 'date'],
            'end' => ['label' => 'Sampai tanggal', 'type' => 'date'],
        ];
    }

    private function selectField(string $label, array $options): array
    {
        return ['label' => $label, 'type' => 'select', 'options' => $options];
    }
}
