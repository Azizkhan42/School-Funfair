<?php

/*
|--------------------------------------------------------------------------
| Draw Helper
|--------------------------------------------------------------------------
|
| Shared functions used by the manual Draw Engine (draw.php) and the
| Live TV auto-draw API (draw_api.php).
|
*/

/**
 * Perform a single draw (transactional).
 *
 * Rules:
 *  - Only one prize per student per event (one-student-one-prize).
 *  - Only ACTIVE tickets are eligible.
 *  - Event must be CLOSED or DRAWING.
 *  - Prize must be AVAILABLE.
 *
 * @return array Winner details
 * @throws Exception on any rule violation
 */
function performDraw(
    PDO $pdo,
    int $eventId,
    int $prizeId
): array {

    $pdo->beginTransaction();

    try {

        /*
        | Lock the event row to serialize draws for this event.
        */

        $eventStmt = $pdo->prepare("

            SELECT
                event_id,
                status

            FROM lucky_draw_events WITH (UPDLOCK)

            WHERE event_id = :event_id

        ");

        $eventStmt->execute([
            ':event_id' => $eventId
        ]);

        $lockedEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lockedEvent) {
            throw new Exception('Event not found.');
        }

        if (
            !in_array(
                $lockedEvent['status'],
                ['CLOSED', 'DRAWING'],
                true
            )
        ) {
            throw new Exception(
                'Event must be CLOSED before the draw can begin.'
            );
        }


        /*
        | Lock and verify the prize.
        */

        $prizeStmt = $pdo->prepare("

            SELECT
                prize_id,
                prize_name,
                prize_position,
                status

            FROM lucky_draw_prizes WITH (UPDLOCK)

            WHERE prize_id = :prize_id
              AND event_id = :event_id

        ");

        $prizeStmt->execute([
            ':prize_id' => $prizeId,
            ':event_id' => $eventId
        ]);

        $prize = $prizeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prize) {
            throw new Exception('Prize not found for this event.');
        }

        if ($prize['status'] === 'AWARDED') {
            throw new Exception(
                'This prize has already been awarded.'
            );
        }

        if ($prize['status'] === 'DISABLED') {
            throw new Exception(
                'This prize is disabled.'
            );
        }


        /*
        | Pick a random eligible ticket.
        |
        | A student who already won any prize in this event is
        | excluded (one-student-one-prize enforcement).
        */

        $ticketStmt = $pdo->prepare("

            SELECT TOP 1
                t.ticket_id,
                t.ticket_number,
                t.student_id

            FROM lucky_draw_tickets t

            WHERE t.event_id = :event_id
              AND t.status = 'ACTIVE'
              AND NOT EXISTS (

                    SELECT 1

                    FROM lucky_draw_winners w

                    WHERE w.event_id = t.event_id
                      AND w.student_id = t.student_id

              )

            ORDER BY NEWID()

        ");

        $ticketStmt->execute([
            ':event_id' => $eventId
        ]);

        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            throw new Exception(
                'No eligible tickets left for this draw.'
            );
        }


        /*
        | Record the winner.
        */

        $winnerStmt = $pdo->prepare("

            INSERT INTO lucky_draw_winners
            (
                event_id,
                prize_id,
                ticket_id,
                student_id,
                drawn_by,
                drawn_at,
                status
            )
            VALUES
            (
                :event_id,
                :prize_id,
                :ticket_id,
                :student_id,
                :drawn_by,
                GETDATE(),
                'CONFIRMED'
            )

        ");

        $winnerStmt->execute([
            ':event_id' => $eventId,
            ':prize_id' => $prizeId,
            ':ticket_id' => $ticket['ticket_id'],
            ':student_id' => $ticket['student_id'],
            ':drawn_by' => 1
        ]);


        /*
        | Mark ticket as WINNER and prize as AWARDED.
        */

        $ticketUpd = $pdo->prepare("

            UPDATE lucky_draw_tickets

            SET status = 'WINNER'

            WHERE ticket_id = :ticket_id

        ");

        $ticketUpd->execute([
            ':ticket_id' => $ticket['ticket_id']
        ]);


        $prizeUpd = $pdo->prepare("

            UPDATE lucky_draw_prizes

            SET status = 'AWARDED'

            WHERE prize_id = :prize_id

        ");

        $prizeUpd->execute([
            ':prize_id' => $prizeId
        ]);


        /*
        | Move event to DRAWING status.
        */

        $eventUpd = $pdo->prepare("

            UPDATE lucky_draw_events

            SET
                status = 'DRAWING',
                updated_at = GETDATE()

            WHERE event_id = :event_id

        ");

        $eventUpd->execute([
            ':event_id' => $eventId
        ]);


        $pdo->commit();

        logAudit(
            $pdo,
            1,
            'WINNER_DRAWN',
            'PRIZE',
            $prizeId,
            $eventId,
            'Winner drawn for prize "' .
                $prize['prize_name'] .
                '" with ticket ' .
                $ticket['ticket_number']
        );

        return [
            'ticket_number' => $ticket['ticket_number'],
            'prize_name' => $prize['prize_name'],
            'student_id' => $ticket['student_id'],
            'student_name' => null,
            'class_name' => null
        ];

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;

    }

}


/**
 * Get the next prize that should be drawn for an event.
 *
 * @return array|null The next AVAILABLE prize (by position) or null.
 */
function nextDrawablePrize(
    PDO $pdo,
    int $eventId
): ?array {

    $stmt = $pdo->prepare("

        SELECT
            prize_id,
            prize_name,
            prize_position,
            status

        FROM lucky_draw_prizes

        WHERE event_id = :event_id
          AND status = 'AVAILABLE'

        ORDER BY prize_position

    ");

    $stmt->execute([
        ':event_id' => $eventId
    ]);

    $prize = $stmt->fetch(PDO::FETCH_ASSOC);

    return $prize ?: null;

}


/**
 * Mark the event COMPLETED when every prize has been awarded.
 */
function completeEventIfAllAwarded(
    PDO $pdo,
    int $eventId
): void {

    $stmt = $pdo->prepare("

        SELECT
            COUNT(*) AS remaining

        FROM lucky_draw_prizes

        WHERE event_id = :event_id
          AND status = 'AVAILABLE'

    ");

    $stmt->execute([
        ':event_id' => $eventId
    ]);

    $remaining = (int)$stmt->fetchColumn();

    if ($remaining === 0) {

        $update = $pdo->prepare("

            UPDATE lucky_draw_events

            SET
                status = 'COMPLETED',
                updated_at = GETDATE()

            WHERE event_id = :event_id

        ");

        $update->execute([
            ':event_id' => $eventId
        ]);

    }

}


/**
 * Get the full winners list for an event.
 */
function getWinnersList(
    PDO $pdo,
    int $eventId
): array {

    $stmt = $pdo->prepare("

        SELECT
            w.winner_id,
            w.drawn_at,
            p.prize_name,
            p.prize_position,
            t.ticket_number,
            s.student_name,
            s.class_name

        FROM lucky_draw_winners w

        INNER JOIN lucky_draw_prizes p
            ON p.prize_id = w.prize_id

        INNER JOIN lucky_draw_tickets t
            ON t.ticket_id = w.ticket_id

        INNER JOIN test_students s
            ON s.student_id = w.student_id

        WHERE w.event_id = :event_id

        ORDER BY p.prize_position

    ");

    $stmt->execute([
        ':event_id' => $eventId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}