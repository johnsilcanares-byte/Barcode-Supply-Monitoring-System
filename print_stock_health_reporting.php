<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();

// ========== INCLUDE CONFIG AND PDF LIBRARY ==========
$configPath = 'config.php';
if (!file_exists($configPath)) {
    die("ERROR: config.php not found at: " . realpath($configPath) . "<br>Current script: " . __FILE__);
}
$pdfLibPath = './pdflib/logics-builder-pdf.php';
if (!file_exists($pdfLibPath)) {
    die("ERROR: pdflib/logics-builder-pdf.php not found at: " . realpath($pdfLibPath) . "<br>Current script: " . __FILE__);
}

require_once $configPath;
require_once $pdfLibPath;

if (!class_exists('LB_PDF')) {
    die('LB_PDF class not found! Make sure pdflib/logics-builder-pdf.php is correct.');
}

// ========== DATABASE CONNECTION DETECTION ==========
$dbConnection = null;
if (isset($con) && $con instanceof PDO) {
    $dbConnection = $con;
} elseif (isset($db) && $db instanceof PDO) {
    $dbConnection = $db;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $dbConnection = $pdo;
} else {
    foreach ($GLOBALS as $key => $value) {
        if ($value instanceof PDO) {
            $dbConnection = $value;
            break;
        }
    }
}

if (!$dbConnection) {
    die("ERROR: No PDO database connection found. Check your config.php.");
}

$con = $dbConnection;

