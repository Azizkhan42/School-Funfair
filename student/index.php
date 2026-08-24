<?php

require_once __DIR__ . '/../includes/student-session.php';

require_once __DIR__ . '/../includes/audit.php';

$errors = [];

$success = null;

/*
|--------------------------------------------------------------------------
| Handle registration
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    $ticketQty = filter_input(INPUT_POST, 'ticket_quantity', FILTER_VALIDATE_INT);

    $action = $_POST['action'] ?? 'register';

    if ($eventId) {

        if ($action === 'register') {

            $eventCheck = $pdo->prepare("

                SELECT event_name, status, max_tickets_per_student

                FROM lucky_draw_events

                WHERE event_id = :event_id
                  AND status IN ('DRAFT', 'OPEN')

            ");

            $eventCheck->execute([
                ':event_id' => $eventId
            ]);

            $eventRow = $eventCheck->fetch(PDO::FETCH_ASSOC);

            if (!$eventRow) {

                $errors[] = 'This event is not accepting registrations.';

            } elseif (
                !$ticketQty ||
                $ticketQty < 1 ||
                $ticketQty > (int)$eventRow['max_tickets_per_student']
            ) {

                $errors[] =
                    'Please choose between 1 and ' .
                    (int)$eventRow['max_tickets_per_student'] .
                    ' tickets.';

            } else {

                $checkStmt = $pdo->prepare("

                    SELECT COUNT(*)

                    FROM lucky_draw_registrations

                    WHERE event_id = :event_id
                      AND student_id = :student_id

                ");

                $checkStmt->execute([
                    ':event_id' => $eventId,
                    ':student_id' => $studentId
                ]);

                if ((int)$checkStmt->fetchColumn() > 0) {

                    $errors[] = 'You are already registered for this event.';

                } else {

                    try {

                        $pdo->beginTransaction();

                        $insertStmt = $pdo->prepare("

                            INSERT INTO lucky_draw_registrations
                            (
                                event_id,
                                student_id,
                                teacher_id,
                                ticket_quantity,
                                registered_at,
                                status
                            )
                            VALUES
                            (
                                :event_id,
                                :student_id,
                                :teacher_id,
                                :ticket_quantity,
                                GETDATE(),
                                'PENDING'
                            )

                        ");

                        $insertStmt->execute([
                            ':event_id' => $eventId,
                            ':student_id' => $studentId,
                            ':teacher_id' => $currentStudent['teacher_id'],
                            ':ticket_quantity' => $ticketQty
                        ]);

                        $pdo->commit();

                        $success =
                            'Registration Successful! You requested ' .
                            $ticketQty .
                            ' ticket(s). Status: Pending Teacher Approval / Ticket Issuance.';

                        logAudit(
                            $pdo,
                            1,
                            'STUDENT_REGISTERED',
                            'EVENT',
                            $eventId,
                            $eventId,
                            'Student ' .
                                $currentStudent['student_name'] .
                                ' (ID ' .
                                $studentId .
                                ') registered for event "' .
                                $eventRow['event_name'] .
                                '".'
                        );

                    } catch (Throwable $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $errors[] = 'Unable to register: ' . $e->getMessage();

                    }

                }

            }

        } elseif ($action === 'cancel') {

            $ticketCheck = $pdo->prepare("

                SELECT COUNT(*)

                FROM lucky_draw_tickets

                WHERE event_id = :event_id
                  AND student_id = :student_id

            ");

            $ticketCheck->execute([
                ':event_id' => $eventId,
                ':student_id' => $studentId
            ]);

            if ((int)$ticketCheck->fetchColumn() > 0) {

                $errors[] =
                    'Tickets have already been issued for this registration. ' .
                    'Please contact your teacher to cancel.';

            } else {

                $cancelStmt = $pdo->prepare("

                    DELETE FROM lucky_draw_registrations

                    WHERE event_id = :event_id
                      AND student_id = :student_id

                ");

                $cancelStmt->execute([
                    ':event_id' => $eventId,
                    ':student_id' => $studentId
                ]);

                if ($cancelStmt->rowCount() > 0) {

                    $success = 'Your registration was cancelled.';

                    logAudit(
                        $pdo,
                        1,
                        'REGISTRATION_REMOVED',
                        'EVENT',
                        $eventId,
                        $eventId,
                        'Student ' .
                            $currentStudent['student_name'] .
                            ' (ID ' .
                            $studentId .
                            ') cancelled registration for event ID ' .
                            $eventId .
                            '.'
                    );

                }

            }

        }

    }

}

$pageTitle = 'Student Portal';

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

/*
|--------------------------------------------------------------------------
| Events accepting registrations (DRAFT / OPEN)
|--------------------------------------------------------------------------
*/

