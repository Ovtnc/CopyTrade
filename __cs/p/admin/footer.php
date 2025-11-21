        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Language Translations (only if needed) -->
    <!-- <script src="<?= WEB_URL; ?>/vendor/lang.js"></script> -->
    <!-- Custom JS (disabled for admin panel to prevent conflicts) -->
    <!-- <script src="<?= WEB_URL; ?>/vendor/sc.js"></script> -->
    
    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('adminSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const adminMain = document.getElementById('adminMain');
        
        let sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        function toggleSidebar() {
            if (window.innerWidth <= 992) {
                // Mobile: toggle open/close
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('active');
            } else {
                // Desktop: toggle collapsed/expanded
                sidebarCollapsed = !sidebarCollapsed;
                localStorage.setItem('sidebarCollapsed', sidebarCollapsed);
                
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    adminMain.classList.add('sidebar-collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    adminMain.classList.remove('sidebar-collapsed');
                }
            }
        }
        
        function initSidebar() {
            if (window.innerWidth > 992) {
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    adminMain.classList.add('sidebar-collapsed');
                }
            }
        }
        
        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });
        
        // Initialize sidebar on load
        initSidebar();
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
                initSidebar();
            } else {
                sidebar.classList.remove('collapsed');
                adminMain.classList.remove('sidebar-collapsed');
            }
        });

        // Fix modal z-index when opened
        document.addEventListener('shown.bs.modal', function (event) {
            const modal = event.target;
            if (modal) {
                modal.style.zIndex = '1060';
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.zIndex = '1055';
                }
            }
        });

        // Ensure modal is on top
        document.addEventListener('show.bs.modal', function (event) {
            const modal = event.target;
            if (modal) {
                // Close sidebar overlay if open on mobile
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>

