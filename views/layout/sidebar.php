<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function isActive(string $p): string
{
    global $currentPage;
    return $currentPage === $p ? ' active' : '';
}

$tripPages = ['RegularTrips', 'AgentTrips', 'DirectTrips', 'RegularTripForm', 'AgentTripForm'];
$tripOpen  = in_array($currentPage, $tripPages);
$billPages = ['RegularBill_generate'];
$billOpen  = in_array($currentPage, $billPages);
?>
<style>
    .sama-brand-logo {
        display: flex !important;
        align-items: center;
        text-decoration: none !important;
        padding: 4px 0;
    }

    .sama-logo-full {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sama-logo-icon {
        font-size: 28px;
        line-height: 1;
        flex-shrink: 0;
    }

    .sama-logo-text {
        display: flex;
        flex-direction: column;
        line-height: 1;
    }

    .sama-logo-name {
        font-size: 17px;
        font-weight: 900;
        color: #1a237e;
        letter-spacing: .5px;
        text-transform: uppercase;
    }

    .sama-logo-name2 {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-top: 1px;
    }

    .sama-logo-mini {
        display: none;
        font-size: 26px;
        line-height: 1;
    }

    [data-toggled="icon-overlay"] .sama-logo-full,
    [data-toggled="close"] .sama-logo-full {
        display: none !important;
    }

    [data-toggled="icon-overlay"] .sama-logo-mini,
    [data-toggled="close"] .sama-logo-mini {
        display: inline !important;
    }

    body:not([data-toggled="icon-overlay"]):not([data-toggled="close"]) .app-sidebar .side-menu__label {
        white-space: normal !important;
        overflow: visible !important;
        text-overflow: unset !important;
        line-height: 1.35;
        word-break: break-word;
        flex: 1;
    }

    body:not([data-toggled="icon-overlay"]):not([data-toggled="close"]) .app-sidebar .side-menu__item {
        height: auto !important;
        min-height: 40px;
        padding-top: 8px !important;
        padding-bottom: 8px !important;
        display: flex !important;
        align-items: center !important;
    }

    body:not([data-toggled="icon-overlay"]):not([data-toggled="close"]) .app-sidebar .side-menu__icon {
        flex-shrink: 0 !important;
        margin-top: 0 !important;
    }

    body:not([data-toggled="icon-overlay"]):not([data-toggled="close"]) .app-sidebar .side-menu__angle {
        flex-shrink: 0 !important;
        margin-left: auto !important;
        margin-top: 0 !important;
    }

    body:not([data-toggled="icon-overlay"]):not([data-toggled="close"]) .app-sidebar .slide-menu .side-menu__item {
        min-height: 36px !important;
        padding-top: 6px !important;
        padding-bottom: 6px !important;
    }
</style>

<aside class="app-sidebar sticky" id="sidebar">
    <div class="main-sidebar-header">
        <a href="/Sama_Roadlines/index.php" class="header-logo sama-brand-logo">
            <span class="sama-logo-full">
                <span class="sama-logo-icon">🚛</span>
                <span class="sama-logo-text">
                    <span class="sama-logo-name">Sama</span>
                    <span class="sama-logo-name2">Roadlines</span>
                </span>
            </span>
            <span class="sama-logo-mini">🚛</span>
        </a>
    </div>

    <div class="main-sidebar" id="sidebar-scroll">
        <nav class="main-menu-container nav nav-pills flex-column sub-open">

            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z" />
                </svg>
            </div>

            <ul class="main-menu">

                <!-- MASTER -->
                <li class="slide__category"><span class="category-name">Master</span></li>

                <li class="slide">
                    <a href="/Sama_Roadlines/index.php" class="side-menu__item<?= isActive('index') ?>">
                        <i class="ri-home-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Dashboard</span>
                    </a>
                </li>
                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/PartyView.php" class="side-menu__item<?= isActive('PartyView') ?>">
                        <i class="ri-group-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Party</span>
                    </a>
                </li>
                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/VehicleOwnerView.php" class="side-menu__item<?= isActive('VehicleOwnerView') ?>">
                        <i class="ri-user-star-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Vehicle Owner</span>
                    </a>
                </li>
                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/VehicleView.php" class="side-menu__item<?= isActive('VehicleView') ?>">
                        <i class="ri-truck-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Vehicle</span>
                    </a>
                </li>

                <!-- TRIPS -->
                <li class="slide__category"><span class="category-name">Trip Section</span></li>

                <li class="slide has-sub<?= $tripOpen ? ' open' : '' ?>">
                    <a href="javascript:void(0);" class="side-menu__item<?= $tripOpen ? ' active' : '' ?>">
                        <i class="ri-road-map-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Trips</span>
                        <i class="ri-arrow-right-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1"><a href="javascript:void(0)">Trips</a></li>

                        <!-- Lists -->
                        <li class="slide">
                            <a href="/Sama_Roadlines/views/pages/RegularTrips.php" class="side-menu__item<?= isActive('RegularTrips') ?>">
                                <i class="ri-file-list-line me-2"></i>
                                <span class="side-menu__label">Regular Trips</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="/Sama_Roadlines/views/pages/AgentTrips.php" class="side-menu__item<?= isActive('AgentTrips') ?>">
                                <i class="ri-user-star-line me-2"></i>
                                <span class="side-menu__label">Agent Trips</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="/Sama_Roadlines/views/pages/DirectTrips.php" class="side-menu__item<?= isActive('DirectTrips') ?>">
                                <i class="ri-arrow-right-circle-line me-2 text-warning"></i>
                                <span class="side-menu__label">Direct Pay Trips</span>
                            </a>
                        </li>

                        <!-- Divider + New buttons -->
                        <li class="slide" style="margin-top:6px;padding-top:6px;border-top:1px solid #f1f5f9;">
                            <a href="/Sama_Roadlines/views/pages/RegularTripForm.php"
                                class="side-menu__item<?= isActive('RegularTripForm') ?>">
                                <i class="ri-add-circle-line me-2" style="color:#16a34a;"></i>
                                <span class="side-menu__label" style="color:#16a34a;">+ New Regular Trip</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="/Sama_Roadlines/views/pages/AgentTripForm.php"
                                class="side-menu__item<?= isActive('AgentTripForm') ?>">
                                <i class="ri-add-circle-line me-2" style="color:#d97706;"></i>
                                <span class="side-menu__label" style="color:#d97706;">+ New Agent Trip</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- PARTY BILLING -->
                <li class="slide__category"><span class="category-name">Party Billing</span></li>

                <li class="slide has-sub<?= $billOpen ? ' open' : '' ?>">
                    <a href="javascript:void(0);" class="side-menu__item<?= $billOpen ? ' active' : '' ?>">
                        <i class="ri-bill-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Generate Bills</span>
                        <i class="ri-arrow-right-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1"><a href="javascript:void(0)">Bills</a></li>
                        <li class="slide">
                            <a href="/Sama_Roadlines/views/pages/RegularBill_generate.php" class="side-menu__item<?= isActive('RegularBill_generate') ?>">
                                <i class="ri-file-list-3-line me-2"></i>
                                <span class="side-menu__label">Regular Bills</span>
                            </a>
                        </li>
                    </ul>
                </li>
  <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/AgentPayments.php" class="side-menu__item<?= isActive('AgentPayments') ?>">
                        <i class="ri-secure-payment-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Agent Payments</span>
                    </a>
                </li>
                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/BillPayment_manage.php" class="side-menu__item<?= isActive('BillPayment_manage') ?>">
                        <i class="ri-secure-payment-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Bill Payments</span>
                    </a>
                </li>
                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/PartyAdvance.php" class="side-menu__item<?= isActive('PartyAdvance') ?>">
                        <i class="ri-hand-coin-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Party Advance</span>
                    </a>
                </li>

                <!-- OWNER PAYMENTS -->
                <li class="slide__category"><span class="category-name">Owner Payments</span></li>

                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/OwnerPayment_manage.php" class="side-menu__item<?= isActive('OwnerPayment_manage') ?>">
                        <i class="ri-money-dollar-box-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Owner Freight Payment</span>
                    </a>
                </li>
                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/OwnerAdvance_manage.php" class="side-menu__item<?= isActive('OwnerAdvance_manage') ?>">
                        <i class="ri-hand-coin-line side-menu__icon fs-18" style="color:#7c3aed;"></i>
                        <span class="side-menu__label">Owner Advance</span>
                    </a>
                </li>

                <!-- COMMISSION -->
                <li class="slide__category"><span class="category-name">Commission</span></li>

                <li class="slide">
                    <a href="/Sama_Roadlines/views/pages/CommissionTrack.php" class="side-menu__item<?= isActive('CommissionTrack') ?>">
                        <i class="ri-percent-line side-menu__icon fs-18"></i>
                        <span class="side-menu__label">Commission Tracker</span>
                    </a>
                </li>

                <!-- ACCOUNT -->
                <li class="slide__category"><span class="category-name">Account</span></li>

                <li class="slide">
                    <a href="javascript:void(0);" class="side-menu__item text-danger" onclick="confirmLogout()">
                        <i class="ri-logout-box-r-line side-menu__icon fs-18 text-danger"></i>
                        <span class="side-menu__label">Logout</span>
                    </a>
                </li>

            </ul>

            <div class="slide-right" id="slide-right">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z" />
                </svg>
            </div>

        </nav>
    </div>
</aside>