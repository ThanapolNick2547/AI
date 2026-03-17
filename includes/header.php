<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchoolAI - Management System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="wrapper">
    <!-- Sidebar Included Here -->
    <?php include 'sidebar.php'; ?>

    <!-- Page Content -->
    <div id="content">
        <!-- Main Header Navbar -->
        <header class="main-header">
            <div class="d-flex align-items-center">
                <button type="button" id="sidebarCollapse" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="ms-3 d-none d-md-block">
                    <h5 class="mb-0 text-dark fw-bold">SchoolAI Dashboard</h5>
                </div>
            </div>
            
            <div class="header-right d-flex align-items-center">
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <a class="text-secondary position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                            <span class="visually-hidden">New alerts</span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#">Meeting at 10:00 AM</a></li>
                        <li><a class="dropdown-item" href="#">New student registered</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center small text-muted" href="#">View all</a></li>
                    </ul>
                </div>
                
                <!-- User Profile -->
                <div class="dropdown">
                    <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username'] ?? 'User'); ?>&background=D02752&color=fff" alt="User Avatar" class="rounded-circle me-2" width="35" height="35">
                        <div class="d-none d-md-block text-dark">
                            <span class="fw-semibold d-block" style="line-height:1;"><?php echo e($_SESSION['username'] ?? 'User'); ?></span>
                            <small class="text-muted text-uppercase" style="font-size:11px;"><?php echo e($_SESSION['user_role'] ?? 'Guest'); ?></small>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user fa-sm fa-fw me-2 text-muted"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cogs fa-sm fa-fw me-2 text-muted"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area (padding added in page) -->
        <main class="p-4">
