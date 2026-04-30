<?php
include("../config/db.php");
$conn = getDBConnection();
include("../includes/auth.php");
require_once("../includes/app_helper.php");
require_once("../includes/sla_report_helper.php");
require_once("../includes/sla_escalation.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

run_sla_escalation($conn);
$reportRows = get_live_sla_report_rows($conn);
$generatedAt = date('d M Y, h:i A');

$tcpdfPath = 'C:/xampp/phpMyAdmin/vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    http_response_code(500);
    exit('PDF library not available.');
}
require_once $tcpdfPath;

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(app_name());
$pdf->SetAuthor(app_name());
$pdf->SetTitle('Live SLA Compliance Report');
$pdf->SetMargins(14, 12, 14);
$pdf->SetAutoPageBreak(true, 12);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

$html = '
<h1 style="text-align:center; font-size:16px; font-weight:bold;">' . htmlspecialchars(app_name(), ENT_QUOTES, 'UTF-8') . '</h1>
<p style="text-align:center; font-size:11px; font-weight:bold;">Live SLA Compliance Report</p>
<p style="text-align:right; font-size:8px;"><strong>Generated:</strong> ' . htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') . '</p>
<hr>
';

if (empty($reportRows)) {
    $html .= '<p>No SLA complaint data available.</p>';
} else {
    foreach ($reportRows as $row) {
        $html .= '
        <h3 style="font-size:11px; font-weight:bold;">Complaint #' . (int)$row['complaint_id'] . '</h3>

        <table cellpadding="4" border="0" width="100%">
            <tr><td width="20%"><strong>Title</strong></td><td width="80%">' . htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Description</strong></td><td>' . htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Priority</strong></td><td>' . htmlspecialchars($row['priority'], ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Complaint Status</strong></td><td>' . htmlspecialchars($row['status_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Overall SLA</strong></td><td>' . htmlspecialchars($row['overall_sla_badge'], ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Category</strong></td><td>' . htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Location</strong></td><td>' . htmlspecialchars($row['campus'], ENT_QUOTES, 'UTF-8') . ' / ' . htmlspecialchars($row['building'], ENT_QUOTES, 'UTF-8') . ' / ' . htmlspecialchars($row['spot'], ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Created</strong></td><td>' . htmlspecialchars(date('d M Y, h:i A', strtotime($row['created_at'])), ENT_QUOTES, 'UTF-8') . '</td></tr>
            <tr><td><strong>Assigned Staff</strong></td><td>' . htmlspecialchars($row['assigned_staff'], ENT_QUOTES, 'UTF-8') . '</td></tr>
        </table>

        <br>

        <table cellpadding="5" border="1" width="100%">
            <tr>
                <th width="50%"><strong>Initial SLA</strong></th>
                <th width="50%"><strong>Resolution SLA</strong></th>
            </tr>
            <tr>
                <td>
                    Start: ' . htmlspecialchars(date('d M Y, h:i A', strtotime($row['initial_sla_start'])), ENT_QUOTES, 'UTF-8') . '<br>
                    Deadline: ' . htmlspecialchars(date('d M Y, h:i A', strtotime($row['initial_sla_deadline'])), ENT_QUOTES, 'UTF-8') . '<br>
                    Timer: ' . htmlspecialchars($row['initial_timer']['label'], ENT_QUOTES, 'UTF-8') . '<br>
                    Status: ' . htmlspecialchars($row['initial_status'], ENT_QUOTES, 'UTF-8') . '
                </td>
                <td>
                    Start: ' . htmlspecialchars(date('d M Y, h:i A', strtotime($row['resolution_sla_start'])), ENT_QUOTES, 'UTF-8') . '<br>
                    Deadline: ' . htmlspecialchars(date('d M Y, h:i A', strtotime($row['resolution_sla_deadline'])), ENT_QUOTES, 'UTF-8') . '<br>
                    Timer: ' . htmlspecialchars($row['resolution_timer']['label'], ENT_QUOTES, 'UTF-8') . '<br>
                    Status: ' . htmlspecialchars($row['resolution_status'], ENT_QUOTES, 'UTF-8') . '
                </td>
            </tr>
        </table>

        <p style="text-align:right; font-size:7px; color:#666;">
            Current Time: ' . htmlspecialchars(date('d M Y, h:i:s A'), ENT_QUOTES, 'UTF-8') . '
        </p>

        <hr>
        ';
    }
}

$html .= '
<p style="text-align:center; font-size:7px; color:#666;">
    System-generated report using live MySQL data for SLA monitoring and compliance review.
</p>';

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('live_sla_compliance_report.pdf', 'I');
exit;
