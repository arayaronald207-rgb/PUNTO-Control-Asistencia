
<?php
// Zona horaria de Costa Rica
date_default_timezone_set('America/Costa_Rica');

// Configuración de la base de datos
$servidor = "127.0.0.1";
$usuario = "root";
$contrasena = "";
$base_datos = "control_asistencia";
$puerto = 3306;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $servidor,
        $usuario,
        $contrasena,
        $base_datos,
        $puerto
    );

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("No se pudo establecer conexión con la base de datos.");
}
?>
