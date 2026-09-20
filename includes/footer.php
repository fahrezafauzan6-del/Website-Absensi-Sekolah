<?php
$base_url = $base_url ?? '/absensi-sekolah';

?>
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script
    src="<?= $base_url ?>/assets/js/app.js"></script>

<?php if (isset($additional_js) && is_array($additional_js)): ?>

    <?php foreach ($additional_js as $js): ?>

        <script
            src="<?= $base_url ?>/assets/js/<?= htmlspecialchars($js) ?>"></script>

    <?php endforeach; ?>

<?php endif; ?>


</body>

</html>