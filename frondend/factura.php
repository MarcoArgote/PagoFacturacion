<?php
// Este archivo servirá como punto de entrada para la generación de facturas en PDF usando PHP
// Aquí se puede integrar una librería como FPDF o TCPDF para crear PDFs
// Por ahora, solo muestra un mensaje de prueba

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/setasign/fpdf/fpdf.php';

// Conexión a la base de datos
$mysqli = new mysqli('db', 'user', 'userpassword', 'serviciosdb');
if ($mysqli->connect_errno) {
    die('Error de conexión: ' . $mysqli->connect_error);
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$detalles = isset($_GET['detalles']);
if ($id <= 0) die('ID de factura inválido');
$res = $mysqli->query("SELECT * FROM facturas WHERE id = $id");
if (!$res || $res->num_rows === 0) die('Factura no encontrada');
$factura = $res->fetch_assoc();
$datos = json_decode($factura['datos_cliente'], true);
$res2 = $mysqli->query("SELECT s.nombre, fs.cantidad, fs.precio_unitario FROM factura_servicios fs JOIN servicios s ON fs.servicio_id = s.id WHERE fs.factura_id = $id");
$servicios = $res2->fetch_all(MYSQLI_ASSOC);
if ($detalles) {
    echo "<html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
    echo "<title>Factura #$id</title>\n";
    echo "<link href='https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap' rel='stylesheet'>\n";
    echo "<style>
    body { font-family: 'Montserrat', Arial, sans-serif; background: linear-gradient(120deg, #e0eafc 0%, #cfdef3 100%); margin:0; min-height:100vh; }
    .container { max-width: 600px; margin: 48px auto 0 auto; background: #fff; padding: 40px 32px 32px 32px; border-radius: 18px; box-shadow: 0 8px 32px #0002, 0 1.5px 8px #0001; }
    h2 { text-align: center; color: #1a237e; margin-bottom: 24px; }
    .factura-info { margin-bottom: 18px; }
    .factura-info b { color: #1a237e; }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    th, td { border: 1px solid #bdbdbd; padding: 10px; text-align: center; }
    th { background: #e3eafc; color: #1a237e; font-weight: 700; }
    tr:nth-child(even) { background: #f5f7fa; }
    .total-row th, .total-row td { font-size: 1.1rem; color: #1a237e; font-weight: 700; }
    .btn-descargar { display: inline-block; margin: 24px auto 0 auto; background: #1a237e; color: #fff; border-radius: 8px; padding: 12px 28px; font-size: 1.1rem; font-weight: 600; box-shadow: 0 2px 8px #0001; transition: background 0.2s, color 0.2s; border: none; text-decoration: none; }
    .btn-descargar:hover { background: #3949ab; color: #fff; }
    @media (max-width: 700px) { .container { padding: 18px 4vw; } th, td { font-size: 0.95rem; } }
    </style>\n";
    echo "</head><body><div class='container'>\n";
    echo "<h2>Factura #$id</h2>\n";
    echo "<div class='factura-info'>\n";
    echo "<b>Cliente:</b> " . htmlspecialchars($datos['nombre']) . "<br>";
    echo "<b>Dirección:</b> " . htmlspecialchars($datos['direccion']) . "<br>";
    echo "<b>NIT/RFC:</b> " . htmlspecialchars($datos['nit']) . "<br>";
    echo "<b>Método de Pago:</b> " . htmlspecialchars(isset($datos['metodoPago']) ? $datos['metodoPago'] : 'No especificado') . "<br>";
    echo "</div>\n";
    echo "<table><tr><th>Servicio</th><th>Cantidad</th><th>Precio</th><th>Total</th></tr>";
    $total = 0;
    foreach ($servicios as $s) {
        $t = $s['cantidad'] * $s['precio_unitario'];
        $total += $t;
        echo "<tr><td>".htmlspecialchars($s['nombre'])."</td><td>{$s['cantidad']}</td><td>Bs ".number_format($s['precio_unitario'],2)."</td><td>Bs ".number_format($t,2)."</td></tr>";
    }
    echo "<tr class='total-row'><th colspan='3'>Total</th><th>Bs " . number_format($total,2) . "</th></tr></table>";
    echo "<a href='factura.php?id=$id' class='btn-descargar'>Descargar PDF</a>";
    echo "</div></body></html>";
    exit;
}
// Generar PDF real con FPDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="factura_'.$id.'.pdf"');
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Factura #'.$id,0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Cliente: '.$datos['nombre'],0,1);
$pdf->Cell(0,8,'Direccion: '.$datos['direccion'],0,1);
$pdf->Cell(0,8,'NIT/RFC: '.$datos['nit'],0,1);
$pdf->Cell(0,8,'Metodo de Pago: '.(isset($datos['metodoPago']) ? $datos['metodoPago'] : 'No especificado'),0,1);
$pdf->Ln(5);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(80,8,'Servicio',1);
$pdf->Cell(30,8,'Cantidad',1);
$pdf->Cell(40,8,'Precio',1);
$pdf->Cell(40,8,'Total',1);
$pdf->Ln();
$pdf->SetFont('Arial','',12);
$total = 0;
foreach ($servicios as $s) {
    $t = $s['cantidad'] * $s['precio_unitario'];
    $total += $t;
    $pdf->Cell(80,8,$s['nombre'],1);
    $pdf->Cell(30,8,$s['cantidad'],1);
    $pdf->Cell(40,8,number_format($s['precio_unitario'],2),1);
    $pdf->Cell(40,8,number_format($t,2),1);
    $pdf->Ln();
}
$pdf->SetFont('Arial','B',12);
$pdf->Cell(150,8,'Total',1);
$pdf->Cell(40,8,number_format($total,2),1);
$pdf->Output('D', 'factura_'.$id.'.pdf');
exit;
