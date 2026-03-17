        </main> <!-- End Main Content -->
        
        <!-- Footer Content -->
        <footer class="mt-auto py-3 px-4 bg-white border-top">
            <div class="d-flex align-items-center justify-content-between small">
                <div class="text-muted">Copyright &copy; SchoolAI <?php echo date('Y'); ?>. All rights reserved.</div>
                <div>
                    <a href="#" class="text-decoration-none text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-decoration-none text-muted h-100 me-3">Terms &amp; Conditions</a>
                    <span class="text-muted">Version 1.0.0</span>
                </div>
            </div>
        </footer>

    </div> <!-- End Page Content id=content -->
</div> <!-- End Wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom Script -->
<script src="assets/js/script.js"></script>

<script>
    // Specific initialization if needed
    window.onload = function() {
        if(typeof initDashboardCharts === 'function') {
            initDashboardCharts();
        }
    };
</script>

</body>
</html>
