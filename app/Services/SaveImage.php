<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaveImage
{
    public function __construct(private PrepareImage $images) {}

    public function handle(?UploadedFile $upload, string $directory, bool $avatar, Closure $write): Model
    {
        $newPath = $upload ? $this->images->store($upload, $directory, $avatar) : null;

        try {
            return DB::transaction(function () use ($newPath, $write) {
                return $write($newPath);
            }, 3);
        } catch (Throwable $exception) {
            $this->images->delete($newPath);
            throw $exception;
        }
    }

    public function removeAfterCommit(?string $path): void
    {
        DB::afterCommit(fn () => $this->images->delete($path));
    }
}
