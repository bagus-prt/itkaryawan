<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Services\LeaveRequestPeriodSummary;
use App\Services\LeaveSignatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveReviewController extends Controller
{
    public function index(Request $request, LeaveRequestPeriodSummary $periodSummary): Response
    {
        $this->ensureManager();
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

        return Inertia::render('LeaveReviews/Index', [
            'requests' => $requests,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'month' => isset($filters['month']) ? (int) $filters['month'] : '',
                'year' => isset($filters['year']) ? (int) $filters['year'] : '',
            ],
            'periodSummary' => $periodSummary->currentQuarter(),
            'pendingCount' => DocumentRequest::query()
                ->where('type', 'cuti')
                ->where('status', 'diajukan')
                ->count(),
        ]);
    }

    public function update(
        Request $request,
        DocumentRequest $leaveRequest,
        LeaveSignatureService $signatures
    ): RedirectResponse {
        $this->ensureManager();
        abort_unless($leaveRequest->type === 'cuti', 404);
        abort_unless($leaveRequest->status === 'diajukan', 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['disetujui', 'ditolak', 'perlu_revisi'])],
            'notes' => ['required', 'string', 'max:2000'],
            'manager_signature' => ['required_if:action,disetujui', 'string', 'max:350000'],
        ], [
            'notes.required' => 'Catatan keputusan wajib diisi.',
            'manager_signature.required' => 'Tanda tangan wajib dibubuhkan untuk menyetujui cuti.',
        ]);

        $signaturePath = $validated['action'] === 'disetujui'
            ? $signatures->store($validated['manager_signature'], 'manager_signature')
            : null;

        try {
            DB::transaction(function () use ($leaveRequest, $validated, $signaturePath): void {
                $lockedRequest = DocumentRequest::query()
                    ->whereKey($leaveRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($lockedRequest->status === 'diajukan', 403);

                $oldStatus = $lockedRequest->status;
                $lockedRequest->status = $validated['action'];
                $lockedRequest->reviewed_by = Auth::id();
                $lockedRequest->reviewed_at = now();
                $lockedRequest->review_notes = $validated['notes'];
                $lockedRequest->reviewer_name_snapshot = Auth::user()->name;
                $lockedRequest->manager_signature_path = $signaturePath;

                if ($validated['action'] === 'disetujui') {
                    $lockedRequest->letter_number = sprintf('CUTI/%s/%06d', now()->format('Y'), $lockedRequest->id);
                }

                $lockedRequest->save();
                $lockedRequest->histories()->create([
                    'actor_id' => Auth::id(),
                    'action' => $validated['action'],
                    'old_status' => $oldStatus,
                    'new_status' => $validated['action'],
                    'notes' => $validated['notes'],
                ]);
            });
        } catch (\Throwable $exception) {
            $signatures->delete($signaturePath);
            throw $exception;
        }

        return redirect()
            ->route('leave-requests.show', $leaveRequest)
            ->with('success', match ($validated['action']) {
                'disetujui' => 'Pengajuan cuti disetujui.',
                'ditolak' => 'Pengajuan cuti ditolak.',
                default => 'Pengajuan cuti dikembalikan untuk diperbaiki.',
            });
    }

    private function ensureManager(): void
    {
        abort_unless(Auth::user()?->role?->name === 'manager', 403);
    }
}
