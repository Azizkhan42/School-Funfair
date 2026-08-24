<?php

require_once __DIR__ . '/../includes/teacher-session.php';

$search = trim($_GET['q'] ?? '');

/*
|--------------------------------------------------------------------------
| Registrations of MY students only
|--------------------------------------------------------------------------
|
| A registration belongs to this teacher when it was stored with their
| teacher_id, or when the student's current concern teacher matches.
|
*/

$sql = "

    SELECT
        r.registration_id,
        r.status,
        r.registered_at,
        r.ticket_quantity,
        s.student_id,
        s.student_name,
        s.class_name,
        e.event_id,
        e.event_name,
        e.ticket_price,
        e.sales_end,
        e.status AS event_status,
        (
            SELECT COUNT(*)
            FROM lucky_draw_tickets t
            WHERE t.event_id = e.event_id
              AND t.student_id = s.student_id
        ) AS ticket_count

    FROM lucky_draw_registrations r

    INNER JOIN test_students s
        ON s.student_id = r.student_id

    INNER JOIN lucky_draw_events e
        ON e.event_id = r.event_id

    WHERE (r.teacher_id = :teacher_id OR s.teacher_id = :teacher_id2)

";

$params = [
    ':teacher_id' => $teacherId,
    ':teacher_id2' => $teacherId
];

if ($search !== '') {

    $sql .= " AND s.student_name LIKE :search";

    $params[':search'] = '%' . $search . '%';

}

$sql .= " ORDER BY r.registered_at DESC, r.registration_id DESC";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'FunFair Registrations';

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-clipboard-check"></i> FunFair Registrations
            </h2>

            <p class="text-muted mb-0">
                Students of
                <strong>
                    <?= htmlspecialchars($currentTeacher['teacher_name']) ?>
                </strong>
                who registered for FunFair events.
            </p>
        </div>

    </div>

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form
                method="GET"
                class="row g-3 align-items-end"
            >

                <div class="col-md-6">

                    <label class="form-label fw-semibold mb-1">
                        Search by student name
                    </label>

                    <input
                        type="text"
                        name="q"
                        class="form-control"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="e.g. Muhammad Ali"
                    >

                </div>

                <div class="col-md-6 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Search
                    </button>

                    <a
                        href="registrations.php"
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

            <?php if (count($registrations) === 0): ?>

                <div class="text-center py-5">

                    <h5>
                        No registrations found
                    </h5>

                    <p class="text-muted mb-0">
                        Registrations appear here when your students register for a FunFair event.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>Student</th>

                                <th>Class</th>

                                <th>Event</th>

                                <th>Requested Tickets</th>

                                <th>Registered At</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($registrations as $reg): ?>

                                <?php

                                $isIssued = $reg['status'] === 'TICKET_ISSUED';

                                ?>

                                <tr>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($reg['student_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reg['class_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reg['event_name']) ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <i class="bi bi-ticket-perforated"></i>
                                            <?= (int)$reg['ticket_quantity'] ?>
                                            requested
                                        </span>
                                    </td>

                                    <td class="text-nowrap">
                                        <?= date(
                                            'd M Y h:i A',
                                            strtotime($reg['registered_at'])
                                        ) ?>
                                    </td>

                                    <td>

                                        <span class="badge bg-<?= $isIssued ? 'success' : 'warning text-dark' ?>">
                                            <?= $isIssued ? 'Ticket Issued' : 'Pending' ?>
                                        </span>

                                        <?php if ($isIssued): ?>

                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-ticket-perforated"></i>
                                                <?= (int)$reg['ticket_count'] ?>
                                                ticket<?= (int)$reg['ticket_count'] === 1 ? '' : 's' ?>
                                            </div>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if (!$isIssued): ?>

                                            <a
                                                href="issue-ticket.php?event_id=<?= $reg['event_id'] ?>&student_id=<?= $reg['student_id'] ?>"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Issue Ticket
                                            </a>

                                        <?php else: ?>

                                            <a
                                                href="tickets.php?event_id=<?= $reg['event_id'] ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                View Tickets
                                            </a>

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

<?php

require_once __DIR__ . '/../includes/footer.php';

?>
