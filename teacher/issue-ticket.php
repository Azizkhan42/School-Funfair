<?php

require_once __DIR__ . '/../../includes/teacher-session.php';

require_once __DIR__ . '/../../includes/audit.php';


$eventId = filter_input(
    INPUT_GET,
    'event_id',
    FILTER_VALIDATE_INT
);

if (!$eventId) {

    header('Location: index.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Event
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT *

    FROM lucky_draw_events

    WHERE event_id = :event_id

");

$stmt->execute([
    ':event_id' => $eventId
]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$event) {

    die('Event not found.');

}


/*
|--------------------------------------------------------------------------
| Validate Event
|--------------------------------------------------------------------------
*/

$now = new DateTime();

$salesStart = new DateTime(
    $event['sales_start']
);

$salesEnd = new DateTime(
    $event['sales_end']
);


if ($event['status'] !== 'OPEN') {

    $blockMessage =
        'This event is not currently open for ticket sales.';

} elseif ($now < $salesStart) {

    $blockMessage =
        'Ticket sales for this event have not started yet. ' .
        'They begin on ' .
        date('d M Y h:i A', strtotime($event['sales_start'])) .
        '.';

} elseif ($now >= $salesEnd) {

    $blockMessage =
        'Ticket sales for this event ended on ' .
        date('d M Y h:i A', strtotime($event['sales_end'])) .
        '.';

}

if (isset($blockMessage)) {

    $pageTitle = 'Issue Lucky Draw Tickets';

    require_once __DIR__ . '/../../includes/header.php';

    require_once __DIR__ . '/../../includes/navbar.php';

    ?>

    <div class="container py-4">

        <div class="mb-4">

            <h2 class="fw-bold mb-1">
                Issue Lucky Draw Tickets
            </h2>

            <p class="text-muted mb-0">
                <?= htmlspecialchars($event['event_name']) ?>
            </p>

        </div>

        <div class="alert alert-warning">

            <h5 class="alert-heading">
                Ticket sales are currently closed
            </h5>

            <p class="mb-0">
                <?= htmlspecialchars($blockMessage) ?>
            </p>

        </div>

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <p>
                    <strong>Sales Start:</strong>
                    <?= date('d M Y h:i A', strtotime($event['sales_start'])) ?>
                </p>

                <p class="mb-0">
                    <strong>Sales End:</strong>
                    <?= date('d M Y h:i A', strtotime($event['sales_end'])) ?>
                </p>

            </div>

        </div>

        <div class="mt-3">

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                ← Back to Teacher Portal
            </a>

        </div>

    </div>

    <?php

    require_once __DIR__ . '/../../includes/footer.php';

    exit;
}
$studentStmt = $pdo->prepare("

    SELECT
        s.student_id,
        s.student_name,
        s.class_name,
        r.ticket_quantity AS requested_quantity,
        r.status AS registration_status,
        (
            SELECT COUNT(*)
            FROM lucky_draw_tickets t
            WHERE t.event_id = :event_id2
              AND t.student_id = s.student_id
              AND t.status IN ('ACTIVE', 'WINNER')
        ) AS issued_count

    FROM test_students s

    INNER JOIN lucky_draw_registrations r
        ON r.student_id = s.student_id
       AND r.event_id = :event_id

    WHERE s.teacher_id = :teacher_id

    ORDER BY s.student_name

");

$studentStmt->execute([
    ':event_id' => $eventId,
    ':event_id2' => $eventId,
    ':teacher_id' => $teacherId
]);

$students = $studentStmt->fetchAll(
    PDO::FETCH_ASSOC
);

$registeredCount = count($students);

$preselectStudent = filter_input(
    INPUT_GET,
    'student_id',
    FILTER_VALIDATE_INT
);

$errors = [];

$successTickets = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Student
    |--------------------------------------------------------------------------
    */

    if (!$studentId) {

        $errors[] =
            'Please select a student.';

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Registration + Concern Teacher
    |--------------------------------------------------------------------------
    |
    | A student can only be issued tickets if they applied for this
    | event AND they belong to the current teacher.
    |
    */

    if (
        empty($errors) &&
        $studentId
    ) {

        $regCheckStmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM lucky_draw_registrations r

            INNER JOIN test_students s
                ON s.student_id = r.student_id

            WHERE r.event_id = :event_id
              AND r.student_id = :student_id
              AND s.teacher_id = :teacher_id

        ");

        $regCheckStmt->execute([

            ':event_id' => $eventId,

            ':student_id' => $studentId,

            ':teacher_id' => $teacherId

        ]);

        if ((int)$regCheckStmt->fetchColumn() === 0) {

            $errors[] =
                'This student is not registered for this event with you. ' .
                'Only students who applied for the event can be issued tickets.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Validate Quantity
    |--------------------------------------------------------------------------
    */

    if (
        !$quantity ||
        $quantity < 1
    ) {

        $errors[] =
            'Ticket quantity must be at least 1.';

    }


    if (
        $quantity &&
        $quantity > $event[
            'max_tickets_per_student'
        ]
    ) {

        $errors[] =
            'Maximum allowed tickets are ' .
            $event[
                'max_tickets_per_student'
            ] . '.';

    }
        if (
        empty($errors) &&
        $studentId
    ) {

        $countStmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM lucky_draw_tickets

            WHERE event_id = :event_id

            AND student_id = :student_id

            AND status IN (
                'ACTIVE',
                'WINNER'
            )

        ");

        $countStmt->execute([

            ':event_id' => $eventId,

            ':student_id' => $studentId

        ]);

        $existingTickets =
            (int)$countStmt->fetchColumn();


        if ($existingTickets > 0) {

            $errors[] =
                'Tickets have already been issued to this student ' .
                '(' .
                $existingTickets .
                ' ticket(s)). Each student can receive tickets only once.';

        }

    }
        if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock event row
            |--------------------------------------------------------------------------
            */

            $lockStmt = $pdo->prepare("

                SELECT
                    event_id,
                    ticket_price,
                    max_tickets_per_student,
                    status,
                    sales_end

                FROM lucky_draw_events WITH (UPDLOCK)

                WHERE event_id = :event_id

            ");

            $lockStmt->execute([
                ':event_id' => $eventId
            ]);

            $lockedEvent =
                $lockStmt->fetch(PDO::FETCH_ASSOC);


            if (!$lockedEvent) {

                throw new Exception(
                    'Event not found.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Final status check
            |--------------------------------------------------------------------------
            */

            if (
                $lockedEvent['status'] !==
                'OPEN'
            ) {

                throw new Exception(
                    'Ticket sales are closed.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Next ticket sequence (per event)
            |--------------------------------------------------------------------------
            |
            | The event row lock (WITH UPDLOCK) serializes ticket
            | issuance for this event, so the sequence counter is
            | safe from races and does not reuse gaps left by
            | cancelled/deleted tickets.
            |
            */

            $updateSeqStmt = $pdo->prepare("

                UPDATE lucky_draw_sequences

                SET last_sequence = last_sequence + 1

                WHERE event_id = :event_id

            ");

            $insertSeqStmt = $pdo->prepare("

                INSERT INTO lucky_draw_sequences
                (
                    event_id,
                    last_sequence
                )
                VALUES
                (
                    :event_id,
                    1
                )

            ");

            $selectSeqStmt = $pdo->prepare("

                SELECT last_sequence

                FROM lucky_draw_sequences

                WHERE event_id = :event_id

            ");

            $nextSequenceStmt = function () use (
                $pdo,
                $eventId,
                $updateSeqStmt,
                $insertSeqStmt,
                $selectSeqStmt
            ): int {

                $updateSeqStmt->execute([
                    ':event_id' => $eventId
                ]);

                if ($updateSeqStmt->rowCount() === 0) {

                    $insertSeqStmt->execute([
                        ':event_id' => $eventId
                    ]);

                    return 1;

                }

                $selectSeqStmt->execute([
                    ':event_id' => $eventId
                ]);

                return (int)$selectSeqStmt->fetchColumn();

            };


            /*
            |--------------------------------------------------------------------------
            | Generate tickets
            |--------------------------------------------------------------------------
            */

            $insertStmt = $pdo->prepare("

                INSERT INTO lucky_draw_tickets
                (
                    event_id,
                    student_id,
                    teacher_id,
                    ticket_number,
                    amount,
                    payment_method,
                    payment_status,
                    status
                )

                VALUES
                (
                    :event_id,
                    :student_id,
                    :teacher_id,
                    :ticket_number,
                    :amount,
                    'CASH',
                    'PAID',
                    'ACTIVE'
                )

            ");


            for ($i = 0; $i < $quantity; $i++) {

                $sequence = $nextSequenceStmt();

                $ticketNumber =
                    'LD-' .
                    date('Y') .
                    '-' .
                    str_pad(
                        $eventId,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ) .
                    '-' .
                    str_pad(
                        $sequence,
                        5,
                        '0',
                        STR_PAD_LEFT
                    );


                $insertStmt->execute([

                    ':event_id' =>
                        $eventId,

                    ':student_id' =>
                        $studentId,

                    ':teacher_id' =>
                        $teacherId,

                    ':ticket_number' =>
                        $ticketNumber,

                    ':amount' =>
                        $lockedEvent[
                            'ticket_price'
                        ]

                ]);


                $successTickets[] =
                    $ticketNumber;

            }


            /*
            |--------------------------------------------------------------------------
            | Mark registration as Ticket Issued
            |--------------------------------------------------------------------------
            */

            $updateRegStmt = $pdo->prepare("

                UPDATE lucky_draw_registrations

                SET status = 'TICKET_ISSUED'

                WHERE event_id = :event_id
                  AND student_id = :student_id

            ");

            $updateRegStmt->execute([
                ':event_id' => $eventId,
                ':student_id' => $studentId
            ]);


            $pdo->commit();


            logAudit(
                $pdo,
                $teacherId,
                'TICKETS_ISSUED',
                'EVENT',
                $eventId,
                $eventId,
                'Issued ' .
                    $quantity .
                    ' ticket(s) for student ID ' .
                    $studentId .
                    ' (' .
                    implode(', ', $successTickets) .
                    ')'
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            $errors[] =
                'Unable to issue tickets: ' .
                $e->getMessage();

        }

    }

}

$pageTitle = 'Issue Lucky Draw Tickets';

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/navbar.php';

require_once __DIR__ . '/../../includes/teacher-nav.php';

?>

<div class="container py-4">

    <div class="mb-4">

        <h2 class="fw-bold">
            Issue Lucky Draw Tickets
        </h2>

        <p class="text-muted">
            <?= htmlspecialchars(
                $event['event_name']
            ) ?>
        </p>

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


    <?php if (!empty($successTickets)): ?>

        <div class="alert alert-success">

            <h5 class="alert-heading">
                🎟️ Tickets Issued Successfully
            </h5>

            <hr>

            <?php foreach ($successTickets as $ticket): ?>

                <div class="fw-bold">
                    <?= htmlspecialchars($ticket) ?>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <?php if ($registeredCount === 0): ?>

        <div class="alert alert-warning">

            <strong>
                No registered students yet.
            </strong>

            <div class="mt-1">
                Only students who
                <strong>applied for this event</strong>
                and belong to
                <strong>
                    <?= htmlspecialchars($currentTeacher['teacher_name']) ?>
                </strong>
                appear in the student list. Ask your students to register from their
                <a href="../student/index.php">
                    Student Portal
                </a>
                first.
            </div>

        </div>

    <?php endif; ?>


    <div class="row">

        <div class="col-lg-8">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <form method="POST">

                        <div class="mb-4">

                            <label class="form-label fw-semibold">
                                Student
                            </label>

                            <select
                                name="student_id"
                                id="student_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Student
                                </option>

                                <?php foreach ($students as $student): ?>

                                    <?php

                                    $isStudentIssued =
                                        (int)$student['issued_count'] > 0 ||
                                        $student['registration_status'] === 'TICKET_ISSUED';

                                    ?>

                                    <option
                                        value="<?= $student['student_id'] ?>"
                                        data-requested="<?= (int)$student['requested_quantity'] ?>"
                                        data-issued="<?= (int)$student['issued_count'] ?>"
                                        <?= $isStudentIssued ? 'disabled' : '' ?>
                                        <?= !$isStudentIssued && $preselectStudent && $preselectStudent == $student['student_id'] ? 'selected' : '' ?>
                                    >

                                        <?= htmlspecialchars(
                                            $student['student_name']
                                        ) ?>

                                        —
                                        <?= htmlspecialchars(
                                            $student['class_name']
                                        ) ?>

                                        <?php if ($isStudentIssued): ?>

                                            (✅ tickets issued)

                                        <?php else: ?>

                                            (requested
                                            <?= (int)$student['requested_quantity'] ?>
                                            ticket<?= (int)$student['requested_quantity'] > 1 ? 's' : '' ?>)

                                        <?php endif; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="form-text">
                                Only students registered for this event who belong to
                                <strong>
                                    <?= htmlspecialchars($currentTeacher['teacher_name']) ?>
                                </strong>
                                are listed.
                            </div>

                        </div>


                        <div class="mb-4">

                            <label class="form-label fw-semibold">
                                Number of Tickets
                            </label>

                            <select
                                name="quantity"
                                id="quantity"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Quantity
                                </option>

                                <?php
                                for (
                                    $i = 1;
                                    $i <= $event[
                                        'max_tickets_per_student'
                                    ];
                                    $i++
                                ):
                                ?>

                                    <option value="<?= $i ?>">
                                        <?= $i ?>
                                        Ticket<?= $i > 1 ? 's' : '' ?>
                                    </option>

                                <?php endfor; ?>

                            </select>

                            <div
                                class="form-text"
                                id="quantityHint"
                            ></div>

                        </div>


                        <div class="alert alert-light border">

                            <div class="d-flex justify-content-between">

                                <span>
                                    Ticket Price
                                </span>

                                <strong>
                                    Rs.
                                    <?= number_format(
                                        $event['ticket_price'],
                                        2
                                    ) ?>
                                </strong>

                            </div>

                            <div class="d-flex justify-content-between mt-2">

                                <span>
                                    Payment Method
                                </span>

                                <strong>
                                    Cash
                                </strong>

                            </div>

                        </div>


                        <div class="d-flex gap-2">

                            <a
                                href="index.php"
                                class="btn btn-secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Issue Tickets
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        </div>


        <div class="col-lg-4 mt-4 mt-lg-0">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h5 class="fw-bold">
                        Event Information
                    </h5>

                    <hr>

                    <p>
                        <strong>Ticket Price:</strong><br>

                        Rs.
                        <?= number_format(
                            $event['ticket_price'],
                            2
                        ) ?>
                    </p>

                    <p>
                        <strong>Maximum Tickets:</strong><br>

                        <?= $event[
                            'max_tickets_per_student'
                        ] ?>
                    </p>

                    <p class="mb-0">

                        <strong>Payment:</strong><br>

                        Cash only

                    </p>

                </div>

            </div>


            <div class="card border-0 shadow-sm mt-4">

                <div class="card-body">

                    <h5 class="fw-bold">
                        🎓 My Students — Status
                    </h5>

                    <hr>

                    <?php if (count($students) === 0): ?>

                        <p class="text-muted mb-0">
                            No registered students yet.
                        </p>

                    <?php else: ?>

                        <ul class="list-group list-group-flush">

                            <?php foreach ($students as $student): ?>

                                <?php

                                $isStudentIssued =
                                    (int)$student['issued_count'] > 0 ||
                                    $student['registration_status'] === 'TICKET_ISSUED';

                                ?>

                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">

                                    <div>
                                        <strong>
                                            <?= htmlspecialchars($student['student_name']) ?>
                                        </strong>

                                        <div class="text-muted small">
                                            <?= htmlspecialchars($student['class_name']) ?>
                                            • requested
                                            <?= (int)$student['requested_quantity'] ?>
                                        </div>
                                    </div>

                                    <?php if ($isStudentIssued): ?>

                                        <span class="badge bg-success">
                                            ✅ Issued
                                            <?= (int)$student['issued_count'] ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-warning text-dark">
                                            Pending
                                        </span>

                                    <?php endif; ?>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
(function () {
    var studentSelect = document.getElementById('student_id');
    var quantitySelect = document.getElementById('quantity');
    var hint = document.getElementById('quantityHint');

    if (!studentSelect || !quantitySelect) {
        return;
    }

    function applyRequested() {
        var opt = studentSelect.options[studentSelect.selectedIndex];

        if (!opt || !opt.value || opt.disabled) {
            hint.textContent = '';
            return;
        }

        var requested = parseInt(opt.getAttribute('data-requested'), 10) || 1;
        var issued = parseInt(opt.getAttribute('data-issued'), 10) || 0;

        var remaining = <?= (int)$event['max_tickets_per_student'] ?> - issued;

        if (remaining < 1) {
            remaining = 1;
        }

        var qty = Math.min(requested, remaining);

        quantitySelect.value = String(qty);

        hint.innerHTML =
            'Student requested <strong>' + requested + '</strong> ticket' +
            (requested > 1 ? 's' : '') +
            ' — quantity pre-filled. Already issued: <strong>' +
            issued + '</strong>.';
    }

    studentSelect.addEventListener('change', applyRequested);

    applyRequested();
})();
</script>

<?php

require_once __DIR__ . '/../../includes/footer.php';

?>