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

// Function to generate schema creation SQL
function generateCreateSchema($schemaName)
{
    return "CREATE SCHEMA IF NOT EXISTS \"$schemaName\";\n";
}

// Function to generate the table creation SQL only for selected fields
function generateCreateTable($conn, $schemaName, $tableName, $fields)
{
    if (empty($fields)) {
        return "";  // No fields selected, skip table creation
    }

    // Prepare the query to fetch field details for the selected fields
    $fieldsList = "'" . implode("', '", $fields) . "'";
    $tableStructureQuery = "
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_schema = '$schemaName' AND table_name = '$tableName'
        AND column_name IN ($fieldsList);
    ";

    $result = pg_query($conn, $tableStructureQuery);
    if (!$result) {
        error_log("Failed to retrieve table structure: " . pg_last_error($conn));
        return "";
    }

    $createTable = "CREATE TABLE IF NOT EXISTS \"$schemaName\".\"$tableName\" (\n";
    $columns = [];
    while ($row = pg_fetch_assoc($result)) {
        $columnDef = "\"{$row['column_name']}\" {$row['data_type']}";

        // Check if column uses BIGSERIAL or SERIAL and handle sequence generation automatically
        if (strpos($row['column_default'], 'nextval') !== false) {
            // Assume it's a serial/bigserial type; you can just use SERIAL/BIGSERIAL in SQL
            if (stripos($row['data_type'], 'bigint') !== false) {
                $columnDef = "\"{$row['column_name']}\" BIGSERIAL";
            } else {
                $columnDef = "\"{$row['column_name']}\" SERIAL";
            }
        }

        // Handle NOT NULL constraint
        if ($row['is_nullable'] === 'NO') {
            $columnDef .= " NOT NULL";
        }

        // Handle default values if not a sequence/serial type
        if ($row['column_default'] !== null && strpos($row['column_default'], 'nextval') === false) {
            $columnDef .= " DEFAULT {$row['column_default']}";
        }

        $columns[] = $columnDef;
    }
    $createTable .= implode(",\n", $columns) . "\n);\n";
    return $createTable;
}


// Function to generate data insertion SQL for selected fields
function generateInsertData($conn, $schemaName, $tableName, $fields)
{
    if (empty($fields)) {
        return "";  // No fields selected, skip data insertion
    }

    // Create the query to get the data from the table
    $fieldList = implode(', ', array_map(function ($field) {
        return "\"$field\"";
    }, $fields));
    $query = "SELECT $fieldList FROM \"$schemaName\".\"$tableName\";";
    $result = pg_query($conn, $query);

    $dump = "";
    if ($result) {
        $dump .= "-- Dumping data for table $schemaName.$tableName\n";
        while ($row = pg_fetch_assoc($result)) {
            // Escape each value for insertion
            $values = array_map(function ($val) {
                return "'" . pg_escape_string($val) . "'";
            }, array_values($row));

            // Add the INSERT statement to the dump
            $dump .= "INSERT INTO \"$schemaName\".\"$tableName\" ($fieldList) VALUES (" . implode(', ', $values) . ");\n";
        }
        $dump .= "\n";  // Add a newline after the INSERTs
    }
    return $dump;
}


if ($_POST['selectedData']) {
    // Connect to the database
    $conn = connectToDb();
    $selectedData = json_decode($_POST['selectedData'], true);

    // Initialize the SQL dump
    $dump = "-- PostgreSQL SQL Dump\n\n";

    // Loop through the selected schemas, tables, and fields
    foreach ($selectedData as $schemaName => $schema) {
        // Generate schema creation SQL
        $dump .= generateCreateSchema($schemaName);

        foreach ($schema['tables'] as $tableName => $table) {
            $fields = $table['fields'];  // Get the selected fields

            // Generate the table creation SQL only for selected fields
            $dump .= generateCreateTable($conn, $schemaName, $tableName, $fields);

            // Generate data insertion SQL only for selected fields
            $dump .= generateInsertData($conn, $schemaName, $tableName, $fields);
        }
    }

    // Create the dump file
    $fileName = 'pgsql_dump_' . time() . '.sql';
    $filePath = __DIR__ . '/' . $fileName;

    // Write the SQL dump to the file
    file_put_contents($filePath, $dump);

    // Return the file for download
    echo $fileName;

    // Close the database connection
    pg_close($conn);
}
