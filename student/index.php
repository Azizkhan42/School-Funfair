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

require_once __DIR__ . '/../includes/student-nav.php';

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

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                🎓 Student Portal
            </h2>

            <p class="text-muted mb-0">
                Register for events. Your concern teacher will then issue your ticket.
            </p>
        </div>

    </div>

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body">

            <form
                method="GET"
                class="row g-3 align-items-center"
            >

                <div class="col-md-6">

                    <label class="form-label fw-semibold mb-1">
                        Who are you?
                    </label>

                    <select
                        name="student_id"
                        class="form-select"
                        onchange="this.form.submit()"
                    >

                        <?php foreach ($allStudents as $student): ?>

                            <option
                                value="<?= $student['student_id'] ?>"
                                <?= $student['student_id'] == $studentId ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($student['student_name']) ?>
                                —
                                <?= htmlspecialchars($student['class_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-6">

                    <div class="border-start ps-3 pt-2 pt-md-0">

                        <div class="fw-bold">
                            👤 <?= htmlspecialchars($currentStudent['student_name']) ?>
                        </div>

                        <div class="text-muted small">
                            <?= htmlspecialchars($currentStudent['class_name']) ?>
                            • Concern teacher:
                            <strong>
                                <?= htmlspecialchars($currentStudent['teacher_name'] ?? '—') ?>
                            </strong>
                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>


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
            ✅ <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <div class="row">

        <div class="col-lg-7">

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <h5 class="fw-bold mb-3">
                        🎟️ Events open for registration
                    </h5>

                    <?php if (count($events) === 0): ?>

                        <p class="text-muted mb-0">
                            No events are currently accepting registrations.
                        </p>

                    <?php else: ?>

                        <?php foreach ($events as $event): ?>

                            <div class="border rounded-3 p-3 mb-3">

                                <div class="d-flex justify-content-between align-items-center">

                                    <div>

                                        <div class="fw-bold fs-5">
                                            <?= htmlspecialchars($event['event_name']) ?>
                                        </div>

                                        <div class="text-muted small">
                                            Event Date:
                                            <?= date('d M Y', strtotime($event['sales_end'])) ?>
                                            •
                                            Ticket Price:
                                            Rs.
                                            <?= number_format($event['ticket_price'], 2) ?>
                                        </div>

                                        <span class="badge bg-<?= $event['status'] === 'OPEN' ? 'success' : 'secondary' ?> mt-1">
                                            <?= htmlspecialchars($event['status']) ?>
                                        </span>

                                    </div>

                                    <?php if ($event['is_registered']): ?>

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

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Cancel your registration for this event?');"
                                            >
                                                Cancel Registration
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form method="POST" class="text-end">

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

                                            <div class="mb-2">

                                                <label class="form-label small fw-semibold mb-1">
                                                    🎟️ How many tickets do you want?
                                                </label>

                                                <select
                                                    name="ticket_quantity"
                                                    class="form-select form-select-sm"
                                                    style="min-width: 130px;"
                                                    required
                                                >

                                                    <?php for ($i = 1; $i <= (int)$event['max_tickets_per_student']; $i++): ?>

                                                        <option value="<?= $i ?>">
                                                            <?= $i ?>
                                                            Ticket<?= $i > 1 ? 's' : '' ?>
                                                            (Rs.
                                                            <?= number_format($i * $event['ticket_price'], 0) ?>
                                                            )
                                                        </option>

                                                    <?php endfor; ?>

                                                </select>

                                            </div>

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Register
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="col-lg-5">

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <h5 class="fw-bold mb-3">
                        📋 My Registrations
                    </h5>

                    <?php if (count($myRegs) === 0): ?>

                        <p class="text-muted mb-0">
                            You have not registered for any event yet.
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
                                                •
                                                Registered:
                                                <?= date('d M Y h:i A', strtotime($reg['registered_at'])) ?>
                                            </div>

                                            <div class="small mt-1">
                                                Requested:
                                                <span class="fw-semibold">
                                                    🎟️
                                                    <?= (int)$reg['ticket_quantity'] ?>
                                                    ticket<?= (int)$reg['ticket_quantity'] > 1 ? 's' : '' ?>
                                                </span>
                                            </div>

                                            <div class="small mt-1">
                                                Ticket:
                                                <?php if ($isIssued && (int)$reg['ticket_count'] > 0): ?>
                                                    <span class="fw-bold text-success">
                                                        🎟️
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

        </div>

    </div>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>