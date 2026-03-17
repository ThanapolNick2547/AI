<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Protect this route
requireLogin();

// Include Header (which includes sidebar internally)
include 'includes/header.php';

?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </ol>
</nav>

<h2 class="mb-4 fw-bold text-dark">Welcome to SchoolAI Dashboard</h2>

<!-- Summary Widgets / Stat Cards -->
<div class="row g-4 mb-4">
    <!-- Total Teachers -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-details">
                <h3>142</h3>
                <p>Total Teachers</p>
            </div>
        </div>
    </div>
    
    <!-- Total Students -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-details">
                <h3>2,450</h3>
                <p>Total Students</p>
            </div>
        </div>
    </div>

    <!-- Active Classes -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="stat-details">
                <h3>64</h3>
                <p>Active Classes</p>
            </div>
        </div>
    </div>

    <!-- Today's Attendance Rate -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3>94.2%</h3>
                <p>Today's Attendance</p>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-4">
    <!-- Bar Chart for Attendance -->
    <div class="col-12 col-lg-8">
        <div class="chart-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Weekly Attendance Overview</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        This Week
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#">This Week</a></li>
                        <li><a class="dropdown-item" href="#">Last Week</a></li>
                    </ul>
                </div>
            </div>
            <div style="height: 300px;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Doughnut Chart for Grade Distribution -->
    <div class="col-12 col-lg-4">
        <div class="chart-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Grade Distribution</h5>
                <button class="btn btn-sm btn-outline-secondary">
                    Term 1
                </button>
            </div>
            <div style="height: 250px; display: flex; align-items: center; justify-content: center;">
                <canvas id="gradeChart"></canvas>
            </div>
            <div class="text-center mt-3 text-muted small">
                Showing relative distribution of overall grades across all active subjects.
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities Table -->
<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; border: 1px solid var(--border-color) !important;">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                <h5 class="mb-0 fw-bold">Recent Updates</h5>
                <a href="#" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Activity</th>
                                <th>Actor</th>
                                <th>Module</th>
                                <th>Time</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3">
                                            <i class="fas fa-user-plus fa-fw"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">New Teacher Added</h6>
                                            <small class="text-muted">Sompong Jai-dee</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Admin User</td>
                                <td><span class="badge bg-secondary">Master Data</span></td>
                                <td>10 mins ago</td>
                                <td class="pe-4 text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                            </tr>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-3">
                                            <i class="fas fa-clipboard-check fa-fw"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Attendance Submitted</h6>
                                            <small class="text-muted">Math 101 - Class 1/1</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Teacher Suda</td>
                                <td><span class="badge bg-secondary">Attendance</span></td>
                                <td>1 hour ago</td>
                                <td class="pe-4 text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                            </tr>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-2 me-3">
                                            <i class="fas fa-edit fa-fw"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Grades Updated</h6>
                                            <small class="text-muted">Science 202 - Class 2/3</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Teacher Manop</td>
                                <td><span class="badge bg-secondary">Grading</span></td>
                                <td>3 hours ago</td>
                                <td class="pe-4 text-end"><button class="btn btn-sm btn-outline-primary">View</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

// Include Footer
include 'includes/footer.php';

?>
