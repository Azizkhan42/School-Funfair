<?php

require_once __DIR__ . '/../../includes/teacher-session.php';

$selectedEvent = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Events for filter
|--------------------------------------------------------------------------
*/

$eventsStmt = $pdo->prepare("

    SELECT DISTINCT
        e.event_id,
        e.event_name

    FROM lucky_draw_events e

    INNER JOIN lucky_draw_tickets t
        ON t.event_id = e.event_id
        AND t.teacher_id = :teacher_id

    ORDER BY e.event_id DESC

");

$eventsStmt->execute([
    ':teacher_id' => $teacherId
]);

$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Tickets issued by this teacher
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT
        t.ticket_id,
        t.ticket_number,
        t.amount,
        t.payment_method,
        t.status,
        t.issued_at,
        t.student_id,
        s.student_name,
        s.class_name,
        e.event_id,
        e.event_name

    FROM lucky_draw_tickets t

    INNER JOIN test_students s
        ON s.student_id = t.student_id

    INNER JOIN lucky_draw_events e
        ON e.event_id = t.event_id

    WHERE t.teacher_id = :teacher_id

";

$params = [
    ':teacher_id' => $teacherId
];

if ($selectedEvent) {

    $sql .= " AND t.event_id = :event_id";

    $params[':event_id'] = $selectedEvent;

}

$sql .= " ORDER BY t.issued_at DESC, t.ticket_id DESC";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| My collection summary
|--------------------------------------------------------------------------
*/

$summaryStmt = $pdo->prepare("

    SELECT
        COUNT(*) AS total_tickets,
        ISNULL(SUM(amount), 0) AS total_amount

    FROM lucky_draw_tickets

    WHERE teacher_id = :teacher_id

");

$summaryStmt->execute([
    ':teacher_id' => $teacherId
]);

$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'My Ticket History';

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/navbar.php';

require_once __DIR__ . '/../../includes/teacher-nav.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                My Ticket History
            </h2>

            <p class="text-muted mb-0">
                Tickets issued by
                <strong>
                    <?= htmlspecialchars($currentTeacher['teacher_name']) ?>
                </strong>.
            </p>
        </div>

        <div class="d-flex gap-3 text-md-end">

            <div>
                <span class="text-muted">
                    Tickets Issued
                </span>
                <h3 class="fw-bold mb-0">
                    <?= number_format($summary['total_tickets']) ?>
                </h3>
            </div>

            <div>
                <span class="text-muted">
                    Cash Collected
                </span>
                <h3 class="fw-bold mb-0">
                    Rs.
                    <?= number_format($summary['total_amount'], 2) ?>
                </h3>
            </div>

        </div>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" class="row g-3">

                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Event
                    </label>

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
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-6 d-flex align-items-end">

                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Filter
                        </button>

                        <a
                            href="tickets.php"
                            class="btn btn-outline-secondary"
                        >
                            Reset
                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <?php if (count($tickets) === 0): ?>

                <div class="text-center py-5">

                    <h5>
                        No tickets found
                    </h5>

                    <p class="text-muted">
                        You have not issued any tickets yet.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>Ticket #</th>

                                <th>Event</th>

                                <th>Student</th>

                                <th>Class</th>

                                <th>Amount</th>

                                <th>Status</th>

                                <th>Issued At</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($tickets as $ticket): ?>

                            <tr>

                                <td class="fw-bold text-nowrap">
                                    <?= htmlspecialchars($ticket['ticket_number']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($ticket['event_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($ticket['student_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($ticket['class_name']) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format($ticket['amount'], 2) ?>
                                </td>

                                <td>

                                    <?php

                                    $tBadge = match ($ticket['status']) {
                                        'ACTIVE' => 'bg-success',
                                        'WINNER' => 'bg-primary',
                                        'CANCELLED' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };

                                    ?>

                                    <span class="badge <?= $tBadge ?>">
                                        <?= htmlspecialchars($ticket['status']) ?>
                                    </span>

                                </td>

                                <td class="text-nowrap">
                                    <?= date(
                                        'd M Y h:i A',
                                        strtotime($ticket['issued_at'])
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

require_once __DIR__ . '/../../includes/footer.php';

?>