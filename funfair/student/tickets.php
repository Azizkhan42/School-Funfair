<?php

require_once __DIR__ . '/../../includes/student-session.php';

$pageTitle = 'My Tickets';

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/navbar.php';

require_once __DIR__ . '/../../includes/student-nav.php';

/*
|--------------------------------------------------------------------------
| My Tickets (only the logged-in student's own tickets)
|--------------------------------------------------------------------------
*/

$ticketsStmt = $pdo->prepare("

    SELECT
        t.ticket_id,
        t.ticket_number,
        t.amount,
        t.status,
        t.issued_at,
        e.event_name

    FROM lucky_draw_tickets t

    INNER JOIN lucky_draw_events e
        ON e.event_id = t.event_id

    WHERE t.student_id = :student_id

    ORDER BY t.issued_at DESC, t.ticket_id DESC

");

$ticketsStmt->execute([
    ':student_id' => $studentId
]);

$tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                🎟️ My Tickets
            </h2>

            <p class="text-muted mb-0">
                Lucky Draw tickets issued to
                <strong>
                    <?= htmlspecialchars($currentStudent['student_name']) ?>
                </strong>
                (<?= htmlspecialchars($currentStudent['class_name']) ?>).
            </p>
        </div>

    </div>

    <div class="card border-0 shadow-sm">

        <div class="card-body">

            <?php if (count($tickets) === 0): ?>

                <div class="text-center py-5">

                    <h5>
                        No tickets yet
                    </h5>

                    <p class="text-muted">
                        Your tickets will appear here after your concern teacher issues them.
                    </p>

                    <a
                        href="index.php"
                        class="btn btn-primary"
                    >
                        Go to FunFair
                    </a>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>Ticket Number</th>

                                <th>Event</th>

                                <th>Amount</th>

                                <th>Status</th>

                                <th>Issued At</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($tickets as $ticket): ?>

                                <?php

                                $tBadge = match ($ticket['status']) {
                                    'ACTIVE' => 'bg-success',
                                    'WINNER' => 'bg-primary',
                                    'CANCELLED' => 'bg-danger',
                                    default => 'bg-secondary'
                                };

                                ?>

                                <tr>

                                    <td class="fw-bold text-nowrap">
                                        <?= htmlspecialchars($ticket['ticket_number']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($ticket['event_name']) ?>
                                    </td>

                                    <td>
                                        Rs.
                                        <?= number_format((float)$ticket['amount'], 2) ?>
                                    </td>

                                    <td>
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
