<?php

if (! function_exists('secret_env')) {
    function secret_env(string $key)
    {
        static $secrets = null;

        if ($secrets === null) {
            $file = env('SFTP_SECRET_FILE');

            if (! $file || ! file_exists($file)) {
                throw new RuntimeException("Secret file not found: {$file}");
            }

            $secrets = [];

            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                [$k, $v] = array_pad(explode('=', $line, 2), 2, null);
                $secrets[trim($k)] = trim($v);
            }
        }

        if (! array_key_exists($key, $secrets)) {
            throw new RuntimeException("Secret key missing: {$key}");
        }

        return $secrets[$key];
    }
}