$eventsStmt = $pdo->prepare("

    SELECT
        e.event_id,
        e.event_name,
        e.status,
        e.ticket_price,
        e.sales_start,
        e.sales_end,
        e.max_tickets_per_student,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM lucky_draw_registrations r
                WHERE r.event_id = e.event_id
                  AND r.student_id = :student_id
            ) THEN 1
            ELSE 0
        END AS is_registered

    FROM lucky_draw_events e

    WHERE e.status IN ('DRAFT', 'OPEN')

    ORDER BY e.event_id DESC

");

$eventsStmt->execute([
    ':student_id' => $studentId
]);

$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| My registrations
|--------------------------------------------------------------------------
*/

$myRegsStmt = $pdo->prepare("

    SELECT
        r.registration_id,
        r.registered_at,
        r.status AS registration_status,
        r.ticket_quantity,
        e.event_name,
        e.ticket_price,
        COALESCE(e.draw_date, e.sales_end) AS event_date,
        (
            SELECT COUNT(*)
            FROM lucky_draw_tickets t
            WHERE t.event_id = e.event_id
              AND t.student_id = r.student_id
        ) AS ticket_count,
        (
            SELECT ISNULL(SUM(t.amount), 0)
            FROM lucky_draw_tickets t
            WHERE t.event_id = e.event_id
              AND t.student_id = r.student_id
        ) AS total_amount

    FROM lucky_draw_registrations r

    INNER JOIN lucky_draw_events e
        ON e.event_id = r.event_id

    WHERE r.student_id = :student_id

    ORDER BY r.registered_at DESC

");

$myRegsStmt->execute([
    ':student_id' => $studentId
]);

$myRegs = $myRegsStmt->fetchAll(PDO::FETCH_ASSOC);

$currentView = $_GET['view'] ?? '';

if ($currentView === '' && isset($_POST['view'])) {
    $currentView = $_POST['view'];
}

if (!in_array($currentView, ['dashboard', 'events', 'registrations'], true)) {
    $currentView = 'dashboard';
}

?>
<div class="container py-4">

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-1"></i>
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($currentView === 'dashboard'): ?>

        <!--
        |--------------------------------------------------------------------------
        | Dashboard: main cards
        |--------------------------------------------------------------------------
        -->

        <div class="mb-4">

            <h2 class="fw-bold mb-1">
                <i class="bi bi-mortarboard text-primary me-2"></i>Welcome,
                <?= htmlspecialchars($currentStudent['student_name']) ?>!
            </h2>

            <p class="text-muted mb-0">
                <?= htmlspecialchars($currentStudent['class_name']) ?>
                &bull; Concern teacher:
                <strong>
                    <?= htmlspecialchars($currentStudent['teacher_name'] ?? '-') ?>
                </strong>
            </p>

        </div>

        <div class="row g-4">

            <div class="col-md-4">

                <a
                    href="?view=events"
                    class="text-decoration-none"
                >
                    <div class="card border-0 shadow-sm h-100 student-menu-card">

                        <div class="card-body text-center py-5">

                            <div class="student-card-icon bi-calendar-event">
                                <i class="bi bi-calendar-event"></i>
                            </div>

                            <h5 class="fw-bold mt-3 mb-1">
                                Available Events
                            </h5>

                            <p class="text-muted small mb-0">
                                See events and apply for tickets
                            </p>

                        </div>

                    </div>
                </a>

            </div>

            <div class="col-md-4">

                <a
                    href="?view=registrations"
                    class="text-decoration-none"
                >
                    <div class="card border-0 shadow-sm h-100 student-menu-card">

                        <div class="card-body text-center py-5">

                            <div class="student-card-icon bi-clipboard-check">
                                <i class="bi bi-clipboard-check"></i>
                            </div>

                            <h5 class="fw-bold mt-3 mb-1">
                                My Registrations
                            </h5>

                            <p class="text-muted small mb-0">
                                Track your applications
                                (<?= count($myRegs) ?>)
                            </p>

                        </div>

                    </div>
                </a>

            </div>

            <div class="col-md-4">

                <a
                    href="tickets.php"
                    class="text-decoration-none"
                >
                    <div class="card border-0 shadow-sm h-100 student-menu-card">

                        <div class="card-body text-center py-5">

                            <div class="student-card-icon bi-ticket-perforated">
                                <i class="bi bi-ticket-perforated"></i>
                            </div>

                            <h5 class="fw-bold mt-3 mb-1">
                                My Tickets
                            </h5>

                            <p class="text-muted small mb-0">
                                View your issued lucky draw tickets
                            </p>

                        </div>

                    </div>
                </a>

            </div>

        </div>

    <?php elseif ($currentView === 'events'): ?>

        <!--
        |--------------------------------------------------------------------------
        | Available Events: event cards -> click to apply (modal)
        |--------------------------------------------------------------------------
        -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>
                <h2 class="fw-bold mb-1">
                    <i class="bi bi-calendar-event text-primary me-2"></i>Available Events
                </h2>

                <p class="text-muted mb-0">
                    Click on an event card to see details and apply.
                </p>
            </div>

            <a
                href="index.php"
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>

        </div>

        <?php if (count($events) === 0): ?>

            <div class="card border-0 shadow-sm">

                <div class="card-body text-center py-5">
                    <h5>No events available right now</h5>
                    <p class="text-muted mb-0">
                        Please check back later.
                    </p>
                </div>

            </div>

        <?php else: ?>

            <div class="row g-4">

                <?php foreach ($events as $index => $event): ?>

                    <?php

                    $isRegistered = (bool)$event['is_registered'];

                    $modalId = 'eventApplyModal' . (int)$event['event_id'];

                    $maxTickets = (int)$event['max_tickets_per_student'];

                    ?>

                    <div class="col-md-6 col-xl-4">

                        <div
                            class="card border-0 shadow-sm h-100 student-event-card"
                            data-bs-toggle="modal"
                            data-bs-target="#<?= $modalId ?>"
                            role="button"
                        >

                            <div class="card-body">

                                <span class="badge bg-<?= $event['status'] === 'OPEN' ? 'success' : 'secondary' ?> mb-2">
                                    <?= htmlspecialchars($event['status']) ?>
                                </span>

                                <?php if ($isRegistered): ?>

                                    <span class="badge bg-info text-dark mb-2 ms-1">
                                        <i class="bi bi-patch-check-fill me-1"></i>Applied
                                    </span>

                                <?php endif; ?>

                                <h5 class="fw-bold mb-2">
                                    <?= htmlspecialchars($event['event_name']) ?>
                                </h5>

                                <ul class="list-unstyled small text-muted mb-3">

                                    <li class="mb-1">
                                        <i class="bi bi-calendar3 me-2"></i>Event Date:
                                        <?= date('d M Y', strtotime($event['sales_end'])) ?>
                                    </li>

                                    <li class="mb-1">
                                        <i class="bi bi-cash-coin me-2"></i>Ticket Price:
                                        Rs. <?= number_format($event['ticket_price'], 2) ?>
                                    </li>

                                    <li>
                                        <i class="bi bi-ticket-perforated me-2"></i>Max Tickets:
                                        <?= $maxTickets ?> per student
                                    </li>

                                </ul>

                                <button
                                    type="button"
                                    class="btn btn-sm <?= $isRegistered ? 'btn-outline-primary' : 'btn-primary' ?>"
                                >
                                    <?= $isRegistered ? 'View Application' : 'Apply Now' ?>
                                    <?php if (!$isRegistered): ?>
                                        <i class="bi bi-arrow-right ms-1"></i>
                                    <?php endif; ?>
                                </button>

                            </div>

                        </div>

                    </div>

                    <!-- Apply modal for this event -->
                    <div
                        class="modal fade"
                        id="<?= $modalId ?>"
                        tabindex="-1"
                        aria-hidden="true"
                    >

                        <div class="modal-dialog modal-dialog-centered">

                            <div class="modal-content">

                                <div class="modal-header">

                                    <h5 class="modal-title fw-bold">
                                        <?= htmlspecialchars($event['event_name']) ?>
                                    </h5>

                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>

                                </div>

                                <div class="modal-body">

                                    <ul class="list-group list-group-flush mb-3">

                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Status</span>
                                            <strong><?= htmlspecialchars($event['status']) ?></strong>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Event Date</span>
                                            <strong><?= date('d M Y', strtotime($event['sales_end'])) ?></strong>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Ticket Price</span>
                                            <strong>Rs. <?= number_format($event['ticket_price'], 2) ?></strong>
                                        </li>

                                        <li class="list-group-item d-flex justify-content-between">
                                            <span>Sales Window</span>
                                            <strong>
                                                <?= date('d M', strtotime($event['sales_start'])) ?>
                                                &mdash;
                                                <?= date('d M Y', strtotime($event['sales_end'])) ?>
                                            </strong>
                                        </li>

                                    </ul>

                                    <?php if ($isRegistered): ?>

                                        <div class="alert alert-info mb-3">
                                            <i class="bi bi-info-circle-fill me-1"></i>
                                            You have already applied for this event.
                                            Your teacher will issue your ticket soon.
                                        </div>

                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="event_id"
                                                value="<?= $event['event_id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="cancel"
                                            >

                                            <input
                                                type="hidden"
                                                name="view"
                                                value="events"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-outline-danger w-100"
                                                onclick="return confirm('Cancel your application for this event?');"
                                            >
                                                Cancel Application
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="event_id"
                                                value="<?= $event['event_id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="register"
                                            >

                                            <input
                                                type="hidden"
                                                name="view"
                                                value="events"
                                            >

                                            <div class="mb-3">

                                                <label class="form-label fw-semibold mb-1">
                                                    <i class="bi bi-ticket-perforated me-1"></i>
                                                    How many tickets do you want?
                                                </label>

                                                <select
                                                    name="ticket_quantity"
                                                    class="form-select"
                                                    required
                                                >

                                                    <?php for ($i = 1; $i <= $maxTickets; $i++): ?>

                                                        <option value="<?= $i ?>">
                                                            <?= $i ?>
                                                            Ticket<?= $i > 1 ? 's' : '' ?>
                                                            (Rs. <?= number_format($i * $event['ticket_price'], 0) ?>)
                                                        </option>

                                                    <?php endfor; ?>

                                                </select>

                                            </div>

                                            <button
                                                type="submit"
                                                class="btn btn-primary w-100"
                                            >
                                                <i class="bi bi-ticket-detailed-fill me-1"></i>
                                                Apply For This Event
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php else: ?>

        <!--
        |--------------------------------------------------------------------------
        | My Registrations
        |--------------------------------------------------------------------------
        -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>
                <h2 class="fw-bold mb-1">
                    <i class="bi bi-clipboard-check text-primary me-2"></i>My Registrations
                </h2>

                <p class="text-muted mb-0">
                    Applications for
                    <strong><?= htmlspecialchars($currentStudent['student_name']) ?></strong>.
                </p>
            </div>

            <a
                href="index.php"
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>

        </div>

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <?php if (count($myRegs) === 0): ?>

                    <p class="text-muted mb-0 text-center py-4">
                        You have not registered for any event yet.

                        <a href="?view=events">Browse available events</a>.
                    </p>

                <?php else: ?>

                    <ul class="list-group list-group-flush">

                        <?php foreach ($myRegs as $reg): ?>

                            <?php

                            $isIssued = $reg['registration_status'] === 'TICKET_ISSUED';

                            ?>

                            <li class="list-group-item">

                                <div class="d-flex justify-content-between">

                                    <div>

                                        <strong>
                                            <?= htmlspecialchars($reg['event_name']) ?>
                                        </strong>

                                        <div class="text-muted small">
                                            Event Date:
                                            <?= date('d M Y', strtotime($reg['event_date'])) ?>
                                            &bull;
                                            Registered:
                                            <?= date('d M Y h:i A', strtotime($reg['registered_at'])) ?>
                                        </div>

                                        <div class="small mt-1">
                                            Requested:
                                            <span class="fw-semibold">
                                                <i class="bi bi-ticket-perforated me-1"></i>
                                                <?= (int)$reg['ticket_quantity'] ?>
                                                ticket<?= (int)$reg['ticket_quantity'] > 1 ? 's' : '' ?>
                                            </span>
                                        </div>

                                        <div class="small mt-1">
                                            Ticket:
                                            <?php if ($isIssued && (int)$reg['ticket_count'] > 0): ?>
                                                <span class="fw-bold text-success">
                                                    <i class="bi bi-patch-check-fill me-1"></i>
                                                    <?= (int)$reg['ticket_count'] ?>
                                                    issued
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                    </div>

                                    <div class="text-end">

                                        <span class="badge bg-<?= $isIssued ? 'success' : 'warning text-dark' ?>">
                                            <?= $isIssued ? 'Ticket Issued' : 'Pending' ?>
                                        </span>

                                        <?php if ($isIssued): ?>

                                            <div class="small mt-1">
                                                Total:
                                                Rs.
                                                <?= number_format((float)$reg['total_amount'], 2) ?>
                                            </div>

                                            <a
                                                href="tickets.php"
                                                class="btn btn-sm btn-outline-primary mt-1"
                                            >
                                                View Tickets
                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>
