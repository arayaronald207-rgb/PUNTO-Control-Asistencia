
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: empleados.php");
    exit;
}

$token = $_POST["csrf_token"] ?? "";

if (
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    http_response_code(403);
    exit("Solicitud no válida.");
}

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: empleados.php");
    exit;
}

$stmt = $conn->prepare(
    "UPDATE empleados
     SET estado = CASE
         WHEN estado = 'Activo' THEN 'Inactivo'
         ELSE 'Activo'
     END
     WHERE id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $_SESSION["mensaje"] = "Estado del empleado actualizado.";
} else {
    $_SESSION["mensaje"] = "No se encontró el empleado.";
}

$stmt->close();

header("Location: empleados.php");
exit;
?>
