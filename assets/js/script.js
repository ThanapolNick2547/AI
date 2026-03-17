$(document).ready(function () {

    // Toggle Sidebar
    $('#sidebarCollapse').on('click', function () {
        if ($(window).width() <= 768) {
            $('#sidebar').toggleClass('show');
            // If showing on mobile, ensure it's not collapsed
            if ($('#sidebar').hasClass('show')) {
                $('#sidebar').removeClass('collapsed');
            }
        } else {
            $('#sidebar').toggleClass('collapsed');
            
            // Close submenus when sidebar is collapsed
            if ($('#sidebar').hasClass('collapsed')) {
                $('.sidebar-submenu.show').collapse('hide');
            }
        }
    });

    // Handle submenu toggling when sidebar is collapsed
    $('#sidebar [data-bs-toggle="collapse"]').on('click', function(e) {
        if ($('#sidebar').hasClass('collapsed') && $(window).width() > 768) {
            e.preventDefault();
            $('#sidebar').removeClass('collapsed');
            setTimeout(() => {
                let target = $(this).attr('data-bs-target') || $(this).attr('href');
                $(target).collapse('show');
            }, 300);
        }
    });

    // Close sidebar on mobile when clicking outside
    $(document).on('click', function (e) {
        if ($(window).width() <= 768) {
            if (!$(e.target).closest('#sidebar').length && !$(e.target).closest('#sidebarCollapse').length) {
                $('#sidebar').removeClass('show');
            }
        }
    });

    // Set active menu item based on current URL
    let currentUrl = window.location.pathname.split('/').pop();
    if(currentUrl === '') currentUrl = 'index.php';
    
    $('#sidebar ul li a').each(function() {
        let href = $(this).attr('href');
        if (href && href.includes(currentUrl)) {
            $(this).parent().addClass('active');
            
            // If inside a submenu, open the parent
            let parentUl = $(this).closest('ul.collapse');
            if (parentUl.length > 0) {
                parentUl.addClass('show');
                parentUl.prev('a').attr('aria-expanded', 'true');
            }
        }
    });

});

// Initialize Dashboard Charts
function initDashboardCharts() {
    // Check if we are on dashboard page
    if (document.getElementById('attendanceChart') && document.getElementById('gradeChart')) {
        
        // Common Chart.js Config
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = "#6c757d";
        const primaryColor = '#D02752';

        // 1. Attendance Chart (Bar)
        const ctxAttendance = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctxAttendance, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                datasets: [{
                    label: 'Present (%)',
                    data: [95, 92, 98, 91, 89],
                    backgroundColor: primaryColor,
                    borderRadius: 4
                }, {
                    label: 'Absent (%)',
                    data: [5, 8, 2, 9, 11],
                    backgroundColor: '#e9ecef',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // 2. Grade Distribution Chart (Doughnut)
        const ctxGrade = document.getElementById('gradeChart').getContext('2d');
        new Chart(ctxGrade, {
            type: 'doughnut',
            data: {
                labels: ['Grade 4', 'Grade 3', 'Grade 2', 'Grade 1', 'Grade 0'],
                datasets: [{
                    data: [35, 40, 15, 8, 2],
                    backgroundColor: [
                        '#198754', // Success green
                        '#0dcaf0', // Info blue
                        '#ffc107', // Warning yellow
                        '#fd7e14', // Orange
                        primaryColor // Primary red
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                },
                cutout: '70%'
            }
        });
    }
}
