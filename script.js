    // Toggle Sidebar
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('toggle-sidebar');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        
        // Theme Toggle Functionality
        function initThemeToggle() {
            const themeToggle = document.getElementById('theme-toggle');
            
            if (!themeToggle) {
                console.warn('Theme toggle element not found - this page might not have a theme toggle button');
                return;
            }
            
            // Check for saved theme preference or use default dark theme
            const savedTheme = localStorage.getItem('theme') || 'dark';
            
            // Update checkbox state based on saved theme
            themeToggle.checked = savedTheme === 'light';
            
            // Add event listener to toggle button
            themeToggle.addEventListener('change', function() {
                const newTheme = this.checked ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                
                // Ensure any iframes or embedded content also get the theme (if applicable)
                document.querySelectorAll('iframe').forEach(iframe => {
                    try {
                        if (iframe.contentDocument) {
                            iframe.contentDocument.documentElement.setAttribute('data-theme', newTheme);
                        }
                    } catch (e) {
                        // Ignore cross-origin errors
                    }
                });
                
                // Debug output
                console.log(`Theme switched to ${newTheme} mode`);
            });
            
            // Handle direct label clicks with a separate handler
            const themeToggleLabel = document.querySelector('.theme-toggle-label');
            if (themeToggleLabel) {
                themeToggleLabel.addEventListener('click', function(e) {
                    // This will trigger the change event on the checkbox
                    // We use preventDefault here but let the change event bubble up
                    e.preventDefault();
                    themeToggle.checked = !themeToggle.checked;
                    
                    // Manually trigger the change event
                    const event = new Event('change');
                    themeToggle.dispatchEvent(event);
                });
            }
        }
        
        // Initialize active nav item
        function setActiveNavItem() {
            // Get current page path or set default
            const currentPath = window.location.pathname || '/';
            const navLinks = document.querySelectorAll('.nav-link');
            
            // Set first item as active by default
            if (!document.querySelector('.nav-item.active') && navLinks.length > 0) {
                navLinks[0].parentElement.classList.add('active');
            }
        }
        
        // Function to toggle mobile menu state
        function toggleMobileMenu() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('active');
            mobileMenuBtn.classList.toggle('active');
            
            // Force sidebar to be visible when shown
            if (sidebar.classList.contains('show')) {
                sidebar.style.transform = 'translateX(0)';
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            } else {
                sidebar.style.transform = '';
                document.body.style.overflow = ''; // Re-enable scrolling
            }
        }
        
        // Desktop sidebar toggle
        toggleBtn.addEventListener('click', () => {
            // Only toggle collapsed state if we're not in mobile mode
            if (window.innerWidth > 640) {
                sidebar.classList.toggle('sidebar-collapsed');
                mainContent.classList.toggle('main-content-expanded');
            } else {
                // In mobile mode, toggle the show class
                toggleMobileMenu();
            }
        });
        
        // Mobile menu toggle
        mobileMenuBtn.addEventListener('click', () => {
            toggleMobileMenu();
        });
        
        // Close sidebar when clicking on overlay
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('active');
            mobileMenuBtn.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Handle nav item clicks - make item active and close mobile menu if needed
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                // Don't prevent default link behavior so navigation works
                // e.preventDefault(); -- Removed to allow normal navigation
                
                // Add active class to clicked item
                const navItems = document.querySelectorAll('.nav-item');
                navItems.forEach(item => item.classList.remove('active'));
                link.parentElement.classList.add('active');
                
                // Close mobile menu if in mobile view
                if (window.innerWidth <= 640) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('active');
                    mobileMenuBtn.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Mobile Menu Toggle and responsive behavior
        function handleResize() {
            if (window.innerWidth <= 640) {
                // Mobile view
                mainContent.classList.add('main-content-expanded');
                mobileMenuBtn.style.display = 'flex'; // Ensure mobile button is shown
                
                if (!sidebar.classList.contains('show')) {
                    sidebar.style.transform = 'translateX(-100%)';
                } else {
                    sidebar.style.transform = 'translateX(0)'; // Keep sidebar visible if shown
                }
                
                // Reset any desktop-specific classes
                if (sidebar.classList.contains('sidebar-collapsed')) {
                    sidebar.classList.remove('sidebar-collapsed');
                }
            } else if (window.innerWidth <= 768) {
                // Tablet view - always collapsed sidebar
                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.add('main-content-expanded');
                sidebar.style.transform = '';
                mobileMenuBtn.style.display = 'none'; // Hide mobile button
                
                // Reset any mobile-specific classes
                if (sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('active');
                    mobileMenuBtn.classList.remove('active');
                    document.body.style.overflow = '';
                }
            } else {
                // Desktop view - restore normal state
                sidebar.style.transform = '';
                mobileMenuBtn.style.display = 'none'; // Hide mobile button
                
                // Only adjust if not manually collapsed
                if (!sidebar.classList.contains('sidebar-collapsed')) {
                    mainContent.classList.remove('main-content-expanded');
                }
                
                // Reset any mobile-specific classes
                if (sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('active');
                    mobileMenuBtn.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        }
        
        // Initialize on page load to ensure mobile menu setup
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize responsive behavior
            handleResize();
            
            // Initialize active nav items
            setActiveNavItem();
            
            // Initialize theme toggle
            initThemeToggle();
            
            // Ensure sidebar scrolls to top when opened
            mobileMenuBtn.addEventListener('click', function() {
                if (sidebar.classList.contains('show')) {
                    sidebar.scrollTop = 0;
                }
            });
            
            // We don't call renderBandwidthChart() here anymore
            // It's handled in the inline script in index.php
        });
        
        // Initialize responsive behavior
        handleResize();
        window.addEventListener('resize', handleResize);
        
        // Initialize active nav items
        setActiveNavItem();
        
        // Generate Bandwidth Chart - Legacy version, will be overridden by the dynamic implementation
        const bandwidthChart = document.getElementById('bandwidth-chart');
        const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const generateRandomData = () => {
            return days.map(() => Math.floor(Math.random() * 80) + 20);
        };
        
        function renderBandwidthChart() {
            // This function will be replaced at runtime by our dynamic implementation
            // We keep it here for backward compatibility
            console.log("Legacy chart function called - should be replaced by dynamic version");
            const data = generateRandomData();
            if (!bandwidthChart) return;
            
            bandwidthChart.innerHTML = '';
            
            data.forEach((value, index) => {
                const bar = document.createElement('div');
                bar.className = 'chart-bar';
                bar.style.height = `${value}%`;
                bar.setAttribute('data-label', days[index]);
                bandwidthChart.appendChild(bar);
            });
        }
        
        // We don't call renderBandwidthChart() here anymore
        // It's handled in the inline script in index.php
        
        // Time filter buttons - now handled by links in the HTML with timespan query params
        const timeButtons = document.querySelectorAll('.time-btn');
        timeButtons.forEach(button => {
            if (!button.href) {  // Only add click handlers to buttons, not links
                button.addEventListener('click', () => {
                    timeButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    // Don't regenerate chart data here, as we now use page reloads with query params
                });
            }
        });
        
        // Add hover effect to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-8px)';
                card.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.2)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(-5px)';
                card.style.boxShadow = 'var(--card-shadow)';
            });
        });