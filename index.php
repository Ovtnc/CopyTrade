<?php ob_start(); session_start(); 
define('V_PATH', './__cs/');
include(V_PATH."c.php");
include(V_PATH."auth.php");

// Check authentication on every page load
$currentUser = getCurrentUser(); 

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= WEB_URL; ?>/vendor/logo.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= WEB_URL; ?>/vendor/st.css">
<?php

// Normal sayfa istekleri
if(isset($_GET["p"]) && !empty($_GET["p"])) {
    $page = $_GET["p"];
    
    // Admin pages routing
    if ($page === 'admin') {
        // Admin dashboard
        $adminFilePath = realpath('./__cs/p/')."/admin/dashboard.php";
        if(file_exists($adminFilePath)) {
            include($adminFilePath);
        } else {
            include(realpath('./__cs/p/').'/_404.php');
        }
    } elseif (preg_match('/^admin\/(.+)$/i', $page, $matches)) {
        $adminPage = $matches[1];
        // Handle nested admin routes like admin/traders/add
        $adminFilePath = realpath('./__cs/p/')."/admin/".str_replace('/', '/', $adminPage).".php";
        if(file_exists($adminFilePath)) {
            include($adminFilePath);
        } else {
            include(realpath('./__cs/p/').'/_404.php');
        }
    }
    // Check if it's a trader detail page (trader/1 format)
    elseif (preg_match('/^trader\/(\d+)$/i', $page, $matches)) {
        $traderId = intval($matches[1]);
        $_GET['trader_id'] = $traderId;
        if(file_exists(realpath('./__cs/p/')."/trader.php")) {
            include(realpath('./__cs/p/')."/trader.php");
        } else {
            include(realpath('./__cs/p/').'/_404.php');
        }
    } elseif(file_exists(realpath('./__cs/p/')."/".$page.".php")) {
        include(realpath('./__cs/p/')."/".$page.".php");
    } else {
        include(realpath('./__cs/p/').'/_404.php');
    }
} else {
    include(realpath('./__cs/p/').'/_index.php');
} 
?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Language Translations -->
    <script src="<?= WEB_URL; ?>/vendor/lang.js"></script>
    <!-- Custom JS -->
    <script src="<?= WEB_URL; ?>/vendor/sc.js"></script>
</body>
</html>