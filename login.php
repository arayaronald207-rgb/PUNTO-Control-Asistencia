
<?php
session_start();

require_once __DIR__ . "/config/conexion.php";

if (isset($_SESSION["admin_id"])) {
    header("Location: admin/index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($usuario === "" || $password === "") {

        $error = "Complete todos los campos.";

    } else {

        $sql = "SELECT id, nombre, password, estado
                FROM usuarios
                WHERE usuario = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s", $usuario);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $admin = $resultado->fetch_assoc();

        if (
            $admin &&
            $admin["estado"] === "Activo" &&
            password_verify($password, $admin["password"])
        ) {

            session_regenerate_id(true);

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_nombre"] = $admin["nombre"];

            header("Location: admin/index.php");
            exit;

        } else {

            $error = "Usuario o contraseña incorrectos.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Acceso administrativo | PUNTO</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="css/estilos.css">

    <style>
        .login-marca-actualizada {
            display: inline-flex;
            align-items: center;
            gap: 13px;
            text-decoration: none;
            color: #ffffff;
        }

        .login-marca-actualizada .marca-icono {
            width: 43px;
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 11px;
            font-size: 25px;
            color: #e47b45;
            flex-shrink: 0;
        }

        .login-marca-texto {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .login-marca-texto strong {
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .login-marca-texto .marca-punto {
            color: #e47b45;
        }

        .login-marca-texto small {
            margin-top: 5px;
            color: rgba(255,255,255,0.75);
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 2px;
        }
    </style>
</head>

<body class="login-body">

<div class="login-layout">

    <section class="login-presentacion">

        <!-- Logotipo actualizado -->

        <div class="login-marca-actualizada">

            <span class="marca-icono">◷</span>

            <span class="login-marca-texto">

                <strong>
                    PUNTO<span class="marca-punto">.</span>
                </strong>

                <small>CONTROL DE ASISTENCIA</small>

            </span>

        </div>

        <div class="presentacion-contenido">

            <div class="etiqueta-sistema">
                GESTIÓN DE PERSONAL
            </div>

            <h1>
                El tiempo de tu equipo,
                <span>mejor organizado.</span>
            </h1>

            <p>
                Una plataforma sencilla para administrar
                empleados, registrar jornadas y consultar
                la asistencia de tu empresa.
            </p>

            <div class="linea-decorativa"></div>

        </div>

        <div class="presentacion-pie">
            Sistema de control de asistencia
        </div>

    </section>

    <section class="login-formulario">

        <div class="login-contenedor">

            <div class="login-encabezado">

                <span class="login-etiqueta">
                    PORTAL ADMINISTRATIVO
                </span>

                <h2>Bienvenido de nuevo</h2>

                <p>
                    Ingresa tus credenciales para acceder
                    al panel de administración.
                </p>

            </div>

            <?php if ($error !== ""): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

            <?php endif; ?>

            <form method="POST" autocomplete="on">

                <div class="mb-4">

                    <label for="usuario" class="form-label">
                        Usuario
                    </label>

                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        class="form-control"
                        placeholder="Ingresa tu usuario"
                        autocomplete="username"
                        required
                    >

                </div>

                <div class="mb-4">

                    <label for="password" class="form-label">
                        Contraseña
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="btn btn-acceso w-100"
                >
                    Iniciar sesión
                    <span>→</span>
                </button>

            </form>

            <div class="login-enlace-publico">

                <a href="index.php">
                    ← Volver al registro de asistencia
                </a>

            </div>

        </div>

        <div class="login-copyright">
            © <?= date("Y") ?> PUNTO.
            Control de asistencia.
        </div>

    </section>

</div>

</body>
</html>
