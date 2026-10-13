
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IT Karyawan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">

    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold text-gray-900">
            IT Karyawan
        </h1>

        <p class="mt-2 text-sm text-gray-500">
            Silakan masuk menggunakan akun Anda.
        </p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-2 block text-sm font-medium">
                    Email
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                    class="w-full rounded-lg border border-gray-300 px-4 py-3 outline-none focus:border-purple-600"
                >

                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium">
                    Password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded-lg border border-gray-300 px-4 py-3 outline-none focus:border-purple-600"
                >

                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1">
                Ingat saya
            </label>

            <button
                type="submit"
                class="w-full rounded-lg bg-purple-700 px-4 py-3 font-semibold text-white hover:bg-purple-800"
            >
                Masuk
            </button>
        </form>
    </div>

</body>
</html>
