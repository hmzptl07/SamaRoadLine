<!-- ===== FOOTER ===== -->
<footer class="footer mt-auto py-0 bg-white" style="border-top: 1px solid #e9ecef;">
    <div class="container-fluid px-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between" style="min-height:46px;gap:8px;">

            <!-- Left: Copyright -->
            <div class="d-flex align-items-center gap-2">
                <span style="font-size:11.5px;color:#94a3b8;">
                    Copyright &copy; <span id="year"></span>
                </span>
                <strong style="font-size:12px;color:#1e293b;font-family:'Plus Jakarta Sans',sans-serif;">
                    🚛 Sama Roadlines
                </strong>
                <span style="font-size:11px;color:#cbd5e1;">— All rights reserved.</span>
            </div>

            <!-- Center: System Status -->
            <div class="d-none d-md-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-1" style="font-size:11px;color:#64748b;">
                    <span style="width:7px;height:7px;background:#22c55e;border-radius:50%;display:inline-block;box-shadow:0 0 0 2px rgba(34,197,94,0.2);"></span>
                    System Online
                </div>
                <div style="width:1px;height:14px;background:#e2e8f0;"></div>
                <div style="font-size:11px;color:#64748b;">
                    <i class="ri-database-2-line me-1" style="color:#1d4ed8;"></i>DB Connected
                </div>
                <div style="width:1px;height:14px;background:#e2e8f0;"></div>
                <div style="font-size:11px;color:#64748b;" id="footerSessionTime">
                    <i class="ri-time-line me-1" style="color:#f59e0b;"></i>Session: <span id="sessionTimer">00:00:00</span>
                </div>
            </div>

            <!-- Right: Version -->
            <div class="d-flex align-items-center gap-2">
                <span style="font-size:10.5px;background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;padding:2px 8px;border-radius:20px;font-weight:600;">
                    v1.0.0
                </span>
                <span style="font-size:10.5px;color:#cbd5e1;">Powered by</span>
                <span style="font-size:11px;font-weight:700;color:#1d4ed8;">PHP + MySQL</span>
            </div>

        </div>
    </div>
</footer>

<!-- Scroll To Top -->
<div class="scrollToTop">
    <span class="arrow"><i class="ti ti-arrow-narrow-up fs-20"></i></span>
</div>
<div id="responsive-overlay"></div>

<!-- ===== JS ===== -->

<!-- Popper JS -->
<script src="/Sama_Roadlines/assets/libs/@popperjs/core/umd/popper.min.js"></script>

<!-- Bootstrap JS -->
<script src="/Sama_Roadlines/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Theme JS -->
<script src="/Sama_Roadlines/assets/js/defaultmenu.min.js"></script>
<script src="/Sama_Roadlines/assets/js/sticky.js"></script>
<script src="/Sama_Roadlines/assets/js/custom.js"></script>

<!-- Node Waves -->
<script src="/Sama_Roadlines/assets/libs/node-waves/waves.min.js"></script>

<!-- Simplebar -->
<script src="/Sama_Roadlines/assets/libs/simplebar/simplebar.min.js"></script>
<script src="/Sama_Roadlines/assets/js/simplebar.js"></script>

<!-- FlatPickr -->
<script src="/Sama_Roadlines/assets/libs/flatpickr/flatpickr.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    /* ── Footer Year ── */
    document.getElementById('year').textContent = new Date().getFullYear();

    /* ── Session Timer (counts up from page load) ── */
    var sessionStart = Date.now();
    function updateSessionTimer() {
        var elapsed = Math.floor((Date.now() - sessionStart) / 1000);
        var h = Math.floor(elapsed / 3600).toString().padStart(2, '0');
        var m = Math.floor((elapsed % 3600) / 60).toString().padStart(2, '0');
        var s = (elapsed % 60).toString().padStart(2, '0');
        var el = document.getElementById('sessionTimer');
        if (el) el.textContent = h + ':' + m + ':' + s;
    }
    setInterval(updateSessionTimer, 1000);
</script>

</body>
</html>
