<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeaveSignatureService
{
    public function store(string $signature, string $field, string $directory = 'leave-signatures'): string
    {
        if (! preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $signature, $matches)) {
            throw ValidationException::withMessages([
                $field => 'Tanda tangan harus berupa gambar PNG yang valid.',
            ]);
        }

        $image = base64_decode($matches[1], true);
        $imageInformation = $image === false ? false : @getimagesizefromstring($image);

        if (
            $image === false ||
            strlen($image) > 262144 ||
            $imageInformation === false ||
            $imageInformation['mime'] !== 'image/png'
        ) {
            throw ValidationException::withMessages([
                $field => 'Tanda tangan tidak valid atau ukurannya terlalu besar.',
            ]);
        }

        $path = $directory.'/'.Str::uuid().'.png';

        if (! Storage::disk('local')->put($path, $image)) {
            throw new \RuntimeException('Tanda tangan tidak dapat disimpan.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}
