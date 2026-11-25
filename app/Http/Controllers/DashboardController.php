<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestHistory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\PtppRequest;
use App\Models\PtppRequestHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $month = now()->startOfMonth();
        $leave = DocumentRequest::query()->where('type', 'cuti');
        $ptpp = PtppRequest::query();

        $dashboard = match ($user->role?->name) {
            'admin' => $this->admin($request, $leave, $month),
            'manager' => $this->manager($leave, $ptpp, $month),
            'employee', 'karyawan' => $this->employee(
                $leave->whereBelongsTo($user),
                $ptpp->whereBelongsTo($user),
                $user,
                $month,
            ),
            default => null,
        };

        return Inertia::render('Dashboard', ['dashboard' => $dashboard]);
    }

    private function admin(Request $request, Builder $leave, Carbon $month): array
    {
        $filters = $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $period = isset($filters['period'])
            ? Carbon::createFromFormat('!Y-m', $filters['period'])
            : $month;
        $periodLeave = (clone $leave)->where('created_at', '>=', $period)
            ->where('created_at', '<', $period->copy()->addMonth());
        $statuses = (clone $periodLeave)->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $oldest = (clone $periodLeave)->where('status', 'diajukan')->min('created_at');
        $stockAlerts = InventoryItem::query()->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('stock_on_hand', 0)
                ->orWhereColumn('stock_on_hand', '<=', 'minimum_stock'));
        $returned = InventoryTransactionItem::query()->selectRaw('coalesce(sum(quantity), 0)')
            ->whereColumn('related_loan_item_id', 'inventory_transaction_items.id')
            ->from('inventory_transaction_items as returned_items');
        $openLoans = InventoryTransaction::query()->where('type', 'loan')
            ->whereHas('items', fn (Builder $items) => $items->where('quantity', '>', $returned));
        $complaints = Complaint::query()->where('created_at', '>=', $month)
            ->where('created_at', '<', $month->copy()->addMonth());

        return [
            'role' => 'Admin',
            'period' => $period->format('Y-m'),
            'highlight' => [
                'title' => 'Pemantauan Surat Cuti',
                'value' => (int) $statuses->sum(),
                'label' => 'Pengajuan pada periode terpilih',
                'note' => $oldest
                    ? 'Pengajuan tertua sudah menunggu '.$this->daysWaiting(Carbon::parse($oldest)).' hari.'
                    : 'Tidak ada pengajuan yang menunggu pada periode ini.',
                'details' => [
                    ['label' => 'Menunggu review', 'value' => (int) $statuses->get('diajukan', 0)],
                    ['label' => 'Disetujui', 'value' => (int) $statuses->get('disetujui', 0)],
                    ['label' => 'Perlu revisi', 'value' => (int) $statuses->get('perlu_revisi', 0)],
                    ['label' => 'Ditolak', 'value' => (int) $statuses->get('ditolak', 0)],
                ],
            ],
            'metrics' => [
                $this->metric('Stok menipis atau habis', (clone $stockAlerts)->count(), 'Barang aktif pada batas minimum', route('inventory.index')),
                $this->metric('Peminjaman belum kembali', $openLoans->count(), 'Transaksi dengan sisa barang', route('inventory.transactions.index')),
                $this->metric('Keluhan SPBU masuk', $complaints->count(), $month->copy()->locale('id')->translatedFormat('F Y'), route('complaints.index')),
                $this->metric('Akun aktif', User::query()->where('is_active', true)->count(), 'Pengguna dengan akses aktif', route('users.index', ['status' => 'active'])),
            ],
            'chart' => [
                'title' => 'Tren Pengajuan Surat Cuti',
                'months' => $this->monthLabels($period),
                'series' => [['label' => 'Pengajuan cuti', 'values' => $this->monthlyCounts($leave, $period)]],
            ],
            'panel' => [
                'title' => 'Rekap Surat per Bagian',
                'empty' => 'Belum ada pengajuan cuti pada periode ini.',
                'rows' => (clone $periodLeave)
                    ->selectRaw('department, count(*) as total, sum(case when status = ? then 1 else 0 end) as pending', ['diajukan'])
                    ->groupBy('department')->orderByDesc('total')->orderBy('department')->get()
                    ->map(fn (DocumentRequest $row) => [
                        'title' => filled($row->department) ? $row->department : 'Bagian belum tercatat',
                        'detail' => $row->pending.' menunggu review',
                        'badge' => $row->total.' pengajuan',
                    ])->all(),
            ],
        ];
    }

    private function manager(Builder $leave, Builder $ptpp, Carbon $month): array
    {
        $pendingLeave = (clone $leave)->where('status', 'diajukan');
        $pendingPtpp = (clone $ptpp)->where('approval_status', 'menunggu_persetujuan');
        $leaveCount = (clone $pendingLeave)->count();
        $ptppCount = (clone $pendingPtpp)->count();

        $leaveQueue = $pendingLeave->with('user:id,name')->oldest()->orderBy('id')->limit(6)->get()
            ->map(fn (DocumentRequest $row) => [
                'title' => $row->employee_name ?: ($row->user?->name ?? 'Pengajuan cuti'),
                'detail' => $row->department,
                'badge' => 'Review cuti',
                'date' => $row->created_at?->toDateTimeString(),
                'waitingDays' => $this->daysWaiting($row->created_at),
                'href' => route('leave-requests.show', $row),
            ]);
        $ptppQueue = $pendingPtpp->with('user:id,name')->oldest()->orderBy('id')->limit(6)->get()
            ->map(fn (PtppRequest $row) => [
                'title' => $row->title,
                'detail' => $row->user?->name,
                'badge' => 'Persetujuan PTPP',
                'date' => $row->created_at?->toDateTimeString(),
                'waitingDays' => $this->daysWaiting($row->created_at),
                'href' => route('ptpp.show', $row),
            ]);

        $leaveDecisions = DocumentRequestHistory::query()
            ->whereHas('documentRequest', fn (Builder $query) => $query->where('type', 'cuti'))
            ->whereIn('action', ['disetujui', 'ditolak', 'perlu_revisi']);
        $ptppDecisions = PtppRequestHistory::query()
            ->whereIn('action', ['disetujui', 'perlu_revisi']);

        return [
            'role' => 'Manager',
            'highlight' => [
                'title' => 'Antrean Persetujuan',
                'value' => $leaveCount + $ptppCount,
                'label' => 'Pengajuan membutuhkan keputusan',
                'details' => [
                    ['label' => 'Cuti', 'value' => $leaveCount],
                    ['label' => 'PTPP', 'value' => $ptppCount],
                ],
            ],
            'metrics' => [
                $this->metric('Cuti menunggu review', $leaveCount, 'Pengajuan belum diputuskan', route('leave-reviews.index', ['status' => 'diajukan'])),
                $this->metric('PTPP menunggu persetujuan', $ptppCount, 'Pengajuan belum diputuskan', route('ptpp.index')),
                $this->metric('PTPP dalam pengerjaan', (clone $ptpp)->where('approval_status', 'disetujui')->where('work_status', 'dalam_pengerjaan')->count(), 'Pekerjaan yang sedang berlangsung', route('ptpp.index')),
            ],
            'chart' => [
                'title' => 'Tren Pengajuan dan Keputusan',
                'months' => $this->monthLabels($month),
                'series' => [
                    ['label' => 'Pengajuan masuk', 'values' => array_map(
                        fn (int $cuti, int $ptpp) => $cuti + $ptpp,
                        $this->monthlyCounts($leave, $month),
                        $this->monthlyCounts($ptpp, $month),
                    )],
                    ['label' => 'Keputusan', 'values' => array_map(
                        fn (int $cuti, int $ptpp) => $cuti + $ptpp,
                        $this->monthlyCounts($leaveDecisions, $month),
                        $this->monthlyCounts($ptppDecisions, $month),
                    )],
                ],
            ],
            'panel' => [
                'title' => 'Prioritas Review',
                'empty' => 'Tidak ada pengajuan yang menunggu review.',
                'rows' => $leaveQueue->concat($ptppQueue)->sortBy('date')->take(6)->values()->all(),
            ],
        ];
    }

    private function employee(Builder $leave, Builder $ptpp, User $user, Carbon $month): array
    {
        $incoming = PtppRequest::query()->whereBelongsTo($user, 'recipient')->where('approval_status', 'disetujui');
        $toAccept = (clone $incoming)->where('work_status', 'menunggu_diterima')->count();
        $inProgress = (clone $incoming)->where('work_status', 'dalam_pengerjaan')->count();
        $leaveRevisions = (clone $leave)->where('status', 'perlu_revisi')->count();
        $ptppRevisions = (clone $ptpp)->where('approval_status', 'perlu_revisi')->count();
        $recentLeave = (clone $leave)->latest('updated_at')->latest('id')->limit(6)->get()
            ->map(fn (DocumentRequest $row) => [
                'title' => 'Pengajuan cuti',
                'detail' => $row->status === 'perlu_revisi' ? $row->review_notes : null,
                'badge' => $this->statusLabel($row->status),
                'date' => $row->updated_at?->toDateTimeString(),
                'href' => route('leave-requests.show', $row),
            ]);
        $recentPtpp = (clone $ptpp)->latest('updated_at')->latest('id')->limit(6)->get()
            ->map(fn (PtppRequest $row) => [
                'title' => $row->title,
                'detail' => $row->approval_status === 'perlu_revisi' ? $row->review_notes : 'PTPP diajukan',
                'badge' => $this->statusLabel($row->approval_status === 'disetujui' ? $row->work_status : $row->approval_status),
                'date' => $row->updated_at?->toDateTimeString(),
                'href' => route('ptpp.show', $row),
            ]);
        $recentIncoming = (clone $incoming)->where('user_id', '!=', $user->id)
            ->latest('updated_at')->latest('id')->limit(6)->get()
            ->map(fn (PtppRequest $row) => [
                'title' => $row->title,
                'detail' => 'PTPP masuk',
                'badge' => $this->statusLabel($row->work_status),
                'date' => $row->updated_at?->toDateTimeString(),
                'href' => route('ptpp.show', $row),
            ]);

        return [
            'role' => 'Karyawan',
            'highlight' => [
                'title' => 'Perlu Tindakan Anda',
                'value' => $toAccept + $leaveRevisions + $ptppRevisions + $inProgress,
                'label' => 'Pengajuan atau pekerjaan yang perlu ditindaklanjuti',
                'details' => [
                    ['label' => 'PTPP menunggu diterima', 'value' => $toAccept],
                    ['label' => 'PTPP dalam pengerjaan', 'value' => $inProgress],
                    ['label' => 'Pengajuan perlu revisi', 'value' => $leaveRevisions + $ptppRevisions],
                ],
            ],
            'metrics' => [
                $this->metric('Cuti menunggu review', (clone $leave)->where('status', 'diajukan')->count(), 'Pengajuan cuti Anda', route('leave-requests.index')),
                $this->metric('PTPP saya masih terbuka', (clone $ptpp)->where(fn (Builder $query) => $query
                    ->whereIn('approval_status', ['menunggu_persetujuan', 'perlu_revisi'])
                    ->orWhere(fn (Builder $approved) => $approved->where('approval_status', 'disetujui')
                        ->whereIn('work_status', ['menunggu_diterima', 'dalam_pengerjaan'])))->count(), 'Pengajuan PTPP Anda', route('ptpp.index')),
                $this->metric('PTPP masuk aktif', $toAccept + $inProgress, 'Pekerjaan PTPP untuk Anda', route('ptpp.index', ['tab' => 'masuk'])),
            ],
            'chart' => [
                'title' => 'Riwayat Pengajuan Saya',
                'months' => $this->monthLabels($month),
                'series' => [
                    ['label' => 'Cuti', 'values' => $this->monthlyCounts($leave, $month)],
                    ['label' => 'PTPP', 'values' => $this->monthlyCounts($ptpp, $month)],
                ],
            ],
            'panel' => [
                'title' => 'Aktivitas Terbaru',
                'empty' => 'Belum ada pengajuan atau pekerjaan PTPP.',
                'rows' => $recentLeave->concat($recentPtpp)->concat($recentIncoming)
                    ->sortByDesc('date')->take(6)->values()->all(),
            ],
            'actions' => [
                ['label' => 'Ajukan Cuti', 'href' => route('leave-requests.create')],
                ['label' => 'Buat PTPP', 'href' => route('ptpp.create')],
            ],
        ];
    }

    private function metric(string $label, int $value, string $detail, string $href): array
    {
        return compact('label', 'value', 'detail', 'href');
    }

    private function statusLabel(string $status): string
    {
        return $status === 'diajukan' ? 'Menunggu review' : ucfirst(str_replace('_', ' ', $status));
    }

    private function daysWaiting(?Carbon $createdAt): ?int
    {
        return $createdAt === null ? null : max(0, (int) $createdAt->diffInDays(now()));
    }

    private function monthLabels(Carbon $period): array
    {
        return array_map(
            fn (int $offset) => $period->copy()->subMonths($offset)->locale('id')->translatedFormat('M y'),
            range(5, 0),
        );
    }

    private function monthlyCounts(Builder $query, Carbon $period): array
    {
        $series = (clone $query)
            ->where('created_at', '>=', $period->copy()->subMonths(5))
            ->where('created_at', '<', $period->copy()->addMonth());

        foreach (range(5, 0) as $offset) {
            $start = $period->copy()->subMonths($offset);
            $series->selectRaw("count(case when created_at >= ? and created_at < ? then 1 end) as month_{$offset}", [
                $start,
                $start->copy()->addMonth(),
            ]);
        }

        return array_map('intval', array_values((array) $series->toBase()->first()));
    }
}
