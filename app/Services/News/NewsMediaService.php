<?php

namespace App\Services\News;

use App\Models\News;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsMediaService
{
    /** @return array{original:string,card:?string,thumbnail:?string} */
    public function store(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['cover_image' => __('news.validation.image_mime')]);
        }
        $binary = file_get_contents($file->getRealPath());
        if ($binary === false || @getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages(['cover_image' => __('news.validation.image_invalid')]);
        }
        $base = 'news-covers/'.now('UTC')->format('Y/m').'/'.Str::uuid();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'webp'
        };
        $original = $base.'.'.$extension;
        Storage::disk('public')->put($original, $this->reencode($binary, $mime));
        $card = $this->resize($binary, 960, 640, $base.'-card.webp');
        $thumbnail = $this->resize($binary, 360, 240, $base.'-thumb.webp');

        return compact('original', 'card', 'thumbnail');
    }

    public function deleteIfUnused(?string $path): void
    {
        if (! $path) {
            return;
        }
        if (News::query()->where('cover_image', $path)->exists()) {
            return;
        }
        $stem = preg_replace('/\.(jpe?g|png|webp)$/i', '', $path);
        Storage::disk('public')->delete(array_filter([$path, $stem.'-card.webp', $stem.'-thumb.webp']));
    }

    private function reencode(string $binary, string $mime): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $binary;
        }
        $image = @imagecreatefromstring($binary);
        if (! $image) {
            return $binary;
        }
        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 88),
            'image/png' => imagepng($image, null, 7),
            default => imagewebp($image, null, 88),
        };
        $clean = ob_get_clean();
        imagedestroy($image);

        return is_string($clean) ? $clean : $binary;
    }

    private function resize(string $binary, int $maxWidth, int $maxHeight, string $path): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }
        $source = @imagecreatefromstring($binary);
        if (! $source) {
            return null;
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
        ob_start();
        imagewebp($target, null, 84);
        $result = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);
        if (! is_string($result)) {
            return null;
        }
        Storage::disk('public')->put($path, $result);

        return $path;
    }
}
