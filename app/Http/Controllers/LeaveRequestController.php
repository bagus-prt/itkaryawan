<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestHistory;
use App\Models\User;
use App\Services\LeaveRequestPeriodSummary;
use App\Services\LeaveSignatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureEmployee();
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ]);

        $requests = DocumentRequest::query()
            ->where('type', 'cuti')
            ->whereBelongsTo(Auth::user())
            ->with('user:id,name')
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (DocumentRequest $request): array => [
                'id' => $request->id,
                'employee_name' => $request->employee_name ?? $request->user?->name,
                'start_date' => $request->start_date?->format('Y-m-d'),
                'end_date' => $request->end_date?->format('Y-m-d'),
                'status' => $request->status,
                'review_notes' => $request->review_notes,
                'created_at' => $request->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('LeaveRequests/Index', [
            'requests' => $requests,
        ]);
    }

    public function create(): Response
    {
        $this->ensureEmployee();

        return Inertia::render('LeaveRequests/Form', [
            'request' => null,
            'today' => now()->toDateString(),
            'employeeName' => Auth::user()->name,
            'action' => route('leave-requests.store'),
        ]);
    }

    public function store(Request $request, LeaveSignatureService $signatures): RedirectResponse
    {
        $this->ensureEmployee();
        $validated = $this->validateLeaveRequest($request);
        $signaturePath = $signatures->store($validated['employee_signature'], 'employee_signature');
        $data = $this->leaveData($validated);

        try {
            $leaveRequest = DB::transaction(function () use ($data, $signaturePath): DocumentRequest {
                $leaveRequest = DocumentRequest::query()->create([
                    ...$data,
                    'user_id' => Auth::id(),
                    'type' => 'cuti',
                    'status' => 'diajukan',
                    'employee_signature_path' => $signaturePath,
                ]);

                $leaveRequest->histories()->create([
                    'actor_id' => Auth::id(),
                    'action' => 'diajukan',
                    'new_status' => 'diajukan',
                ]);

                return $leaveRequest;
            });
        } catch (\Throwable $exception) {
            $signatures->delete($signaturePath);
            throw $exception;
        }

        return redirect()
            ->route('leave-requests.show', $leaveRequest)
            ->with('success', 'Pengajuan cuti berhasil dikirim.');
    }

    public function show(DocumentRequest $leaveRequest): Response
    {
        $this->authorizeView($leaveRequest);

        return Inertia::render('LeaveRequests/Show', [
            'request' => $this->requestData($leaveRequest),
            'history' => $this->historyData($leaveRequest),
            'success' => session('success'),
            'canEdit' => $this->isEmployee()
                && $leaveRequest->status === 'perlu_revisi',
            'canReview' => Auth::user()->role?->name === 'manager'
                && $leaveRequest->status === 'diajukan',
            'isManager' => Auth::user()->role?->name === 'manager',
            'readOnly' => Auth::user()->role?->name === 'admin',
        ]);
    }

    public function edit(DocumentRequest $leaveRequest): Response
    {
        $this->ensureEmployee();
        $this->assertOwner($leaveRequest);
        abort_unless($leaveRequest->type === 'cuti' && $leaveRequest->status === 'perlu_revisi', 404);

        return Inertia::render('LeaveRequests/Form', [
            'request' => $this->requestData($leaveRequest),
            'today' => now()->toDateString(),
            'employeeName' => Auth::user()->name,
            'action' => route('leave-requests.update', $leaveRequest),
        ]);
    }

    public function update(
        Request $request,
        DocumentRequest $leaveRequest,
        LeaveSignatureService $signatures
    ): RedirectResponse {
        $this->ensureEmployee();
        $this->assertOwner($leaveRequest);
        abort_unless($leaveRequest->type === 'cuti' && $leaveRequest->status === 'perlu_revisi', 403);

        $validated = $this->validateLeaveRequest($request);
        $signaturePath = $signatures->store($validated['employee_signature'], 'employee_signature');
        $data = $this->leaveData($validated);
        $oldSignaturePath = $leaveRequest->employee_signature_path;

        try {
            DB::transaction(function () use ($leaveRequest, $data, $signaturePath): void {
                $lockedRequest = DocumentRequest::query()
                    ->whereKey($leaveRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($lockedRequest->status === 'perlu_revisi', 403);

                $oldStatus = $lockedRequest->status;
                $lockedRequest->fill([
                    ...$data,
                    'status' => 'diajukan',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'reviewer_name_snapshot' => null,
                    'employee_signature_path' => $signaturePath,
                    'manager_signature_path' => null,
                ]);
                $lockedRequest->save();
                $lockedRequest->histories()->create([
                    'actor_id' => Auth::id(),
                    'action' => 'diajukan_kembali',
                    'old_status' => $oldStatus,
                    'new_status' => 'diajukan',
                ]);
            });
        } catch (\Throwable $exception) {
            $signatures->delete($signaturePath);
            throw $exception;
        }

        $signatures->delete($oldSignaturePath);

        return redirect()
            ->route('leave-requests.show', $leaveRequest)
            ->with('success', 'Perbaikan pengajuan cuti berhasil dikirim kembali.');
    }

    public function administration(Request $request, LeaveRequestPeriodSummary $periodSummary): Response
    {
        $this->ensureAdmin();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['diajukan', 'disetujui', 'ditolak', 'perlu_revisi'])],
            'month' => ['nullable', 'integer', Rule::in(range(1, 12))],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ]);

        $requests = DocumentRequest::query()
            ->where('type', 'cuti')
            ->with('user:id,name')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('employee_name', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['month'] ?? null, fn (Builder $query, int $month) => $query->whereMonth('created_at', $month))
            ->when($filters['year'] ?? null, fn (Builder $query, int $year) => $query->whereYear('created_at', $year))
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (DocumentRequest $request): array => [
                'id' => $request->id,
                'employee_name' => $request->employee_name ?? $request->user->name,
                'department' => $request->department,
                'start_date' => $request->start_date?->format('Y-m-d'),
                'end_date' => $request->end_date?->format('Y-m-d'),
                'status' => $request->status,
                'created_at' => $request->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('LeaveRequests/Administration', [
            'requests' => $requests,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'month' => isset($filters['month']) ? (int) $filters['month'] : '',
                'year' => isset($filters['year']) ? (int) $filters['year'] : '',
            ],
            'periodSummary' => $periodSummary->currentQuarter(),
        ]);
    }

    public function letter(DocumentRequest $leaveRequest): Response
    {
        $this->authorizeView($leaveRequest);
        abort_unless($leaveRequest->status === 'disetujui', 404);

        return Inertia::render('LeaveRequests/Letter', [
            'request' => $this->requestData($leaveRequest),
        ]);
    }

    public function signature(DocumentRequest $leaveRequest, string $kind): StreamedResponse
    {
        $this->authorizeView($leaveRequest);
        abort_unless(in_array($kind, ['employee', 'manager'], true), 404);

        $path = $kind === 'employee'
            ? $leaveRequest->employee_signature_path
            : $leaveRequest->manager_signature_path;

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        $disk = Storage::disk('local');

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => method_exists($disk, 'mimeType')
                ? ($disk->mimeType($path) ?: 'application/octet-stream')
                : (mime_content_type($disk->path($path)) ?: 'application/octet-stream'),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLeaveRequest(Request $request): array
    {
        return $request->validate([
            'employee_name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:150'],
            'work_location' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'destination' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:5000'],
            'leave_entitlement_year' => ['required', 'integer', 'between:2000,2100'],
            'leave_entitlement_days' => ['required', 'integer', 'min:0', 'max:366'],
            'leave_taken_days' => ['required', 'integer', 'min:0', 'max:366', 'lte:leave_entitlement_days'],
            'transportation' => ['required', 'string', 'max:100'],
            'expense_paid_by' => ['required', 'string', 'max:100'],
            'return_to_work_date' => ['required', 'date', 'after:end_date'],
            'leave_due_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'companions' => ['nullable', 'array', 'max:3'],
            'companions.*.name' => ['nullable', 'string', 'max:150'],
            'companions.*.notes' => ['nullable', 'string', 'max:150'],
            'employee_signature' => ['required', 'string', 'max:350000'],
        ], [
            'start_date.after_or_equal' => 'Tanggal mulai cuti tidak boleh sebelum hari ini.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'return_to_work_date.after' => 'Tanggal masuk kerja kembali harus setelah tanggal selesai cuti.',
            'leave_due_date.after_or_equal' => 'Tanggal jatuh tempo cuti harus sama atau setelah tanggal selesai.',
            'leave_taken_days.lte' => 'Jumlah cuti yang telah diambil tidak boleh melebihi hak cuti.',
            'employee_signature.required' => 'Tanda tangan pemohon wajib dibubuhkan.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function leaveData(array $validated): array
    {
        $companions = collect($validated['companions'] ?? [])
            ->filter(fn (array $companion): bool => filled($companion['name'] ?? null))
            ->map(fn (array $companion): array => [
                'name' => $companion['name'],
                'notes' => $companion['notes'] ?? '',
            ])
            ->values()
            ->all();

        return [
            'employee_name' => $validated['employee_name'],
            'department' => $validated['department'],
            'work_location' => $validated['work_location'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'destination' => $validated['destination'],
            'reason' => $validated['reason'],
            'leave_entitlement_year' => $validated['leave_entitlement_year'],
            'leave_entitlement_days' => $validated['leave_entitlement_days'],
            'leave_taken_days' => $validated['leave_taken_days'],
            'transportation' => $validated['transportation'],
            'expense_paid_by' => $validated['expense_paid_by'],
            'return_to_work_date' => $validated['return_to_work_date'],
            'leave_due_date' => $validated['leave_due_date'] ?? null,
            'companions' => $companions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(DocumentRequest $leaveRequest): array
    {
        $leaveRequest->loadMissing(['user:id,name', 'reviewer:id,name']);
        $leaveRequestedDays = $leaveRequest->start_date && $leaveRequest->end_date
            ? (int) $leaveRequest->start_date->diffInDays($leaveRequest->end_date) + 1
            : null;

        return [
            'id' => $leaveRequest->id,
            'employee_name' => $leaveRequest->employee_name ?? $leaveRequest->user->name,
            'submitted_by_name' => $leaveRequest->user->name,
            'department' => $leaveRequest->department,
            'work_location' => $leaveRequest->work_location,
            'start_date' => $leaveRequest->start_date?->format('Y-m-d'),
            'end_date' => $leaveRequest->end_date?->format('Y-m-d'),
            'destination' => $leaveRequest->destination,
            'reason' => $leaveRequest->reason,
            'leave_entitlement_year' => $leaveRequest->leave_entitlement_year,
            'leave_entitlement_days' => $leaveRequest->leave_entitlement_days,
            'leave_taken_days' => $leaveRequest->leave_taken_days,
            'leave_requested_days' => $leaveRequestedDays,
            'leave_remaining_days' => $leaveRequest->leave_entitlement_days !== null
                && $leaveRequest->leave_taken_days !== null
                ? $leaveRequest->leave_entitlement_days
                    - $leaveRequest->leave_taken_days
                    - ($leaveRequestedDays ?? 0)
                : null,
            'transportation' => $leaveRequest->transportation,
            'expense_paid_by' => $leaveRequest->expense_paid_by,
            'return_to_work_date' => $leaveRequest->return_to_work_date?->format('Y-m-d'),
            'leave_due_date' => $leaveRequest->leave_due_date?->format('Y-m-d'),
            'companions' => $leaveRequest->companions ?? [],
            'status' => $leaveRequest->status,
            'letter_number' => $leaveRequest->letter_number,
            'review_notes' => $leaveRequest->review_notes,
            'reviewer_name' => $leaveRequest->reviewer_name_snapshot ?? $leaveRequest->reviewer?->name,
            'manager_name' => $leaveRequest->reviewer_name_snapshot
                ?? $leaveRequest->reviewer?->name
                ?? User::query()
                    ->where('is_active', true)
                    ->whereHas('role', fn ($query) => $query->where('name', 'manager'))
                    ->orderBy('id')
                    ->value('name')
                ?? 'IT Manager',
            'employee_signature_url' => $leaveRequest->employee_signature_path
                ? route('leave-requests.signature', [$leaveRequest, 'employee'])
                : null,
            'manager_signature_url' => $leaveRequest->manager_signature_path
                ? route('leave-requests.signature', [$leaveRequest, 'manager'])
                : null,
            'submission_date' => $leaveRequest->created_at?->format('d/m/Y'),
            'created_at' => $leaveRequest->created_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyData(DocumentRequest $leaveRequest): array
    {
        return $leaveRequest->histories()
            ->with('actor:id,name')
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->map(fn (DocumentRequestHistory $history): array => [
                'action' => $history->action,
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'notes' => $history->notes,
                'actor_name' => $history->actor?->name ?? 'Sistem',
                'created_at' => $history->created_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }

    private function authorizeView(DocumentRequest $leaveRequest): void
    {
        abort_unless($leaveRequest->type === 'cuti', 404);

        if ($this->isEmployee()) {
            $this->assertOwner($leaveRequest);

            return;
        }

        abort_unless(in_array(Auth::user()->role?->name, ['manager', 'admin'], true), 403);
    }

    private function assertOwner(DocumentRequest $leaveRequest): void
    {
        abort_unless((int) $leaveRequest->user_id === (int) Auth::id(), 404);
    }

    private function ensureEmployee(): void
    {
        abort_unless($this->isEmployee(), 403);
    }

    private function isEmployee(): bool
    {
        return in_array(Auth::user()?->role?->name, ['employee', 'karyawan'], true);
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->role?->name === 'admin', 403);
    }
}
