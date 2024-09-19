<?php

if (isset($_POST['filePath'])) {
    $filePath = $_POST['filePath'];

    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo "File deleted successfully.";
        } else {
            echo "Error deleting the file.";
        }
    } else {
        echo "File not found.";
    }
} else {
    echo "No file path specified.";
}
?>
