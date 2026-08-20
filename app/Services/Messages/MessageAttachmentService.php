<?php

namespace App\Services\Messages;

use App\Models\ContactMessage;
use App\Models\MessageAttachment;
use App\Models\MessageThreadEntry;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class MessageAttachmentService
{
    /** @var array<string, list<string>> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    /** @param list<UploadedFile> $files @return list<MessageAttachment> */
    public function store(ContactMessage $message, MessageThreadEntry $entry, array $files, User $actor, string $visibility = 'public'): array
    {
        $maximum = min(10, max(1, (int) Setting::valueFor('library_feedback_max_attachments', 5)));
        if (count($files) > $maximum) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.too_many_attachments', ['count' => $maximum])]);
        }

        $stored = [];
        try {
            foreach ($files as $file) {
                $stored[] = $this->storeOne($message, $entry, $file, $actor, $visibility);
            }

            return $stored;
        } catch (\Throwable $exception) {
            foreach ($stored as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
                $attachment->delete();
            }
            throw $exception;
        }
    }

    public function deletePhysical(MessageAttachment $attachment): void
    {
        if (Storage::disk($attachment->disk)->exists($attachment->path) && ! Storage::disk($attachment->disk)->delete($attachment->path)) {
            throw new RuntimeException('Unable to delete message attachment.');
        }
    }

    private function storeOne(ContactMessage $message, MessageThreadEntry $entry, UploadedFile $file, User $actor, string $visibility): MessageAttachment
    {
        abort_unless(in_array($visibility, ['public', 'internal', 'director_only'], true), 422);
        $maxKb = min(51200, max(256, (int) Setting::valueFor('library_feedback_max_attachment_kb', 10240)));
        if (! $file->isValid() || $file->getSize() <= 0 || $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_size', ['size' => $maxKb])]);
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $allowed = (array) Setting::valueFor('library_feedback_allowed_mimes', array_keys(self::MIME_EXTENSIONS));
        $categoryExtensions = (array) ($message->messageCategory?->allowed_attachment_types ?: array_merge(...array_values(self::MIME_EXTENSIONS)));
        if (! isset(self::MIME_EXTENSIONS[$mime]) || ! in_array($mime, $allowed, true) || ! in_array($extension, self::MIME_EXTENSIONS[$mime], true) || ! in_array($extension, $categoryExtensions, true)) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_type')]);
        }

        $raw = file_get_contents($file->getRealPath());
        if (! is_string($raw) || $raw === '') {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
        }
        $sanitized = $this->sanitize($raw, $mime, $file->getRealPath());
        $sha = hash('sha256', $sanitized);
        if ($message->messageAttachments()->where('sha256', $sha)->exists()) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_duplicate')]);
        }

        $publicId = (string) Str::uuid();
        $path = 'message-attachments/'.$message->public_id.'/'.$publicId.'.'.$extension;
        if (! Storage::disk('local')->put($path, $sanitized)) {
            throw new RuntimeException('Unable to store message attachment.');
        }

        try {
            return MessageAttachment::query()->create([
                'contact_message_id' => $message->getKey(), 'thread_entry_id' => $entry->getKey(),
                'uploaded_by' => $actor->getKey(), 'public_id' => $publicId, 'disk' => 'local', 'path' => $path,
                'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                'extension' => $extension, 'mime' => $mime, 'size' => strlen($sanitized), 'sha256' => $sha,
                'visibility' => $visibility, 'scan_status' => 'restricted_allowlist',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function sanitize(string $raw, string $mime, string $temporaryPath): string
    {
        return match ($mime) {
            'image/jpeg' => $this->stripJpegMetadata($raw),
            'image/png' => $this->stripPngMetadata($raw),
            'image/webp' => $this->stripWebpMetadata($raw),
            'application/pdf' => $this->validatePdf($raw),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->validateDocx($raw, $temporaryPath),
            default => throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_type')]),
        };
    }

    private function stripJpegMetadata(string $raw): string
    {
        if (! str_starts_with($raw, "\xFF\xD8")) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
        }
        $output = "\xFF\xD8";
        $offset = 2;
        $length = strlen($raw);
        while ($offset + 4 <= $length) {
            if (ord($raw[$offset]) !== 0xFF) {
                break;
            }
            $marker = ord($raw[$offset + 1]);
            if ($marker === 0xDA) {
                return $output.substr($raw, $offset);
            }
            $segmentLength = unpack('n', substr($raw, $offset + 2, 2))[1] ?? 0;
            if ($segmentLength < 2 || $offset + 2 + $segmentLength > $length) {
                throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
            }
            if (! in_array($marker, [0xE1, 0xED], true)) {
                $output .= substr($raw, $offset, $segmentLength + 2);
            }
            $offset += $segmentLength + 2;
        }
        throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
    }

    private function stripPngMetadata(string $raw): string
    {
        if (! str_starts_with($raw, "\x89PNG\r\n\x1A\n")) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
        }
        $output = substr($raw, 0, 8);
        $offset = 8;
        $safeChunks = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS'];
        while ($offset + 12 <= strlen($raw)) {
            $size = unpack('N', substr($raw, $offset, 4))[1] ?? 0;
            $chunkLength = 12 + $size;
            $type = substr($raw, $offset + 4, 4);
            if ($size > 20_000_000 || $offset + $chunkLength > strlen($raw)) {
                throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
            }
            if (in_array($type, $safeChunks, true)) {
                $output .= substr($raw, $offset, $chunkLength);
            }
            $offset += $chunkLength;
            if ($type === 'IEND') {
                return $output;
            }
        }
        throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
    }

    private function stripWebpMetadata(string $raw): string
    {
        if (substr($raw, 0, 4) !== 'RIFF' || substr($raw, 8, 4) !== 'WEBP') {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
        }
        $chunks = '';
        $offset = 12;
        while ($offset + 8 <= strlen($raw)) {
            $type = substr($raw, $offset, 4);
            $size = unpack('V', substr($raw, $offset + 4, 4))[1] ?? 0;
            $padded = $size + ($size % 2);
            if ($size > 20_000_000 || $offset + 8 + $padded > strlen($raw)) {
                throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
            }
            if (! in_array($type, ['EXIF', 'XMP '], true)) {
                $chunks .= substr($raw, $offset, 8 + $padded);
            }
            $offset += 8 + $padded;
        }

        return 'RIFF'.pack('V', strlen($chunks) + 4).'WEBP'.$chunks;
    }

    private function validatePdf(string $raw): string
    {
        if (! str_starts_with($raw, '%PDF-') || preg_match('/\/(JavaScript|JS|Launch|EmbeddedFile)\b/i', $raw)) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
        }

        return $raw;
    }

    private function validateDocx(string $raw, string $temporaryPath): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.docx_unavailable')]);
        }
        $zip = new ZipArchive;
        if ($zip->open($temporaryPath) !== true || $zip->numFiles > 500) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
        }
        $uncompressed = 0;
        $contentTypes = false;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = (string) ($stat['name'] ?? '');
            $uncompressed += (int) ($stat['size'] ?? 0);
            if ($uncompressed > 50 * 1024 * 1024 || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/vbaProject|macros|activeX/i', $name)) {
                $zip->close();
                throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
            }
            $contentTypes = $contentTypes || $name === '[Content_Types].xml';
        }
        $zip->close();
        if (! $contentTypes) {
            throw ValidationException::withMessages(['attachments' => __('messages.validation.attachment_invalid')]);
        }

        return $raw;
    }
}
