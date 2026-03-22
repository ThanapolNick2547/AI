<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<nav id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-school logo-icon"></i>
        <h3>SchoolAI</h3>
    </div>
    
    <div class="px-3 py-2">
        <small class="text-uppercase fw-bold text-muted ps-2" style="font-size: 0.75rem;">เมนูระบบ</small>
    </div>

    <ul class="list-unstyled components mb-5">
        
        <!-- Dashboard -->
        <li class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php">
                <i class="fas fa-chart-pie"></i>
                <span>แดชบอร์ด</span>
            </a>
        </li>
        
        <!-- Module 1: Master Data Management -->
        <?php $master_data_pages = ['teachers.php', 'students.php', 'subjects.php', 'classes.php', 'classrooms.php']; // Add future pages here ?>
        <li class="<?php echo in_array($current_page, $master_data_pages) ? 'active' : ''; ?>">
            <a href="#masterDataSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $master_data_pages) ? 'true' : 'false'; ?>" class="dropdown-toggle">
                <i class="fas fa-database"></i>
                <span>ข้อมูลหลัก</span>
            </a>
            <ul class="collapse list-unstyled sidebar-submenu <?php echo in_array($current_page, $master_data_pages) ? 'show' : ''; ?>" id="masterDataSubmenu" data-bs-parent="#sidebar">
                <li class="<?php echo ($current_page == 'teachers.php') ? 'active-submenu' : ''; ?>">
                    <a href="teachers.php" class="<?php echo ($current_page == 'teachers.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher <?php echo ($current_page == 'teachers.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> ข้อมูลครูผู้สอน
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'students.php') ? 'active-submenu' : ''; ?>">
                    <a href="students.php" class="<?php echo ($current_page == 'students.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-user-graduate <?php echo ($current_page == 'students.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> ข้อมูลนักเรียน
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'subjects.php') ? 'active-submenu' : ''; ?>">
                    <a href="subjects.php" class="<?php echo ($current_page == 'subjects.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-book <?php echo ($current_page == 'subjects.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> ข้อมูลรายวิชา
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'classes.php') ? 'active-submenu' : ''; ?>">
                    <a href="classes.php" class="<?php echo ($current_page == 'classes.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-layer-group <?php echo ($current_page == 'classes.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> ข้อมูลระดับชั้น
                    </a>
                </li>
                <li class="<?php echo ($current_page == 'classrooms.php') ? 'active-submenu' : ''; ?>">
                    <a href="classrooms.php" class="<?php echo ($current_page == 'classrooms.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-door-open <?php echo ($current_page == 'classrooms.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> ข้อมูลห้องเรียน
                    </a>
                </li>
            </ul>
        </li>

        <!-- Module 2: Schedule Management -->
        <li class="<?php echo ($current_page == 'schedules.php') ? 'active' : ''; ?>">
            <a href="schedules.php">
                <i class="far fa-calendar-alt"></i>
                <span>ตารางสอน</span>
            </a>
        </li>

        <!-- Module 3: Grading System -->
        <li class="<?php echo ($current_page == 'grading.php') ? 'active' : ''; ?>">
            <a href="grading.php">
                <i class="fas fa-award"></i>
                <span>ผลการเรียน</span>
            </a>
        </li>

        <!-- Module 5: Attendance Tracking -->
        <li class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
            <a href="attendance.php">
                <i class="fas fa-clipboard-user"></i>
                <span>เช็คชื่อเข้าเรียน</span>
            </a>
        </li>
        
        <div class="px-3 py-2 mt-4">
            <small class="text-uppercase fw-bold text-muted ps-2" style="font-size: 0.75rem;">ตั้งค่าระบบ</small>
        </div>

        <!-- System Settings & Access Control -->
        <li class="<?php echo ($current_page == 'roles.php') ? 'active' : ''; ?>">
            <a href="roles.php">
                <i class="fas fa-shield-alt"></i>
                <span>จัดการสิทธิ์เข้าถึง</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
            <a href="#settingsSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo ($current_page == 'users.php') ? 'true' : 'false'; ?>" class="dropdown-toggle">
                <i class="fas fa-cog"></i>
                <span>การตั้งค่า</span>
            </a>
            <ul class="collapse list-unstyled sidebar-submenu <?php echo ($current_page == 'users.php') ? 'show' : ''; ?>" id="settingsSubmenu" data-bs-parent="#sidebar">
                <li class="<?php echo ($current_page == 'users.php') ? 'active-submenu' : ''; ?>">
                    <a href="users.php" class="<?php echo ($current_page == 'users.php') ? 'text-primary fw-bold' : ''; ?>">
                        <i class="fas fa-user-shield <?php echo ($current_page == 'users.php') ? 'text-primary' : 'text-muted'; ?> fa-sm me-2"></i> บัญชีผู้ใช้งาน
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</nav>
