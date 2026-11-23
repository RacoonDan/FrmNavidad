<?php
require_once "conexion.php";[cite: 2]

if ($_SERVER["REQUEST_METHOD"] !== "POST") {[cite: 2]
    header("Location: formulario.html");[cite: 2]
    exit;[cite: 2]
}

$persona_id = filter_input(INPUT_POST, "persona_id", FILTER_VALIDATE_INT);[cite: 2]
$titulo = trim($_POST["titulo"] ?? "");[cite: 2]

$rawDescripciones = $_POST["descripciones"] ?? [];
$rawEnlaces = $_POST["enlaces"] ?? [];

$descripciones = [];
$enlaces = [];

for ($i = 0; $i < 3; $i++) {
    $descripciones[$i] = is_string($rawDescripciones[$i] ?? null) ? trim($rawDescripciones[$i]) : "";
    $enlaces[$i] = is_string($rawEnlaces[$i] ?? null) ? trim($rawEnlaces[$i]) : "";
}

$imagenes = [null, null, null];
$archivosSubidos = [];

function limpiarArchivos(array $rutas): void {
    foreach ($rutas as $ruta) {
        if ($ruta && file_exists($ruta)) {
            unlink($ruta);
        }
    }
}

function volverConError(string $mensaje, array $archivosParaBorrar = []): never {
    limpiarArchivos($archivosParaBorrar);
    echo "<!doctype html><html lang='es'><meta charset='UTF-8'><title>Error</title>";[cite: 2]
    echo "<body style='font-family:Arial;text-align:center;padding:50px'>";[cite: 2]
    echo "<h2>" . htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") . "</h2>";[cite: 2]
    echo "<a href='formulario.html'>Volver al formulario</a></body></html>";[cite: 2]
    exit;[cite: 2]
}

if (!$persona_id || $titulo === "") {[cite: 2]
    volverConError("Completa los campos obligatorios.");[cite: 2]
}

for ($i = 0; $i < 3; $i++) {
    if ($enlaces[$i] !== "") {
        $esquema = strtolower((string) parse_url($enlaces[$i], PHP_URL_SCHEME));
        if (!filter_var($enlaces[$i], FILTER_VALIDATE_URL) || !in_array($esquema, ["http", "https"], true)) {
            volverConError("El enlace " . ($i + 1) . " no es válido. Incluye https://");[cite: 2]
        }
    }
}

// Verificar que la persona seleccionada exista[cite: 2]
$consultaPersona = mysqli_prepare($conexion, "SELECT id FROM personas WHERE id = ?");[cite: 2]
if (!$consultaPersona) {
    volverConError("Error al verificar la persona.");
}
mysqli_stmt_bind_param($consultaPersona, "i", $persona_id);[cite: 2]
mysqli_stmt_execute($consultaPersona);[cite: 2]
$resultadoPersona = mysqli_stmt_get_result($consultaPersona);[cite: 2]

if (!$resultadoPersona || mysqli_num_rows($resultadoPersona) === 0) {[cite: 2]
    volverConError("La persona seleccionada no existe en la base de datos.");[cite: 2]
}

// Procesar hasta 3 imágenes opcionales (name="imagenes[]")[cite: 2]
if (isset($_FILES["imagenes"]) && is_array($_FILES["imagenes"]["name"])) {
    $permitidos = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/gif"  => "gif",
        "image/webp" => "webp",
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);[cite: 2]
    $carpetaUploads = __DIR__ . "/uploads";

    for ($i = 0; $i < 3; $i++) {
        $errorSubida = $_FILES["imagenes"]["error"][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($errorSubida === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorSubida !== UPLOAD_ERR_OK) {
            volverConError("Ocurrió un problema al cargar la imagen " . ($i + 1) . ".", $archivosSubidos);[cite: 2]
        }

        $tamano = $_FILES["imagenes"]["size"][$i] ?? 0;
        if ($tamano > 5 * 1024 * 1024) {[cite: 2]
            volverConError("La imagen " . ($i + 1) . " supera el límite de 5 MB.", $archivosSubidos);[cite: 2]
        }

        $tmpName = $_FILES["imagenes"]["tmp_name"][$i] ?? "";
        $tipo = $tmpName !== "" ? $finfo->file($tmpName) : false;[cite: 2]
        if (!is_string($tipo) || !isset($permitidos[$tipo])) {
            volverConError("Formato no permitido en la imagen " . ($i + 1) . ".", $archivosSubidos);[cite: 2]
        }

        // Asegurar la existencia de uploads/ justo antes de mover la imagen
        if (!is_dir($carpetaUploads) && !mkdir($carpetaUploads, 0775, true)) {
            volverConError("No se pudo crear la carpeta uploads.", $archivosSubidos);[cite: 2]
        }

        $nombreArchivo = bin2hex(random_bytes(16)) . "." . $permitidos[$tipo];[cite: 2]
        $rutaDestino = $carpetaUploads . "/" . $nombreArchivo;

        if (!move_uploaded_file($tmpName, $rutaDestino)) {[cite: 2]
            volverConError("No se pudo guardar la imagen " . ($i + 1) . ". Revisa los permisos de la carpeta uploads.", $archivosSubidos);[cite: 2]
        }

        $imagenes[$i] = $nombreArchivo;
        $archivosSubidos[] = $rutaDestino;
    }
}

$sql = "INSERT INTO registros (
            persona_id, titulo,
            descripcion1, enlace1, imagen1,
            descripcion2, enlace2, imagen2,
            descripcion3, enlace3, imagen3
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) {
    volverConError("Error al preparar el guardado en la base de datos.", $archivosSubidos);
}

mysqli_stmt_bind_param(
    $stmt,
    "issssssssss",
    $persona_id,
    $titulo,
    $descripciones[0], $enlaces[0], $imagenes[0],
    $descripciones[1], $enlaces[1], $imagenes[1],
    $descripciones[2], $enlaces[2], $imagenes[2]
);

if (mysqli_stmt_execute($stmt)) {
    header("Location: registros.php?guardado=1");[cite: 2]
    exit;[cite: 2]
}

volverConError("No se pudieron guardar los datos. Verifica la base de datos.", $archivosSubidos);[cite: 2]
