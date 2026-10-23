
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: empleados.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT * FROM empleados WHERE id = ? LIMIT 1"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$empleado = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$empleado) {
    header("Location: empleados.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cedula = trim($_POST["cedula"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $token = $_POST["csrf_token"] ?? "";

    $empleado["cedula"] = $cedula;
    $empleado["nombre"] = $nombre;
    $empleado["correo"] = $correo;
    $empleado["telefono"] = $telefono;

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {

        $error = "Solicitud no válida. Recarga la página.";

    } elseif (
        $cedula === "" ||
        $nombre === "" ||
        $correo === "" ||
        $telefono === ""
    ) {

        $error = "Todos los campos son obligatorios.";

    } elseif (
        strlen($cedula) > 25 ||
        strlen($nombre) > 150 ||
        strlen($correo) > 150 ||
        strlen($telefono) > 25
    ) {

        $error = "Uno de los campos supera el tamaño permitido.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $error = "Ingrese un correo electrónico válido.";

    } elseif (!preg_match('/^[0-9-]+$/', $cedula)) {

        $error = "La cédula solo puede contener números y guiones.";

    } elseif (!preg_match('/^[0-9+\s-]+$/', $telefono)) {

        $error = "Ingrese un teléfono válido.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id FROM empleados
             WHERE cedula = ? AND id != ?"
        );

        $stmt->bind_param("si", $cedula, $id);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if ($existe) {

            $error = "La cédula ya pertenece a otro empleado.";

        } else {

            try {

                $stmt = $conn->prepare(
                    "UPDATE empleados
                     SET cedula = ?,
                         nombre = ?,
                         correo = ?,
                         telefono = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "ssssi",
                    $cedula,
                    $nombre,
                    $correo,
                    $telefono,
                    $id
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION["mensaje"] =
                    "Empleado actualizado correctamente.";

                header("Location: empleados.php");
                exit;

            } catch (mysqli_sql_exception $e) {

                error_log($e->getMessage());

                $error = "No se pudo actualizar el empleado.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Editar empleado | PUNTO</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../css/estilos.css">
</head>

<body class="panel-body">

<?php require __DIR__ . "/includes/header.php"; ?>

<main class="panel-main container-fluid">

    <div class="panel-titulo">

        <div>
            <span class="seccion-etiqueta">
                PERSONAL / EDICIÓN
            </span>

            <h1>Editar empleado<span>.</span></h1>

            <p>
                Actualiza la información del empleado seleccionado.
            </p>
        </div>

        <a href="empleados.php" class="btn-limpiar">
            ← Volver
        </a>

    </div>

    <div class="row">

        <div class="col-lg-8 col-xl-7">

            <section class="panel-bloque">

                <div class="bloque-encabezado">

                    <div>
                        <span class="seccion-etiqueta">
                            INFORMACIÓN PERSONAL
                        </span>

                        <h2>Datos del empleado</h2>
                    </div>

                </div>

                <?php if ($error !== ""): ?>

                    <div class="alert alert-danger">
                        <?= escapar($error) ?>
                    </div>

                <?php endif; ?>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapar($_SESSION["csrf_token"]) ?>"
                    >

                    <div class="row g-4">

                        <div class="col-md-6">

                            <label for="cedula" class="form-label">
                                Cédula *
                            </label>

                            <input
                                type="text"
                                id="cedula"
                                name="cedula"
                                class="form-control"
                                maxlength="25"
                                value="<?= escapar($empleado["cedula"]) ?>"
                                required
                            >

                        </div>

                        <div class="col-md-6">

                            <label for="nombre" class="form-label">
                                Nombre completo *
                            </label>

                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                class="form-control"
                                maxlength="150"
                                value="<?= escapar($empleado["nombre"]) ?>"
                                required
                            >

                        </div>

                        <div class="col-md-6">

                            <label for="correo" class="form-label">
                                Correo electrónico *
                            </label>

                            <input
                                type="email"
                                id="correo"
                                name="correo"
                                class="form-control"
                                maxlength="150"
                                value="<?= escapar($empleado["correo"]) ?>"
                                required
                            >

                        </div>

                        <div class="col-md-6">

                            <label for="telefono" class="form-label">
                                Teléfono *
                            </label>

                            <input
                                type="tel"
                                id="telefono"
                                name="telefono"
                                class="form-control"
                                maxlength="25"
                                value="<?= escapar($empleado["telefono"]) ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="codigo-aviso">

                        <strong>Código de acceso</strong>

                        <p>
                            <?= escapar($empleado["codigo_acceso"]) ?>
                            — Este código se conserva al editar los datos.
                        </p>

                    </div>

                    <div class="form-acciones">

                        <a href="empleados.php" class="btn-limpiar">
                            Cancelar
                        </a>

                        <button type="submit" class="btn btn-acceso">
                            Guardar cambios →
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </div>

</main>

</body>
</html>
