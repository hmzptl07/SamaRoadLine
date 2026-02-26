<!-- ===== FOOTER ===== -->
<footer class="footer mt-auto py-3 bg-white text-center">
    <div class="container">
        <span class="text-muted fs-12">
            Copyright &copy; <span id="year"></span>
            <strong class="text-dark">Sama Roadlines</strong>. All rights reserved.
        </span>
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
    // Auto year in footer
    document.getElementById('year').textContent = new Date().getFullYear();
</script>

</body>
</html>
