<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class WorkChangeRequestService
{
    public function __construct(
        private readonly AccountChangeRequestRequirementService $accountChangeRequestRequirementService,
        private readonly WorkChangeRequestApplier $workChangeRequestApplier
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{request_id:int,status:string,approval_required:bool,applied_entity_id:int}
     */
    public function queueCreate(string $entityType, array $after, int $requestedBy, ?string $comment = null, array $meta = []): array
    {
        return $this->queue($entityType, 0, 'CREATE', null, $after, $requestedBy, $comment, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{request_id:int,status:string,approval_required:bool,applied_entity_id:int}
     */
    public function queueUpdate(string $entityType, int $entityId, mixed $before, mixed $after, int $requestedBy, ?string $comment = null, array $meta = []): array
    {
        return $this->queue($entityType, $entityId, 'UPDATE', $before, $after, $requestedBy, $comment, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{request_id:int,status:string,approval_required:bool,applied_entity_id:int}
     */
    public function queueDelete(string $entityType, int $entityId, mixed $before, int $requestedBy, ?string $comment = null, array $meta = []): array
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
     * @param array{approval_required?:bool} $submission
     */
    public function approvalRequired(array $submission): bool
    {
        return (bool)($submission['approval_required'] ?? true);
    }

    /**
     * @param array{request_id?:int} $submission
     */
    public function requestId(array $submission): int
    {
        return (int)($submission['request_id'] ?? 0);
    }

    /**
     * @param array{approval_required?:bool} $submission
     */
    public function outcomeMessage(array $submission, string $appliedMessage, string $pendingMessage): string
    {
        return $this->approvalRequired($submission) ? $pendingMessage : $appliedMessage;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{request_id:int,status:string,approval_required:bool,applied_entity_id:int}
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
    ): array {
        $comment = trim((string)$comment);
        if ($comment === '') {
            $comment = null;
        }

        $entityType = strtolower(trim($entityType));
        $payload = [
            'before' => $before,
            'after' => $after,
            'meta' => $meta,
        ];

        return DB::transaction(function () use (
            $entityType,
            $entityId,
            $operation,
            $before,
            $after,
            $requestedBy,
            $comment,
            $meta,
            $payload
        ): array {
            $approvalRequired = $this->accountChangeRequestRequirementService->requiresChangeRequest(
                $entityType,
                $entityId,
                $requestedBy,
                $before,
                $after,
                $meta
            );

            $now = now();
            $status = $approvalRequired ? 'PENDING' : 'APPROVED';
            $requestId = (int)DB::table('change_requests')->insertGetId([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'operation' => strtoupper($operation),
                'proposed_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'requested_by' => $requestedBy,
                'approved_by' => $approvalRequired ? null : ($requestedBy > 0 ? $requestedBy : null),
                'approved_at' => $approvalRequired ? null : $now,
                'comment' => $comment,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $appliedEntityId = $entityId;
            if (!$approvalRequired) {
                $requestRow = (object)[
                    'id' => $requestId,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'requested_by' => $requestedBy,
                    'operation' => strtoupper($operation),
                ];
                $appliedEntityId = $this->workChangeRequestApplier->apply($requestRow, $payload, $requestedBy);

                if (strtoupper($operation) === 'CREATE' && $appliedEntityId > 0) {
                    DB::table('change_requests')
                        ->where('id', $requestId)
                        ->update([
                            'entity_id' => $appliedEntityId,
                            'updated_at' => now(),
                        ]);
                }
            }

            return [
                'request_id' => $requestId,
                'status' => $status,
                'approval_required' => $approvalRequired,
                'applied_entity_id' => (int)$appliedEntityId,
            ];
        });
    }
}
