<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Create FunFair Event';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $eventName = trim($_POST['event_name'] ?? '');
    $ticketPrice = $_POST['ticket_price'] ?? '';
    $maxTickets = $_POST['max_tickets'] ?? '';
    $salesStart = $_POST['sales_start'] ?? '';
    $salesEnd = $_POST['sales_end'] ?? '';
    $drawDate = $_POST['draw_date'] ?? '';

    /*
     * Validation
     */

    if ($eventName === '') {
        $errors[] = 'Event name is required.';
    }

    if ($ticketPrice === '' || !is_numeric($ticketPrice)) {
        $errors[] = 'Valid ticket price is required.';
    } elseif ((float)$ticketPrice <= 0) {
        $errors[] = 'Ticket price must be greater than zero.';
    }

    if ($maxTickets === '' || !filter_var($maxTickets, FILTER_VALIDATE_INT)) {
        $errors[] = 'Maximum tickets must be a valid number.';
    } elseif ((int)$maxTickets < 1) {
        $errors[] = 'Maximum tickets must be at least 1.';
    }

    if ($salesStart === '') {
        $errors[] = 'Sales start date is required.';
    }

    if ($salesEnd === '') {
        $errors[] = 'Sales end date is required.';
    }

    if ($drawDate === '') {
        $errors[] = 'Draw date is required.';
    }

    /*
     * Date validation
     */

    if (
        $salesStart !== '' &&
        $salesEnd !== '' &&
        strtotime($salesEnd) <= strtotime($salesStart)
    ) {
        $errors[] = 'Sales end must be after sales start.';
    }

    if (
        $salesEnd !== '' &&
        $drawDate !== '' &&
        strtotime($drawDate) <= strtotime($salesEnd)
    ) {
        $errors[] = 'Draw date must be after sales end.';
    }


    /*
     * Insert event
     */

    if (empty($errors)) {

        $sql = "
            INSERT INTO lucky_draw_events
            (
                event_name,
                ticket_price,
                max_tickets_per_student,
                sales_start,
                sales_end,
                draw_date,
                status,
                allow_multiple_wins,
                created_by
            )
            VALUES
            (
                :event_name,
                :ticket_price,
                :max_tickets,
                :sales_start,
                :sales_end,
                :draw_date,
                'DRAFT',
                0,
                :created_by
            )
        ";

        $stmt = $pdo->prepare($sql);

        /*
         * Temporary user ID.
         *
         * Later this will come from the
         * existing school system session.
         */

        $createdBy = 1;

        $salesStartDb = date(
    'Y-m-d H:i:s',
    strtotime($salesStart)
);

$salesEndDb = date(
    'Y-m-d H:i:s',
    strtotime($salesEnd)
);

$drawDateDb = date(
    'Y-m-d H:i:s',
    strtotime($drawDate)
);

       $stmt->execute([
    ':event_name' => $eventName,
    ':ticket_price' => $ticketPrice,
    ':max_tickets' => $maxTickets,
    ':sales_start' => $salesStartDb,
    ':sales_end' => $salesEndDb,
    ':draw_date' => $drawDateDb,
    ':created_by' => $createdBy
]);

        header('Location: events.php?created=1');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="mb-4">

        <h2 class="fw-bold">
            Create FunFair Event
        </h2>

        <p class="text-muted">
            Configure a new Lucky Draw event.
        </p>

    </div>


    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <strong>
                Please fix the following:
            </strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <div class="card border-0 shadow-sm">

        <div class="card-body p-4">

            <form method="POST">

                <div class="mb-3">

                    <label class="form-label fw-semibold">
                        Event Name
                    </label>

                    <input
                        type="text"
                        name="event_name"
                        class="form-control"
                        placeholder="e.g. Annual FunFair 2026"
                        value="<?= htmlspecialchars($_POST['event_name'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">
                            Ticket Price
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                Rs.
                            </span>

                            <input
                                type="number"
                                name="ticket_price"
                                class="form-control"
                                min="1"
                                step="0.01"
                                value="<?= htmlspecialchars($_POST['ticket_price'] ?? '100') ?>"
                                required
                            >

                        </div>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">
                            Maximum Tickets Per Student
                        </label>

                        <input
                            type="number"
                            name="max_tickets"
                            class="form-control"
                            min="1"
                            value="<?= htmlspecialchars($_POST['max_tickets'] ?? '4') ?>"
                            required
                        >

                        <small class="text-muted">
                            Current requirement: maximum 4 tickets.
                        </small>

                    </div>

                </div>


                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Sales Start
                        </label>

                        <input
                            type="datetime-local"
                            name="sales_start"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['sales_start'] ?? '') ?>"
                            required
                        >

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Sales End
                        </label>

                        <input
                            type="datetime-local"
                            name="sales_end"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['sales_end'] ?? '') ?>"
                            required
                        >

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="form-label fw-semibold">
                            Draw Date & Time
                        </label>

                        <input
                            type="datetime-local"
                            name="draw_date"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['draw_date'] ?? '') ?>"
                            required
                        >

                    </div>

                </div>


                <hr class="my-4">


                <div class="alert alert-info">

                    <strong>
                        Draw Rule
                    </strong>

                    <p class="mb-0 mt-1">
                        A student can win only one prize in this event.
                    </p>

                </div>


                <div class="d-flex gap-2">

                    <a
                        href="events.php"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Create Event
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>