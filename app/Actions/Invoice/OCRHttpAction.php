<?php

namespace App\Actions\Invoice;

use App\Contracts\Invoice\InvoiceParsing;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process as SymphonyProcess;
use App\Models\FACTWM01\FACTWM_MSHCONFIGURATION as Config;

class OCRHttpAction implements InvoiceParsing
{
    public function __construct(
        private $file,
        private ?int $renderDpi = null
    ) {}

    public function parse(): ?string
    {
        $list_ocr_page = Config::where('VVARIABLE', 'ocr_read_document_page')->value('VVALUE') ?? '1,2,last';

        $query = http_build_query([
            'lang' => 'en',
            'pages' => $list_ocr_page,
        ]);

        $baseUrl = trim((string) config('ocr.kbi_http.url', 'http://127.0.0.1:8000'));
        $url = rtrim($baseUrl, '/') . '/ocr/text?' . $query;
        $timeout = (int) config('ocr.kbi_http.timeout', 300);
        $verifySsl = (bool) config('ocr.kbi_http.verify_ssl', true);
        $os = (string) config('ocr.kbi.os', 'windows');
        $ghostscriptRequired = (bool) config('ocr.kbi_http.ghostscript_required', config('ocr.kbi.ghostscript_required', false));
        $normalizedPdfPath = null;

        if ($baseUrl === '') {
            throw new Exception('OCR HTTP endpoint is not configured.', 500);
        }

        [$path, $filename] = $this->resolveFilePayload();

        try {
            if ($ghostscriptRequired && $this->isPdfFile($path)) {
                $normalizedPdfPath = $this->normalizePdfWithGhostscript($path, $os);
                $path = $normalizedPdfPath;
                $filename = basename($normalizedPdfPath);
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                throw new Exception('Failed to open OCR file payload.', 500);
            }

            try {
                $response = Http::timeout($timeout)
                    // ->withOptions([
                    //     'verify' => $verifySsl,
                    // ])
                    ->attach('file', $handle, $filename)
                    ->post($url)
                    ->throw();
            } finally {
                fclose($handle);
            }

            $data = $response->json();

            return is_array($data) ? ($data['text'] ?? '') : '';
        } catch (Exception $e) {
            Log::error('OCR HTTP processing failed: ' . $e->getMessage(), [
                'url' => $url,
                'filename' => $filename,
            ]);

            throw new Exception('OCR HTTP processing failed: ' . $e->getMessage(), 500);
        } finally {
            if (!empty($normalizedPdfPath) && is_file($normalizedPdfPath)) {
                @unlink($normalizedPdfPath);
            }
        }
    }

    private function resolveFilePayload(): array
    {
        if ($this->file instanceof UploadedFile) {
            return [
                $this->file->getRealPath(),
                $this->file->getClientOriginalName(),
            ];
        }

        if (is_string($this->file) && is_file($this->file)) {
            return [
                $this->file,
                basename($this->file),
            ];
        }

        throw new Exception('Invalid file payload for OCR HTTP request.', 500);
    }

    private function isPdfFile(string $path): bool
    {
        return strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function normalizePdfWithGhostscript(string $inputPath, string $os): string
    {
        $ghostscriptBinary = $this->resolveGhostscriptBinary($os);
        if (empty($ghostscriptBinary)) {
            throw new Exception('Ghostscript binary not found. Set OCR_GHOSTSCRIPT_BINARY to full path.', 500);
        }

        $gsTempDir = Storage::disk('local')->path('tmp/ocr-gs');
        Storage::disk('local')->makeDirectory('tmp/ocr-gs');
        if (!is_dir($gsTempDir) && !@mkdir($gsTempDir, 0777, true) && !is_dir($gsTempDir)) {
            throw new Exception('Failed to prepare Ghostscript temporary directory.', 500);
        }

        $normalizedPdfPath = $gsTempDir . DIRECTORY_SEPARATOR . 'normalized-http-' . uniqid('', true) . '.pdf';

        $gsProcess = new SymphonyProcess([
            $ghostscriptBinary,
            '-sDEVICE=pdfwrite',
            '-dDetectDuplicateImages',
            '-dCompressFonts=true',
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            "-sOutputFile={$normalizedPdfPath}",
            $inputPath,
        ]);
        $gsProcess->setTimeout(120);
        $gsProcess->setEnv([
            'PATH' => getenv('PATH') ?: '',
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: '',
            'TEMP' => $gsTempDir,
            'TMP' => $gsTempDir,
            'TMPDIR' => $gsTempDir,
        ]);
        $gsProcess->run();

        if (!$gsProcess->isSuccessful()) {
            throw new Exception('Ghostscript normalize failed: ' . $gsProcess->getErrorOutput(), 500);
        }

        if (!is_file($normalizedPdfPath) || filesize($normalizedPdfPath) === 0) {
            throw new Exception('Ghostscript normalize failed: output file is empty.', 500);
        }

        return $normalizedPdfPath;
    }

    private function resolveGhostscriptBinary(string $os): ?string
    {
        $configured = trim((string) config('ocr.kbi_http.ghostscript_binary', config('ocr.kbi.ghostscript_binary', '')));
        if ($configured !== '') {
            return $configured;
        }

        if (strtolower($os) !== 'windows') {
            return 'gs';
        }

        $candidates = ['gswin64c', 'gswin32c', 'gs'];
        foreach ($candidates as $candidate) {
            if ($this->commandExists($candidate)) {
                return $candidate;
            }
        }

        $programDirs = array_filter([
            getenv('ProgramFiles'),
            getenv('ProgramFiles(x86)'),
        ]);

        foreach ($programDirs as $programDir) {
            $base = rtrim((string) $programDir, '\\/') . DIRECTORY_SEPARATOR . 'gs';
            if (!is_dir($base)) {
                continue;
            }

            $versions = glob($base . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'gswin64c.exe');
            if (is_array($versions) && !empty($versions)) {
                rsort($versions);
                return $versions[0];
            }

            $versions32 = glob($base . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'gswin32c.exe');
            if (is_array($versions32) && !empty($versions32)) {
                rsort($versions32);
                return $versions32[0];
            }
        }

        return null;
    }

    private function commandExists(string $command): bool
    {
        $check = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? ['where', $command]
            : ['which', $command];

        $process = new SymphonyProcess($check);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }
}
