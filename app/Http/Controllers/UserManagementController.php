<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\PtppRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    private function ensureAdmin(): void
    {
        abort_unless(
            Auth::user()?->role?->name === 'admin',
            403,
            'Anda tidak memiliki izin untuk mengelola pengguna.'
        );
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ]);

        $users = User::query()
            ->with('role')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role_id'] ?? null, function ($query, $roleId) {
                $query->where('role_id', $roleId);
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where(
                    'is_active',
                    $status === 'active'
                );
            })
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::query()
                ->orderBy('id')
                ->get(['id', 'name', 'label']),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role_id' => $filters['role_id'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'summary' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user = new User;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = $validated['password'];
        $user->role_id = $validated['role_id'];
        $user->is_active = true;
        $user->save();

        return redirect()
            ->route('users.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAdmin();
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        if (
            $user->role?->name === 'admin' &&
            (int) $validated['role_id'] !== (int) $user->role_id &&
            User::where('role_id', $user->role_id)
                ->where('is_active', true)
                ->count() <= 1
        ) {
            return back()->withErrors([
                'role_id' => 'Role Admin tidak dapat diubah karena ini adalah satu-satunya Admin aktif.',
            ]);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role_id = $validated['role_id'];
        $user->save();

        return redirect()
            ->route('users.index')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function toggleStatus(User $user)
    {
        $this->ensureAdmin();

        if ((int) $user->id === (int) Auth::id()) {
            return back()->withErrors([
                'user' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ]);
        }

        if ($user->is_active && $user->role?->name === 'admin') {
            $activeAdmins = User::where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
                ->count();

            if ($activeAdmins <= 1) {
                return back()->withErrors([
                    'user' => 'Admin aktif terakhir tidak dapat dinonaktifkan.',
                ]);
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                $user->is_active
                    ? 'Pengguna berhasil diaktifkan.'
                    : 'Pengguna berhasil dinonaktifkan.'
            );
    }

    public function destroy(User $user)
    {
        $this->ensureAdmin();

        if ((int) $user->id === (int) Auth::id()) {
            return back()->withErrors([
                'user' => 'Anda tidak dapat menghapus akun sendiri.',
            ]);
        }

        if (
            $user->role?->name === 'admin' &&
            $user->is_active &&
            User::where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
                ->count() <= 1
        ) {
            return back()->withErrors([
                'user' => 'Admin aktif terakhir tidak dapat dihapus.',
            ]);
        }

        if (
            DocumentRequest::where('user_id', $user->id)->exists() ||
            PtppRequest::where('user_id', $user->id)->exists() ||
            PtppRequest::where('recipient_id', $user->id)->exists()
        ) {
            return back()->withErrors([
                'user' => 'Akun memiliki riwayat pengajuan dokumen atau PTPP. Nonaktifkan akun agar riwayat tetap tersimpan.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Akun pengguna berhasil dihapus.');
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge([
                'email' => Str::lower(trim($email)),
            ]);
        }
    }
}
