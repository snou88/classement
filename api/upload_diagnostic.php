<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if file was uploaded
if (!isset($_FILES['excelFile'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun fichier n\'a été téléchargé.']);
    exit;
}

$file = $_FILES['excelFile'];
$uploadDir = __DIR__ . '/uploads/';
$targetFile = $uploadDir . 'Results_V2_updated.xlsx';

// Create uploads directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossible de créer le répertoire de téléchargement.']);
        exit;
    }
}

// Check for errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la limite de taille définie dans php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la limite de taille spécifiée dans le formulaire HTML.',
        UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé.',
        UPLOAD_ERR_NO_TMP_DIR => 'Le dossier temporaire est manquant.',
        UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
        UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléchargement du fichier.',
    ];
    
    $errorMessage = $errorMessages[$file['error']] ?? 'Erreur inconnue lors du téléchargement.';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

// Check file type
$fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileType, ['xlsx', 'xls'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Seuls les fichiers Excel (.xlsx, .xls) sont autorisés.']);
    exit;
}

// Try to move the uploaded file
if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    // Set proper permissions
    chmod($targetFile, 0644);
    
    echo json_encode([
        'success' => true,
        'message' => 'Le fichier a été téléchargé avec succès.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors du téléchargement du fichier.'
    ]);
}
?>
