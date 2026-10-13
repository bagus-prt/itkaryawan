
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IT Karyawan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="mx-auto max-w-4xl rounded-xl bg-white p-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        <p class="mt-2 text-gray-600">
            Login berhasil.
        </p>

        <p class="mt-2">
            Selamat datang, {{ auth()->user()->name }}.
        </p>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf

            <button class="rounded-lg bg-red-600 px-4 py-2 text-white">
                Logout
            </button>
        </form>
    </div>
</body>
</html>
