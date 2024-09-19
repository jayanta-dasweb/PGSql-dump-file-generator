<?php
session_start();

// Function to connect to the PostgreSQL database
function connectToDb()
{
    $host = $_SESSION["host"];
    $username = $_SESSION["username"];
    $password = $_SESSION["password"];
    $dbname = $_SESSION["dbname"];

    $conn = pg_connect("host=$host dbname=$dbname user=$username password=$password");

    if (!$conn) {
        echo json_encode(['error' => 'Connection failed']);
        exit;
    }

    return $conn;
}

// Function to generate CSV for a table's selected fields
function generateCSV($conn, $schemaName, $tableName, $fields, $outputDir)
{
    if (empty($fields)) {
        return;  // No fields selected, skip
    }

    // Prepare the query to get the selected fields data
    $fieldList = implode(', ', array_map(function ($field) {
        return "\"$field\"";
    }, $fields));
    
    $query = "SELECT $fieldList FROM \"$schemaName\".\"$tableName\";";
    $result = pg_query($conn, $query);

    // CSV file path
    $csvFilePath = "$outputDir/{$schemaName}_{$tableName}.csv";

    // Open the file for writing
    $fileHandle = fopen($csvFilePath, 'w');
    if ($result) {
        // Add CSV header
        fputcsv($fileHandle, $fields);

        // Add rows to CSV
        while ($row = pg_fetch_assoc($result)) {
            $rowData = [];
            foreach ($fields as $field) {
                $rowData[] = $row[$field];
            }
            fputcsv($fileHandle, $rowData);
        }
    }
    // Close the file
    fclose($fileHandle);
}

if ($_POST['selectedData']) {
    // Connect to the database
    $conn = connectToDb();
    $selectedData = json_decode($_POST['selectedData'], true);

    // Directory for CSV files
    $outputDir = __DIR__ . '/csv_dumps_' . time();
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Loop through the selected schemas, tables, and fields
    foreach ($selectedData as $schemaName => $schema) {
        foreach ($schema['tables'] as $tableName => $table) {
            $fields = $table['fields'];  // Get the selected fields

            // Generate CSV for the selected fields
            generateCSV($conn, $schemaName, $tableName, $fields, $outputDir);
        }
    }

    // Create a ZIP file containing all CSV files
    $zipFileName = 'pgsql_csv_dump_' . time() . '.zip';
    $zipFilePath = __DIR__ . '/' . $zipFileName;

    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
        // Add all CSV files to the ZIP
        $csvFiles = scandir($outputDir);
        foreach ($csvFiles as $file) {
            if ($file != '.' && $file != '..') {
                $zip->addFile("$outputDir/$file", $file);
            }
        }
        $zip->close();
    } else {
        echo json_encode(['error' => 'Failed to create ZIP file']);
        exit;
    }

    // Clean up: remove the temporary CSV directory
    array_map('unlink', glob("$outputDir/*.*"));
    rmdir($outputDir);

    // Return the ZIP file for download
    echo $zipFileName;

    // Close the database connection
    pg_close($conn);
}
