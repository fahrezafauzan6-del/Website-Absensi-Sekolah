<?php

$page_title = $page_title ?? 'Absensi Sekolah';
$base_url   = '/absensi-sekolah';
$app_name   = 'Absensi Sekolah';

$additional_css = $additional_css ?? [];

$additional_vendor_js = $additional_vendor_js ?? [];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Sistem absensi sekolah berbasis web"
    >

    <meta
        name="author"
        content="Absensi Sekolah"
    >

    <link
        rel="icon"
        type="image/png"
        href="<?= htmlspecialchars(
            $base_url . '/assets/images/favicon.png',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <title>
        <?= htmlspecialchars(
            $page_title,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            $base_url . '/assets/css/style.css',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            $base_url . '/assets/css/dashboard.css',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <?php foreach ($additional_css as $css): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(
                $base_url . '/assets/css/' . ltrim($css, '/'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

    <?php endforeach; ?>

    <?php foreach ($additional_vendor_js as $vendor_js): ?>

        <script
            src="<?= htmlspecialchars(
                $vendor_js,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        ></script>

    <?php endforeach; ?>

</head>

<body>

<div id="app">