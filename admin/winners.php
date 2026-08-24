<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Winners';

$selectedEvent = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Events for filter
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
| Winners query
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT
        w.winner_id,
        w.drawn_at,
        w.status,
        p.prize_name,
        p.prize_position,
        t.ticket_number,
        s.student_name,
        s.class_name,
        e.event_id,
        e.event_name

    FROM lucky_draw_winners w

    INNER JOIN lucky_draw_prizes p
        ON p.prize_id = w.prize_id

    INNER JOIN lucky_draw_tickets t
        ON t.ticket_id = w.ticket_id

    INNER JOIN test_students s
        ON s.student_id = w.student_id

    INNER JOIN lucky_draw_events e
        ON e.event_id = w.event_id

    WHERE 1 = 1

";

$params = [];

if ($selectedEvent) {

    $sql .= " AND w.event_id = :event_id";

    $params[':event_id'] = $selectedEvent;

}

$sql .= " ORDER BY w.drawn_at DESC";

$winnersStmt = $pdo->prepare($sql);

$winnersStmt->execute($params);

$winners = $winnersStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Count winners by event for selector summary
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->query("

    SELECT
        event_id,
        COUNT(*) AS winner_count

    FROM lucky_draw_winners

    GROUP BY event_id

");

$winnerCounts = [];

foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $winnerCounts[$row['event_id']] = (int)$row['winner_count'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                Winners
            </h2>

            <p class="text-muted mb-0">
                All lucky draw winners.
            </p>
        </div>

        <div class="text-md-end">
            <span class="text-muted">
                Total Winners
            </span>
            <h3 class="fw-bold">
                <?= count($winners) ?>
            </h3>
        </div>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" class="row g-3">

                <div class="col-md-8">

                    <select
                        name="event_id"
                        class="form-select"
                    >

                        <option value="">
                            All Events
                        </option>

                        <?php foreach ($events as $event): ?>

                            <option
                                value="<?= $event['event_id'] ?>"
                                <?= $selectedEvent == $event['event_id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($event['event_name']) ?>
                                (<?= $winnerCounts[$event['event_id']] ?? 0 ?> winners)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-4 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary flex-fill"
                    >
                        Filter
                    </button>

                    <a
                        href="winners.php"
                        class="btn btn-outline-secondary"
                    >
                        Reset
                    </a>

                </div>

            </form>

        </div>

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <?php if (count($winners) === 0): ?>

                <div class="text-center py-5">

                    <h5>
                        No winners yet
                    </h5>

                    <p class="text-muted">
                        Run the draw to select winners.
                    </p>

                    <a
                        href="draw.php"
                        class="btn btn-primary"
                    >
                        Go to Draw Engine
                    </a>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>Prize</th>

                                <th>Student</th>

                                <th>Class</th>

                                <th>Ticket #</th>

                                <th>Event</th>

                                <th>Drawn At</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($winners as $winner): ?>

                            <tr>

                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($winner['prize_name']) ?>
                                    </div>

                                    <small class="text-muted">
                                        Position <?= $winner['prize_position'] ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars($winner['student_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($winner['class_name']) ?>
                                </td>

                                <td class="fw-bold text-nowrap">
                                    <?= htmlspecialchars($winner['ticket_number']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($winner['event_name']) ?>
                                </td>

                                <td class="text-nowrap">
                                    <?= date(
                                        'd M Y h:i A',
                                        strtotime($winner['drawn_at'])
                                    ) ?>
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

<?php

require_once __DIR__ . '/../includes/footer.php';

?>