<?php
require_once "conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: formulario.html");
    exit;
}

$persona_id = filter_input(INPUT_POST, "persona_id", FILTER_VALIDATE_INT);
$titulo = trim($_POST["titulo"] ?? "");

// Normalizar a 3 descripciones y 3 enlaces (índices 0, 1, 2)
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
    echo "<!doctype html><html lang='es'><meta charset='UTF-8'><title>Error</title>";
    echo "<body style='font-family:Arial;text-align:center;padding:50px'>";
    echo "<h2>" . htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") . "</h2>";
    echo "<a href='formulario.html'>Volver al formulario</a></body></html>";
    exit;
}

if (!$persona_id || $titulo === "") {
    volverConError("Completa los campos obligatorios.");
}

// Validar los 3 enlaces si se ingresaron
for ($i = 0; $i < 3; $i++) {
    if ($enlaces[$i] !== "") {
        $esquema = strtolower((string) parse_url($enlaces[$i], PHP_URL_SCHEME));
        if (!filter_var($enlaces[$i], FILTER_VALIDATE_URL) || !in_array($esquema, ["http", "https"], true)) {
            volverConError("El enlace " . ($i + 1) . " no es válido. Incluye https://");
        }
    }
}

// Verificar que la persona seleccionada exista
$consultaPersona = mysqli_prepare($conexion, "SELECT id FROM personas WHERE id = ?");
if (!$consultaPersona) {
    volverConError("Error al verificar la persona.");
}
mysqli_stmt_bind_param($consultaPersona, "i", $persona_id);
mysqli_stmt_execute($consultaPersona);
$resultadoPersona = mysqli_stmt_get_result($consultaPersona);

if (!$resultadoPersona || mysqli_num_rows($resultadoPersona) === 0) {
    volverConError("La persona seleccionada no existe en la base de datos.");
}

// Procesar hasta 3 imágenes opcionales (name="imagenes[]")
if (isset($_FILES["imagenes"]) && is_array($_FILES["imagenes"]["name"])) {
    $permitidos = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/gif"  => "gif",
        "image/webp" => "webp",
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $carpetaUploads = __DIR__ . "/uploads";

    for ($i = 0; $i < 3; $i++) {
        $errorSubida = $_FILES["imagenes"]["error"][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($errorSubida === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorSubida !== UPLOAD_ERR_OK) {
            volverConError("Ocurrió un problema al cargar la imagen " . ($i + 1) . ".", $archivosSubidos);
        }

        $tamano = $_FILES["imagenes"]["size"][$i] ?? 0;
        if ($tamano > 5 * 1024 * 1024) {
            volverConError("La imagen " . ($i + 1) . " supera el límite de 5 MB.", $archivosSubidos);
        }

        $tmpName = $_FILES["imagenes"]["tmp_name"][$i] ?? "";
        $tipo = $tmpName !== "" ? $finfo->file($tmpName) : false;
        if (!is_string($tipo) || !isset($permitidos[$tipo])) {
            volverConError("Formato no permitido en la imagen " . ($i + 1) . ".", $archivosSubidos);
        }

        if (!is_dir($carpetaUploads) && !mkdir($carpetaUploads, 0775, true)) {
            volverConError("No se pudo crear la carpeta uploads.", $archivosSubidos);
        }

        $nombreArchivo = bin2hex(random_bytes(16)) . "." . $permitidos[$tipo];
        $rutaDestino = $carpetaUploads . "/" . $nombreArchivo;

        if (!move_uploaded_file($tmpName, $rutaDestino)) {
            volverConError("No se pudo guardar la imagen " . ($i + 1) . ".", $archivosSubidos);
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
    header("Location: registros.php?guardado=1");
    exit;
}

volverConError("No se pudieron guardar los datos. Verifica la base de datos.", $archivosSubidos);
