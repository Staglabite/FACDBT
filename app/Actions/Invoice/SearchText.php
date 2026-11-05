<?php

namespace App\Actions\Invoice;

use Illuminate\Validation\ValidationException;
use App\Models\FACTWM01\FACTWM_MSHCONFIGURATION as Config;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;

class SearchText
{
    public static function perform(string $text, array $keywords)
    {
        $config_toleransi_ppn = Config::where('VVARIABLE', 'toleransi_ppn')->value('VVALUE') ?? 0;
        $config_toleransi_dpp = Config::where('VVARIABLE', 'toleransi_dpp')->value('VVALUE') ?? 0;

        // group berdasarkan key
        $grouped = collect($keywords)->groupBy('key');

        $formattedTextBase = strtolower($text);

        $results = Concurrency::run(
            $grouped->map(function ($items, $key) use ($formattedTextBase, $config_toleransi_ppn, $config_toleransi_dpp) {
                return function () use ($items, $key, $formattedTextBase, $config_toleransi_ppn, $config_toleransi_dpp) {

                    $found = false;

                    $formattedText = $key === 'invoice_number'
                        ? preg_replace('/\s+/', '', $formattedTextBase)
                        : preg_replace('/\s+/', ' ', $formattedTextBase);

                    foreach ($items as $item) {

                        if (!empty($item['checked'])) {
                            continue;
                        }

                        $value = strtolower($item['value']);
                        $formattedValue = $key === 'invoice_number'
                            ? preg_replace('/\s+/', '', $value)
                            : trim(preg_replace('/\s+/', ' ', $value));

                        if ($key === 'ppn') {
                            $rawPpn = (float) str_replace(['.', ','], '', $item['value']);
                            $numeric = (int) $rawPpn;
                            $min = $numeric - $config_toleransi_ppn;
                            $max = $numeric + $config_toleransi_ppn;

                            $ppnFormats = [
                                number_format($rawPpn, 0, ',', '.'),
                                number_format($rawPpn, 0, '.', ','),
                                strval((int) $rawPpn),
                            ];

                            // Cek 1: direct string match
                            foreach ($ppnFormats as $format) {
                                if (str_contains($formattedText, strtolower($format))) {
                                    $found = true;
                                    break;
                                }
                            }

                            // Cek 2: fallback regex + toleransi (untuk OCR yang salah baca 1-2 digit)
                            if (!$found && preg_match_all('/[\d][.,\d]*/', $formattedText, $matches)) {
                                foreach ($matches[0] as $number) {
                                    $clean = self::cleanNumeric($number);
                                    if ($clean > 0 && $clean >= $min && $clean <= $max) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }

                            if ($found) break;
                        } else if ($key === 'dpp-nilai-lain') {
                            $rawDpp = (float) str_replace(['.', ','], '', $item['value']);
                            $numeric = (int) $rawDpp;
                            $min = $numeric - $config_toleransi_dpp;
                            $max = $numeric + $config_toleransi_dpp;

                            $dppFormats = [
                                number_format($rawDpp, 0, ',', '.'),
                                number_format($rawDpp, 0, '.', ','),
                                strval((int) $rawDpp),
                            ];

                            // Cek 1: direct string match
                            foreach ($dppFormats as $format) {
                                if (str_contains($formattedText, strtolower($format))) {
                                    $found = true;
                                    break;
                                }
                            }

                            // Cek 2: fallback regex + toleransi
                            if (!$found && preg_match_all('/\d[\d.,]*/', $formattedText, $matches)) {
                                foreach ($matches[0] as $number) {
                                    $clean = self::cleanNumeric($number);
                                    if ($clean >= $min && $clean <= $max) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        } else {
                            if (str_contains($formattedText, $formattedValue)) {
                                $found = true;
                                break;
                            }
                        }
                    }

                    return [
                        'key' => $key,
                        'found' => $found,
                    ];
                };
            })->values()->toArray()
        );

        // merge result ke keywords
        foreach ($results as $result) {

            foreach ($keywords as &$keyword) {

                if ($keyword['key'] === $result['key'] && empty($keyword['checked'])) {

                    $keyword['checked'] = $result['found'];

                    if (!$result['found']) {
                        $keyword['error'] =
                            ucwords(str_replace('_', ' ', $result['key'])) .
                            ' did not match! or The document does not match the expected format.';
                    } else {
                        $keyword['error'] = null;
                    }
                }
            }
        }

        return self::extractUniqueErrors($keywords);
    }

    private static function extractUniqueErrors(array $keywords): array
    {
        $grouped = [];

        // group berdasarkan key
        foreach ($keywords as $item) {
            $grouped[$item['key']][] = $item;
        }

        $result = [];

        foreach ($grouped as $key => $items) {

            // cari item yang punya error
            foreach ($items as $item) {
                $result[] = $item;
                break; // satu key cukup satu error
            }
        }

        return array_values($result);
    }

    private static function normalizeTaxInvoice(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return $digits;
    }

    // ✅ Benar
    private static function cleanNumeric($value): int
    {
        // Hapus decimal (.00 atau ,00 di akhir)
        $value = preg_replace('/[.,]\d{1,2}$/', '', $value);
        // Hapus semua separator
        return (int) preg_replace('/\D/', '', $value);
    }
}
