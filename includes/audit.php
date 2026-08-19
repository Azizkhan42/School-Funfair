<?php

/*
|--------------------------------------------------------------------------
| Audit Log Helper
|--------------------------------------------------------------------------
|
| Insert a row into lucky_draw_audit_logs.
|
| Usage:
|   require_once __DIR__ . '/../includes/audit.php';
|   logAudit($pdo, $userId, $action, $entityType, $entityId, $eventId, $description);
|
*/

function logAudit(
    PDO $pdo,
    int $userId,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?int $eventId = null,
    ?string $description = null
): void {
    $stmt = $pdo->prepare("

        INSERT INTO lucky_draw_audit_logs
        (
            event_id,
            user_id,
            action,
            entity_type,
            entity_id,
            description,
            created_at
        )
        VALUES
        (
            :event_id,
            :user_id,
            :action,
            :entity_type,
            :entity_id,
            :description,
            GETDATE()
        )

    ");

    $stmt->execute([
        ':event_id' => $eventId,
        ':user_id' => $userId,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':description' => $description
    ]);
}
