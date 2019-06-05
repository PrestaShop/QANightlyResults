<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto+Mono">
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" type="text/css" href="/public/assets/css/style.css">

    <link rel="icon" href="/public/assets/favicon.ico" sizes="16x16">
    <?php
        if (isset($js)) {
            foreach($js as $file) {
                echo '<script src="'.$file.'"></script>';
            }
        }
    ?>
</head>
<body>