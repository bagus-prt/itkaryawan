<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\FuelStation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureComplaintManager();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'integer', Rule::in(range(1, 12))],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'age' => ['nullable', Rule::in(['baru', 'lama', 'sangat_lama'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ]);
        $today = now()->timezone('Asia/Jakarta')->startOfDay();
        $recent = $today->copy()->subDays(7);
        $older = $today->copy()->subDays(30);

        $complaints = Complaint::query()
            ->with('fuelStation:id,code,address')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('station_code', 'like', "%{$search}%")
                        ->orWhere('station_address', 'like', "%{$search}%")
                        ->orWhereHas('fuelStation', function (Builder $stationQuery) use ($search): void {
                            $stationQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('address', 'like', "%{$search}%");
                        })
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['month'] ?? null, fn (Builder $query, int $month) => $query->whereMonth('complaint_date', $month))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereYear('complaint_date', $year))
            ->when($filters['age'] ?? null, function (Builder $query, string $age) use ($recent, $older): void {
                $query->where(function (Builder $group) use ($age, $recent, $older): void {
                    $this->filterByAge($group, 'complaint_date', $age, $recent->toDateString(), $older->toDateString());
                    $group->orWhere(function (Builder $fallback) use ($age, $recent, $older): void {
                        $fallback->whereNull('complaint_date');
                        $this->filterByAge($fallback, 'created_at', $age, $recent->copy()->utc()->toDateTimeString(), $older->copy()->utc()->toDateTimeString());
                    });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(function (Complaint $complaint) use ($recent, $older): array {
                $date = $complaint->complaint_date ?? $complaint->created_at?->copy()->timezone('Asia/Jakarta');
                $age = match (true) {
                    $date === null => null,
                    $date->toDateString() >= $recent->toDateString() => 'baru',
                    $date->toDateString() >= $older->toDateString() => 'lama',
                    default => 'sangat_lama',
                };

                return [
                    'id' => $complaint->id,
                    'station_code' => $complaint->station_code ?? $complaint->fuelStation?->code,
                    'station_address' => $complaint->station_address ?? $complaint->fuelStation?->address,
                    'description' => $complaint->description,
                    'status' => $complaint->status,
                    'age' => $age,
                    'date' => $date?->format('d/m/Y') ?? '',
                ];
            });

        return Inertia::render('Complaints/Index', [
            'complaints' => $complaints,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'month' => isset($filters['month']) ? (int) $filters['month'] : '',
                'year' => isset($filters['year']) ? (int) $filters['year'] : '',
                'age' => $filters['age'] ?? '',
            ],
            'today' => $today->toDateString(),
        ]);
    }

    private function filterByAge(Builder $query, string $column, string $age, string $recent, string $older): void
    {
        match ($age) {
            'baru' => $query->where($column, '>=', $recent),
            'lama' => $query->where($column, '>=', $older)->where($column, '<', $recent),
            'sangat_lama' => $query->where($column, '<', $older),
        };
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureComplaintManager();
        $validated = $request->validate($this->complaintRules(), $this->validationMessages());
        $this->createComplaint($validated, Auth::id());

        return redirect()
            ->route('complaints.index')
            ->with('success', 'Keluhan SPBU berhasil dicatat.');
    }

    public function publicCreate(): Response
    {
        return Inertia::render('Complaints/PublicCreate', [
            'today' => now()->timezone('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function publicStore(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->complaintRules(), $this->validationMessages());
        $this->createComplaint($validated, null);

        return redirect()
            ->route('complaints.public')
            ->with('success', 'Keluhan berhasil dikirim ke Pertamina.');
    }

    public function qr(): Response
    {
        $this->ensureComplaintManager();

        return Inertia::render('Complaints/Qr', [
            'qrUrl' => route('complaints.public'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function complaintRules(): array
    {
        return [
            'station_code' => ['required', 'string', 'max:50'],
            'station_address' => ['required', 'string', 'max:2000'],
            'description' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'station_code.required' => 'Nomor/kode SPBU wajib diisi.',
            'station_code.max' => 'Nomor/kode SPBU maksimal 50 karakter.',
            'station_address.required' => 'Lokasi/alamat SPBU wajib diisi.',
            'station_address.max' => 'Lokasi/alamat SPBU maksimal 2000 karakter.',
            'description.required' => 'Keterangan keluhan wajib diisi.',
            'description.max' => 'Keterangan keluhan maksimal 10000 karakter.',
        ];
    }

    /**
     * @param  array{station_code: string, station_address: string, description: string}  $validated
     */
    private function createComplaint(array $validated, ?int $creatorId): void
    {
        Complaint::query()->create([
            ...$validated,
            'fuel_station_id' => FuelStation::query()
                ->where('code', $validated['station_code'])
                ->value('id'),
            'complaint_date' => now()->timezone('Asia/Jakarta')->toDateString(),
            'category' => 'Keluhan SPBU',
            'status' => 'baru',
            'created_by' => $creatorId,
        ]);
    }

    private function ensureComplaintManager(): void
    {
        abort_unless(
            in_array(Auth::user()?->role?->name, ['admin', 'manager'], true),
            403,
            'Anda tidak memiliki izin untuk mengelola keluhan SPBU.'
        );
    }
}
