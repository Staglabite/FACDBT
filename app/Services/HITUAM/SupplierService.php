<?php

namespace App\Services\HITUAM;

// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER as Supplier;
use App\Models\HITUAM01\HITUAM_MSHSUPPLIER as Supplier;
// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierUser;
use App\Models\HITUAM01\HITUAM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
// use App\Models\FACTWM01\FACTWM_MSHCONFIGURATION as Config;
use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION as Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupplierService
{
    public function sync()
    {
        $token = config('services.ifs.token');
        $base_url_api = Config::where('VVARIABLE', 'base_url_api')->value('VVALUE');
        $endpoint = Config::where('VVARIABLE', 'endpoint_supplier')->value('VVALUE');
        $config_verify_api = Config::where('VVARIABLE', 'verify_api')->first();
        $verify_api = filter_var(
            $config_verify_api->VVALUE ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$base_url_api || !$endpoint) {
            return [];
        }

        $url = $base_url_api . $endpoint;

        if ($verify_api) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();

                return $data;
            }

            if ($response->status() === 422) {
                return [];
            }

            Log::error('API Error', [
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } else {
            $dummy = json_decode(File::get(base_path('dummy/sync-vendor.json')), true);
            return $dummy;
        }
    }

    public function getAllSuppliers(Request $request)
    {
        $data = Supplier::filtered($request)->get();

        return $data;
    }

    public function getSuppliersByIds(array $ids)
    {
        $data = Supplier::whereIn('IID', $ids)->get();

        return $data;
    }

    public function getSupplierUsers(Request $request)
    {
        $data = SupplierUser::filtered($request, 'email')->get();

        return $data;
    }

    public function mappingComMethods($data, $supplier)
    {
        $mapped = [
            'ICOMM_ID' => $data['CommId'],
            'VSUPPLIER_CODE' => $supplier->VSUPPLIER_CODE,
            'VSUPPLIER_NAME' => $supplier->VNAME,
            'VNAME' => $data['Name'],
            'VMETHOD_ID' => $data['MethodId'],
            'VDESCRIPTION' => $data['Description'],
            'VADDRESS_ID' => $supplier->VADDRESS,
            'VPARTY_TYPE_DB_VAL' => $data['PartyType'],
            'BMETHOD_DEFAULT' => $data['MethodDefault'],
            'VVALUE' => $data['Value'],
            'ISUPPLIER_ID' => $supplier->IID
        ];

        return $mapped;
    }
}
