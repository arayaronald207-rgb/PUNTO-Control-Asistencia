
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

date_default_timezone_set("America/Costa_Rica");

/*
 * Configuración de jornadas.
 * Debe coincidir con reportes.php y asistencias.php.
 */
$horasHabituales = 8;
$horasRevision = 12;

$segundosHabituales = $horasHabituales * 3600;
$segundosRevision = $horasRevision * 3600;

/*
 * Filtros recibidos desde el historial.
 */
$buscar = trim((string)($_GET["buscar"] ?? ""));
$fecha = trim((string)($_GET["fecha"] ?? ""));
$desde = trim((string)($_GET["desde"] ?? ""));
$hasta = trim((string)($_GET["hasta"] ?? ""));

function fechaValida($valor) {
    if ($valor === "") {
        return true;
    }

    $d = DateTimeImmutable::createFromFormat("!Y-m-d", $valor);

    return $d !== false && $d->format("Y-m-d") === $valor;
}

/*
 * Validación de filtros.
 */
if (
    !fechaValida($fecha) ||
    !fechaValida($desde) ||
    !fechaValida($hasta) ||
    ($desde !== "" && $hasta !== "" && $desde > $hasta) ||
    mb_strlen($buscar) > 150
) {
    http_response_code(400);
    exit("Filtros no válidos.");
}

$condiciones = [];
$valores = [];
$tipos = "";

/*
 * Filtro por empleado o cédula.
 */
if ($buscar !== "") {
    $condiciones[] = "(e.nombre LIKE ? OR e.cedula LIKE ?)";

    $termino = "%" . $buscar . "%";

    $valores[] = $termino;
    $valores[] = $termino;

    $tipos .= "ss";
}

/*
 * Filtro por fecha específica.
 */
if ($fecha !== "") {
    $condiciones[] = "a.fecha = ?";
    $valores[] = $fecha;
    $tipos .= "s";
}

/*
 * Filtro desde una fecha.
 */
if ($desde !== "") {
    $condiciones[] = "a.fecha >= ?";
    $valores[] = $desde;
    $tipos .= "s";
}

/*
 * Filtro hasta una fecha.
 */
if ($hasta !== "") {
    $condiciones[] = "a.fecha <= ?";
    $valores[] = $hasta;
    $tipos .= "s";
}

/*
 * Consulta de asistencias.
 */
$sql = "
    SELECT
        e.cedula,
        e.nombre,
        a.fecha,
        a.hora_entrada,
        a.hora_salida,
        a.observacion
    FROM asistencias a
    INNER JOIN empleados e
        ON e.id = a.empleado_id
";

if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$sql .= "
    ORDER BY
        a.fecha DESC,
        a.hora_entrada DESC,
        a.id DESC
";

$stmt = $conn->prepare($sql);

if ($tipos !== "") {
    $stmt->bind_param($tipos, ...$valores);
}

$stmt->execute();

$resultado = $stmt->get_result();

/*
 * Evita que Excel interprete determinados
 * valores como fórmulas.
 */
function celdaSegura($valor) {
    $valor = (string)$valor;

    if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $valor)) {
        return "'" . $valor;
    }

    return $valor;
}

/*
 * Escribe una fila en formato CSV.
 */
function escribirFila($archivo, $datos) {
    fputcsv(
        $archivo,
        array_map("celdaSegura", $datos),
        ";",
        '"',
        ""
    );
}

/*
 * Calcula la duración de una jornada.
 */
function calcularDuracion($entrada, $salida) {
    if ($entrada === null || $salida === null) {
        return null;
    }

    return strtotime($salida) - strtotime($entrada);
}

/*
 * Convierte segundos a horas y minutos.
 */
function formatoHoras($segundos) {
    if ($segundos === null || $segundos < 0) {
        return "";
    }

    $minutos = intdiv($segundos, 60);

    return sprintf(
        "%02d:%02d",
        intdiv($minutos, 60),
        $minutos % 60
    );
}

/*
 * Clasifica cada jornada.
 */
function clasificarJornada(
    $segundos,
    $segundosHabituales,
    $segundosRevision
) {
    if ($segundos === null) {
        return "Pendiente";
    }

    if ($segundos < 0) {
        return "Revisar horario";
    }

    if ($segundos > $segundosRevision) {
        return "Revisar jornada";
    }

    if ($segundos > $segundosHabituales) {
        return "Tiempo adicional";
    }

    return "Habitual";
}

/*
 * Nombre del archivo descargado.
 */
$nombreArchivo = "asistencias_" . date("Y-m-d") . ".csv";

header("Content-Type: text/csv; charset=UTF-8");

header(
    "Content-Disposition: attachment; filename=\"$nombreArchivo\""
);

header("Cache-Control: no-store");

/*
 * Generación del CSV.
 */
$archivo = fopen("php://output", "w");

/*
 * Encabezados.
 *
 * No agregamos la línea sep=; porque en tu
 * Excel aparecía como una fila de texto.
 */
escribirFila($archivo, [
    "Cedula",
    "Empleado",
    "Fecha",
    "Entrada",
    "Salida",
    "Horas trabajadas",
    "Estado",
    "Clasificacion",
    "Observacion"
]);

/*
 * Exportación de los registros.
 */
while ($fila = $resultado->fetch_assoc()) {
    $entrada = $fila["hora_entrada"];
    $salida = $fila["hora_salida"];

    $segundos = calcularDuracion($entrada, $salida);

    $horas = formatoHoras($segundos);

    $clasificacion = clasificarJornada(
        $segundos,
        $segundosHabituales,
        $segundosRevision
    );

    $estado = $salida === null
        ? "En jornada"
        : "Finalizada";

    $observacion = trim(
        (string)($fila["observacion"] ?? "")
    );

    escribirFila($archivo, [
        $fila["cedula"],
        $fila["nombre"],
        date("d/m/Y", strtotime($fila["fecha"])),
        $entrada,
        $salida ?? "",
        $horas,
        $estado,
        $clasificacion,
        $observacion
    ]);
}

fclose($archivo);

$stmt->close();

exit;
?>
