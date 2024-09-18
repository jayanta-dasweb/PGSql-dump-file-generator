<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PostgreSQL Dump Generator - Tree View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .table-list, .field-list {
            padding-left: 20px;
            margin-top: 10px;
        }
        .icon {
            margin-right: 8px;
        }
        .collapsible {
            cursor: pointer;
            background-color: #f1f1f1;
            padding: 10px;
            border: none;
            width: 100%;
            text-align: left;
            outline: none;
            font-size: 16px;
        }
        .content {
            display: none;
            overflow: hidden;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">PostgreSQL Dump Generator</h1>

        <!-- Search bar for filtering schemas -->
        <input type="text" id="schemaSearch" class="form-control mb-3" placeholder="Search for schemas...">

        <!-- Database Connection Form -->
        <form id="dbConnectForm" class="mt-4">
            <div class="row">
                <div class="col-md-3">
                    <input type="text" name="host" class="form-control" placeholder="Host" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="col-md-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="dbname" class="form-control" placeholder="Database Name" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Connect</button>
        </form>

        <!-- Tree View for Schemas, Tables, and Fields -->
        <div id="treeContainer" class="mt-5 d-none">
            <h3>Schemas</h3>
            <div id="schemaList"></div>
        </div>

        <!-- Button to trigger the dump generation -->
        <button id="generateDump" class="btn btn-success mt-3 d-none">Generate SQL Dump</button>
    </div>

    <script>
        $(document).ready(function() {
            var selectedSchemas = {};  // Store selected schemas, tables, and fields

            // Handle database connection and fetching schemas
            $('#dbConnectForm').on('submit', function (e) {
                e.preventDefault();
                $.ajax({
                    url: 'process.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=connect',
                    success: function (response) {
                        var schemas = JSON.parse(response);
                        populateSchemas(schemas);
                        $('#treeContainer').removeClass('d-none');  // Show the tree view
                    },
                    error: function(err) {
                        console.error("Error in connection: ", err);
                    }
                });
            });

            // Function to dynamically render schemas with collapsible sections
            function populateSchemas(schemas) {
                var schemaList = $('#schemaList');
                schemaList.empty();  // Clear any existing content

                $.each(schemas, function(index, schema) {
                    var schemaItem = `
                        <div>
                            <button class="collapsible"><i class="fas fa-database icon"></i> ${schema.name}</button>
                            <div class="content">
                                <div class="table-list d-none" id="tables_${schema.name}"></div>
                            </div>
                        </div>`;
                    schemaList.append(schemaItem);
                });

                // Add collapsible functionality
                $('.collapsible').on('click', function() {
                    this.classList.toggle('active');
                    var content = $(this).next(".content");
                    content.toggle();  // Toggle visibility of the content (tables)

                    // Load tables only when the schema is expanded
                    var schemaName = $(this).text().trim();
                    if (content.is(':visible') && !selectedSchemas[schemaName]) {
                        fetchTables(schemaName);
                    }
                });
            }

            // Add search functionality
            $('#schemaSearch').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#schemaList div button').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });

            // Function to fetch tables for a schema and render them
            function fetchTables(schemaName) {
                var formData = $('#dbConnectForm').serialize();  // Get connection credentials
                $.ajax({
                    url: 'process.php',
                    type: 'POST',
                    data: formData + '&schema=' + schemaName + '&action=getTables',
                    success: function(response) {
                        var tables = JSON.parse(response);
                        var tableList = $('#tables_' + schemaName);
                        tableList.empty().removeClass('d-none');

                        $.each(tables, function(index, table) {
                            var tableItem = `
                                <div class="form-check">
                                    <input class="form-check-input table-checkbox" type="checkbox" value="${table.name}" id="table_${table.name}" data-schema="${schemaName}">
                                    <label class="form-check-label" for="table_${table.name}">
                                        <i class="fas fa-table icon"></i> ${table.name}
                                    </label>
                                    <div class="field-list d-none" id="fields_${table.name}"></div>
                                </div>`;
                            tableList.append(tableItem);
                        });
                    },
                    error: function(err) {
                        console.error("Error fetching tables: ", err);
                    }
                });
            }

            // Handle table checkbox click event to load fields dynamically
            $(document).on('change', '.table-checkbox', function() {
                var tableName = $(this).val();
                var schemaName = $(this).data('schema'); // Fetch schema name from the data attribute
                var isChecked = $(this).is(':checked');
                var formData = $('#dbConnectForm').serialize();  // Get connection credentials

                // Check if schema exists before accessing tables
                if (!selectedSchemas[schemaName]) {
                    selectedSchemas[schemaName] = { tables: {} };
                }

                if (isChecked) {
                    // Add table to the selectedSchemas object if it doesn't already exist
                    if (!selectedSchemas[schemaName].tables[tableName]) {
                        selectedSchemas[schemaName].tables[tableName] = { fields: [] };
                    }

                    // Fetch fields for this table, including connection credentials
                    fetchFields(schemaName, tableName, formData);  // Pass schemaName and tableName
                } else {
                    // Remove table from selectedSchemas
                    delete selectedSchemas[schemaName].tables[tableName];
                    $('#fields_' + tableName).addClass('d-none').empty();
                }
                toggleGenerateDumpButton();  // Toggle the dump button visibility
            });

            // Function to fetch fields for a table and render them
            function fetchFields(schemaName, tableName, formData) {
                $.ajax({
                    url: 'process.php',
                    type: 'POST',
                    data: formData + '&table=' + tableName + '&action=getFields',
                    success: function(response) {
                        var fields = JSON.parse(response);
                        var fieldList = $('#fields_' + tableName);
                        fieldList.empty().removeClass('d-none');

                        $.each(fields, function(index, field) {
                            var fieldItem = `
                                <div class="form-check">
                                    <input class="form-check-input field-checkbox" type="checkbox" value="${field.name}" id="field_${field.name}" data-schema="${schemaName}" data-table="${tableName}">
                                    <label class="form-check-label" for="field_${field.name}">
                                        <i class="fas fa-columns icon"></i> ${field.name} (${field.data_type}, ${field.is_nullable ? 'NULL' : 'NOT NULL'}, ${field.column_default ? 'Default: ' + field.column_default : 'No Default'})
                                    </label>
                                </div>`;
                            fieldList.append(fieldItem);
                        });
                    },
                    error: function(err) {
                        console.error("Error fetching fields: ", err);
                    }
                });
            }

            // Handle field checkbox click event to select/deselect fields
            $(document).on('change', '.field-checkbox', function() {
                var fieldName = $(this).val();
                var tableName = $(this).data('table');  // Use data attribute to get the table name
                var schemaName = $(this).data('schema');  // Use data attribute to get the schema name
                var isChecked = $(this).is(':checked');

                // Check if schema exists before adding fields
                if (!selectedSchemas[schemaName]) {
                    selectedSchemas[schemaName] = { tables: {} };
                }
                if (!selectedSchemas[schemaName].tables[tableName]) {
                    selectedSchemas[schemaName].tables[tableName] = { fields: [] };
                }

                if (isChecked) {
                    // Add the field to the selected fields array
                    selectedSchemas[schemaName].tables[tableName].fields.push(fieldName);
                } else {
                    // Remove the field from the selected fields array
                    var fieldIndex = selectedSchemas[schemaName].tables[tableName].fields.indexOf(fieldName);
                    if (fieldIndex > -1) {
                        selectedSchemas[schemaName].tables[tableName].fields.splice(fieldIndex, 1);
                    }
                }
                toggleGenerateDumpButton();  // Toggle the dump button visibility
            });

            // Enable or disable the "Generate SQL Dump" button based on selections
            function toggleGenerateDumpButton() {
                var anyFieldsSelected = false;

                // Check if there are any selected fields
                $.each(selectedSchemas, function(schemaName, schema) {
                    $.each(schema.tables, function(tableName, table) {
                        if (table.fields.length > 0) {
                            anyFieldsSelected = true;
                        }
                    });
                });

                if (anyFieldsSelected) {
                    $('#generateDump').removeClass('d-none');
                } else {
                    $('#generateDump').addClass('d-none');
                }
            }

            // Handle "Generate SQL Dump" button click
            $('#generateDump').on('click', function() {
                var formData = $('#dbConnectForm').serialize();
                var selectedData = JSON.stringify(selectedSchemas);

                $.ajax({
                    url: './dump.php',
                    type: 'POST',
                    data: formData + '&selectedData=' + selectedData,
                    success: function(response) {
                        window.location.href = response;  // Download the generated SQL dump
                    },
                    error: function(err) {
                        console.error("Error generating dump: ", err);
                    }
                });
            });
        });
    </script>
</body>
</html>
