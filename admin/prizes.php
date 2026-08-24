<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../includes/audit.php';

$pageTitle = 'Prize Management';

$selectedEvent = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$action = $_GET['action'] ?? '';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$toggleId = filter_input(INPUT_GET, 'toggle', FILTER_VALIDATE_INT);
$deleteId = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);

/*
|--------------------------------------------------------------------------
| Events for filter / add form
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
| Toggle prize status
|--------------------------------------------------------------------------
*/

if ($toggleId) {

    $toggleStmt = $pdo->prepare("

        SELECT
            prize_id,
            event_id,
            status

        FROM lucky_draw_prizes

        WHERE prize_id = :prize_id

    ");

    $toggleStmt->execute([
        ':prize_id' => $toggleId
    ]);

    $prize = $toggleStmt->fetch(PDO::FETCH_ASSOC);

    if ($prize) {

        $nextStatus = $prize['status'] === 'AVAILABLE'
            ? 'DISABLED'
            : 'AVAILABLE';

        $updateStmt = $pdo->prepare("

            UPDATE lucky_draw_prizes

            SET status = :status

            WHERE prize_id = :prize_id

        ");

        $updateStmt->execute([
            ':status' => $nextStatus,
            ':prize_id' => $toggleId
        ]);

        logAudit(
            $pdo,
            1,
            'PRIZE_STATUS_CHANGED',
            'PRIZE',
            $toggleId,
            $prize['event_id'],
            'Prize status changed to ' . $nextStatus
        );

        header(
            'Location: prizes.php?event_id=' .
            $prize['event_id'] .
            '&toggled=1'
        );

        exit;
    }

}


/*
|--------------------------------------------------------------------------
| Delete prize
|--------------------------------------------------------------------------
*/

if ($deleteId) {

    $delStmt = $pdo->prepare("

        SELECT
            prize_id,
            event_id

        FROM lucky_draw_prizes

        WHERE prize_id = :prize_id

    ");

    $delStmt->execute([
        ':prize_id' => $deleteId
    ]);

    $prize = $delStmt->fetch(PDO::FETCH_ASSOC);

    if ($prize) {

        $deleteStmt = $pdo->prepare("

            DELETE FROM lucky_draw_prizes

            WHERE prize_id = :prize_id

        ");

        $deleteStmt->execute([
            ':prize_id' => $deleteId
        ]);

        logAudit(
            $pdo,
            1,
            'PRIZE_DELETED',
            'PRIZE',
            $deleteId,
            $prize['event_id'],
            'Prize deleted'
        );

        header(
            'Location: prizes.php?event_id=' .
            $prize['event_id'] .
            '&deleted=1'
        );

        exit;

    }

}


/*
|--------------------------------------------------------------------------
| Add / Update prize
|--------------------------------------------------------------------------
*/

$errors = [];

$editPrize = null;

if ($editId) {

    $editStmt = $pdo->prepare("

        SELECT
            prize_id,
            event_id,
            prize_name,
            description,
            prize_position

        FROM lucky_draw_prizes

        WHERE prize_id = :prize_id

    ");

    $editStmt->execute([
        ':prize_id' => $editId
    ]);

    $editPrize = $editStmt->fetch(PDO::FETCH_ASSOC);

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $prizeId = filter_input(INPUT_POST, 'prize_id', FILTER_VALIDATE_INT);
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $prizeName = trim($_POST['prize_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $position = filter_input(INPUT_POST, 'prize_position', FILTER_VALIDATE_INT);

    if (!$eventId) {
        $errors[] = 'Please select an event.';
    }

    if ($prizeName === '') {
        $errors[] = 'Prize name is required.';
    }

    if (!$position || $position < 1) {
        $errors[] = 'Prize position must be at least 1.';
    }

    if (empty($errors)) {

        if ($prizeId) {

            $updateStmt = $pdo->prepare("

                UPDATE lucky_draw_prizes

                SET
                    event_id = :event_id,
                    prize_name = :prize_name,
                    description = :description,
                    prize_position = :prize_position

                WHERE prize_id = :prize_id

            ");

            $updateStmt->execute([
                ':event_id' => $eventId,
                ':prize_name' => $prizeName,
                ':description' => $description,
                ':prize_position' => $position,
                ':prize_id' => $prizeId
            ]);

            logAudit(
                $pdo,
                1,
                'PRIZE_UPDATED',
                'PRIZE',
                $prizeId,
                $eventId,
                'Prize "' . $prizeName . '" updated'
            );

            header(
                'Location: prizes.php?event_id=' .
                $eventId .
                '&updated=1'
            );

            exit;

        }

        $insertStmt = $pdo->prepare("

            INSERT INTO lucky_draw_prizes
            (
                event_id,
                prize_name,
                description,
                prize_position,
                status,
                created_at
            )
            VALUES
            (
                :event_id,
                :prize_name,
                :description,
                :prize_position,
                'AVAILABLE',
                GETDATE()
            )

        ");

        $insertStmt->execute([
            ':event_id' => $eventId,
            ':prize_name' => $prizeName,
            ':description' => $description,
            ':prize_position' => $position
        ]);

        logAudit(
            $pdo,
            1,
            'PRIZE_ADDED',
            'PRIZE',
            (int)$pdo->lastInsertId(),
            $eventId,
            'Prize "' . $prizeName . '" added'
        );

        header(
            'Location: prizes.php?event_id=' .
            $eventId .
            '&added=1'
        );

        exit;

    }

}


/*
|--------------------------------------------------------------------------
| Prize list
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT
        p.prize_id,
        p.event_id,
        p.prize_name,
        p.description,
        p.prize_position,
        p.status,
        p.created_at,
        e.event_name

    FROM lucky_draw_prizes p

    INNER JOIN lucky_draw_events e
        ON e.event_id = p.event_id

    WHERE 1 = 1

";

$params = [];

if ($selectedEvent) {
    $sql .= " AND p.event_id = :event_id";
    $params[':event_id'] = $selectedEvent;
}

$sql .= " ORDER BY e.event_id DESC, p.prize_position";

$prizesStmt = $pdo->prepare($sql);
$prizesStmt->execute($params);
$prizes = $prizesStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                Prize Management
            </h2>

            <p class="text-muted mb-0">
                Configure prizes for each event.
            </p>
        </div>

    </div>


    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">Prize added successfully.</div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Prize updated successfully.</div>
    <?php elseif (isset($_GET['toggled'])): ?>
        <div class="alert alert-info">Prize status updated.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning">Prize deleted.</div>
    <?php endif; ?>


    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li><?= htmlspecialchars($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <div class="row g-4">

        <div class="col-lg-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-3">
                        <?= $editPrize ? 'Edit Prize' : 'Add Prize' ?>
                    </h5>

                    <form method="POST">

                        <?php if ($editPrize): ?>

                            <input
                                type="hidden"
                                name="prize_id"
                                value="<?= $editPrize['prize_id'] ?>"
                            >

                        <?php endif; ?>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Event
                            </label>

                            <select
                                name="event_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select Event
                                </option>

                                <?php foreach ($events as $event): ?>

                                    <option
                                        value="<?= $event['event_id'] ?>"
                                        <?=
                                            (
                                                $editPrize &&
                                                $editPrize['event_id'] == $event['event_id']
                                            ) || $selectedEvent == $event['event_id']
                                                ? 'selected'
                                                : ''
                                        ?>
                                    >
                                        <?= htmlspecialchars($event['event_name']) ?>
                                        (<?= htmlspecialchars($event['status']) ?>)
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Prize Name
                            </label>

                            <input
                                type="text"
                                name="prize_name"
                                class="form-control"
                                placeholder="e.g. Bicycle"
                                value="<?= htmlspecialchars($editPrize['prize_name'] ?? '') ?>"
                                required
                            >

                        </div>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="form-control"
                                rows="2"
                                placeholder="Optional details"
                            ><?= htmlspecialchars($editPrize['description'] ?? '') ?></textarea>

                        </div>

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Position
                            </label>

                            <input
                                type="number"
                                name="prize_position"
                                class="form-control"
                                min="1"
                                value="<?= htmlspecialchars($editPrize['prize_position'] ?? '') ?>"
                                required
                            >

                            <small class="text-muted">
                                Order in which the prize is drawn (1 = first).
                            </small>

                        </div>

                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary flex-fill"
                            >
                                <?= $editPrize ? 'Save Changes' : 'Add Prize' ?>
                            </button>

                            <?php if ($editPrize): ?>

                                <a
                                    href="prizes.php?event_id=<?= $editPrize['event_id'] ?>"
                                    class="btn btn-outline-secondary"
                                >
                                    Cancel
                                </a>

                            <?php endif; ?>

                        </div>

                    </form>

                </div>

            </div>

        </div>


        <div class="col-lg-8">

            <div class="card border-0 shadow-sm mb-3">

                <div class="card-body">

                    <form method="GET" class="row g-2">

                        <div class="col-md-8">

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

                        <div class="col-md-4">

                            <button
                                type="submit"
                                class="btn btn-outline-primary w-100"
                            >
                                Filter
                            </button>

                        </div>

                    </form>

                </div>

            </div>

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <?php if (count($prizes) === 0): ?>

                        <div class="text-center py-5">

                            <h5>
                                No prizes found
                            </h5>

                            <p class="text-muted">
                                Add prizes for the selected event.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive">

                            <table class="table table-hover align-middle">

                                <thead class="table-light">

                                    <tr>

                                        <th>Pos</th>

                                        <th>Prize</th>

                                        <th>Event</th>

                                        <th>Status</th>

                                        <th>Actions</th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach ($prizes as $prize): ?>

                                    <tr>

                                        <td>
                                            <?= $prize['prize_position'] ?>
                                        </td>

                                        <td>

                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($prize['prize_name']) ?>
                                            </div>

                                            <?php if ($prize['description']): ?>

                                                <small class="text-muted">
                                                    <?= htmlspecialchars($prize['description']) ?>
                                                </small>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars($prize['event_name']) ?>
                                        </td>

                                        <td>

                                            <?php

                                            $pBadge = match ($prize['status']) {
                                                'AVAILABLE' => 'bg-success',
                                                'AWARDED' => 'bg-primary',
                                                'DISABLED' => 'bg-secondary',
                                                default => 'bg-secondary'
                                            };

                                            ?>

                                            <span class="badge <?= $pBadge ?>">
                                                <?= htmlspecialchars($prize['status']) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <div class="d-flex gap-1">

                                                <a
                                                    href="prizes.php?edit=<?= $prize['prize_id'] ?>&event_id=<?= $prize['event_id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                >
                                                    Edit
                                                </a>

                                                <?php if ($prize['status'] === 'AVAILABLE'): ?>

                                                    <a
                                                        href="prizes.php?toggle=<?= $prize['prize_id'] ?>"
                                                        class="btn btn-sm btn-outline-secondary"
                                                    >
                                                        Disable
                                                    </a>

                                                <?php elseif ($prize['status'] === 'DISABLED'): ?>

                                                    <a
                                                        href="prizes.php?toggle=<?= $prize['prize_id'] ?>"
                                                        class="btn btn-sm btn-outline-success"
                                                    >
                                                        Enable
                                                    </a>

                                                <?php endif; ?>

                                                <?php if ($prize['status'] !== 'AWARDED'): ?>

                                                    <a
                                                        href="prizes.php?delete=<?= $prize['prize_id'] ?>"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Delete this prize?');"
                                                    >
                                                        Delete
                                                    </a>

                                                <?php endif; ?>

                                            </div>

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

    </div>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>