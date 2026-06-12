<?php

namespace Database\Seeders;

use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION as Config;
use Illuminate\Database\Seeder;

class ConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configuration = [
            [
                'VVARIABLE' => 'pengaturan_privasi_anda',
                'VVALUE' => '<strong>Privacy &amp; Terms – Aplikasi Web Portal Internal IDBM Terintegrasi (POINT)</strong> <p> Aplikasi ini merupakan <strong>sistem internal perusahaan</strong> yang digunakan untuk membantu operasional perusahaan. </p> <p>Dengan menggunakan aplikasi ini, Anda menyetujui bahwa:</p> <ul style="padding-left:18px;"> <li>Data yang diakses dan diproses hanya untuk kepentingan operasional perusahaan</li> <li>Seluruh aktivitas pengguna dicatat dalam sistem (audit log)</li> <li>Akses data dibatasi berdasarkan hak akses dan peran pengguna</li> <li>Pengguna bertanggung jawab menjaga kerahasiaan akun dan password</li> <li>Penyalahgunaan sistem dapat dikenakan sanksi sesuai kebijakan perusahaan</li> </ul> <p> Data dikelola secara aman dan tidak dibagikan ke pihak ketiga tanpa izin resmi. </p> <p> <strong>Dengan login, Anda dianggap telah membaca dan menyetujui ketentuan ini.</strong> </p>',
            ],
            [
                'VVARIABLE' => 'contact_phone_number',
                'VVALUE' => '08912345678'
            ],
            [
                'VVARIABLE' => 'contact_email',
                'VVALUE' => 'sample@mail.com'
            ],
            [
                'VVARIABLE' => 'TOKEN_EXPIRY_HOURS',
                'VVALUE' => '5',
            ],
            [
                'VVARIABLE' => 'CACHE_EXPIRY_HOURS',
                'VVALUE' => '24',
            ],
            [
                'VVARIABLE' => 'LOGIN_MAX_FAILED_ATTEMPTS',
                'VVALUE' => '5',
            ],
            [
                'VVARIABLE' => 'LOGIN_LOCKOUT_MINUTES',
                'VVALUE' => '15',
            ],
            [
                'VVARIABLE' => 'sidebar_sections',
                'VVALUE' => 'Basic Data,Transactions,Report,Dashboard',
            ],
            [
                'VVARIABLE' => 'api_token',
                'VVALUE' => 'OYpsA292HX2XW4x5K5JUK526ldUJNF3rpFRvfsM703csFhtEahOruzhVMDsemHwo',
            ],
            [
                'VVARIABLE' => 'login_api',
                'VVALUE' => 'http://webdev.idbm.co.id/ifs10dev/api/login',
            ],
            [
                'VVARIABLE' => 'max_len_view_news',
                'VVALUE' => '150',
            ],
            [
                'VVARIABLE' => 'base_url_api',
                'VVALUE' => 'http://webdev.idbm.co.id/ifs10dev/api'
            ],
            [
                'VVARIABLE' => 'verify_api',
                'VVALUE' => 'true',
            ],
            [
                'VVARIABLE' => 'legal_cookies',
                'VVALUE' => '<strong>Cookie Policy</strong> <p> Aplikasi web ini menggunakan cookie untuk memastikan fungsi autentikasi, keamanan, dan kelancaran penggunaan sistem. </p> <p> Cookie digunakan untuk menyimpan sesi login, preferensi pengguna, serta mendukung pencatatan aktivitas (audit log). </p> <p> Aplikasi ini <strong>tidak menggunakan cookie untuk tujuan pemasaran atau pelacakan pihak ketiga</strong>. </p> <p> Dengan melanjutkan penggunaan aplikasi ini, Anda menyetujui penggunaan cookie sesuai dengan kebijakan ini. </p>'
            ]
        ];

        Config::truncate();
        Config::insert($configuration);
    }
}
