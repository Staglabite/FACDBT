<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\DataTables\Original\HITUAM01\HITUAMF010DataTable;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\HITUAM\HITUAM01\ConfigurationRequest;
use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION as ConfigurationHituam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PSpell\Config;

class HITUAMF010 extends Controller
{
    public function index(HITUAMF010DataTable $dataTable)
    {
        return $dataTable->render('modules.HITUAM.HITUAM01.HITUAMF010.HITUAMF010');
    }

    public function store(ConfigurationRequest $request)
    {
        $data = $request->validated();

        $configuration = DB::transaction(function () use ($data) {
            $configuration = ConfigurationHituam::create([
                'VVARIABLE' => $data['variable'],
                'VVALUE' => $data['value']
            ]);

            return $configuration;
        });

        return Response::success(message: "Config {$configuration->VVARIABLE} created successfully");
    }

    public function show(ConfigurationHituam $configuration)
    {
        return Response::success(data: $configuration);
    }

    public function showByVariable(Request $request)
    {
        $variable = $request->query('variable');

        if (!$variable) {
            return response()->json([
                'success' => false,
                'message' => 'Variable parameter is required'
            ], 400);
        }

        try {
            $config = ConfigurationHituam::where('VVARIABLE', $variable)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'content' => $config->VVALUE
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(ConfigurationRequest $request, ConfigurationHituam $configuration)
    {
        $data = $request->validated();

        $configuration = DB::transaction(function () use ($data, $configuration) {
            $configuration->update([
                'VVARIABLE' => $data['variable'],
                'VVALUE' => $data['value']
            ]);

            return $configuration;
        });

        return Response::success(message: "Config {$configuration->VVARIABLE} updated successfully");
    }

    public function destroy(ConfigurationHituam $configuration)
    {
        try {
            $configuration->delete();

            return Response::success(message: "Configuration {$configuration->VVARIABLE} deleted successfully");
        } catch (\Throwable $th) {
            return Response::error(message: 'Configuration data cannot be deleted, it may be related to other data');
        }
    }
}
