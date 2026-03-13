/**
 * ============================================================
 *  reports_ajax.js  —  Sama Roadlines
 *  Include this JS in all 4 report pages.
 *  Path: /Sama_Roadlines/assets/js/reports_ajax.js
 *
 *  All calls go to:
 *  /Sama_Roadlines/reports/code.php?action=XXX
 * ============================================================
 */

var CODE_PHP = '/Sama_Roadlines/reports/code.php';

/* ── Rupee formatter ── */
function fmtRs(n) {
    n = parseFloat(n) || 0;
    return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ── Date helper (dd-mm-yyyy for display) ── */
function fmtDate(d) {
    if (!d) return '-';
    var parts = d.split('-');
    if (parts.length === 3 && parts[0].length === 4) {
        return parts[2] + '-' + parts[1] + '-' + parts[0]; // yyyy-mm-dd → dd-mm-yyyy
    }
    return d;
}

/* ── Status badge ── */
function statusBadge(status) {
    var colors = {
        'Paid':    'background:#dcfce7;color:#15803d;',
        'Partial': 'background:#fef9c3;color:#b45309;',
        'Pending': 'background:#fee2e2;color:#dc2626;',
        'Regular': 'background:#dbeafe;color:#1d4ed8;',
        'Agent':   'background:#f3e8ff;color:#7c3aed;',
        'Direct':  'background:#fef9c3;color:#b45309;',
    };
    var style = colors[status] || 'background:#f1f5f9;color:#475569;';
    return '<span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;' + style + '">' + status + '</span>';
}

/* ── Show loader inside tbody ── */
function showLoader(tbodyId, cols) {
    $('#' + tbodyId).html(
        '<tr><td colspan="' + cols + '" class="text-center py-4">' +
        '<div class="spinner-border spinner-border-sm text-primary me-2"></div>' +
        '<span style="color:#64748b;font-size:13px;">Loading...</span></td></tr>'
    );
}

/* ── Show empty state ── */
function showEmpty(tbodyId, cols, msg) {
    msg = msg || 'No records found.';
    $('#' + tbodyId).html(
        '<tr><td colspan="' + cols + '" class="text-center py-4" style="color:#94a3b8;font-size:13px;">' +
        '<i class="ri-inbox-line me-2"></i>' + msg + '</td></tr>'
    );
}

/* ══════════════════════════════════════════
   POPULATE DROPDOWNS ON PAGE LOAD
══════════════════════════════════════════ */

/* Parties dropdown */
function loadPartyDropdown(selectId) {
    $.get(CODE_PHP, { action: 'party_list' }, function (res) {
        if (!res.success) return;
        var $sel = $('#' + selectId).empty().append('<option value="">-- Select Party --</option>');
        res.data.forEach(function (p) {
            $sel.append('<option value="' + p.id + '" data-city="' + p.city + '" data-phone="' + p.phone + '">' + p.party_name + '</option>');
        });
        if (typeof $sel.select2 === 'function') $sel.select2({ placeholder: 'Search party...', allowClear: true, width: '100%' });
    });
}

/* Owners dropdown */
function loadOwnerDropdown(selectId) {
    $.get(CODE_PHP, { action: 'owner_list' }, function (res) {
        if (!res.success) return;
        var $sel = $('#' + selectId).empty().append('<option value="">-- Select Owner --</option>');
        res.data.forEach(function (o) {
            $sel.append('<option value="' + o.id + '" data-city="' + o.city + '" data-phone="' + o.phone + '">' + o.owner_name + '</option>');
        });
        if (typeof $sel.select2 === 'function') $sel.select2({ placeholder: 'Search owner...', allowClear: true, width: '100%' });
    });
}

/* Vehicles dropdown */
function loadVehicleDropdown(selectId) {
    $.get(CODE_PHP, { action: 'vehicle_list' }, function (res) {
        if (!res.success) return;
        var $sel = $('#' + selectId).empty().append('<option value="">All Vehicles</option>');
        res.data.forEach(function (v) {
            $sel.append('<option value="' + v.id + '">' + v.vehicle_no + ' (' + (v.owner_name || '') + ')</option>');
        });
        if (typeof $sel.select2 === 'function') $sel.select2({ placeholder: 'Select vehicle...', allowClear: true, width: '100%' });
    });
}

/* Agents dropdown */
function loadAgentDropdown(selectId) {
    $.get(CODE_PHP, { action: 'agent_list' }, function (res) {
        if (!res.success) return;
        var $sel = $('#' + selectId).empty().append('<option value="">All Agents</option>');
        res.data.forEach(function (a) {
            $sel.append('<option value="' + a.id + '">' + a.agent_name + '</option>');
        });
        if (typeof $sel.select2 === 'function') $sel.select2({ placeholder: 'Select agent...', allowClear: true, width: '100%' });
    });
}


/* ══════════════════════════════════════════
   1.  TRIP REPORT
   Call: loadTripReport({ from_date, to_date, trip_type, party_id, vehicle_id })
   Renders into: #tripReportTbody  |  DataTable: #tripReportTable
══════════════════════════════════════════ */
function loadTripReport(filters) {
    showLoader('reportTbody', 12);

    var params = $.extend({ action: 'trip_report' }, filters);

    $.get(CODE_PHP, params, function (res) {
        if (!res.success) { showEmpty('reportTbody', 12, res.message || 'Error loading data.'); return; }

        var html = '';
        res.data.forEach(function (r, i) {
            var profit = parseFloat(r.profit) || 0;
            html +=
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + fmtDate(r.trip_date) + '</td>' +
                '<td><strong>' + (r.lr_no || '-') + '</strong></td>' +
                '<td>' + statusBadge(r.trip_type) + '</td>' +
                '<td>' + (r.party_name   || '-') + '</td>' +
                '<td>' + (r.vehicle_no   || '-') + '</td>' +
                '<td>' + (r.from_city    || '-') + '</td>' +
                '<td>' + (r.to_city      || '-') + '</td>' +
                '<td class="text-end">' + fmtRs(r.party_freight) + '</td>' +
                '<td class="text-end">' + fmtRs(r.owner_freight) + '</td>' +
                '<td class="text-end ' + (profit >= 0 ? 'text-success' : 'text-danger') + '"><strong>' + fmtRs(profit) + '</strong></td>' +
                '<td>' + statusBadge(r.status || '-') + '</td>' +
                '</tr>';
        });

        if (!html) { showEmpty('reportTbody', 12); return; }
        $('#reportTbody').html(html);

        // Summary cards
        var s = res.summary;
        $('#cardTotal').text(s.total_trips);
        $('#cardFreight').text(fmtRs(s.total_freight));
        $('#cardOwner').text(fmtRs(s.total_owner));
        $('#cardProfit').text(fmtRs(s.total_profit));

        // Footer
        $('#footParty').text(fmtRs(s.total_freight));
        $('#footOwner').text(fmtRs(s.total_owner));
        $('#footProfit').text(fmtRs(s.total_profit));

        // Redraw DataTable if exists
        if ($.fn.DataTable.isDataTable('#tripReportTable')) {
            $('#tripReportTable').DataTable().destroy();
        }
        $('#tripReportTable').DataTable({
            pageLength: 25,
            order: [[1, 'desc']],
            language: { emptyTable: 'No trips found.' }
        });

    }).fail(function () { showEmpty('reportTbody', 12, 'Server error. Please try again.'); });
}


/* ══════════════════════════════════════════
   2.  BILL PAYMENTS
   Call: loadBillPayments({ from_date, to_date, party_id, payment_mode })
══════════════════════════════════════════ */
function loadBillPayments(filters) {
    showLoader('tblBillPayBody', 10);

    $.get(CODE_PHP, $.extend({ action: 'bill_payments' }, filters), function (res) {
        if (!res.success) { showEmpty('tblBillPayBody', 10, res.message); return; }

        var html = '';
        res.data.forEach(function (r, i) {
            html +=
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + fmtDate(r.payment_date) + '</td>' +
                '<td><strong>' + (r.bill_no || '-') + '</strong></td>' +
                '<td>' + (r.party_name || '-') + '</td>' +
                '<td class="text-end">' + fmtRs(r.bill_amount) + '</td>' +
                '<td class="text-end text-success"><strong>' + fmtRs(r.paid_amount) + '</strong></td>' +
                '<td class="text-end text-danger">' + fmtRs(r.balance) + '</td>' +
                '<td>' + (r.payment_mode || '-') + '</td>' +
                '<td>' + (r.reference_no || '-') + '</td>' +
                '<td>' + statusBadge(r.status) + '</td>' +
                '</tr>';
        });

        if (!html) { showEmpty('tblBillPayBody', 10); return; }
        $('#tblBillPayBody').html(html);

        // Totals
        var s = res.summary;
        $('#bpBillAmt').text(fmtRs(s.total_billed));
        $('#bpPaidAmt').text(fmtRs(s.total_paid));
        $('#bpBalance').text(fmtRs(s.total_balance));

        if ($.fn.DataTable.isDataTable('#tblBillPay')) $('#tblBillPay').DataTable().destroy();
        $('#tblBillPay').DataTable({ pageLength: 25, order: [[1, 'desc']], language: { emptyTable: 'No payments found.' } });

    }).fail(function () { showEmpty('tblBillPayBody', 10, 'Server error.'); });
}


/* ══════════════════════════════════════════
   3.  OWNER PAYMENTS
   Call: loadOwnerPayments({ from_date, to_date, owner_id, vehicle_id, payment_mode })
══════════════════════════════════════════ */
function loadOwnerPayments(filters) {
    showLoader('tblOwnerPayBody', 10);

    $.get(CODE_PHP, $.extend({ action: 'owner_payments' }, filters), function (res) {
        if (!res.success) { showEmpty('tblOwnerPayBody', 10, res.message); return; }

        var html = '';
        res.data.forEach(function (r, i) {
            html +=
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + fmtDate(r.payment_date) + '</td>' +
                '<td>' + (r.owner_name  || '-') + '</td>' +
                '<td>' + (r.vehicle_no  || '-') + '</td>' +
                '<td>' + (r.lr_no       || '-') + '</td>' +
                '<td class="text-end">' + fmtRs(r.owner_freight)  + '</td>' +
                '<td class="text-end">' + fmtRs(r.advance_amount) + '</td>' +
                '<td class="text-end text-success"><strong>' + fmtRs(r.paid_amount) + '</strong></td>' +
                '<td class="text-end text-danger">' + fmtRs(r.balance) + '</td>' +
                '<td>' + (r.payment_mode || '-') + '</td>' +
                '</tr>';
        });

        if (!html) { showEmpty('tblOwnerPayBody', 10); return; }
        $('#tblOwnerPayBody').html(html);

        var s = res.summary;
        $('#opFreight').text(fmtRs(s.total_freight));
        $('#opAdvance').text(fmtRs(s.total_advance));
        $('#opPaid').text(fmtRs(s.total_paid));
        $('#opBalance').text(fmtRs(s.total_balance));

        if ($.fn.DataTable.isDataTable('#tblOwnerPay')) $('#tblOwnerPay').DataTable().destroy();
        $('#tblOwnerPay').DataTable({ pageLength: 25, order: [[1, 'desc']] });

    }).fail(function () { showEmpty('tblOwnerPayBody', 10, 'Server error.'); });
}


/* ══════════════════════════════════════════
   4.  AGENT PAYMENTS
   Call: loadAgentPayments({ from_date, to_date, agent_id, payment_mode })
══════════════════════════════════════════ */
function loadAgentPayments(filters) {
    showLoader('tblAgentPayBody', 9);

    $.get(CODE_PHP, $.extend({ action: 'agent_payments' }, filters), function (res) {
        if (!res.success) { showEmpty('tblAgentPayBody', 9, res.message); return; }

        var html = '';
        res.data.forEach(function (r, i) {
            html +=
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + fmtDate(r.payment_date) + '</td>' +
                '<td>' + (r.agent_name  || '-') + '</td>' +
                '<td>' + (r.lr_no       || '-') + '</td>' +
                '<td class="text-end">' + fmtRs(r.commission_amount) + '</td>' +
                '<td class="text-end text-success"><strong>' + fmtRs(r.paid_amount) + '</strong></td>' +
                '<td class="text-end text-danger">' + fmtRs(r.balance) + '</td>' +
                '<td>' + (r.payment_mode || '-') + '</td>' +
                '<td>' + (r.reference_no || '-') + '</td>' +
                '</tr>';
        });

        if (!html) { showEmpty('tblAgentPayBody', 9); return; }
        $('#tblAgentPayBody').html(html);

        var s = res.summary;
        $('#apComm').text(fmtRs(s.total_commission));
        $('#apPaid').text(fmtRs(s.total_paid));
        $('#apBalance').text(fmtRs(s.total_balance));

        if ($.fn.DataTable.isDataTable('#tblAgentPay')) $('#tblAgentPay').DataTable().destroy();
        $('#tblAgentPay').DataTable({ pageLength: 25, order: [[1, 'desc']] });

    }).fail(function () { showEmpty('tblAgentPayBody', 9, 'Server error.'); });
}


/* ══════════════════════════════════════════
   5.  PARTY LEDGER
   Call: loadPartyLedger(partyId, fromDate, toDate)
══════════════════════════════════════════ */
function loadPartyLedger(partyId, fromDate, toDate) {
    if (!partyId) return;
    showLoader('ledgerTbody', 8);

    $.get(CODE_PHP, {
        action    : 'party_ledger',
        party_id  : partyId,
        from_date : fromDate || '',
        to_date   : toDate   || ''
    }, function (res) {
        if (!res.success) { showEmpty('ledgerTbody', 8, res.message); return; }

        var html = '';
        res.data.forEach(function (r, i) {
            var balClass = r.running_balance > 0 ? 'text-danger' : (r.running_balance < 0 ? 'text-success' : '');
            html +=
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + fmtDate(r.txn_date) + '</td>' +
                '<td>' + (r.particulars || '-') + '</td>' +
                '<td><strong>' + (r.ref_no || '-') + '</strong></td>' +
                '<td>' + (r.vehicle_no || '-') + '</td>' +
                '<td class="text-end text-danger">' + (r.debit  > 0 ? fmtRs(r.debit)  : '-') + '</td>' +
                '<td class="text-end text-success">' + (r.credit > 0 ? fmtRs(r.credit) : '-') + '</td>' +
                '<td class="text-end ' + balClass + '"><strong>' + fmtRs(r.running_balance) + '</strong></td>' +
                '</tr>';
        });

        if (!html) { showEmpty('ledgerTbody', 8, 'No transactions found for this party.'); return; }
        $('#ledgerTbody').html(html);

        // Footer
        var s = res.summary;
        $('#lfDebit').text(fmtRs(s.total_debit));
        $('#lfCredit').text(fmtRs(s.total_credit));
        $('#lfBalance').text(fmtRs(s.closing_balance));

        // Info bar
        var p = s.party;
        $('#piName').text(p.party_name);
        $('#piCity').text(p.city || '--');
        $('#piPhone').text(p.phone || '--');
        $('#piBalance').text(fmtRs(s.closing_balance));
        $('#piBalance').css('color', s.closing_balance > 0 ? '#ef4444' : '#16a34a');
        $('#partyInfoBar').removeClass('d-none');

        // Summary cards
        $('#lsTrips').text(s.total_txns);
        $('#lsBilled').text(fmtRs(s.total_debit));
        $('#lsReceived').text(fmtRs(s.total_credit));
        $('#lsBalance').text(fmtRs(s.closing_balance));
        $('#ledgerSummary').show();

    }).fail(function () { showEmpty('ledgerTbody', 8, 'Server error.'); });
}


/* ══════════════════════════════════════════
   6.  OWNER LEDGER
   Call: loadOwnerLedger(ownerId, fromDate, toDate)
══════════════════════════════════════════ */
function loadOwnerLedger(ownerId, fromDate, toDate) {
    if (!ownerId) return;
    showLoader('ownerLedgerTbody', 10);

    $.get(CODE_PHP, {
        action   : 'owner_ledger',
        owner_id : ownerId,
        from_date: fromDate || '',
        to_date  : toDate   || ''
    }, function (res) {
        if (!res.success) { showEmpty('ownerLedgerTbody', 10, res.message); return; }

        var html = '';
        res.data.forEach(function (r, i) {
            var balClass = r.running_balance > 0 ? 'text-danger' : (r.running_balance < 0 ? 'text-success' : '');
            html +=
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + fmtDate(r.txn_date) + '</td>' +
                '<td>' + (r.particulars || '-') + '</td>' +
                '<td><strong>' + (r.ref_no     || '-') + '</strong></td>' +
                '<td>' + (r.vehicle_no  || '-') + '</td>' +
                '<td>' + (r.particulars || '-') + '</td>' +
                '<td class="text-end text-danger">'  + (r.debit            > 0 ? fmtRs(r.debit)           : '-') + '</td>' +
                '<td class="text-end text-warning">' + (r.credit_advance   > 0 ? fmtRs(r.credit_advance)  : '-') + '</td>' +
                '<td class="text-end text-success">' + (r.credit_payment   > 0 ? fmtRs(r.credit_payment)  : '-') + '</td>' +
                '<td class="text-end ' + balClass + '"><strong>' + fmtRs(r.running_balance) + '</strong></td>' +
                '</tr>';
        });

        if (!html) { showEmpty('ownerLedgerTbody', 10, 'No transactions found for this owner.'); return; }
        $('#ownerLedgerTbody').html(html);

        var s = res.summary;
        $('#olfFreight').text(fmtRs(s.total_freight));
        $('#olfAdvance').text(fmtRs(s.total_advance));
        $('#olfPayment').text(fmtRs(s.total_paid));
        $('#olfBalance').text(fmtRs(s.closing_balance));

        // Info bar
        var o = s.owner;
        $('#oiName').text(o.owner_name);
        $('#oiCity').text(o.city   || '--');
        $('#oiPhone').text(o.phone || '--');
        $('#oiBalance').text(fmtRs(s.closing_balance));
        $('#oiBalance').css('color', s.closing_balance > 0 ? '#7c3aed' : '#16a34a');
        $('#ownerInfoBar').removeClass('d-none');

        // Summary cards
        $('#olTrips').text(s.total_txns);
        $('#olFreight').text(fmtRs(s.total_freight));
        $('#olPaid').text(fmtRs(s.total_paid));
        $('#olBalance').text(fmtRs(s.closing_balance));
        $('#ownerLedgerSummary').show();

    }).fail(function () { showEmpty('ownerLedgerTbody', 10, 'Server error.'); });
}
