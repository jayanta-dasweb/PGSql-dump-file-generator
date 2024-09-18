<?php
session_start();

function connectToDb()
{
    $host = $_POST['host'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $dbname = $_POST['dbname'];

    $conn = pg_connect("host=$host dbname=$dbname user=$username password=$password");

    if (!$conn) {
        echo json_encode(['error' => 'Connection failed']);
        exit;
    }
    $_SESSION["host"] = $host;
    $_SESSION["username"] = $username;
    $_SESSION["password"] = $password;
    $_SESSION["dbname"] = $dbname;

    return $conn;
}

if ($_POST['action'] == 'connect') {
    $conn = connectToDb();

    $result = pg_query($conn, "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name != 'information_schema'");
    $schemas = [];
    while ($row = pg_fetch_assoc($result)) {
        $schemas[] = ['name' => $row['schema_name']];
    }

    echo json_encode($schemas);
    pg_close($conn);
}

if ($_POST['action'] == 'getTables') {
    $conn = connectToDb();
    $schema = $_POST['schema'];

    $result = pg_query($conn, "SELECT table_name FROM information_schema.tables WHERE table_schema = '$schema'");
    $tables = [];
    while ($row = pg_fetch_assoc($result)) {
        $tables[] = ['name' => $row['table_name']];
    }

    echo json_encode($tables);
    pg_close($conn);
}

if ($_POST['action'] == 'getFields') {
    $conn = connectToDb();
    $table = $_POST['table'];

    $result = pg_query($conn, "SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = '$table'");
    $fields = [];
    while ($row = pg_fetch_assoc($result)) {
        $fields[] = [
            'name' => $row['column_name'],
            'data_type' => $row['data_type'],
            'is_nullable' => $row['is_nullable'],
            'column_default' => $row['column_default']
        ];
    }

    echo json_encode($fields);
    pg_close($conn);
}
