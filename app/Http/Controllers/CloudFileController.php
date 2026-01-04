<?php

namespace App\Http\Controllers;

use App\Models\CloudFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CloudFileController extends Controller
{
    private function iniSizeToBytes(?string $value): int
    {
        if ($value === null) {
            return 0;
        }

        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $lastChar = strtoupper(substr($value, -1));
        if (ctype_digit($lastChar)) {
            return (int) $value;
        }

        $number = (float) substr($value, 0, -1);
        return match ($lastChar) {
            'K' => (int) round($number * 1024),
            'M' => (int) round($number * 1024 * 1024),
            'G' => (int) round($number * 1024 * 1024 * 1024),
            'T' => (int) round($number * 1024 * 1024 * 1024 * 1024),
            default => 0,
        };
    }

    private function maxUploadMbFromPhpIni(): int
    {
        $uploadBytes = $this->iniSizeToBytes(ini_get('upload_max_filesize'));
        $postBytes = $this->iniSizeToBytes(ini_get('post_max_size'));

        if ($uploadBytes <= 0 || $postBytes <= 0) {
            return 0;
        }

        $bytes = min($uploadBytes, $postBytes);
        return max(1, (int) floor($bytes / (1024 * 1024)));
    }

    public function index(Request $request)
    {
        $folderPath = (string) $request->query('folder_path', '/');
        $folderPath = trim($folderPath) === '' ? '/' : trim($folderPath);
        if (!str_starts_with($folderPath, '/')) {
            $folderPath = '/' . $folderPath;
        }

        $files = CloudFile::query()
            ->with('uploader')
            ->where('folder_path', $folderPath)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('cloud.index', [
            'files' => $files,
            'folderPath' => $folderPath,
        ]);
    }

    public function create(Request $request)
    {
        $folderPath = (string) $request->query('folder_path', '/');
        $folderPath = trim($folderPath) === '' ? '/' : trim($folderPath);
        if (!str_starts_with($folderPath, '/')) {
            $folderPath = '/' . $folderPath;
        }

        $maxUploadMb = $this->maxUploadMbFromPhpIni();

        return view('cloud.create', [
            'folderPath' => $folderPath,
            'maxUploadMb' => $maxUploadMb,
        ]);
    }

    public function store(Request $request)
    {
        $maxKb = (int) config('cloud.max_upload_kb', 10240);

        $validated = $request->validate([
            'folder_path' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:' . $maxKb],
        ], [
            'file.max' => __('The file may not be greater than :max kilobytes.', ['max' => $maxKb]),
        ]);

        $folderPath = isset($validated['folder_path']) ? trim((string) $validated['folder_path']) : '/';
        $folderPath = $folderPath === '' ? '/' : $folderPath;
        if (!str_starts_with($folderPath, '/')) {
            $folderPath = '/' . $folderPath;
        }

        $file = $request->file('file');
        if ($file === null) {
            abort(422);
        }

        $dir = 'private/cloud/' . now()->format('Y') . '/' . now()->format('m');
        $storedPath = $file->store($dir, 'local');

        $cloudFile = CloudFile::create([
            'folder_path' => $folderPath,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return redirect()
            ->route('cloud.index', ['folder_path' => $cloudFile->folder_path])
            ->with('status', __('File uploaded.'));
    }

    public function download(CloudFile $cloudFile)
    {
        if (!Storage::disk('local')->exists($cloudFile->stored_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $cloudFile->stored_path,
            $cloudFile->original_name
        );
    }

    public function destroy(CloudFile $cloudFile)
    {
        if (Storage::disk('local')->exists($cloudFile->stored_path)) {
            Storage::disk('local')->delete($cloudFile->stored_path);
        }

        $folderPath = $cloudFile->folder_path;
        $cloudFile->delete();

        return redirect()
            ->route('cloud.index', ['folder_path' => $folderPath])
            ->with('status', __('File deleted.'));
    }
}
