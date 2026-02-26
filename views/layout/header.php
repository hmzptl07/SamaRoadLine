<!DOCTYPE html>
<html lang="en" dir="ltr"
    data-nav-layout="vertical"
    data-theme-mode="light"
    data-header-styles="light"
    data-menu-styles="light"
    data-toggled="close">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sama Roadlines</title>

    <!-- Favicon -->
    <link rel="icon" href="/Sama_Roadlines/assets/images/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 -->
    <link id="style" href="/Sama_Roadlines/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Theme Styles -->
    <link href="/Sama_Roadlines/assets/css/styles.css" rel="stylesheet">

    <!-- ✅ All Icons via CDN — No local icon dependency -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">

    <!-- Node Waves -->
    <link href="/Sama_Roadlines/assets/libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar -->
    <link href="/Sama_Roadlines/assets/libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- FlatPickr -->
    <link href="/Sama_Roadlines/assets/libs/flatpickr/flatpickr.min.css" rel="stylesheet">

    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- jQuery (must be first) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Theme Main JS -->
    <script src="/Sama_Roadlines/assets/js/main.js"></script>

    <style>
        /* ── Font ── */
        body, .app-header { font-family: 'Plus Jakarta Sans', sans-serif !important; }

        /* ── DataTable global ── */
        .dataTables_wrapper table td,
        .dataTables_wrapper table th { white-space: nowrap; }

        /* ── Column filter row ── */
        .col-filter input,
        .col-filter select {
            font-size: 12px;
            padding: 3px 6px;
            height: 28px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            width: 100%;
        }

        /* ── Select2 modal fix ── */
        .select2-container { width: 100% !important; }
        .select2-container--bootstrap-5 .select2-selection { min-height: 38px; }

        /* ── Card header ── */
        .card-header { border-bottom: 1px solid #e9ecef; }
        .badge { font-size: 11px; }

        /* ════════════════════════════════
           HEADER CLOCK/CALENDAR WIDGET
        ════════════════════════════════ */
        .header-datetime-widget {
            display: flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #f0f4ff, #e8f0fe);
            border: 1px solid #c7d7fc;
            border-radius: 10px;
            padding: 5px 14px;
            cursor: default;
            user-select: none;
            transition: box-shadow 0.2s;
        }
        .header-datetime-widget:hover {
            box-shadow: 0 2px 12px rgba(29, 78, 216, 0.12);
        }

        /* Date part */
        .hdt-date {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            font-weight: 600;
            color: #1e3a5f;
            border-right: 1px solid #c7d7fc;
            padding-right: 10px;
        }
        .hdt-date i {
            font-size: 15px;
            color: #1d4ed8;
        }

        /* Day badge */
        .hdt-day-badge {
            background: #1d4ed8;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
            letter-spacing: 0.3px;
        }

        /* Clock part */
        .hdt-clock {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.5px;
        }
        .hdt-clock i {
            font-size: 15px;
            color: #f59e0b;
        }

        /* AM/PM badge */
        .hdt-ampm {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        /* Confirm Logout */
        .logout-confirm-btn { cursor: pointer; }
    </style>
</head>

<body class="">

<!-- Loader -->
<div id="loader">
    <img src="/Sama_Roadlines/assets/images/media/loader.svg" alt="Loading...">
</div>

<div class="page">

<!-- ═══════════════════════════════
     HEADER
═══════════════════════════════ -->
<header class="app-header sticky" id="header">
    <div class="main-header-container container-fluid">

        <!-- ── Left ── -->
        <div class="header-content-left">
            <!-- Logo -->
            <div class="header-element">
                <div class="horizontal-logo">
                    <a href="/Sama_Roadlines/index.php" class="header-logo">
                        <img src="/Sama_Roadlines/assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">
                        <img src="/Sama_Roadlines/assets/images/brand-logos/toggle-dark.png"   alt="logo" class="toggle-dark">
                        <img src="/Sama_Roadlines/assets/images/brand-logos/desktop-dark.png"  alt="logo" class="desktop-dark">
                        <img src="/Sama_Roadlines/assets/images/brand-logos/toggle-logo.png"   alt="logo" class="toggle-logo">
                        <img src="/Sama_Roadlines/assets/images/brand-logos/toggle-white.png"  alt="logo" class="toggle-white">
                        <img src="/Sama_Roadlines/assets/images/brand-logos/desktop-white.png" alt="logo" class="desktop-white">
                    </a>
                </div>
            </div>
            <!-- Sidebar Toggle -->
            <div class="header-element mx-lg-0 mx-2">
                <a aria-label="Hide Sidebar"
                   class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle"
                   data-bs-toggle="sidebar" href="javascript:void(0);">
                    <span></span>
                </a>
            </div>
        </div>
        <!-- /Left -->

        <!-- ── Right ── -->
        <ul class="header-content-right" style="gap:10px;">

            <!-- ════ DATE & TIME WIDGET ════ -->
            <li class="header-element d-none d-md-flex align-items-center">
                <div class="header-datetime-widget" id="datetimeWidget">
                    <!-- Date section -->
                    <div class="hdt-date">
                        <i class="ri-calendar-line"></i>
                        <span id="hdtDateStr">--</span>
                        <span class="hdt-day-badge" id="hdtDayName">---</span>
                    </div>
                    <!-- Clock section -->
                    <div class="hdt-clock" style="padding-left:10px;">
                        <i class="ri-time-line"></i>
                        <span id="hdtTime">--:--:--</span>
                        <span class="hdt-ampm" id="hdtAmPm">--</span>
                    </div>
                </div>
            </li>
            <!-- /Date & Time Widget -->

            <!-- ════ User Profile Dropdown ════ -->
            <li class="header-element dropdown">
                <a href="javascript:void(0);"
                   class="header-link dropdown-toggle"
                   id="mainHeaderProfile"
                   data-bs-toggle="dropdown"
                   data-bs-auto-close="outside"
                   aria-expanded="false">
                    <div class="d-flex align-items-center gap-2">
                        <img src="/Sama_Roadlines/assets/images/faces/15.jpg"
                             alt="user"
                             class="avatar custom-header-avatar avatar-rounded">
                        <div class="d-none d-lg-block text-start">
                            <div style="font-size:13px;font-weight:700;color:#1e293b;line-height:1.2;">
                                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                            </div>
                            <div style="font-size:11px;color:#94a3b8;">Administrator</div>
                        </div>
                        <i class="ri-arrow-down-s-line" style="font-size:16px;color:#94a3b8;"></i>
                    </div>
                </a>
                <ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end"
                    aria-labelledby="mainHeaderProfile"
                    style="min-width:200px;">

                    <!-- Profile header -->
                    <li>
                        <div class="dropdown-item text-center py-3 border-bottom"
                             style="background:linear-gradient(135deg,#f0f4ff,#e8f0fe);">
                            <div style="width:44px;height:44px;background:linear-gradient(135deg,#0f172a,#1d4ed8);border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:20px;">
                                👤
                            </div>
                            <div style="font-size:14px;font-weight:700;color:#0f172a;">
                                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                            </div>
                            <div style="font-size:11px;color:#64748b;">Administrator</div>
                        </div>
                    </li>

                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="javascript:void(0);"
                           style="font-size:13.5px;">
                            <i class="ri-user-settings-line" style="font-size:16px;color:#1d4ed8;"></i>
                            My Profile
                        </a>
                    </li>

                    <li><hr class="dropdown-divider my-1"></li>

                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2 logout-confirm-btn"
                           href="javascript:void(0);"
                           onclick="confirmLogout()"
                           style="font-size:13.5px;">
                            <i class="ri-logout-box-r-line" style="font-size:16px;color:#ef4444;"></i>
                            <span style="color:#ef4444;font-weight:600;">Log Out</span>
                        </a>
                    </li>
                </ul>
            </li>
            <!-- /User Profile -->

        </ul>
        <!-- /Right -->

    </div>
</header>
<!-- /HEADER -->

<!-- ═══════════════════════════════
     HEADER SCRIPTS
═══════════════════════════════ -->
<script>

/* ── Live Clock & Date ── */
function updateHeaderClock() {
    var now = new Date();

    // Date string: "21 Feb 2026"
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var days   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    var dateStr = now.getDate().toString().padStart(2,'0') + ' ' +
                  months[now.getMonth()] + ' ' +
                  now.getFullYear();
    var dayName = days[now.getDay()];

    // Time 12-hour format
    var h   = now.getHours();
    var m   = now.getMinutes().toString().padStart(2,'0');
    var s   = now.getSeconds().toString().padStart(2,'0');
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    var timeStr = h.toString().padStart(2,'0') + ':' + m + ':' + s;

    var dEl  = document.getElementById('hdtDateStr');
    var dyEl = document.getElementById('hdtDayName');
    var tEl  = document.getElementById('hdtTime');
    var aEl  = document.getElementById('hdtAmPm');

    if (dEl)  dEl.textContent  = dateStr;
    if (dyEl) dyEl.textContent = dayName;
    if (tEl)  tEl.textContent  = timeStr;
    if (aEl)  aEl.textContent  = ampm;
}

// Run immediately + every second
updateHeaderClock();
setInterval(updateHeaderClock, 1000);


/* ── Confirm Logout (SweetAlert2) ── */
function confirmLogout() {
    // Fallback if Swal not loaded yet
    if (typeof Swal === 'undefined') {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = "/Sama_Roadlines/logout.php";
        }
        return;
    }
    Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to sign out?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor:  '#64748b',
        confirmButtonText:  '<i class="ri-logout-box-r-line me-1"></i> Yes, Logout',
        cancelButtonText:   'Cancel',
        borderRadius:       '14px',
        customClass: {
            popup:         'swal-custom-popup',
            confirmButton: 'swal-custom-confirm'
        }
    }).then(function(result) {
        if (result.isConfirmed) {
            window.location.href = "/Sama_Roadlines/logout.php";
        }
    });
}
</script>
