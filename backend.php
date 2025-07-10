<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'getLogFolders':
            getLogFolders();
            break;

        case 'getLogFiles':
            getLogFilesInFolder();
            break;

        case 'readLogFile':
            readSelectedLogFile();
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}

function getLogFolders() {
    $baseDir = "C:\\wamp64\\www";
    $foldersWithLogs = [];

    if (!is_dir($baseDir)) {
        echo json_encode($foldersWithLogs);
        return;
    }

    $dirIterator = new DirectoryIterator($baseDir);
    foreach ($dirIterator as $fileInfo) {
        if ($fileInfo->isDot() || !$fileInfo->isDir()) continue;

        $folderPath = $fileInfo->getPathname();
        if (folderContainsLogFile($folderPath)) {
            $foldersWithLogs[] = $fileInfo->getFilename();
        }
    }

    echo json_encode($foldersWithLogs);
}

function folderContainsLogFile($folder) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'log') {
            return true;
        }
    }

    return false;
}

function getLogFilesInFolder() {
    $folder = $_POST['folder'] ?? '';
    $folderPath = "C:\\wamp64\\www\\$folder";

    $logFiles = [];

    if (!is_dir($folderPath)) {
        echo json_encode($logFiles);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'log') {
            $relativePath = str_replace($folderPath . DIRECTORY_SEPARATOR, '', $fileInfo->getPathname());
            $logFiles[] = $relativePath;
        }
    }

    echo json_encode($logFiles);
}

function readSelectedLogFile() {
    $folder = $_POST['folder'] ?? '';
    $file = $_POST['file'] ?? '';

    $basePath = "C:\\wamp64\\www\\$folder";
    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
    $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;

    if (!file_exists($fullPath)) {
        echo "File not found.";
        return;
    }

    if (!is_readable($fullPath)) {
        echo "File is not readable.";
        return;
    }

    echo file_get_contents($fullPath);
}
