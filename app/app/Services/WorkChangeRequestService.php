<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class WorkChangeRequestService
{
    public function queueCreate(string $entityType, array $after, int $requestedBy, ?string $comment = null, array $meta = []): int
    {
        return $this->queue($entityType, 0, 'CREATE', null, $after, $requestedBy, $comment, $meta);
    }

    public function queueUpdate(string $entityType, int $entityId, mixed $before, mixed $after, int $requestedBy, ?string $comment = null, array $meta = []): int
    {
        return $this->queue($entityType, $entityId, 'UPDATE', $before, $after, $requestedBy, $comment, $meta);
    }

    public function queueDelete(string $entityType, int $entityId, mixed $before, int $requestedBy, ?string $comment = null, array $meta = []): int
    {
        return $this->queue($entityType, $entityId, 'DELETE', $before, null, $requestedBy, $comment, $meta);
    }

    public function decodePayload(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function queue(
        string $entityType,
        int $entityId,
        string $operation,
        mixed $before,
        mixed $after,
        int $requestedBy,
        ?string $comment,
        array $meta
    ): int {
        $comment = trim((string)$comment);
        if ($comment === '') {
            $comment = null;
        }

        $payload = [
            'before' => $before,
            'after' => $after,
            'meta' => $meta,
        ];

        return (int)DB::table('change_requests')->insertGetId([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => strtoupper($operation),
            'proposed_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'PENDING',
            'requested_by' => $requestedBy,
            'comment' => $comment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
