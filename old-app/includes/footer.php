<?php
// footer.php
?>
    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="copyright">
                        <i class="fas fa-copyright me-1"></i> <?php echo date('Y'); ?> Sablayan MDRRMO. All rights reserved.
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="version">
                        <i class="fas fa-code-branch me-1"></i> Version 2.0.0
                    </span>
                </div>
            </div>
        </div>
    </footer>
</div> <!-- Close main-wrapper -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    // Check localStorage for sidebar state
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        mainWrapper.classList.add('expanded');
    }
    
    // Toggle sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('collapsed');
            mainWrapper.classList.toggle('expanded');
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
    
    // Mobile sidebar handling
    if (window.innerWidth <= 992) {
        sidebar.classList.add('mobile');
    }
    
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 992) {
            sidebar.classList.add('mobile');
        } else {
            sidebar.classList.remove('mobile');
        }
    });
    
    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 992 && 
            !sidebar.contains(event.target) && 
            sidebarToggle && !sidebarToggle.contains(event.target) &&
            sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    });
});
</script>

<!-- Initialize tooltips -->
<script>
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>

</body>
</html>