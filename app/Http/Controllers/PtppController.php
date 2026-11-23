<?php

namespace App\Http\Controllers;

use App\Models\PtppProgressUpdate;
use App\Models\PtppRequest;
use App\Models\User;
use App\Services\LeaveSignatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PtppController extends Controller
{
    private const SOURCE_CATEGORIES = [
        'keluhan_pelanggan' => 'Keluhan Pelanggan',
        'audit' => 'Audit',
        'tinjauan_manajemen' => 'Tinjauan Manajemen',
        'survey_pelanggan' => 'Survey Pelanggan',
        'usulan_saran' => 'Usulan / Saran',
        'lainnya' => 'Lainnya',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'tab' => ['sometimes', 'string', Rule::in(['diajukan', 'masuk'])],
        ]);
        $user = Auth::user();
        $role = $user?->role?->name;
        $isEmployee = in_array($role, ['employee', 'karyawan'], true);
        $tab = $filters['tab'] ?? 'diajukan';

        $requests = PtppRequest::query()
            ->with(['user:id,name', 'recipient:id,name'])
            ->when($isEmployee, function (Builder $query) use ($user, $tab): void {
                if ($tab === 'masuk') {
                    $query->where('recipient_id', $user->id)
                        ->where('approval_status', 'disetujui');

                    return;
                }

                $query->where('user_id', $user->id);
            })
            ->when(! $isEmployee && ! in_array($role, ['manager', 'admin'], true), function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhere(function (Builder $recipientQuery) use ($user): void {
                            $recipientQuery->where('recipient_id', $user->id)
                                ->where('approval_status', 'disetujui');
                        });
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString()
            ->through(fn (PtppRequest $ptppRequest): array => [
                'id' => $ptppRequest->id,
                'ptpp_number' => $ptppRequest->ptpp_number,
                'submitter_name' => $ptppRequest->user?->name ?? 'Pengguna',
                'recipient_name' => $ptppRequest->recipient?->name ?? 'Belum ditentukan',
                'created_at' => $ptppRequest->created_at?->format('d/m/Y H:i'),
                'approval_status' => $ptppRequest->approval_status,
                'work_status' => $ptppRequest->work_status,
            ]);

        return Inertia::render('Ptpp/Index', [
            'requests' => $requests,
            'pendingCount' => $role === 'manager'
                ? PtppRequest::query()->where('approval_status', 'menunggu_persetujuan')->count()
                : 0,
            'canCreate' => $isEmployee,
            'isManager' => $role === 'manager',
        ]);
    }

    public function create(): Response
    {
        $this->ensureCanSubmit();

        return Inertia::render('Ptpp/Form', [
            ...$this->formData(),
            'ptppRequest' => null,
            'action' => route('ptpp.store'),
        ]);
    }

    public function edit(PtppRequest $ptppRequest): Response
    {
        $this->ensureCanSubmit();
        abort_unless((int) $ptppRequest->user_id === (int) Auth::id(), 404);
        abort_unless($ptppRequest->approval_status === 'perlu_revisi', 404);

        return Inertia::render('Ptpp/Form', [
            ...$this->formData(),
            'ptppRequest' => $this->requestData($ptppRequest),
            'action' => route('ptpp.update', $ptppRequest),
        ]);
    }

    public function store(Request $request, LeaveSignatureService $signatures): RedirectResponse
    {
        $this->ensureCanSubmit();
        $validated = $this->validateRequest($request);
        $this->ensureValidRecipient((int) $validated['recipient_id']);

        $signaturePath = null;
        $illustrationPath = null;

        try {
            $signaturePath = $signatures->store(
                $validated['sender_signature'],
                'sender_signature',
                'ptpp/signatures'
            );
            $illustrationPath = $request->file('illustration')?->store('ptpp/illustrations', 'local');
            $ptppRequest = DB::transaction(function () use ($validated, $illustrationPath, $signaturePath): PtppRequest {
                $ptppRequest = PtppRequest::query()->create([
                    'ptpp_number' => $validated['ptpp_number'],
                    'user_id' => Auth::id(),
                    'recipient_id' => $validated['recipient_id'],
                    'title' => 'PTPP '.$validated['ptpp_number'],
                    'description' => $validated['description'],
                    'location' => $validated['location'],
                    'to_function' => $validated['to_function'],
                    'from_function' => $validated['from_function'],
                    'sender_signature_name' => $validated['sender_signature_name'],
                    'sender_short_name' => $validated['sender_short_name'],
                    'sender_signature_path' => $signaturePath,
                    'source_category' => $validated['source_category'],
                    'illustration_path' => $illustrationPath,
                    'status' => 'diajukan',
                    'approval_status' => 'menunggu_persetujuan',
                    'work_status' => 'menunggu_diterima',
                    'progress_percent' => 0,
                ]);

                $this->recordHistory(
                    $ptppRequest,
                    'diajukan',
                    null,
                    'menunggu_persetujuan',
                    null,
                    'menunggu_diterima'
                );

                return $ptppRequest;
            });
        } catch (\Throwable $exception) {
            $signatures->delete($signaturePath);

            if ($illustrationPath !== null) {
                Storage::disk('local')->delete($illustrationPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('ptpp.show', $ptppRequest)
            ->with('success', 'Pengajuan PTPP berhasil dikirim ke manager.');
    }

    public function show(PtppRequest $ptppRequest): Response
    {
        $this->authorizeView($ptppRequest);
        $ptppRequest->load([
            'user:id,name',
            'recipient:id,name',
            'reviewer:id,name',
            'progressUpdates.user:id,name',
            'histories.actor:id,name',
        ]);

        $role = Auth::user()?->role?->name;
        $isRecipient = (int) $ptppRequest->recipient_id === (int) Auth::id();
        $isEmployee = in_array($role, ['employee', 'karyawan'], true);

        return Inertia::render('Ptpp/Show', [
            'ptppRequest' => $this->requestData($ptppRequest, true),
            'history' => $this->historyData($ptppRequest),
            'success' => session('success'),
            'backUrl' => route('ptpp.index', $isEmployee
                ? ['tab' => $isRecipient ? 'masuk' : 'diajukan']
                : []),
            'canEdit' => (int) $ptppRequest->user_id === (int) Auth::id()
                && $ptppRequest->approval_status === 'perlu_revisi',
            'canReview' => $role === 'manager'
                && $ptppRequest->approval_status === 'menunggu_persetujuan',
            'canAccept' => $isRecipient
                && $ptppRequest->approval_status === 'disetujui'
                && $ptppRequest->work_status === 'menunggu_diterima',
            'canUpdateWork' => $isRecipient
                && $ptppRequest->approval_status === 'disetujui'
                && $ptppRequest->work_status === 'dalam_pengerjaan',
            'isManager' => $role === 'manager',
            'readOnly' => $role === 'admin',
        ]);
    }

    public function update(
        Request $request,
        PtppRequest $ptppRequest,
        LeaveSignatureService $signatures
    ): RedirectResponse {
        $this->ensureCanSubmit();
        abort_unless((int) $ptppRequest->user_id === (int) Auth::id(), 404);
        abort_unless($ptppRequest->approval_status === 'perlu_revisi', 403);

        $validated = $this->validateRequest($request, $ptppRequest);
        $this->ensureValidRecipient((int) $validated['recipient_id']);
        $newSignaturePath = null;
        $newIllustrationPath = null;
        $oldSignaturePath = $ptppRequest->sender_signature_path;
        $oldIllustrationPath = $ptppRequest->illustration_path;
        $oldReviewerSignaturePath = $ptppRequest->reviewer_signature_path;

        try {
            $newSignaturePath = $signatures->store(
                $validated['sender_signature'],
                'sender_signature',
                'ptpp/signatures'
            );
            $newIllustrationPath = $request->file('illustration')?->store('ptpp/illustrations', 'local');
            DB::transaction(function () use ($ptppRequest, $validated, $newIllustrationPath, $newSignaturePath): void {
                $lockedRequest = PtppRequest::query()
                    ->whereKey($ptppRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless($lockedRequest->user_id === Auth::id(), 404);
                abort_unless($lockedRequest->approval_status === 'perlu_revisi', 403);

                $oldApprovalStatus = $lockedRequest->approval_status;
                $oldWorkStatus = $lockedRequest->work_status;
                $lockedRequest->fill([
                    'ptpp_number' => $validated['ptpp_number'],
                    'recipient_id' => $validated['recipient_id'],
                    'title' => 'PTPP '.$validated['ptpp_number'],
                    'description' => $validated['description'],
                    'location' => $validated['location'],
                    'to_function' => $validated['to_function'],
                    'from_function' => $validated['from_function'],
                    'sender_signature_name' => $validated['sender_signature_name'],
                    'sender_short_name' => $validated['sender_short_name'],
                    'sender_signature_path' => $newSignaturePath,
                    'reviewer_signature_path' => null,
                    'source_category' => $validated['source_category'],
                    'illustration_path' => $newIllustrationPath ?? $lockedRequest->illustration_path,
                    'status' => 'diajukan',
                    'approval_status' => 'menunggu_persetujuan',
                    'work_status' => 'menunggu_diterima',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'reviewer_name_snapshot' => null,
                    'work_received_at' => null,
                ]);
                $lockedRequest->save();

                $this->recordHistory(
                    $lockedRequest,
                    'diajukan_kembali',
                    $oldApprovalStatus,
                    'menunggu_persetujuan',
                    $oldWorkStatus,
                    'menunggu_diterima'
                );
            });
        } catch (\Throwable $exception) {
            $signatures->delete($newSignaturePath);

            if ($newIllustrationPath !== null) {
                Storage::disk('local')->delete($newIllustrationPath);
            }

            throw $exception;
        }

        $signatures->delete($oldSignaturePath);
        $signatures->delete($oldReviewerSignaturePath);

        if ($newIllustrationPath !== null && $oldIllustrationPath !== null) {
            Storage::disk('local')->delete($oldIllustrationPath);
        }

        return redirect()
            ->route('ptpp.show', $ptppRequest)
            ->with('success', 'Perbaikan PTPP berhasil dikirim kembali ke manager.');
    }

    public function review(
        Request $request,
        PtppRequest $ptppRequest,
        LeaveSignatureService $signatures
    ): RedirectResponse {
        $this->ensureManager();
        abort_unless($ptppRequest->approval_status === 'menunggu_persetujuan', 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['disetujui', 'perlu_revisi'])],
            'notes' => ['required', 'string', 'max:2000'],
            'reviewer_signature' => [
                Rule::when($request->input('action') === 'disetujui', ['required'], ['nullable']),
                'string',
                'max:350000',
            ],
        ], [
            'notes.required' => 'Catatan keputusan wajib diisi.',
            'reviewer_signature.required' => 'Tanda tangan manager wajib dibubuhkan untuk menyetujui PTPP.',
        ]);

        $signaturePath = null;

        try {
            if ($validated['action'] === 'disetujui') {
                $signaturePath = $signatures->store(
                    $validated['reviewer_signature'],
                    'reviewer_signature',
                    'ptpp/signatures'
                );
            }

            DB::transaction(function () use ($ptppRequest, $validated, $signaturePath): void {
                $lockedRequest = PtppRequest::query()
                    ->whereKey($ptppRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless($lockedRequest->approval_status === 'menunggu_persetujuan', 403);

                $oldApprovalStatus = $lockedRequest->approval_status;
                $oldWorkStatus = $lockedRequest->work_status;
                $lockedRequest->approval_status = $validated['action'];
                $lockedRequest->status = $validated['action'];
                $lockedRequest->reviewed_by = Auth::id();
                $lockedRequest->reviewed_at = now();
                $lockedRequest->review_notes = $validated['notes'];
                $lockedRequest->reviewer_name_snapshot = Auth::user()->name;
                $lockedRequest->reviewer_signature_path = $signaturePath;
                $lockedRequest->save();

                $this->recordHistory(
                    $lockedRequest,
                    $validated['action'],
                    $oldApprovalStatus,
                    $validated['action'],
                    $oldWorkStatus,
                    $lockedRequest->work_status,
                    $validated['notes']
                );
            });
        } catch (\Throwable $exception) {
            $signatures->delete($signaturePath);

            throw $exception;
        }

        return redirect()
            ->route('ptpp.show', $ptppRequest)
            ->with(
                'success',
                $validated['action'] === 'disetujui'
                    ? 'PTPP disetujui dan sudah dapat dilihat oleh penerima.'
                    : 'PTPP dikembalikan kepada pengaju untuk diperbaiki.'
            );
    }

    public function accept(PtppRequest $ptppRequest): RedirectResponse
    {
        abort_unless((int) $ptppRequest->recipient_id === (int) Auth::id(), 403);

        DB::transaction(function () use ($ptppRequest): void {
            $lockedRequest = PtppRequest::query()
                ->whereKey($ptppRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless((int) $lockedRequest->recipient_id === (int) Auth::id(), 403);
            abort_unless($lockedRequest->approval_status === 'disetujui', 403);
            abort_unless($lockedRequest->work_status === 'menunggu_diterima', 403);

            $lockedRequest->work_status = 'dalam_pengerjaan';
            $lockedRequest->status = 'diproses';
            $lockedRequest->work_received_at = now();
            $lockedRequest->save();

            $this->recordHistory(
                $lockedRequest,
                'diterima_penerima',
                $lockedRequest->approval_status,
                $lockedRequest->approval_status,
                'menunggu_diterima',
                'dalam_pengerjaan'
            );
        });

        return redirect()
            ->route('ptpp.show', $ptppRequest)
            ->with('success', 'Surat diterima. Status pekerjaan sekarang dalam pengerjaan.');
    }

    public function storeUpdate(Request $request, PtppRequest $ptppRequest): RedirectResponse
    {
        abort_unless((int) $ptppRequest->recipient_id === (int) Auth::id(), 403);
        abort_unless($ptppRequest->approval_status === 'disetujui', 403);
        abort_unless($ptppRequest->work_status === 'dalam_pengerjaan', 403);

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'finish' => ['sometimes', 'boolean'],
        ], [
            'notes.required' => 'Keterangan update wajib diisi.',
            'photo.image' => 'Berkas harus berupa gambar.',
            'photo.max' => 'Ukuran gambar maksimal 5 MB.',
        ]);
        $photoPath = $request->file('photo')?->store('ptpp/progress', 'local');
        $isFinished = (bool) ($validated['finish'] ?? false);

        try {
            DB::transaction(function () use ($ptppRequest, $validated, $photoPath, $isFinished): void {
                $lockedRequest = PtppRequest::query()
                    ->whereKey($ptppRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless((int) $lockedRequest->recipient_id === (int) Auth::id(), 403);
                abort_unless($lockedRequest->approval_status === 'disetujui', 403);
                abort_unless($lockedRequest->work_status === 'dalam_pengerjaan', 403);

                $lockedRequest->progressUpdates()->create([
                    'user_id' => Auth::id(),
                    'progress_percent' => $isFinished ? 100 : $lockedRequest->progress_percent,
                    'notes' => $validated['notes'],
                    'photo_path' => $photoPath,
                    'recorded_at' => now(),
                ]);

                if ($isFinished) {
                    $oldWorkStatus = $lockedRequest->work_status;
                    $lockedRequest->work_status = 'selesai';
                    $lockedRequest->status = 'selesai';
                    $lockedRequest->progress_percent = 100;
                    $lockedRequest->completion_submitted_at = now();
                    $lockedRequest->verified_by = Auth::id();
                    $lockedRequest->completed_at = now();
                    $lockedRequest->save();

                    $this->recordHistory(
                        $lockedRequest,
                        'pekerjaan_selesai',
                        $lockedRequest->approval_status,
                        $lockedRequest->approval_status,
                        $oldWorkStatus,
                        'selesai',
                        $validated['notes']
                    );
                }
            });
        } catch (\Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('local')->delete($photoPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('ptpp.show', $ptppRequest)
            ->with(
                'success',
                $isFinished
                    ? 'Update tersimpan dan pekerjaan ditandai selesai.'
                    : 'Update progres berhasil ditambahkan.'
            );
    }

    public function letter(PtppRequest $ptppRequest): Response
    {
        $this->authorizeView($ptppRequest);
        abort_unless($ptppRequest->approval_status === 'disetujui', 404);
        $ptppRequest->load([
            'user:id,name',
            'recipient:id,name',
            'reviewer:id,name',
        ]);

        return Inertia::render('Ptpp/Letter', [
            'ptppRequest' => $this->requestData($ptppRequest),
        ]);
    }

    public function illustration(PtppRequest $ptppRequest): StreamedResponse
    {
        $this->authorizeView($ptppRequest);
        abort_unless($ptppRequest->illustration_path !== null, 404);

        return $this->streamPrivateImage($ptppRequest->illustration_path);
    }

    public function signature(PtppRequest $ptppRequest, string $kind): StreamedResponse
    {
        $this->authorizeView($ptppRequest);
        abort_unless(in_array($kind, ['sender', 'reviewer'], true), 404);

        $path = $kind === 'sender'
            ? $ptppRequest->sender_signature_path
            : $ptppRequest->reviewer_signature_path;

        abort_unless($path !== null, 404);

        return $this->streamPrivateImage($path);
    }

    public function photo(
        PtppRequest $ptppRequest,
        PtppProgressUpdate $update
    ): StreamedResponse {
        $this->authorizeView($ptppRequest);
        abort_unless((int) $update->ptpp_request_id === (int) $ptppRequest->id, 404);
        abort_unless($update->photo_path !== null, 404);

        return $this->streamPrivateImage($update->photo_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'today' => now()->toDateString(),
            'recipients' => User::query()
                ->where('is_active', true)
                ->where('id', '!=', Auth::id())
                ->whereDoesntHave('role', fn (Builder $query) => $query->where('name', 'admin'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'sourceCategories' => self::SOURCE_CATEGORIES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?PtppRequest $ptppRequest = null): array
    {
        return $request->validate([
            'ptpp_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('ptpp_requests', 'ptpp_number')->ignore($ptppRequest?->id),
            ],
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'to_function' => ['required', 'string', 'max:200'],
            'from_function' => ['required', 'string', 'max:200'],
            'sender_signature_name' => ['required', 'string', 'max:255'],
            'sender_short_name' => ['required', 'string', 'max:80'],
            'sender_signature' => ['required', 'string', 'max:350000'],
            'location' => ['required', 'string', 'max:200'],
            'source_category' => ['required', Rule::in(array_keys(self::SOURCE_CATEGORIES))],
            'description' => ['required', 'string', 'max:5000'],
            'illustration' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'ptpp_number.required' => 'Nomor surat PTPP wajib diisi.',
            'ptpp_number.unique' => 'Nomor surat PTPP sudah digunakan.',
            'recipient_id.exists' => 'Pilih penerima surat yang terdaftar.',
            'sender_signature_name.required' => 'Nama untuk tanda tangan pemohon wajib diisi.',
            'sender_signature.required' => 'Tanda tangan pemohon wajib dibubuhkan sebelum mengirim PTPP.',
            'sender_short_name.required' => 'Nama pendek pada kotak kuning wajib diisi.',
            'description.required' => 'Uraian ketidaksesuaian atau potensinya wajib diisi.',
            'illustration.image' => 'Berkas ilustrasi harus berupa gambar.',
            'illustration.max' => 'Ukuran ilustrasi maksimal 5 MB.',
        ]);
    }

    private function ensureValidRecipient(int $recipientId): void
    {
        $isValid = User::query()
            ->whereKey($recipientId)
            ->where('is_active', true)
            ->where('id', '!=', Auth::id())
            ->whereDoesntHave('role', fn (Builder $query) => $query->where('name', 'admin'))
            ->exists();

        if (! $isValid) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Pilih penerima aktif selain akun admin dan akun Anda sendiri.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(PtppRequest $ptppRequest, bool $includeProgressUpdates = false): array
    {
        return [
            'id' => $ptppRequest->id,
            'ptpp_number' => $ptppRequest->ptpp_number,
            'submission_date' => $ptppRequest->created_at?->format('d/m/Y'),
            'created_at' => $ptppRequest->created_at?->format('d/m/Y H:i'),
            'submitter_name' => $ptppRequest->user?->name ?? 'Pengguna',
            'recipient_id' => $ptppRequest->recipient_id,
            'recipient_name' => $ptppRequest->recipient?->name ?? 'Belum ditentukan',
            'to_function' => $ptppRequest->to_function ?? '',
            'from_function' => $ptppRequest->from_function ?? '',
            'sender_signature_name' => $ptppRequest->sender_signature_name ?? $ptppRequest->user?->name,
            'sender_short_name' => $ptppRequest->sender_short_name ?? '',
            'sender_signature_url' => $ptppRequest->sender_signature_path
                ? route('ptpp.signature', [$ptppRequest, 'sender'])
                : null,
            'reviewer_signature_url' => $ptppRequest->reviewer_signature_path
                ? route('ptpp.signature', [$ptppRequest, 'reviewer'])
                : null,
            'location' => $ptppRequest->location ?? '',
            'source_category' => $ptppRequest->source_category,
            'source_category_label' => self::SOURCE_CATEGORIES[$ptppRequest->source_category] ?? 'Lainnya',
            'description' => $ptppRequest->description,
            'illustration_url' => $ptppRequest->illustration_path
                ? route('ptpp.illustration', $ptppRequest)
                : null,
            'approval_status' => $ptppRequest->approval_status,
            'work_status' => $ptppRequest->work_status,
            'review_notes' => $ptppRequest->review_notes,
            'manager_name' => $ptppRequest->reviewer_name_snapshot
                ?? $ptppRequest->reviewer?->name
                ?? 'Menunggu persetujuan manager',
            'reviewed_at' => $ptppRequest->reviewed_at?->format('d/m/Y H:i'),
            'work_received_at' => $ptppRequest->work_received_at?->format('d/m/Y H:i'),
            'completed_at' => $ptppRequest->completed_at?->format('d/m/Y H:i'),
            'progress_updates' => $includeProgressUpdates
                ? $ptppRequest->progressUpdates
                    ->sortBy('recorded_at')
                    ->values()
                    ->map(fn (PtppProgressUpdate $update): array => [
                        'id' => $update->id,
                        'user_name' => $update->user?->name ?? 'Pengguna',
                        'notes' => $update->notes,
                        'recorded_at' => $update->recorded_at?->format('d/m/Y H:i'),
                        'photo_url' => $update->photo_path
                            ? route('ptpp.updates.photo', [$ptppRequest, $update])
                            : null,
                    ])
                    ->all()
                : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyData(PtppRequest $ptppRequest): array
    {
        return $ptppRequest->histories
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn ($history): array => [
                'action' => $history->action,
                'actor_name' => $history->actor?->name ?? 'Sistem',
                'notes' => $history->notes,
                'created_at' => $history->created_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }

    private function authorizeView(PtppRequest $ptppRequest): void
    {
        $role = Auth::user()?->role?->name;

        if (in_array($role, ['manager', 'admin'], true)) {
            return;
        }

        $isSubmitter = (int) $ptppRequest->user_id === (int) Auth::id();
        $isApprovedRecipient = (int) $ptppRequest->recipient_id === (int) Auth::id()
            && $ptppRequest->approval_status === 'disetujui';

        abort_unless($isSubmitter || $isApprovedRecipient, 404);
    }

    private function ensureCanSubmit(): void
    {
        abort_unless(
            in_array(Auth::user()?->role?->name, ['employee', 'karyawan'], true),
            403
        );
    }

    private function ensureManager(): void
    {
        abort_unless(Auth::user()?->role?->name === 'manager', 403);
    }

    private function recordHistory(
        PtppRequest $ptppRequest,
        string $action,
        ?string $oldApprovalStatus,
        ?string $newApprovalStatus,
        ?string $oldWorkStatus,
        ?string $newWorkStatus,
        ?string $notes = null
    ): void {
        $ptppRequest->histories()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'old_approval_status' => $oldApprovalStatus,
            'new_approval_status' => $newApprovalStatus,
            'old_work_status' => $oldWorkStatus,
            'new_work_status' => $newWorkStatus,
            'notes' => $notes,
        ]);
    }

    private function streamPrivateImage(string $path): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mimeType, 'image/'), 404);

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if ($stream === false) {
                throw new \RuntimeException('Unable to read the private PTPP image.');
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
