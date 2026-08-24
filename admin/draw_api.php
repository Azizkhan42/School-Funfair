<?php

/*
|--------------------------------------------------------------------------
| Draw API
|--------------------------------------------------------------------------
|
| JSON endpoint used by the Live TV / Projector page.
|
|   draw_api.php?event_id=1&action=status   -> current draw state
|   draw_api.php?event_id=1&action=draw     -> perform the next auto-draw
|
*/

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/audit.php';

require_once __DIR__ . '/../includes/draw-helper.php';

header('Content-Type: application/json');

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

$action = $_GET['action'] ?? 'status';

if (!$eventId) {

    echo json_encode([
        'ok' => false,
        'error' => 'Event ID is required.'
    ]);

    exit;

}

/*
|--------------------------------------------------------------------------
| Load event
|--------------------------------------------------------------------------
*/

$eventStmt = $pdo->prepare("

    SELECT
        e.*,
        (
            SELECT COUNT(*)
            FROM lucky_draw_tickets t
            WHERE t.event_id = e.event_id
        ) AS total_tickets,
        (
            SELECT COUNT(*)
            FROM lucky_draw_winners w
            WHERE w.event_id = e.event_id
        ) AS total_winners

    FROM lucky_draw_events e

    WHERE e.event_id = :event_id

");

$eventStmt->execute([
    ':event_id' => $eventId
]);

$event = $eventStmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {

    echo json_encode([
        'ok' => false,
        'error' => 'Event not found.'
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| Shared state
|--------------------------------------------------------------------------
*/

$now = new DateTime();

$drawDate = new DateTime($event['draw_date']);

$secondsUntilDraw = max(0, $drawDate->getTimestamp() - $now->getTimestamp());

$drawTimeReached = $secondsUntilDraw <= 0;

$statusEligible = in_array(
    $event['status'],
    ['CLOSED', 'DRAWING'],
    true
);

$nextPrize = nextDrawablePrize($pdo, $eventId);

/*
| The auto-draw waits until the scheduled draw time is reached.
| (A 2-second grace is allowed so client clocks don't cause a race.)
*/

$canDraw =
    $statusEligible &&
    $nextPrize !== null &&
    $secondsUntilDraw <= 2;


/*
|--------------------------------------------------------------------------
| Eligible ticket numbers (for the shuffle animation)
|--------------------------------------------------------------------------
*/

$ticketStmt = $pdo->prepare("

    SELECT
        t.ticket_number

    FROM lucky_draw_tickets t

    WHERE t.event_id = :event_id
      AND t.status = 'ACTIVE'
      AND NOT EXISTS (

            SELECT 1

            FROM lucky_draw_winners w

            WHERE w.event_id = t.event_id
              AND w.student_id = t.student_id

      )

    ORDER BY t.ticket_number

");

$ticketStmt->execute([
    ':event_id' => $eventId
]);

$eligibleTickets = $ticketStmt->fetchAll(PDO::FETCH_COLUMN);


/*
|--------------------------------------------------------------------------
| Winners list (for re-rendering the results grid)
|--------------------------------------------------------------------------
*/

$winners = getWinnersList($pdo, $eventId);


/*
|--------------------------------------------------------------------------
| action = status
|--------------------------------------------------------------------------
*/

if ($action === 'status') {

    echo json_encode([
        'ok' => true,
        'event_id' => (int)$event['event_id'],
        'event_name' => $event['event_name'],
        'status' => $event['status'],
        'draw_date' => date(
            'd M Y h:i A',
            strtotime($event['draw_date'])
        ),
        'draw_time_reached' => $drawTimeReached,
        'seconds_until_draw' => $secondsUntilDraw,
        'can_draw' => $canDraw,
        'completed' => $event['status'] === 'COMPLETED',
        'prizes_remaining' => $nextPrize ? 1 : 0,
        'next_prize' => $nextPrize ? [
            'prize_id' => (int)$nextPrize['prize_id'],
            'prize_name' => $nextPrize['prize_name'],
            'prize_position' => (int)$nextPrize['prize_position']
        ] : null,
        'eligible_tickets' => $eligibleTickets,
        'total_tickets' => (int)$event['total_tickets'],
        'total_winners' => (int)$event['total_winners'],
        'winners' => $winners
    ]);

    exit;

}


/*
|--------------------------------------------------------------------------
| action = draw
|--------------------------------------------------------------------------
*/

if ($action === 'draw') {

    if (!$statusEligible) {

        echo json_encode([
            'ok' => false,
            'error' => 'Event must be CLOSED before the draw can begin.'
        ]);

        exit;

    }

    if ($secondsUntilDraw > 2) {

        echo json_encode([
            'ok' => false,
            'error' => 'The draw starts at ' .
                date('h:i A', $drawDate->getTimestamp()) .
                '.'
        ]);

        exit;

    }

    if ($nextPrize === null) {

        completeEventIfAllAwarded($pdo, $eventId);

        echo json_encode([
            'ok' => false,
            'error' => 'No prizes left to draw.',
            'completed' => true
        ]);

        exit;

    }

    try {

        $winner = performDraw(
            $pdo,
            $eventId,
            (int)$nextPrize['prize_id']
        );

        /*
        | Fetch the winner's student details for display.
        */

        $studentStmt = $pdo->prepare("

            SELECT
                student_name,
                class_name

            FROM test_students

            WHERE student_id = :student_id

        ");

        $studentStmt->execute([
            ':student_id' => $winner['student_id']
        ]);

        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        $winner['student_name'] = $student['student_name'] ?? '-';

        $winner['class_name'] = $student['class_name'] ?? '-';

        $winner['prize_position'] = (int)$nextPrize['prize_position'];


        /*
        | Mark event completed when all prizes are awarded.
        */

        completeEventIfAllAwarded($pdo, $eventId);

        $recheckStmt = $pdo->prepare("

            SELECT status

            FROM lucky_draw_events

            WHERE event_id = :event_id

        ");

        $recheckStmt->execute([
            ':event_id' => $eventId
        ]);

        $eventStatus = $recheckStmt->fetchColumn();

        $nextAfter = nextDrawablePrize($pdo, $eventId);

        $winnersAfter = getWinnersList($pdo, $eventId);

        echo json_encode([
            'ok' => true,
            'winner' => $winner,
            'prizes_remaining' => $nextAfter ? 1 : 0,
            'next_prize' => $nextAfter ? [
                'prize_id' => (int)$nextAfter['prize_id'],
                'prize_name' => $nextAfter['prize_name'],
                'prize_position' => (int)$nextAfter['prize_position']
            ] : null,
            'completed' => $eventStatus === 'COMPLETED',
            'winners' => $winnersAfter
        ]);

        exit;

    } catch (Throwable $e) {

        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage()
        ]);

        exit;

    }

}


echo json_encode([
    'ok' => false,
    'error' => 'Unknown action.'
]);