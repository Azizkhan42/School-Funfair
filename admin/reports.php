<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Reports & Cash Collection';

/*
|--------------------------------------------------------------------------
| Overall summary
|--------------------------------------------------------------------------
*/

$summaryStmt = $pdo->query("

    SELECT
        (SELECT COUNT(*) FROM lucky_draw_events) AS total_events,

        (SELECT COUNT(*) FROM lucky_draw_tickets) AS total_tickets,

        (SELECT ISNULL(SUM(amount), 0) FROM lucky_draw_tickets)
            AS total_revenue,

        (SELECT COUNT(*) FROM lucky_draw_winners) AS total_winners

");

$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Per event summary
|--------------------------------------------------------------------------
*/

$eventStmt = $pdo->query("

    SELECT
        e.event_id,
        e.event_name,
        e.ticket_price,
        e.max_tickets_per_student,
        e.sales_start,
        e.sales_end,
        e.status,

        (
            SELECT COUNT(*)
            FROM lucky_draw_tickets t
            WHERE t.event_id = e.event_id
        ) AS tickets_sold,

        (
            SELECT ISNULL(SUM(t.amount), 0)
            FROM lucky_draw_tickets t
            WHERE t.event_id = e.event_id
        ) AS revenue,

        (
            SELECT COUNT(*)
            FROM lucky_draw_prizes p
            WHERE p.event_id = e.event_id
        ) AS total_prizes,

        (
            SELECT COUNT(*)
            FROM lucky_draw_prizes p
            WHERE p.event_id = e.event_id
            AND p.status = 'AWARDED'
        ) AS awarded_prizes,

        (
            SELECT COUNT(*)
            FROM lucky_draw_winners w
            WHERE w.event_id = e.event_id
        ) AS total_winners

    FROM lucky_draw_events e

    ORDER BY e.event_id DESC

");

$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Cash collection by teacher per event
|--------------------------------------------------------------------------
*/

$teacherStmt = $pdo->query("

    SELECT
        th.teacher_id,
        th.teacher_name,
        e.event_id,
        e.event_name,
        COUNT(t.ticket_id) AS tickets_sold,
        ISNULL(SUM(t.amount), 0) AS cash_collected

    FROM test_teachers th

    LEFT JOIN lucky_draw_tickets t
        ON t.teacher_id = th.teacher_id

    LEFT JOIN lucky_draw_events e
        ON e.event_id = t.event_id

    GROUP BY
        th.teacher_id,
        th.teacher_name,
        e.event_id,
        e.event_name

    ORDER BY th.teacher_name, e.event_id

");

$teacherRows = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="mb-4">

        <h2 class="fw-bold mb-1">
            Reports & Cash Collection
        </h2>

        <p class="text-muted mb-0">
            Sales and collection summary across all events.
        </p>

    </div>


    <div class="row g-4 mb-4">

        <div class="col-md-6 col-lg-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Events
                    </p>

                    <h3 class="fw-bold mb-0">
                        <?= number_format($summary['total_events']) ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-lg-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Tickets Sold
                    </p>

                    <h3 class="fw-bold mb-0">
                        <?= number_format($summary['total_tickets']) ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-lg-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Total Cash Collected
                    </p>

                    <h3 class="fw-bold mb-0 text-success">
                        Rs.
                        <?= number_format($summary['total_revenue'], 2) ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-6 col-lg-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <p class="text-muted mb-1">
                        Winners
                    </p>

                    <h3 class="fw-bold mb-0">
                        <?= number_format($summary['total_winners']) ?>
                    </h3>

                </div>

            </div>

        </div>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">
                Event Summary
            </h5>

        </div>

        <div class="card-body">

            <?php if (count($events) === 0): ?>

                <div class="text-center py-4">

                    <p class="text-muted mb-0">
                        No events yet.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>Event</th>

                                <th>Status</th>

                                <th>Tickets</th>

                                <th>Cash Collected</th>

                                <th>Prizes</th>

                                <th>Winners</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($events as $event): ?>

                            <tr>

                                <td>

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($event['event_name']) ?>
                                    </div>

                                    <small class="text-muted">
                                        Ticket: Rs.
                                        <?= number_format($event['ticket_price'], 2) ?>
                                    </small>

                                </td>

                                <td>

                                    <?php

                                    $badgeClass = match ($event['status']) {
                                        'DRAFT' => 'bg-secondary',
                                        'OPEN' => 'bg-success',
                                        'CLOSED' => 'bg-warning text-dark',
                                        'DRAWING' => 'bg-primary',
                                        'COMPLETED' => 'bg-dark',
                                        'CANCELLED' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };

                                    ?>

                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($event['status']) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= number_format($event['tickets_sold']) ?>
                                </td>

                                <td class="fw-semibold">
                                    Rs.
                                    <?= number_format($event['revenue'], 2) ?>
                                </td>

                                <td>
                                    <?= $event['awarded_prizes'] ?>
                                    /
                                    <?= $event['total_prizes'] ?>
                                </td>

                                <td>
                                    <?= number_format($event['total_winners']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">

            <h5 class="fw-bold mb-0">
                Cash Collection by Teacher
            </h5>

        </div>

        <div class="card-body">

            <?php if (count($teacherRows) === 0): ?>

                <div class="text-center py-4">

                    <p class="text-muted mb-0">
                        No collection data available.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>Teacher</th>

                                <th>Event</th>

                                <th>Tickets</th>

                                <th>Cash Collected</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($teacherRows as $row): ?>

                            <?php if ($row['event_id'] === null): ?>

                                <tr>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($row['teacher_name']) ?>
                                    </td>

                                    <td colspan="3" class="text-muted">
                                        No sales yet
                                    </td>

                                </tr>

                            <?php else: ?>

                                <tr>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($row['teacher_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['event_name']) ?>
                                    </td>

                                    <td>
                                        <?= number_format($row['tickets_sold']) ?>
                                    </td>

                                    <td class="fw-semibold">
                                        Rs.
                                        <?= number_format($row['cash_collected'], 2) ?>
                                    </td>

                                </tr>

                            <?php endif; ?>

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