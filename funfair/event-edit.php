<?php

require_once __DIR__ . '/../config/database.php';

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$eventId) {
    header('Location: events.php');
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
    header('Location: events.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Process Update
|--------------------------------------------------------------------------
*/

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $eventName = trim($_POST['event_name'] ?? '');

    $ticketPrice = $_POST['ticket_price'] ?? '';

    $maxTickets = $_POST['max_tickets'] ?? '';

    $salesStart = $_POST['sales_start'] ?? '';

    $salesEnd = $_POST['sales_end'] ?? '';

    $drawDate = $_POST['draw_date'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($eventName === '') {
        $errors[] = 'Event name is required.';
    }


    if ($ticketPrice === '' || !is_numeric($ticketPrice)) {

        $errors[] = 'Valid ticket price is required.';

    } elseif ((float)$ticketPrice <= 0) {

        $errors[] = 'Ticket price must be greater than zero.';

    }


    if (
        $maxTickets === '' ||
        filter_var($maxTickets, FILTER_VALIDATE_INT) === false
    ) {

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


    if (
        $salesStart !== '' &&
        $salesEnd !== '' &&
        strtotime($salesEnd) <= strtotime($salesStart)
    ) {

        $errors[] =
            'Sales end must be after sales start.';

    }


    if (
        $salesEnd !== '' &&
        $drawDate !== '' &&
        strtotime($drawDate) <= strtotime($salesEnd)
    ) {

        $errors[] =
            'Draw date must be after sales end.';

    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        /*
        |--------------------------------------------------------------------------
        | Convert form dates (datetime-local uses "T" separator)
        | to SQL Server datetime format ("YYYY-MM-DD HH:MM:SS").
        |--------------------------------------------------------------------------
        */

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

        $updateSql = "

            UPDATE lucky_draw_events

            SET

                event_name = :event_name,

                ticket_price = :ticket_price,

                max_tickets_per_student = :max_tickets,

                sales_start = :sales_start,

                sales_end = :sales_end,

                draw_date = :draw_date,

                updated_at = GETDATE()

            WHERE event_id = :event_id

        ";

        $updateStmt = $pdo->prepare($updateSql);

        $updateStmt->execute([

            ':event_name' => $eventName,

            ':ticket_price' => $ticketPrice,

            ':max_tickets' => $maxTickets,

            ':sales_start' => $salesStartDb,

            ':sales_end' => $salesEndDb,

            ':draw_date' => $drawDateDb,

            ':event_id' => $eventId

        ]);


        header(
            'Location: events.php?updated=1'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Keep entered values after validation failure
    |--------------------------------------------------------------------------
    */

    $event['event_name'] = $eventName;

    $event['ticket_price'] = $ticketPrice;

    $event['max_tickets_per_student'] = $maxTickets;

    $event['sales_start'] = $salesStart;

    $event['sales_end'] = $salesEnd;

    $event['draw_date'] = $drawDate;

}


$pageTitle = 'Edit FunFair Event';

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

?>
<div class="container py-4">

    <div class="mb-4">

        <h2 class="fw-bold">
            Edit FunFair Event
        </h2>

        <p class="text-muted">
            Update event configuration.
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
                        value="<?= htmlspecialchars(
                            $event['event_name']
                        ) ?>"
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
                                value="<?= htmlspecialchars(
                                    $event['ticket_price']
                                ) ?>"
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
                            value="<?= htmlspecialchars(
                                $event[
                                    'max_tickets_per_student'
                                ]
                            ) ?>"
                            required
                        >

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
                            value="<?= date(
                                'Y-m-d\TH:i',
                                strtotime(
                                    $event['sales_start']
                                )
                            ) ?>"
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
                            value="<?= date(
                                'Y-m-d\TH:i',
                                strtotime(
                                    $event['sales_end']
                                )
                            ) ?>"
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
                            value="<?= date(
                                'Y-m-d\TH:i',
                                strtotime(
                                    $event['draw_date']
                                )
                            ) ?>"
                            required
                        >

                    </div>

                </div>


                <div class="alert alert-info">

                    <strong>
                        Draw Rule
                    </strong>

                    <div class="mt-1">
                        A student can win only one prize.
                    </div>

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
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>