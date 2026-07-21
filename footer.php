<?php
// includes/footer.php
// Gram Panchayat Complaint Management System - Shared Dashboard Footer Template
?>
            </div> <!-- End of content-body -->
            
            <!-- Dashboard Footer styled to match mockup -->
            <footer class="dashboard-footer">
                <div class="dashboard-footer-grid">
                    <div class="dashboard-footer-col">
                        <h3>Contact Us</h3>
                        <p><i class="fas fa-phone-alt" style="color:var(--primary);"></i> +91 1234567890</p>
                        <p><i class="far fa-envelope" style="color:var(--primary);"></i> gpanchayat@gmail.com</p>
                        <p><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Village Name, Taluka Name, District Name, Maharashtra</p>
                    </div>

                    <div class="dashboard-footer-col">
                        <h3>Important Links</h3>
                        <ul>
                            <li><a href="https://maharashtra.gov.in" target="_blank"><i class="fas fa-chevron-right" style="font-size: 0.65rem;"></i> Maharashtra Government</a></li>
                            <li><a href="https://panchayat.gov.in" target="_blank"><i class="fas fa-chevron-right" style="font-size: 0.65rem;"></i> Gram Panchayat Department</a></li>
                        </ul>
                    </div>

                    <div class="dashboard-footer-col" style="grid-column: 3 / -1; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <p style="margin:0; font-weight:600; color:var(--text-primary);">&copy; <?= date('Y') ?> Gram Panchayat. All Rights Reserved.</p>
                            <p style="margin:5px 0 0 0; font-size:0.75rem; color:var(--text-muted);">Version 1.0.0 (Academic Project)</p>
                        </div>
                        
                        <!-- Inline SVG decoration representing Panchayat building and Flag -->
                        <svg viewBox="0 0 120 60" width="100" height="50" style="flex-shrink:0;">
                            <!-- Ground line -->
                            <line x1="0" y1="55" x2="120" y2="55" stroke="#a0aec0" stroke-width="2"/>
                            <!-- Building base structure -->
                            <rect x="25" y="25" width="70" height="30" fill="#f7fafc" stroke="#4a5568" stroke-width="1.5"/>
                            <rect x="45" y="20" width="30" height="10" fill="#e2e8f0" stroke="#4a5568" stroke-width="1.5"/>
                            <!-- Pillars -->
                            <line x1="32" y1="25" x2="32" y2="55" stroke="#4a5568" stroke-width="1.5"/>
                            <line x1="88" y1="25" x2="88" y2="55" stroke="#4a5568" stroke-width="1.5"/>
                            <!-- Doorway -->
                            <rect x="54" y="38" width="12" height="17" fill="#a0aec0" rx="1"/>
                            <!-- Flag Pole -->
                            <line x1="15" y1="10" x2="15" y2="55" stroke="#2d3748" stroke-width="1.5"/>
                            <!-- Indian Flag -->
                            <rect x="15" y="10" width="18" height="4" fill="#ff9933"/>
                            <rect x="15" y="14" width="18" height="4" fill="#ffffff"/>
                            <rect x="15" y="18" width="18" height="4" fill="#128807"/>
                            <circle cx="24" cy="16" r="1" fill="#000088"/>
                        </svg>
                    </div>
                </div>
            </footer>
        </main> <!-- End of main-content -->
    </div> <!-- End of dashboard-wrapper -->

    <!-- Core Global Script -->
    <script src="assets/js/main.js"></script>
</body>
</html>
