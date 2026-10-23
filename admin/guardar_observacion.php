
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: asistencias.php");
    exit;
}

$tokenRecibido = $_POST["csrf_token"] ?? "";
$tokenGuardado = $_SESSION["csrf_observaciones"] ?? "";

if (
    !is_string($tokenRecibido) ||
    !is_string($tokenGuardado) ||
    $tokenGuardado === "" ||
    !hash_equals($tokenGuardado, $tokenRecibido)
) {
    http_response_code(403);
    exit("Solicitud no autorizada.");
}

$id = filter_input(
    INPUT_POST,
    "asistencia_id",
    FILTER_VALIDATE_INT
);

$observacion = trim((string)($_POST["observacion"] ?? ""));

$retorno = (string)($_POST["retorno"] ?? "");

$parametrosPermitidos = [
    "buscar",
    "fecha",
    "desde",
    "hasta"
];

parse_str($retorno, $filtrosRecibidos);

$filtros = [];

foreach ($parametrosPermitidos as $campo) {
    if (
        isset($filtrosRecibidos[$campo]) &&
        is_string($filtrosRecibidos[$campo])
    ) {
        $filtros[$campo] = $filtrosRecibidos[$campo];
    }
}

function regresar($filtros, $mensaje) {
    $filtros["mensaje"] = $mensaje;

    header(
        "Location: asistencias.php?" .
        http_build_query($filtros),
        true,
        303
    );

    exit;
}

if (!$id || $id < 1 || mb_strlen($observacion, "UTF-8") > 500) {
    regresar($filtros, "error");
}

try {
    $stmt = $conn->prepare(
        "UPDATE asistencias
         SET observacion = ?
         WHERE id = ?"
    );

    $valor = $observacion === "" ? null : $observacion;

    $stmt->bind_param("si", $valor, $id);
    $stmt->execute();

    $stmt->close();

    $_SESSION["csrf_observaciones"] = bin2hex(random_bytes(32));

    regresar($filtros, "guardado");

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    regresar($filtros, "error");
}