// ========== DATA FUNCTIONS (unchanged) ==========
function getInventoryKPIs($db) {
    try {
        $totalItems = $db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    } catch (PDOException $e) { $totalItems = 0; }
    try {
        $totalStock = $db->query("SELECT SUM(stock) FROM inventory")->fetchColumn() ?: 0;
    } catch (PDOException $e) { $totalStock = 0; }
    try {
        $inventoryValue = $db->query("SELECT SUM(price * stock) FROM inventory")->fetchColumn() ?: 0;
    } catch (PDOException $e) { $inventoryValue = 0; }
    try {
        $outOfStock = $db->query("SELECT COUNT(*) FROM inventory WHERE stock = 0")->fetchColumn();
    } catch (PDOException $e) { $outOfStock = 0; }
    try {
        $lowStock = $db->query("SELECT COUNT(*) FROM inventory WHERE stock > 0 AND stock < 10")->fetchColumn();
    } catch (PDOException $e) { $lowStock = 0; }
    try {
        $healthyStock = $db->query("SELECT COUNT(*) FROM inventory WHERE stock >= 10")->fetchColumn();
    } catch (PDOException $e) { $healthyStock = 0; }
    $expiringSoon = 0;
    try {
        $expiringSoon = $db->query("SELECT COUNT(*) FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (Exception $e) { $expiringSoon = 0; }
    $avgStock = ($totalItems > 0) ? round($totalStock / $totalItems, 1) : 0;
    $recentAdjustments = 0;
    try {
        $recentAdjustments = $db->query("SELECT COUNT(*) FROM stock_adjustments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    } catch (Exception $e) { $recentAdjustments = 0; }
    return compact('totalItems', 'totalStock', 'inventoryValue', 'outOfStock', 'lowStock', 'healthyStock', 'expiringSoon', 'avgStock', 'recentAdjustments');
}

function getStockLevelDistribution($db) {
    try {
        return $db->query("
            SELECT 
                CASE 
                    WHEN stock = 0 THEN 'Out of Stock'
                    WHEN stock < 10 THEN 'Low Stock (<10)'
                    WHEN stock < 50 THEN 'Medium Stock (10-49)'
                    ELSE 'Well Stocked (50+)'
                END as level,
                COUNT(*) as count,
                SUM(stock) as total_units,
                ROUND(SUM(price * stock), 2) as total_value
            FROM inventory
            GROUP BY level
            ORDER BY 
                CASE level
                    WHEN 'Out of Stock' THEN 1
                    WHEN 'Low Stock (<10)' THEN 2
                    WHEN 'Medium Stock (10-49)' THEN 3
                    ELSE 4
                END
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getRestockItems($db) {
    try {
        return $db->query("
            SELECT id, name, barcode, stock, price, (price * stock) as stock_value
            FROM inventory 
            WHERE stock < 10 
            ORDER BY stock ASC, name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function getExpiringItems($db) {
    try {
        return $db->query("
            SELECT id, name, barcode, stock, expiration_date, DATEDIFF(expiration_date, CURDATE()) as days_left
            FROM inventory 
            WHERE expiration_date IS NOT NULL AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
            ORDER BY expiration_date ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function getFullInventory($db, $sortBy = 'name', $sortOrder = 'ASC') {
    $allowed = ['name', 'stock', 'price', 'barcode'];
    $sortBy = in_array($sortBy, $allowed) ? $sortBy : 'name';
    $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
    try {
        return $db->query("
            SELECT id, name, barcode, stock, price, (price * stock) as stock_value,
                   CASE 
                       WHEN stock = 0 THEN 'Out of Stock'
                       WHEN stock < 10 THEN 'Low Stock'
                       WHEN stock < 50 THEN 'Medium Stock'
                       ELSE 'Well Stocked'
                   END as status
            FROM inventory 
            ORDER BY $sortBy $sortOrder
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

function getAdjustmentHistory($db, $limit = 50) {
    try {
        return $db->query("
            SELECT sa.*, i.name as item_name, i.barcode, u.username
            FROM stock_adjustments sa
            JOIN inventory i ON sa.item_id = i.id
            JOIN users u ON sa.adjusted_by = u.id
            ORDER BY sa.created_at DESC
            LIMIT $limit
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

// ========== FETCH ALL DATA ==========
$kpi = getInventoryKPIs($con);
$distribution = getStockLevelDistribution($con);
$restockItems = getRestockItems($con);
$expiringItems = getExpiringItems($con);
$fullInventory = getFullInventory($con);
$adjustmentHistory = getAdjustmentHistory($con);

// ========== CUSTOM PDF CLASS ==========
class StockReportPDF extends LB_PDF {
    public function Header() {
        $y = 12; // base Y for logos and text

        // ----- LEFT LOGO -----
        $leftLogo = './images/debesmscat.png';
        if (file_exists($leftLogo)) {
            $this->Image($leftLogo, 15, $y, 25);
        }

        // ----- RIGHT LOGO -----
        $rightLogo = './images/Bagong_pilipinas.png';
        if (file_exists($rightLogo)) {
            $this->Image($rightLogo, 170, $y, 25);
        }

        // ----- CENTERED TEXT (side‑by‑side with logos) -----
        $this->SetY($y + 4);           // start slightly below top of logos
        $this->SetX(45);               // after left logo + margin
        $w = 125;                      // width between logos
        $this->SetFont('Arial', 'B', 15);
        $this->Cell($w, 6, 'DEBESMSCAT', 0, 1, 'C');
        $this->SetX(45);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell($w, 6, 'Supply Monitoring System', 0, 1, 'C');
        $this->SetX(45);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell($w, 6, 'Stock Health Report', 0, 1, 'C');
        $this->SetX(45);
        $this->SetFont('Arial', '', 9);
        $this->Cell($w, 5, 'Generated on ' . date('F d, Y H:i'), 0, 1, 'C');

        // ---- Underline (full width) ----
        $this->SetY($y + 28);
        $this->Cell(0, 0, '', 'T', 1);
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }

    // Auto‑wrap table row
    function Row($data, $widths) {
        $nb = 0;
        for ($i=0; $i<count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }
        $h = 6 * $nb;
        $this->CheckPageBreak($h);
        for ($i=0; $i<count($data); $i++) {
            $w = $widths[$i];
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 6, $data[$i], 0, 'L');
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage();
        }
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

// ================= GENERATE PDF =================
try {
    $pdf = new StockReportPDF('P', 'mm', 'A4', '', 'Stock Health Report');
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // ====== 1. OVERVIEW ======
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, '1. Overview Inventory Summary', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

// ----- KPI HEADER (light green) -----
$pdf->SetFillColor(200, 255, 200); // <-- light green

$w_kpi = [30, 30, 30, 30, 30, 30];
$header_kpi = ['Total Items', 'Total Units', 'Avg Stock', 'Low Stock', 'Out of Stock', 'Expiring Soon'];
$data_kpi = [
    $kpi['totalItems'],
    $kpi['totalStock'],
    $kpi['avgStock'],
    $kpi['lowStock'],
    $kpi['outOfStock'],
    $kpi['expiringSoon']
];

$pdf->SetFont('Arial', 'B', 9);
foreach ($header_kpi as $i => $col) {
    $pdf->Cell($w_kpi[$i], 8, $col, 1, 0, 'C', true);
}
$pdf->Ln();
$pdf->SetFont('Arial', '', 9);
foreach ($data_kpi as $i => $val) {
    $pdf->Cell($w_kpi[$i], 8, $val, 1, 0, 'C');
}
$pdf->Ln();
$pdf->Ln(4);

// ----- Stock Distribution table -----
if (!empty($distribution)) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Stock Level Distribution', 0, 1, 'L');
    $pdf->SetFont('Arial', 'B', 9);

    // Set light green for this header too
    $pdf->SetFillColor(200, 255, 200); // <-- light green

    $w_dist = [45, 25, 35, 40, 35];
    $header_dist = ['Level', 'Items', 'Units', 'Value (₱)', '% of Items'];
    foreach ($header_dist as $i => $col) {
        $pdf->Cell($w_dist[$i], 8, $col, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 9);
    $totalItems = $kpi['totalItems'];
    foreach ($distribution as $row) {
        $pct = $totalItems > 0 ? round(($row['count'] / $totalItems) * 100, 1) : 0;
        $pdf->Row([
            $row['level'],
            $row['count'],
            number_format($row['total_units']),
            number_format($row['total_value'], 2),
            $pct . '%'
        ], $w_dist);
    }
    $pdf->Ln(4);
}

    // Additional insights
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, 'Quick Insights', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $insights = [
        'Healthy Stock (>10): ' . $kpi['healthyStock'] . ' items',
        'Needs Restock: ' . ($kpi['lowStock'] + $kpi['outOfStock']) . ' items',
        'Expiring within 30 days: ' . $kpi['expiringSoon'] . ' items',
        'Recent Adjustments (30d): ' . $kpi['recentAdjustments'] . ' changes'
    ];
    foreach ($insights as $line) {
        $pdf->Cell(0, 6, '' . $line, 0, 1, 'L');
    }
    $pdf->SetFillColor(200, 255, 200);
    $pdf->Ln(6);

    // ====== 2. RESTOCK NEEDED ======
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, '2. Restock Needed (Stock < 10)', 0, 1, 'L');
    if (!empty($restockItems)) {
        $pdf->SetFont('Arial', 'B', 9);
        $w_r = [12, 50, 30, 20, 30, 38];
        $header_r = ['#', 'Item Name', 'Barcode', 'Stock', 'Price', 'Stock Value'];
        $pdf->SetFillColor(200, 255, 200);
        foreach ($header_r as $i => $col) {
            $pdf->Cell($w_r[$i], 8, $col, 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 9);
        $count = 0;
        foreach ($restockItems as $item) {
            $count++;
            $name = @iconv('UTF-8', 'windows-1252//TRANSLIT', $item['name']) ?: $item['name'];
            $barcode = @iconv('UTF-8', 'windows-1252//TRANSLIT', $item['barcode'] ?? '—') ?: ($item['barcode'] ?? '—');
            $pdf->Row([
                $count,
                $name,
                $barcode,
                $item['stock'],
                number_format($item['price'], 2),
                number_format($item['stock_value'], 2)
            ], $w_r);
        }
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, 'No low‑stock items found. All items are well stocked.', 0, 1, 'L');
        $pdf->Ln(4);
    }

    // ====== 3. EXPIRING SOON ======
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, '3. Expiring Soon (within 60 days)', 0, 1, 'L');
    if (!empty($expiringItems)) {
        $pdf->SetFont('Arial', 'B', 9);
        $w_e = [12, 45, 30, 20, 35, 28];
        $header_e = ['#', 'Item Name', 'Barcode', 'Stock', 'Expiration', 'Days Left'];
        foreach ($header_e as $i => $col) {
            $pdf->Cell($w_e[$i], 8, $col, 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 9);
        $count = 0;
        foreach ($expiringItems as $item) {
            $count++;
            $name = @iconv('UTF-8', 'windows-1252//TRANSLIT', $item['name']) ?: $item['name'];
            $barcode = @iconv('UTF-8', 'windows-1252//TRANSLIT', $item['barcode'] ?? '—') ?: ($item['barcode'] ?? '—');
            $exp = date('Y-m-d', strtotime($item['expiration_date']));
            $days = $item['days_left'] . ' days';
            $pdf->Row([
                $count,
                $name,
                $barcode,
                $item['stock'],
                $exp,
                $days
            ], $w_e);
        }
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, 'No expiring items found.', 0, 1, 'L');
        $pdf->Ln(4);
    }

    // ====== 4. FULL INVENTORY ======
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, '4. Full Inventory List', 0, 1, 'L');
    if (!empty($fullInventory)) {
        $pdf->SetFont('Arial', 'B', 9);
        $w_f = [10, 40, 30, 18, 25, 30, 27];
        $header_f = ['#', 'Item Name', 'Barcode', 'Stock', 'Price', 'Stock Value', 'Status'];
        foreach ($header_f as $i => $col) {
            $pdf->Cell($w_f[$i], 8, $col, 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 9);
        $count = 0;
        foreach ($fullInventory as $item) {
            $count++;
            $name = @iconv('UTF-8', 'windows-1252//TRANSLIT', $item['name']) ?: $item['name'];
            $barcode = @iconv('UTF-8', 'windows-1252//TRANSLIT', $item['barcode'] ?? '—') ?: ($item['barcode'] ?? '—');
            $pdf->Row([
                $count,
                $name,
                $barcode,
                $item['stock'],
                number_format($item['price'], 2),
                number_format($item['stock_value'], 2),
                $item['status']
            ], $w_f);
        }
        $pdf->Ln(4);
        // Total footer
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(array_sum($w_f) - 40, 8, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(40, 8, number_format($kpi['totalStock']) . ' units', 1, 0, 'C');
        $pdf->Ln();
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, 'No inventory items found.', 0, 1, 'L');
        $pdf->Ln(4);
    }

    // ====== 5. ADJUSTMENT HISTORY ======
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, '5. Recent Stock Adjustments (last 50)', 0, 1, 'L');
    if (!empty($adjustmentHistory)) {
        $pdf->SetFont('Arial', 'B', 9);
        $w_a = [22, 32, 16, 16, 16, 24, 30, 24];
        $header_a = ['Date', 'Item', 'Old', 'New', 'Change', 'Type', 'Reason', 'User'];
        foreach ($header_a as $i => $col) {
            $pdf->Cell($w_a[$i], 8, $col, 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 9);
        foreach ($adjustmentHistory as $adj) {
            $date = date('M d, H:i', strtotime($adj['created_at']));
            $itemName = @iconv('UTF-8', 'windows-1252//TRANSLIT', $adj['item_name']) ?: $adj['item_name'];
            $change = ($adj['adjustment_type'] == 'increase' ? '+' : '-') . $adj['quantity'];
            $type = ucfirst($adj['adjustment_type']);
            $reason = @iconv('UTF-8', 'windows-1252//TRANSLIT', $adj['reason'] ?? '—') ?: ($adj['reason'] ?? '—');
            $username = $adj['username'];
            $pdf->Row([
                $date,
                $itemName,
                $adj['old_stock'],
                $adj['new_stock'],
                $change,
                $type,
                $reason,
                $username
            ], $w_a);
        }
        $pdf->Ln(4);
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 8, 'No adjustments recorded yet.', 0, 1, 'L');
        $pdf->Ln(4);
    }

    // ====== SIGNATURES ======
    $pdf->SetY(-45);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX(25);
    $pdf->Cell(100, 6, 'Prepared by (Inventory Manager):', 0, 1);
    $pdf->SetX(25);
    $pdf->Cell(100, 6, '_____________________________', 0, 1);
    $pdf->SetX(25);
    $pdf->Cell(100, 6, 'Name & Signature', 0, 1);

    $pdf->SetY(-45);
    $pdf->SetX(120);
    $pdf->Cell(100, 6, 'Approved by (Supervisor):', 0, 1);
    $pdf->SetX(120);
    $pdf->Cell(100, 6, '_____________________________', 0, 1);
    $pdf->SetX(120);
    $pdf->Cell(100, 6, 'Name & Signature', 0, 1);

    // ====== OUTPUT ======
    ob_end_clean();
    $pdf->Output('I', 'Stock_Health_Report.pdf');
    exit;

} catch (Exception $e) {
    ob_end_clean();
    die("Error generating PDF: " . $e->getMessage() . "<br>Stack trace: " . $e->getTraceAsString());
}
?>