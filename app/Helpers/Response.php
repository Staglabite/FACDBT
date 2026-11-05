<?php

namespace App\Helpers;

class Response
{
    public static function success($data = [], $message = 'Success', $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error($message = 'Error', $status = 400, $data = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
