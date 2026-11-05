<?php

namespace App\Actions\Invoice;

use App\Contracts\Invoice\InvoiceParsing;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process as SymphonyProcess;

class OCRKelolaAction implements InvoiceParsing
{
    private $file;
    private int $renderDpi;

    public function __construct($file, ?int $renderDpi = null)
    {
        $this->file = $file;
        $dpi = $renderDpi ?? 140;
        $this->renderDpi = max(140, $dpi);
    }

    public function parse(): ?string
    {
        $relativePath = null;
        $shouldDeleteTempCopy = false;
        $normalizedPdfPath = null;

        try {
            $serv = config('ocr.kbi.service_loc');
            $os = config('ocr.kbi.os');
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

            $path = null;
            if ($this->file instanceof UploadedFile) {
                $existingPath = $this->file->getRealPath();
                if (is_string($existingPath) && $existingPath !== '' && is_file($existingPath)) {
                    $path = $existingPath;
                }
            } elseif (is_string($this->file) && is_file($this->file)) {
                $path = $this->file;
            }

            if (empty($path)) {
                $relativePath = Storage::disk('local')->putFile('ocr', $this->file);
                $path = Storage::disk('local')->path($relativePath);
                $shouldDeleteTempCopy = true;
            }

            $currentExtension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($currentExtension, $allowedExtensions, true) && $this->file instanceof UploadedFile) {
                $clientExtension = strtolower((string) $this->file->getClientOriginalExtension());
                if (in_array($clientExtension, $allowedExtensions, true)) {
                    if ($shouldDeleteTempCopy && !empty($relativePath) && Storage::disk('local')->exists($relativePath)) {
                        Storage::disk('local')->delete($relativePath);
                    }

                    $tempName = 'ocr-input-' . uniqid('', true) . '.' . $clientExtension;
                    $relativePath = $this->file->storeAs('ocr', $tempName, 'local');
                    $path = Storage::disk('local')->path($relativePath);
                    $shouldDeleteTempCopy = true;
                }
            }

            if ($this->isPdfFile($path) && config('ocr.kbi.ghostscript_required', false)) {
                $normalizedPdfPath = $this->normalizePdfWithGhostscript($path, $os);
                $path = $normalizedPdfPath;
            }

            $finalExtension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($finalExtension, $allowedExtensions, true)) {
                throw new Exception('Unsupported file type. Supported: .pdf, .jpg, .jpeg, .png', 500);
            }


            $python = $this->resolvePythonBinary($serv, $os);

            if ($os == 'windows') {
                $script = "{$serv}\\cli.py";

                $process = new SymphonyProcess([
                    $python,
                    $script,
                    $path,
                    '--queue',
                    '--render-dpi',
                    (string) $this->renderDpi,
                    '--text',
                    '--wait',
                ]);

                $process->setTimeout(300);

                $process->setWorkingDirectory($serv);

                // 🔥 PENTING
                $process->setEnv([
                    'SYSTEMROOT' => getenv('SYSTEMROOT'),
                    'PATH'       => getenv('PATH'),
                    'TEMP'       => getenv('TEMP'),
                    'TMP'        => getenv('TMP'),
                ]);

                $process->run();

                // Check if the process was successful
                if (!$process->isSuccessful()) {
                    throw new Exception('OCR process failed: ' . $process->getErrorOutput(), 500);
                }

                $output = $process->getOutput();
            } else {
                $process = new SymphonyProcess([
                    $python,
                    'cli.py',
                    $path,
                    '--text',
                    '--queue',
                    '--wait',
                    '--render-dpi',
                    (string) $this->renderDpi,
                    // '--force-ocr',
                ]);
                $process->setTimeout(120);
                $process->setWorkingDirectory($serv);
                $process->run();

                // Check if the process was successful
                if (!$process->isSuccessful()) {
                    throw new Exception('OCR process failed: ' . $process->getErrorOutput(), 500);
                }

                $output = $process->getOutput();
            }


            if ($shouldDeleteTempCopy && !empty($relativePath) && Storage::disk('local')->exists($relativePath)) {
                Storage::disk('local')->delete($relativePath);
            }
            if (!empty($normalizedPdfPath) && is_file($normalizedPdfPath)) {
                @unlink($normalizedPdfPath);
            }

            return $output;
        } catch (Exception $e) {
            if ($shouldDeleteTempCopy && !empty($relativePath) && Storage::disk('local')->exists($relativePath)) {
                Storage::disk('local')->delete($relativePath);
            }
            if (!empty($normalizedPdfPath) && is_file($normalizedPdfPath)) {
                @unlink($normalizedPdfPath);
            }

            Log::error('OCR processing failed: ' . $e->getMessage());

            throw new Exception('OCR processing failed: ' . $e->getMessage(), 500);
        }
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

        $normalizedPdfPath = $gsTempDir . DIRECTORY_SEPARATOR . 'normalized-' . uniqid('', true) . '.pdf';

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
        $configured = trim((string) config('ocr.kbi.ghostscript_binary', ''));
        if ($configured !== '') {
            return $configured;
        }

        if ($os !== 'windows') {
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

    private function resolvePythonBinary(string $servicePath, string $os): string
    {
        $venvName = trim((string) config('ocr.kbi.venv_name', '.venv'));
        $venvEnabled = (bool) config('ocr.kbi.venv');

        if ($venvEnabled) {
            $venvPython = $os === 'windows'
                ? $servicePath . DIRECTORY_SEPARATOR . $venvName . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe'
                : $servicePath . DIRECTORY_SEPARATOR . $venvName . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python';

            if (is_file($venvPython)) {
                return $venvPython;
            }
        }

        $candidates = $os === 'windows'
            ? ['python.exe', 'python', 'py']
            : ['python3', 'python'];

        foreach ($candidates as $candidate) {
            if ($this->commandExists($candidate)) {
                return $candidate;
            }
        }

        throw new Exception('Python executable not found for OCR service.', 500);
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
