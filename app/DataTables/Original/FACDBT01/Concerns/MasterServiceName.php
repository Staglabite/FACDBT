<?php

namespace App\DataTables\Original\FACDBT01\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Nama service permission tombol aksi master FACDBT01.
 * Halaman memakai format "FACDBTF00x-<Aksi>" (mis. @serve('FACDBTF002-Delete')), sedangkan tombol aksi
 * tabel dulu memakai "FACDBT00x-<Aksi>"; keduanya diterima supaya tombol tampil sesuai hak akses di HITUAM.
 */
trait MasterServiceName
{
    /**
     * @param  string  $code    kode menu, mis. '002'
     * @param  string  $action  'Update' / 'Delete'
     */
    private function masterService(string $code, string $action): string
    {
        $services = once(fn () => collect(Auth::user()?->serviceNames() ?? []));
        $name = "FACDBTF{$code}-{$action}";

        return $services->contains($name) ? $name : "FACDBT{$code}-{$action}";
    }
}
