<?php
include("../config/db.php");
$conn = getDBConnection();
include("../includes/auth.php");
require_once("../includes/app_helper.php");
require_once("../includes/report_summary_helper.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

$summaryData = get_reports_summary_data($conn);
$generatedAt = !empty($summaryData['generated_at'])
    ? date('d M Y, h:i A', strtotime($summaryData['generated_at']))
    : date('d M Y, h:i A');

$adherenceText = $summaryData['sla_adherence_rate'] === null
    ? 'No resolved/closed complaints available yet'
    : $summaryData['sla_adherence_rate'] . '%';

$tcpdfPath = 'C:/xampp/phpMyAdmin/vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    http_response_code(500);
    exit('PDF library not available.');
}
require_once $tcpdfPath;

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(app_name());
$pdf->SetAuthor(app_name());
$pdf->SetTitle('Reports & Analytics Summary');
$pdf->SetMargins(14, 12, 14);
$pdf->SetAutoPageBreak(true, 12);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

$html = '
<h1 style="text-align:center; font-size:16px; font-weight:bold;">' . htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') . '</h1>
<p style="text-align:center; font-size:11px; font-weight:bold;">Reports &amp; Analytics Summary</p>
<p style="text-align:right; font-size:8px;"><strong>Generated:</strong> ' . htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') . '</p>
<hr>
';

if (!$summaryData['has_data']) {
    $html .= '<p>No complaint data available.</p>';
} else {
    $html .= '
    <h3 style="font-size:11px; font-weight:bold;">Summary Metrics</h3>
    <table cellpadding="5" border="1">
        <tr>
            <th width="35%"><strong>Metric</strong></th>
            <th width="25%"><strong>Value</strong></th>
            <th width="40%"><strong>Remarks</strong></th>
        </tr>
        <tr>
            <td>Total Complaints</td>
            <td align="center">' . (int)$summaryData['total_complaints'] . '</td>
            <td>Total complaint records currently available in the system.</td>
        </tr>
        <tr>
            <td>Resolved / Closed Complaints</td>
            <td align="center">' . (int)$summaryData['resolved_closed'] . '</td>
            <td>Only resolved or closed complaints contribute to final SLA adherence calculations.</td>
        </tr>
        <tr>
            <td>SLA Adherence Rate</td>
            <td>' . htmlspecialchars($adherenceText, ENT_QUOTES, 'UTF-8') . '</td>
            <td>Calculated from resolved or closed complaints against their resolution SLA deadlines.</td>
        </tr>
        <tr>
            <td>Complaints Within SLA</td>
            <td align="center">' . (int)$summaryData['complaints_within_sla'] . '</td>
            <td>Total complaints resolved within allowed SLA time.</td>
        </tr>
        <tr>
            <td>SLA Breached Complaints</td>
            <td align="center">' . (int)$summaryData['sla_breached_complaints'] . '</td>
            <td>Total complaints overdue or resolved after SLA.</td>
        </tr>
    </table>

    <br><br>

    <h3 style="font-size:11px; font-weight:bold;">Category Breakdown</h3>
    ';

    if (empty($summaryData['category_stats'])) {
        $html .= '<p>No category performance data available yet.</p>';
    } else {
        $html .= '
        <table cellpadding="5" border="1">
            <tr>
                <th width="50%"><strong>Category</strong></th>
                <th width="25%"><strong>Avg Resolution Time (Hours)</strong></th>
                <th width="25%"><strong>Complaint Count</strong></th>
            </tr>
        ';

        foreach ($summaryData['category_stats'] as $row) {
            $html .= '
            <tr>
                <td>' . htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8') . '</td>
                <td align="center">' . htmlspecialchars($row['avg_hours'] ?? '0', ENT_QUOTES, 'UTF-8') . '</td>
                <td align="center">' . (int)$row['total_complaints'] . '</td>
            </tr>
            ';
        }

        $html .= '</table>';
    }
}

$html .= '
<br><br>
<p style="text-align:center; font-size:7px; color:#666;">
    System-generated report using live MySQL data for analytics and operational review.
</p>';

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('reports_analytics_summary.pdf', 'I');
exit;
