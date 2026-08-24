<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Ticket History';

$selectedEvent = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| Events for filter dropdown
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
| Build ticket query with filters
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        t.ticket_id,
        t.ticket_number,
        t.amount,
        t.payment_method,
        t.payment_status,
        t.status,
        t.issued_at,
        t.student_id,
        s.student_name,
        s.class_name,
        t.teacher_id,
        th.teacher_name,
        e.event_id,
        e.event_name
    FROM lucky_draw_tickets t
    INNER JOIN test_students s
        ON s.student_id = t.student_id
    LEFT JOIN test_teachers th
        ON th.teacher_id = t.teacher_id
    INNER JOIN lucky_draw_events e
        ON e.event_id = t.event_id
    WHERE 1 = 1
";

$params = [];

if ($selectedEvent) {
    $sql .= " AND t.event_id = :event_id";
    $params[':event_id'] = $selectedEvent;
}

if ($search !== '') {
    $sql .= " AND (t.ticket_number LIKE :search
            OR s.student_name LIKE :search
            OR s.class_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($status !== '') {
    $sql .= " AND t.status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY t.issued_at DESC, t.ticket_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                Ticket History
            </h2>

            <p class="text-muted mb-0">
                Browse all issued FunFair tickets.
            </p>
        </div>

        <div class="text-md-end">
            <span class="text-muted">
                Total Tickets
            </span>
            <h3 class="fw-bold">
                <?= count($tickets) ?>
            </h3>
        </div>

    </div>


    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form method="GET" class="row g-3">

                <div class="col-md-4">

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
                                (<?= htmlspecialchars($event['status']) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-4">

                    <label class="form-label fw-semibold">
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Ticket #, student, class"
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <div class="col-md-2">

                    <label class="form-label fw-semibold">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                    >

                        <option value="">All</option>

                        <?php foreach (['ACTIVE', 'WINNER', 'CANCELLED'] as $s): ?>

                            <option
                                value="<?= $s ?>"
                                <?= $status === $s ? 'selected' : '' ?>
                            >
                                <?= $s ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-2 d-flex align-items-end">

                    <div class="d-flex gap-2 w-100">

                        <button
                            type="submit"
                            class="btn btn-primary flex-fill"
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
                        Try changing the filters.
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

                                <th>Issued By</th>

                                <th>Amount</th>

                                <th>Payment</th>

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
                                    <?= htmlspecialchars($ticket['teacher_name'] ?? '&mdash;') ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format($ticket['amount'], 2) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($ticket['payment_method']) ?>
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

require_once __DIR__ . '/../includes/footer.php';

?>