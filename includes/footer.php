<?php
// includes/footer.php
?>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script>
        // Sidebar Toggle for Mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }
    </script>
</body>
</html>
