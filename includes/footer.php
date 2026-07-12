        </div><!-- /.content-wrapper -->
    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Core Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function(){
    // Initialize DataTables with better defaults
    $('.datatable').DataTable({
        responsive: true,
        pageLength: 25,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records..."
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // Enhanced delete confirmation
    $('.btn-delete').click(function(e){
        e.preventDefault();
        const link = $(this).attr('href');
        Swal.fire({
            title: 'Confirm Deletion',
            text: "This action cannot be undone. Are you sure?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#ef4444',
            confirmButtonText: '<i class="bi bi-trash me-1"></i> Yes, delete',
            cancelButtonText: 'Cancel',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'btn btn-primary px-4 py-2 rounded-3 me-2',
                cancelButton: 'btn btn-outline-secondary px-4 py-2 rounded-3'
            }
        }).then((result) => {
            if(result.isConfirmed) {
                window.location.href = link;
            }
        });
    });

    // Active nav item highlight (already handled by PHP, but additional safety)
    const currentPath = window.location.pathname.split('/').pop();
    $('.nav-item').each(function(){
        const href = $(this).attr('href');
        if (href && href.includes(currentPath)) {
            $(this).addClass('active');
        }
    });

    // Tooltip initialization (if any)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<!-- Additional page-specific scripts can be added before footer inclusion -->
</body>
</html>