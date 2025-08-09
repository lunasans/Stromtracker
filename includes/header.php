<?php
// includes/header.php
// HTML Header für alle Seiten
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Stromtracker' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
    
    <!-- Chart.js für Diagramme -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --energy-color: #eab308;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--energy-color) !important;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-energy {
            background-color: var(--energy-color);
            border-color: var(--energy-color);
            color: white;
        }
        
        .btn-energy:hover {
            background-color: #ca8a04;
            border-color: #ca8a04;
            color: white;
        }
        
        .energy-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--energy-color);
            animation: pulse 2s infinite;
            margin-right: 8px;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(234, 179, 8, 0); }
            100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card .card-body {
            position: relative;
        }
        
        .stats-card .stats-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-card {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .form-control:focus {
            border-color: var(--energy-color);
            box-shadow: 0 0 0 0.2rem rgba(234, 179, 8, 0.25);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(234, 179, 8, 0.1);
        }
        
        .sidebar {
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            min-height: 100vh;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
        
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
        }
    </style>
</head>
<body><?php
// Flash Messages anzeigen (falls vorhanden)
if (class_exists('Flash') && Flash::has()) {
    echo '<div class="container-fluid mt-2">' . Flash::display() . '</div>';
}
?>