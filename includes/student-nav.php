<?php

/*
|--------------------------------------------------------------------------
| Student Sub Navigation
|--------------------------------------------------------------------------
|
| Simple tab bar for the student pages. Highlights the current page
| based on the script name.
|
*/

$currentScript = basename($_SERVER['PHP_SELF']);

$studentTabs = [
    'index.php'   => '🎓 FunFair',
    'tickets.php' => '🎟️ My Tickets',
];

?>

<ul class="nav nav-pills mb-4">

    <?php foreach ($studentTabs as $file => $label): ?>

        <li class="nav-item">

            <a
                class="nav-link <?= $currentScript === $file ? 'active' : '' ?>"
                href="<?= $file ?>"
            >
                <?= $label ?>
            </a>

        </li>

    <?php endforeach; ?>

</ul>
