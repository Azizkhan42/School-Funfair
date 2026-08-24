<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/audit.php';

$eventId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$action = $_GET['action'] ?? '';

if (!$eventId) {
    header('Location: events.php');
    exit;
}


$stmt = $pdo->prepare("
    SELECT *
    FROM lucky_draw_events
    WHERE event_id = :event_id
");

$stmt->execute([
    ':event_id' => $eventId
]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: events.php');
    exit;
}


$newStatus = null;


/*
|--------------------------------------------------------------------------
| Open Sales
|--------------------------------------------------------------------------
*/

if (
    $action === 'open' &&
    $event['status'] === 'DRAFT'
) {

    $now = new DateTime();

    $salesStart = new DateTime(
        $event['sales_start']
    );

    $salesEnd = new DateTime(
        $event['sales_end']
    );


    if ($now < $salesStart) {

        die(
            'Sales period has not started yet.'
        );

    }


    if ($now >= $salesEnd) {

        die(
            'Sales period has already ended.'
        );

    }


    $newStatus = 'OPEN';
}


/*
|--------------------------------------------------------------------------
| Close Sales
|--------------------------------------------------------------------------
*/

if (
    $action === 'close' &&
    $event['status'] === 'OPEN'
) {

    $newStatus = 'CLOSED';
}


if ($newStatus !== null) {

    $update = $pdo->prepare("

        UPDATE lucky_draw_events

        SET
            status = :status,
            updated_at = GETDATE()

        WHERE event_id = :event_id

    ");

    $update->execute([

        ':status' => $newStatus,

        ':event_id' => $eventId

    ]);

    logAudit(
        $pdo,
        1,
        'EVENT_STATUS_CHANGED',
        'EVENT',
        $eventId,
        $eventId,
        'Event status changed to ' . $newStatus
    );

}


header('Location: events.php');

exit;