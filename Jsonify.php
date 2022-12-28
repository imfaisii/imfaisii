<?php

namespace App\Traits;

use Exception;


/*
// Trait for json responses
*/

trait Jsonify
{
    public static function success(mixed $data = [], int $code = 200, string $messsage = 'Success'): array
    {
        return response()->json([
            'message' => $messsage,
            'code' => $code,
            'data' => $data
        ]);
    }

    public static function error(mixed $data = [], int $code = 400, string $messsage = 'Error'): array
    {
        return response()->json([
            'message' => $messsage,
            'code' => $code,
            'data' => $data
        ]);
    }

    public static function exception(int $code = 400, Exception $exception): array
    {
        return response()->json([
            'message' => 'Exception',
            'code' => $code,
            'data' => $exception->errorInfo[2] ?? $exception->getMessage()
        ]);
    }
}
