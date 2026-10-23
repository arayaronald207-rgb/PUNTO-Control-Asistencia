
<?php
require_once __DIR__ . "/../config/seguridad.php";
require_once __DIR__ . "/../config/conexion.php";

$error = "";
$cedula = "";
$nombre = "";
$correo = "";
$telefono = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function generarCodigo($conn) {
    $caracteres = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";

    for ($intento = 0; $intento < 30; $intento++) {
        $codigo = "";

        for ($i = 0; $i < 6; $i++) {
            $codigo .= $caracteres[
                random_int(0, strlen($caracteres) - 1)
            ];
        }

        $stmt = $conn->prepare(
            "SELECT id FROM empleados WHERE codigo_acceso = ?"
        );

        $stmt->bind_param("s", $codigo);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if (!$existe) {
            return $codigo;
        }
    }

    throw new Exception("No se pudo generar un código único.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cedula = trim($_POST["cedula"] ?? "");
    $nombre = trim($_POST["nombre"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {

        $error = "La solicitud no es válida. Recarga la página.";

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

        $error = "Ingrese un número telefónico válido.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id FROM empleados WHERE cedula = ?"
        );

        $stmt->bind_param("s", $cedula);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if ($existe) {

            $error = "Ya existe un empleado con esa cédula.";

        } else {

            try {

                $codigo = generarCodigo($conn);

                $stmt = $conn->prepare(
                    "INSERT INTO empleados
                    (cedula, nombre, correo, telefono, codigo_acceso, estado)
                    VALUES (?, ?, ?, ?, ?, 'Activo')"
                );

                $stmt->bind_param(
                    "sssss",
                    $cedula,
                    $nombre,
                    $correo,
                    $telefono,
                    $codigo
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                $_SESSION["mensaje"] =
                    "Empleado registrado correctamente.";

                $_SESSION["codigo_nuevo"] = $codigo;

                header("Location: empleados.php");
                exit;

            } catch (mysqli_sql_exception $e) {

                error_log($e->getMessage());

                $error = $e->getCode() == 1062
                    ? "La cédula o el código ya están registrados."
                    : "No se pudo registrar el empleado.";

            } catch (Exception $e) {

                $error = "No se pudo generar el código de acceso.";
            }
        }
    }
}

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Nuevo empleado | PUNTO</title>

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
                PERSONAL / NUEVO REGISTRO
            </span>

            <h1>Registrar empleado<span>.</span></h1>

            <p>
                Completa los datos para incorporar una persona al sistema.
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
                                value="<?= escapar($cedula) ?>"
                                maxlength="25"
                                placeholder="Ej. 1-1111-1111"
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
                                value="<?= escapar($nombre) ?>"
                                maxlength="150"
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
                                value="<?= escapar($correo) ?>"
                                maxlength="150"
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
                                value="<?= escapar($telefono) ?>"
                                maxlength="25"
                                required
                            >

                        </div>

                    </div>

                    <div class="codigo-aviso">

                        <strong>
                            Código de acceso automático
                        </strong>

                        <p>
                            El sistema generará un código único de seis
                            caracteres cuando registres al empleado.
                        </p>

                    </div>

                    <div class="form-acciones">

                        <a href="empleados.php" class="btn-limpiar">
                            Cancelar
                        </a>

                        <button type="submit" class="btn btn-acceso">
                            Guardar empleado →
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </div>

</main>

</body>
</html>
