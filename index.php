<?php
session_start();
require_once "businessLogics/Admin.php";
Admin::checkAuth();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once "config/database.php";
require_once "businessLogics/Dashboard.php";

/* ═══════════ PAGE DATA ═══════════ */
$stats        = Dashboard::getStats($pdo);
$monthlyData  = Dashboard::getMonthlyFreight($pdo);
$recentTrips  = Dashboard::getRecentTrips($pdo);
$unpaidBills  = Dashboard::getUnpaidBills($pdo);
$commPending  = Dashboard::getCommissionPendingParty($pdo);

$chartLabels  = json_encode(array_column($monthlyData, 'mon'));
$chartData    = json_encode(array_map(fn($r) => floatval($r['freight']), $monthlyData));

require_once "views/layout/header.php";
require_once "views/layout/sidebar.php";
?>

<style>
  .stat-card {
    border: none;
    box-shadow: 0 2px 12px rgba(0, 0, 0, .08);
    border-radius: 12px;
    transition: .2s;
  }

  .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 24px rgba(0, 0, 0, .12);
  }

  .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
  }

  .section-head {
    font-size: 13px;
    font-weight: 700;
    color: #1a237e;
    border-left: 3px solid #1a237e;
    padding-left: 8px;
    margin-bottom: 12px;
  }

  .dash-table th {
    font-size: 11px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
  }

  .dash-table td {
    font-size: 12px;
    vertical-align: middle;
  }

  .quick-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    text-decoration: none;
    color: #374151;
    font-size: 13px;
    font-weight: 600;
    transition: .15s;
  }

  .quick-link:hover {
    background: #f8fafc;
    border-color: #6366f1;
    color: #4f46e5;
    transform: translateX(3px);
  }

  .quick-link i {
    font-size: 18px;
    width: 24px;
    text-align: center;
  }
</style>

