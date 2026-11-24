<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PrepareImage
{
    public function store(UploadedFile $upload, string $directory, bool $avatar = false): string
    {
        $bytes = file_get_contents($upload->getRealPath());
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] > 4096 || $info[1] > 4096 || strlen($bytes) > 2097152) {
            throw ValidationException::withMessages([$avatar ? 'avatar' : 'image' => 'Upload a valid JPEG, PNG or WebP image up to 2 MB and 4096 × 4096 pixels.']);
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            throw ValidationException::withMessages([$avatar ? 'avatar' : 'image' => 'The image could not be decoded.']);
        }

        [$width, $height] = $info;
        $side = min($width, $height);
        $scale = min(1, 1200 / max($width, $height));
        $outWidth = $avatar ? 256 : max(1, (int) round($width * $scale));
        $outHeight = $avatar ? 256 : max(1, (int) round($height * $scale));
        $output = imagecreatetruecolor($outWidth, $outHeight);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagefill($output, 0, 0, imagecolorallocatealpha($output, 255, 255, 255, 127));

        try {
            imagecopyresampled($output, $source, 0, 0, $avatar ? intdiv($width - $side, 2) : 0, $avatar ? intdiv($height - $side, 2) : 0,
                $outWidth, $outHeight, $avatar ? $side : $width, $avatar ? $side : $height);
            ob_start();
            imagepng($output, null, 6);
            $prepared = ob_get_clean();
            $path = $directory.'/'.Str::uuid().'.png';
            if (! Storage::disk('public')->put($path, $prepared)) {
                throw new RuntimeException('Unable to save the prepared image.');
            }

            return $path;
        } finally {
            imagedestroy($source);
            imagedestroy($output);
        }
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            if (! Storage::disk('public')->delete($path)) {
                Log::warning('Image cleanup failed.', ['path' => $path]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Image cleanup failed.', ['path' => $path, 'error' => $exception->getMessage()]);
        }
    }
}
