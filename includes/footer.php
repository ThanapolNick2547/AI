        </main> <!-- End Main Content -->
        
        <!-- Footer Content -->
        <footer class="mt-auto py-3 px-4 bg-white border-top">
            <div class="d-flex align-items-center justify-content-between small">
                <div class="text-muted">สงวนลิขสิทธิ์ &copy; SchoolAI <?php echo date('Y'); ?></div>
                <div>
                    <a href="#" class="text-decoration-none text-muted me-3">นโยบายความเป็นส่วนตัว</a>
                    <a href="#" class="text-decoration-none text-muted h-100 me-3">เงื่อนไขการใช้งาน</a>
                    <span class="text-muted">เวอร์ชัน 1.0.0</span>
                </div>
            </div>
        </footer>

    </div> <!-- End Page Content id=content -->
</div> <!-- End Wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

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

    $(document).ready(function() {
        // Initialize DataTables on elements with the class 'table-datatable'
        // Or directly apply to standard static tables that aren't on the dashboard
        $('.datatable').DataTable({
            "language": {
                "sProcessing":   "กำลังดำเนินการ...",
                "sLengthMenu":   "แสดง _MENU_ แถว",
                "sZeroRecords":  "ไม่พบข้อมูล",
                "sInfo":         "แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว",
                "sInfoEmpty":    "แสดง 0 ถึง 0 จาก 0 แถว",
                "sInfoFiltered": "(กรองข้อมูล _MAX_ ทุกแถว)",
                "sInfoPostFix":  "",
                "sSearch":       "ค้นหา:",
                "sUrl":          "",
                "oPaginate": {
                    "sFirst":    "หน้าแรก",
                    "sPrevious": "ก่อนหน้า",
                    "sNext":     "ถัดไป",
                    "sLast":     "หน้าสุดท้าย"
                }
            },
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]]
        });
    });
</script>

</body>
</html>
