<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}

$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if (!$eventId) {
    header('Location: events.php');
    exit;
}

$eventStmt = $pdo->prepare("

    SELECT event_name

    FROM lucky_draw_events

    WHERE event_id = :event_id

");

$eventStmt->execute([
    ':event_id' => $eventId
]);

$event = $eventStmt->fetch(PDO::FETCH_ASSOC);

if ($event) {

    $pdo->beginTransaction();

    try {

        $countStmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM lucky_draw_prizes

            WHERE event_id = :event_id

        ");

        $countStmt->execute([
            ':event_id' => $eventId
        ]);

        $prizes = (int) $countStmt->fetchColumn();

        $countStmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM lucky_draw_tickets

            WHERE event_id = :event_id

        ");

        $countStmt->execute([
            ':event_id' => $eventId
        ]);

        $tickets = (int) $countStmt->fetchColumn();

        $countStmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM lucky_draw_winners

            WHERE event_id = :event_id

        ");

        $countStmt->execute([
            ':event_id' => $eventId
        ]);

        $winners = (int) $countStmt->fetchColumn();

        $deletes = [
            "DELETE FROM lucky_draw_winners       WHERE event_id = :event_id",
            "DELETE FROM lucky_draw_tickets       WHERE event_id = :event_id",
            "DELETE FROM lucky_draw_registrations WHERE event_id = :event_id",
            "DELETE FROM lucky_draw_prizes        WHERE event_id = :event_id",
            "DELETE FROM lucky_draw_sequences     WHERE event_id = :event_id",
            "DELETE FROM lucky_draw_audit_logs    WHERE event_id = :event_id",
            "DELETE FROM lucky_draw_events        WHERE event_id = :event_id"
        ];

        foreach ($deletes as $sql) {

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':event_id' => $eventId
            ]);

        }

        $pdo->commit();

        logAudit(
            $pdo,
            1,
            'EVENT_DELETED',
            'EVENT',
            null,
            null,
            'Event "' .
                $event['event_name'] .
                '" deleted (' .
                $prizes .
                ' prizes, ' .
                $tickets .
                ' tickets, ' .
                $winners .
                ' winners).'
        );

        header('Location: events.php?deleted=1');
        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header('Location: events.php?error=1');
        exit;

    }

}

header('Location: events.php');
exit;