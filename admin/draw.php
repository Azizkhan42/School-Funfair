<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/audit.php';

require_once __DIR__ . '/../includes/draw-helper.php';

$pageTitle = 'Draw Engine';

$selectedEvent = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$drawPrizeId = filter_input(INPUT_GET, 'draw_prize', FILTER_VALIDATE_INT);
$drawAll = $_GET['draw_all'] ?? '';

/*
|--------------------------------------------------------------------------
| Events for selector
|--------------------------------------------------------------------------
*/

$eventsStmt = $pdo->query("

    SELECT
        event_id,
        event_name,
        status

    FROM lucky_draw_events

    ORDER BY event_id DESC

");

$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Process single draw
|--------------------------------------------------------------------------
*/

$drawSuccess = null;

$drawError = null;

if ($selectedEvent && $drawPrizeId) {

    try {

        $drawSuccess = performDraw(
            $pdo,
            $selectedEvent,
            $drawPrizeId
        );

    } catch (Throwable $e) {

        $drawError = $e->getMessage();

    }

}


/*
|--------------------------------------------------------------------------
| Process auto-draw all remaining prizes
|--------------------------------------------------------------------------
*/

$autoDrawResults = [];

if ($selectedEvent && $drawAll) {

    while (($prize = nextDrawablePrize($pdo, $selectedEvent)) !== null) {

        try {

            $autoDrawResults[] = performDraw(
                $pdo,
                $selectedEvent,
                (int)$prize['prize_id']
            );

        } catch (Throwable $e) {

            $autoDrawResults[] = [
                'error' => $e->getMessage()
            ];

            break;

        }

    }

    completeEventIfAllAwarded($pdo, $selectedEvent);

}


/*
|--------------------------------------------------------------------------
| Event + prizes + winners data
|--------------------------------------------------------------------------
*/

$event = null;

$prizes = [];

$winners = [];

if ($selectedEvent) {

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
        ':event_id' => $selectedEvent
    ]);

    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);


    $prizesStmt = $pdo->prepare("

        SELECT
            prize_id,
            prize_name,
            description,
            prize_position,
            status

        FROM lucky_draw_prizes

        WHERE event_id = :event_id

        ORDER BY prize_position

    ");

    $prizesStmt->execute([
        ':event_id' => $selectedEvent
    ]);

    $prizes = $prizesStmt->fetchAll(PDO::FETCH_ASSOC);


    $winnersStmt = $pdo->prepare("

        SELECT
            w.winner_id,
            w.prize_id,
            w.ticket_id,
            w.drawn_at,
            w.status,
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

    $winnersStmt->execute([
        ':event_id' => $selectedEvent
    ]);

    $winners = $winnersStmt->fetchAll(PDO::FETCH_ASSOC);

}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                Draw Engine
            </h2>

            <p class="text-muted mb-0">
                Draw winners for each prize.
            </p>
        </div>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" class="row g-3">

                <div class="col-md-8">

                    <label class="form-label fw-semibold">
                        Select Event
                    </label>

                    <select
                        name="event_id"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Select Event
                        </option>

                        <?php foreach ($events as $ev): ?>

                            <option
                                value="<?= $ev['event_id'] ?>"
                                <?= $selectedEvent == $ev['event_id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($ev['event_name']) ?>
                                (<?= htmlspecialchars($ev['status']) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-4 d-flex align-items-end">

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Load Event
                    </button>

                </div>

            </form>

        </div>

    </div>


    <?php if ($drawError): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($drawError) ?>
        </div>

    <?php endif; ?>


    <?php if ($drawSuccess): ?>

        <div class="alert alert-success">

            <h5 class="alert-heading">
                <i class="bi bi-stars"></i> Winner Drawn!
            </h5>

            <div class="mt-2">

                Ticket
                <strong>
                    <?= htmlspecialchars($drawSuccess['ticket_number']) ?>
                </strong>

                won the
                <strong>
                    <?= htmlspecialchars($drawSuccess['prize_name']) ?>
                </strong>

            </div>

        </div>

    <?php endif; ?>


    <?php foreach ($autoDrawResults as $autoResult): ?>

        <?php if (isset($autoResult['error'])): ?>

            <div class="alert alert-warning">
                Auto-draw stopped: <?= htmlspecialchars($autoResult['error']) ?>
            </div>

        <?php else: ?>

            <div class="alert alert-success py-2">

                Ticket
                <strong>
                    <?= htmlspecialchars($autoResult['ticket_number']) ?>
                </strong>

                won the
                <strong>
                    <?= htmlspecialchars($autoResult['prize_name']) ?>
                </strong>

            </div>

        <?php endif; ?>

    <?php endforeach; ?>


    <?php if ($event): ?>

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body">

                <div class="row">

                    <div class="col-md-4">

                        <h4 class="fw-bold mb-0">
                            <?= htmlspecialchars($event['event_name']) ?>
                        </h4>

                        <span class="badge bg-primary mt-2">
                            <?= htmlspecialchars($event['status']) ?>
                        </span>

                    </div>

                    <div class="col-md-8">

                        <div class="row text-center">

                            <div class="col-4">

                                <div class="text-muted">
                                    Tickets Sold
                                </div>

                                <h4 class="fw-bold">
                                    <?= number_format($event['total_tickets']) ?>
                                </h4>

                            </div>

                            <div class="col-4">

                                <div class="text-muted">
                                    Prizes
                                </div>

                                <h4 class="fw-bold">
                                    <?= count($prizes) ?>
                                </h4>

                            </div>

                            <div class="col-4">

                                <div class="text-muted">
                                    Winners
                                </div>

                                <h4 class="fw-bold">
                                    <?= number_format($event['total_winners']) ?>
                                </h4>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <?php if ($event['status'] === 'OPEN'): ?>

            <div class="alert alert-warning">

                <strong>Sales are still open.</strong>

                Close sales first before starting the draw.

                <a
                    href="event-status.php?id=<?= $event['event_id'] ?>&action=close"
                    class="btn btn-sm btn-warning ms-2"
                >
                    Close Sales
                </a>

            </div>

        <?php elseif (
            $event['status'] !== 'CLOSED' &&
            $event['status'] !== 'DRAWING'
        ): ?>

            <div class="alert alert-warning">
                This event is not ready for the draw. Status:
                <?= htmlspecialchars($event['status']) ?>
            </div>

        <?php endif; ?>


        <div class="row g-4">

            <div class="col-lg-7">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <h5 class="fw-bold mb-0">
                                Prizes
                            </h5>

                            <?php
                            $hasAvailable = false;

                            foreach ($prizes as $prize) {
                                if ($prize['status'] === 'AVAILABLE') {
                                    $hasAvailable = true;
                                    break;
                                }
                            }
                            ?>

                            <?php if ($hasAvailable && in_array($event['status'], ['CLOSED', 'DRAWING'], true)): ?>

                                <a
                                    href="draw.php?event_id=<?= $event['event_id'] ?>&draw_all=1"
                                    class="btn btn-success btn-sm"
                                    onclick="return confirm('Auto-draw all remaining prizes now?');"
                                >
                                    <i class="bi bi-lightning-charge-fill"></i> Auto-Draw All
                                </a>

                            <?php endif; ?>

                        </div>

                        <?php if (count($prizes) === 0): ?>

                            <div class="text-center py-4">

                                <p class="text-muted mb-0">
                                    No prizes configured for this event.
                                </p>

                                <a
                                    href="prizes.php?event_id=<?= $event['event_id'] ?>"
                                    class="btn btn-outline-primary btn-sm mt-2"
                                >
                                    Add Prizes
                                </a>

                            </div>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-hover align-middle">

                                    <thead class="table-light">

                                        <tr>

                                            <th>#</th>

                                            <th>Prize</th>

                                            <th>Status</th>

                                            <th>Action</th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php foreach ($prizes as $prize): ?>

                                        <tr>

                                            <td>
                                                <?= $prize['prize_position'] ?>
                                            </td>

                                            <td>

                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($prize['prize_name']) ?>
                                                </div>

                                                <?php if ($prize['description']): ?>

                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($prize['description']) ?>
                                                    </small>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <?php

                                                $pBadge = match ($prize['status']) {
                                                    'AVAILABLE' => 'bg-success',
                                                    'AWARDED' => 'bg-primary',
                                                    'DISABLED' => 'bg-secondary',
                                                    default => 'bg-secondary'
                                                };

                                                ?>

                                                <span class="badge <?= $pBadge ?>">
                                                    <?= htmlspecialchars($prize['status']) ?>
                                                </span>

                                            </td>

                                            <td>

                                                <?php if ($prize['status'] === 'AVAILABLE'): ?>

                                                    <?php if (in_array($event['status'], ['CLOSED', 'DRAWING'], true)): ?>

                                                        <a
                                                            href="draw.php?event_id=<?= $event['event_id'] ?>&draw_prize=<?= $prize['prize_id'] ?>"
                                                            class="btn btn-sm btn-primary"
                                                            onclick="return confirm('Draw a winner for this prize?');"
                                                        >
                                                            <i class="bi bi-dice-5"></i> Draw Winner
                                                        </a>

                                                    <?php else: ?>

                                                        <span class="text-muted">
                                                            Event not ready
                                                        </span>

                                                    <?php endif; ?>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        &mdash;
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <div class="col-lg-5">

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-trophy-fill"></i> Winners Drawn
                        </h5>

                        <?php if (count($winners) === 0): ?>

                            <p class="text-muted">
                                No winners drawn yet.
                            </p>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-sm table-hover align-middle">

                                    <thead class="table-light">

                                        <tr>

                                            <th>Prize</th>

                                            <th>Student</th>

                                            <th>Ticket</th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php foreach ($winners as $winner): ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars($winner['prize_name']) ?>
                                            </td>

                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($winner['student_name']) ?>
                                                </div>

                                                <small class="text-muted">
                                                    <?= htmlspecialchars($winner['class_name']) ?>
                                                </small>
                                            </td>

                                            <td class="fw-bold text-nowrap">
                                                <?= htmlspecialchars($winner['ticket_number']) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    <?php else: ?>

        <div class="alert alert-info">
            Select an event to start the draw.
        </div>

    <?php endif; ?>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>