<div class="main-content app-content">
  <div class="container-fluid">

    <!-- WELCOME -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
      <div>
        <h5 class="fw-bold mb-0">🚛 Sama Roadlines — Dashboard</h5>
        <p class="text-muted fs-12 mb-0">
          <?= date('l, d F Y') ?> &nbsp;|&nbsp; Welcome, <b><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></b>
        </p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="views/pages/TripForm.php?type=Regular" class="btn btn-primary btn-sm">
          <i class="ri-add-line me-1"></i>New Trip
        </a>
        <a href="views/pages/RegularBill_generate.php" class="btn btn-outline-primary btn-sm">
          <i class="ri-bill-line me-1"></i>Generate Bill
        </a>
      </div>
    </div>

    <!-- STAT CARDS ROW -->
    <div class="row g-3 mb-4">

      <div class="col-6 col-md-3">
        <div class="card stat-card">
          <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon bg-primary-transparent"><i class="ri-truck-line text-primary fs-20"></i></div>
              <div class="text-end">
                <div class="fs-11 text-muted">This Month</div>
                <div class="fw-bold text-primary">+<?= $stats['monthTrips'] ?></div>
              </div>
            </div>
            <div class="fw-bold fs-22"><?= $stats['totalTrips'] ?></div>
            <div class="fs-12 text-muted">Total Trips</div>
            <?php if ($stats['ownerPaidTrips'] > 0): ?>
              <div class="fs-11 text-warning mt-1">⚡ <?= $stats['ownerPaidTrips'] ?> Direct Payment</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="card stat-card">
          <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon bg-success-transparent"><i class="ri-money-dollar-circle-line text-success fs-20"></i></div>
              <div class="text-end">
                <div class="fs-11 text-muted">Received</div>
                <div class="fw-bold text-success fs-12">Rs.<?= number_format($stats['receivedBillAmt'], 0) ?></div>
              </div>
            </div>
            <div class="fw-bold fs-18">Rs.<?= number_format($stats['totalBillAmt'], 0) ?></div>
            <div class="fs-12 text-muted">Total Billed</div>
            <div class="progress mt-2" style="height:4px">
              <div class="progress-bar bg-success" style="width:<?= $stats['totalBillAmt'] > 0 ? min(100, round($stats['receivedBillAmt'] / $stats['totalBillAmt'] * 100)) : 0 ?>%"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="card stat-card">
          <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon bg-danger-transparent"><i class="ri-time-line text-danger fs-20"></i></div>
              <div class="text-end">
                <div class="fs-11 text-muted"><?= $stats['regBillTotal'] ?> bills</div>
                <div class="fw-bold text-danger fs-11">Regular</div>
              </div>
            </div>
            <div class="fw-bold fs-18 text-danger">Rs.<?= number_format($stats['pendingBillAmt'], 0) ?></div>
            <div class="fs-12 text-muted">Payment Pending</div>
          </div>
        </div>
      </div>

      <div class="col-6 col-md-3">
        <div class="card stat-card">
          <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="stat-icon bg-warning-transparent"><i class="ri-percent-line text-warning fs-20"></i></div>
              <div class="text-end">
                <div class="fs-11 text-muted">Pending</div>
                <div class="fw-bold text-warning fs-12">Rs.<?= number_format($stats['pendingComm'], 0) ?></div>
              </div>
            </div>
            <div class="fw-bold fs-18">Rs.<?= number_format($stats['totalComm'], 0) ?></div>
            <div class="fs-12 text-muted">Total Commission</div>
            <?php if ($stats['ownerComm'] > 0): ?>
              <div class="fs-11 text-danger mt-1">⚠ Owner: Rs.<?= number_format($stats['ownerComm'], 0) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- CHART + QUICK ACTIONS -->
    <div class="row g-3 mb-4">

      <!-- Chart -->
      <div class="col-md-8">
        <div class="card custom-card shadow-sm h-100">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title mb-0"><i class="ri-bar-chart-line me-2 text-primary"></i>Monthly Freight (Last 6 Months)</div>
          </div>
          <div class="card-body">
            <canvas id="freightChart" height="120"></canvas>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="col-md-4">
        <div class="card custom-card shadow-sm h-100">
          <div class="card-header">
            <div class="card-title mb-0"><i class="ri-flashlight-line me-2 text-warning"></i>Quick Actions</div>
          </div>
          <div class="card-body p-3">
            <div class="d-flex flex-column gap-2">
              <a href="views/pages/TripForm.php?type=Regular" class="quick-link">
                <i class="ri-add-circle-line text-primary"></i>New Regular Trip
              </a>
              <a href="views/pages/TripForm.php?type=Agent" class="quick-link">
                <i class="ri-add-circle-line text-warning"></i>New Agent Trip
              </a>
              <a href="views/pages/RegularBill_generate.php" class="quick-link">
                <i class="ri-bill-line text-success"></i>Generate Regular Bill
              </a>
              <a href="views/pages/BillPayment_manage.php" class="quick-link">
                <i class="ri-secure-payment-line text-danger"></i>Bill Payments
              </a>
              <a href="views/pages/CommissionTrack.php" class="quick-link">
                <i class="ri-percent-line text-info"></i>Commission Tracking
              </a>
              <a href="views/pages/PartyAdvance.php" class="quick-link">
                <i class="ri-hand-coin-line" style="color:#7c3aed"></i>Party Advances
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RECENT TRIPS + PENDING BILLS -->
    <div class="row g-3 mb-4">

      <!-- Recent Trips -->
      <div class="col-md-7">
        <div class="card custom-card shadow-sm">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title mb-0"><i class="ri-map-pin-time-line me-2 text-primary"></i>Recent Trips</div>
            <div class="d-flex gap-1">
              <a href="views/pages/RegularTrips.php" class="btn btn-sm btn-outline-primary">Regular</a>
              <a href="views/pages/AgentTrips.php" class="btn btn-sm btn-outline-warning">Agent</a>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover dash-table mb-0">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Vehicle</th>
                    <th>Route</th>
                    <th>Type</th>
                    <th>Freight</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentTrips as $r):
                    $tsc = ['Open' => 'bg-primary', 'Billed' => 'bg-info', 'Completed' => 'bg-success'];
                    $ts  = $tsc[$r['TripStatus']] ?? 'bg-secondary';
                    $party = $r['TripType'] === 'Agent' ? ($r['AgentName'] ?? '—') : ($r['ConsignerName'] ?? '—');
                  ?>
                    <tr>
                      <td class="text-muted"><?= $r['TripId'] ?></td>
                      <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($r['TripDate'])) ?></td>
                      <td><span class="badge bg-secondary"><?= htmlspecialchars($r['VehicleNumber'] ?? '—') ?></span></td>
                      <td style="font-size:11px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($r['FromLocation']) ?> → <?= htmlspecialchars($r['ToLocation']) ?>
                      </td>
                      <td>
                        <span class="badge <?= $r['TripType'] === 'Agent' ? 'bg-warning text-dark' : 'bg-primary' ?>"><?= $r['TripType'] ?></span>
                        <?php if ($r['FreightPaymentToOwnerStatus'] === 'PaidDirectly'): ?>
                          <span class="badge bg-danger ms-1" title="Owner paid directly" style="font-size:9px">💸 Direct</span>
                        <?php endif; ?>
                      </td>
                      <td class="fw-bold fs-12">Rs.<?= number_format($r['FreightAmount'], 0) ?></td>
                      <td><span class="badge <?= $ts ?>"><?= $r['TripStatus'] ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Pending Bills -->
      <div class="col-md-5">
        <div class="card custom-card shadow-sm">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title mb-0"><i class="ri-error-warning-line me-2 text-danger"></i>Bills Awaiting Payment</div>
            <a href="views/pages/BillPayment_manage.php" class="btn btn-sm btn-outline-danger">Manage</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($unpaidBills)): ?>
              <div class="text-center py-4 text-success">
                <div style="font-size:36px">✅</div>
                <div class="fw-bold mt-1">All bills paid!</div>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover dash-table mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Bill No.</th>
                      <th>Party</th>
                      <th>Net Amt</th>
                      <th>Due</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($unpaidBills as $b):
                      $rem = $b['NetBillAmount'] - $b['PaidAmt'];
                      $pct = $b['NetBillAmount'] > 0 ? min(100, round($b['PaidAmt'] / $b['NetBillAmount'] * 100)) : 0;
                      $ss  = ['Generated' => 'bg-secondary', 'PartiallyPaid' => 'bg-warning text-dark'][$b['BillStatus']] ?? 'bg-secondary';
                    ?>
                      <tr>
                        <td class="fw-bold text-primary"><?= htmlspecialchars($b['BillNo']) ?></td>
                        <td style="font-size:11px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($b['PartyName']) ?></td>
                        <td class="fw-bold">Rs.<?= number_format($b['NetBillAmount'], 0) ?></td>
                        <td>
                          <div class="progress" style="height:5px;min-width:50px">
                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                          </div>
                          <div class="fs-11 text-danger">Rs.<?= number_format($rem, 0) ?></div>
                        </td>
                        <td><span class="badge <?= $ss ?>"><?= $b['BillStatus'] ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- COMMISSION PENDING -->
    <?php if (!empty($commPending) || $stats['ownerComm'] > 0): ?>
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="card custom-card shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between">
              <div class="card-title mb-0"><i class="ri-percent-line me-2 text-info"></i>Commission Pending (Party Recovery)</div>
              <div class="d-flex gap-2 align-items-center">
                <?php if ($stats['ownerComm'] > 0): ?>
                  <span class="badge bg-danger">Owner Recovery: Rs.<?= number_format($stats['ownerComm'], 0) ?></span>
                <?php endif; ?>
                <a href="views/pages/CommissionTrack.php" class="btn btn-sm btn-outline-info">Full Tracker</a>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover dash-table mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Trip #</th>
                      <th>Date</th>
                      <th>Route</th>
                      <th>Party</th>
                      <th>Commission</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($commPending as $c): ?>
                      <tr>
                        <td class="fw-bold">#<?= $c['TripId'] ?></td>
                        <td style="white-space:nowrap"><?= date('d-m-Y', strtotime($c['TripDate'])) ?></td>
                        <td style="font-size:11px"><?= htmlspecialchars($c['FromLocation']) ?> → <?= htmlspecialchars($c['ToLocation']) ?></td>
                        <td style="font-size:11px"><?= htmlspecialchars($c['ConsignerName'] ?? '—') ?></td>
                        <td class="fw-bold text-info">Rs.<?= number_format($c['CommissionAmount'], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
  (function() {
    var ctx = document.getElementById('freightChart');
    if (!ctx) return;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
          label: 'Freight (Rs.)',
          data: <?= $chartData ?>,
          backgroundColor: 'rgba(26,35,126,0.72)',
          borderColor: '#1a237e',
          borderWidth: 1,
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(v) {
                return 'Rs.' + v.toLocaleString('en-IN');
              }
            },
            grid: {
              color: 'rgba(0,0,0,0.05)'
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      }
    });
  })();
</script>

<?php require_once "views/layout/footer.php"; ?>