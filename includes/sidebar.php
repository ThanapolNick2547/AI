<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<nav id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-school logo-icon"></i>
        <h3>SchoolAI</h3>
    </div>
    
    <div class="px-3 py-2">
        <small class="text-uppercase fw-bold text-muted ps-2" style="font-size: 0.75rem;">Menu</small>
    </div>

    <ul class="list-unstyled components mb-5">
        
        <!-- Dashboard -->
        <li class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <!-- Module 1: Master Data Management -->
        <?php $master_data_pages = ['teachers.php', 'students.php', 'subjects.php', 'classes.php']; // Add future pages here ?>
        <li class="<?php echo in_array($current_page, $master_data_pages) ? 'active' : ''; ?>">
            <a href="#masterDataSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $master_data_pages) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                <i class="fas fa-database"></i>
                <span>Master Data</span>
            </a>
            <ul class="collapse list-unstyled sidebar-submenu <?php echo in_array($current_page, $master_data_pages) ? 'show' : ''; ?>" id="masterDataSubmenu" data-bs-parent="#sidebar">
                <li class="<?php echo ($current_page == 'teachers.php') ? 'active-submenu' : ''; ?>">
                    <a href="teachers.php" class="<?php echo ($current_page == 'teachers.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher <?php echo ($current_page == 'teachers.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> Teachers
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'students.php') ? 'active-submenu' : ''; ?>">
                    <a href="students.php" class="<?php echo ($current_page == 'students.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-user-graduate <?php echo ($current_page == 'students.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> Students
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'subjects.php') ? 'active-submenu' : ''; ?>">
                    <a href="subjects.php" class="<?php echo ($current_page == 'subjects.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-book <?php echo ($current_page == 'subjects.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> Subjects
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'classes.php') ? 'active-submenu' : ''; ?>">
                    <a href="classes.php" class="<?php echo ($current_page == 'classes.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-layer-group <?php echo ($current_page == 'classes.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> Classes
                    </a>
                </li>
                <li><a href="#"><i class="fas fa-door-open text-muted fa-sm me-2"></i> Classrooms</a></li>
            </ul>
        </li>

        <!-- Module 2: Schedule Management -->
        <li>
            <a href="#">
                <i class="far fa-calendar-alt"></i>
                <span>Schedules</span>
            </a>
        </li>

        <!-- Module 3: Grading System -->
        <li>
            <a href="#">
                <i class="fas fa-award"></i>
                <span>Grading</span>
            </a>
        </li>

        <!-- Module 5: Attendance Tracking (Skipping Module 4 for sidebar UI, as it's auth/settings) -->
        <li>
            <a href="#">
                <i class="fas fa-clipboard-user"></i>
                <span>Attendance</span>
            </a>
        </li>
        
        <div class="px-3 py-2 mt-4">
            <small class="text-uppercase fw-bold text-muted ps-2" style="font-size: 0.75rem;">System</small>
        </div>

        <!-- Module 4 Access / System Settings -->
        <li>
            <a href="#settingsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-shield-alt"></i>
                <span>Access Control</span>
            </a>
            <ul class="collapse list-unstyled sidebar-submenu" id="settingsSubmenu" data-bs-parent="#sidebar">
                <li><a href="#"><i class="fas fa-users-cog text-muted fa-sm me-2"></i> Roles & Permissions</a></li>
                <li><a href="#"><i class="fas fa-user-shield text-muted fa-sm me-2"></i> User Accounts</a></li>
            </ul>
        </li>
        
        <li>
            <a href="#">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>
