<?php

namespace App\Sessions;

use Illuminate\Session\DatabaseSessionHandler;

class HituamDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected string $application;

    public function __construct($connection, $table, $minutes, $application)
    {
        parent::__construct($connection, $table, $minutes);
        $this->application = $application;
    }

    protected function getDefaultPayload($data)
    {
        $payload = parent::getDefaultPayload($data);
        $payload['application'] = $this->application;

        return $payload;
    }

    public function read($sessionId): string
    {
        $session = (object) $this->getQuery()
            ->where('id', $sessionId)
            ->where('application', $this->application)
            ->first();

        if ($this->expired($session)) {
            $this->exists = true;

            return '';
        }

        if (isset($session->payload)) {
            $this->exists = true;

            return base64_decode($session->payload);
        }

        return '';
    }

    public function write($sessionId, $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        $exists = $this->getQuery()
            ->where('id', $sessionId)
            ->where('application', $this->application)
            ->exists();

        if ($exists) {
            $this->performUpdate($sessionId, $payload);
        } else {
            $this->performInsert($sessionId, $payload);
        }

        return $this->exists = true;
    }

    protected function performInsert($sessionId, $payload)
    {
        try {
            return $this->getQuery()->insert(array_merge([
                'id' => $sessionId,
            ], $payload));
        } catch (\Exception $e) {
            // If insert fails, try update (race condition)
            $this->performUpdate($sessionId, $payload);
        }
    }

    protected function performUpdate($sessionId, $payload)
    {
        return $this->getQuery()
            ->where('id', $sessionId)
            ->where('application', $this->application)
            ->update($payload);
    }

    public function destroy($sessionId): bool
    {
        $this->getQuery()
            ->where('id', $sessionId)
            ->where('application', $this->application)
            ->delete();

        return true;
    }

    public function gc($lifetime): int
    {
        return $this->getQuery()
            ->where('application', $this->application)
            ->where('last_activity', '<=', $this->currentTime() - $lifetime)
            ->delete();
    }
}
