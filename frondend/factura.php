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
    echo "<h2>Factura #$id</h2>";
    echo "<b>Cliente:</b> " . htmlspecialchars($datos['nombre']) . "<br>";
    echo "<b>Dirección:</b> " . htmlspecialchars($datos['direccion']) . "<br>";
    echo "<b>NIT/RFC:</b> " . htmlspecialchars($datos['nit']) . "<br>";
    echo "<b>Método de Pago:</b> " . htmlspecialchars(isset($datos['metodoPago']) ? $datos['metodoPago'] : 'No especificado') . "<br>";
    echo "<hr><table border='1' cellpadding='5'><tr><th>Servicio</th><th>Cantidad</th><th>Precio</th><th>Total</th></tr>";
    $total = 0;
    foreach ($servicios as $s) {
        $t = $s['cantidad'] * $s['precio_unitario'];
        $total += $t;
        echo "<tr><td>{$s['nombre']}</td><td>{$s['cantidad']}</td><td>", number_format($s['precio_unitario'],2), "</td><td>", number_format($t,2), "</td></tr>";
    }
    echo "<tr><th colspan='3'>Total</th><th>" . number_format($total,2) . "</th></tr></table>";
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
