<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PostgreSQL Dump Generator - Tree View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>

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
        .collapsible.selected {
            background-color: #d1e7dd;  /* Light green background */
            color: #0f5132;  /* Dark green text */
        }
        /* Preloader styles */
        .preloader {
            display: none;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        }

        #schemaDropdown{
            display: none;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">PostgreSQL Dump Generator</h1>

        <!-- Preloader -->
        <div id="preloader" class="preloader">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

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
            <button type="submit" id="connectBtn" class="btn btn-primary mt-3">Connect</button>
        </form>

        <!-- Select2 for Schema Selection -->
        <select id="schemaDropdown" class="form-control mt-3" style="width: 100%;">
            <option value="">Select a schema...</option>
        </select>

        <!-- Tree View for Schemas, Tables, and Fields -->
        <div id="treeContainer" class="mt-2 d-none" style="height: 500px; overflow-y: auto;">
            <div id="schemaList"></div>
        </div>

        <!-- Button to trigger the dump generation -->
        <button id="generateDump" class="btn btn-success mt-3 d-none">Generate SQL Dump</button>

        <!-- Button to trigger the CSV export -->
        <button id="exportCsv" class="btn btn-primary mt-3 d-none">Export CSV</button>
    </div>

    <script>
    $(document).ready(function() {
            var selectedSchemas = {};  // Store selected schemas, tables, and fields

            // Show preloader
            function showPreloader() {
                $('#preloader').show();
            }

            // Hide preloader
            function hidePreloader() {
                $('#preloader').hide();
            }

            // Handle database connection and fetching schemas
            $('#dbConnectForm').on('submit', function (e) {
                e.preventDefault();
                showPreloader();
                $.ajax({
                    url: 'process.php',
                    type: 'POST',
                    data: $(this).serialize() + '&action=connect',
                    success: function (response) {
                        var schemas = JSON.parse(response);
                        populateSchemas(schemas);
                        loadSchemaNames(schemas);
                        $('#treeContainer').removeClass('d-none');  // Show the tree view
                        hidePreloader();
                    },
                    error: function(err) {
                        console.error("Error in connection: ", err);
                        hidePreloader();
                    }
                });
            });

            // Fetch and populate schema names in Select2
            function loadSchemaNames(schemas) {
                var $dropdown = $('#schemaDropdown');
                $dropdown.empty(); // Clear previous options

                // Add schema names as options in the dropdown
                $.each(schemas, function (index, schema) {
                    $dropdown.append(new Option(schema.name, schema.name));
                });

                // Reinitialize Select2
                $dropdown.select2({
                    placeholder: "Select a schema...",
                    allowClear: true,
                });
            }

            

            // Function to dynamically render schemas with collapsible sections
            function populateSchemas(schemas) {
                var schemaList = $('#schemaList');
                schemaList.empty();  // Clear any existing content

                $.each(schemas, function(index, schema) {
                    var schemaItem = `
                        <div class="mt-2">
                            <button class="collapsible" id="collapsible_${schema.name}"><i class="fas fa-database icon"></i> ${schema.name}</button>
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
                $("#schemaDropdown").css('display','block');
            }

            // Handle Select2 schema selection
            $('#schemaDropdown').on('change', function () {
                var selectedSchema = $(this).val(); // Get selected schema name

                // Hide all schemas
                $('#schemaList > div').hide();

                // Show only the selected schema
                if (selectedSchema) {
                    $('#collapsible_' + selectedSchema).parent().show(); // Show the schema and its tables
                    fetchTablesForSelectedSchema(selectedSchema);
                }
            });

            // Function to fetch tables for the selected schema
            function fetchTablesForSelectedSchema(schemaName) {
                var formData = $('#dbConnectForm').serialize();

                $.ajax({
                    url: 'process.php',
                    type: 'POST',
                    data: formData + '&schema=' + schemaName + '&action=getTables',
                    success: function (response) {
                        var tables = JSON.parse(response);
                        var tableList = $('#tables_' + schemaName);
                        tableList.empty().removeClass('d-none'); // Clear previous tables and show new ones

                        $.each(tables, function (index, table) {
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
                error: function (err) {
                    console.error("Error fetching tables: ", err);
                }
            });
        }

        // Handle table checkbox click event to load fields dynamically
        $(document).on('change', '.table-checkbox', function () {
            var tableName = $(this).val();
            var schemaName = $(this).data('schema');
            var isChecked = $(this).is(':checked');
            var formData = $('#dbConnectForm').serialize();

            if (isChecked) {
                if (!selectedSchemas[schemaName]) {
                    selectedSchemas[schemaName] = { tables: {} };
                }

                if (!selectedSchemas[schemaName].tables[tableName]) {
                    selectedSchemas[schemaName].tables[tableName] = { fields: [] };
                }

                fetchFields(schemaName, tableName, formData);
            } else {
                delete selectedSchemas[schemaName].tables[tableName];
                $('#fields_' + tableName).addClass('d-none').empty();
            }

            toggleGenerateDumpButton();
        });

        // Function to fetch fields for a table and render them
        function fetchFields(schemaName, tableName, formData) {
            $.ajax({
                url: 'process.php',
                type: 'POST',
                data: formData + '&table=' + tableName + '&action=getFields',
                success: function (response) {
                    var fields = JSON.parse(response);
                    var fieldList = $('#fields_' + tableName);
                    fieldList.empty().removeClass('d-none');

                    $.each(fields, function (index, field) {
                        var fieldId = `field_${schemaName}_${tableName}_${field.name}`;
                        var fieldItem = `
                            <div class="form-check">
                                <input class="form-check-input field-checkbox" type="checkbox" value="${field.name}" id="${fieldId}" data-schema="${schemaName}" data-table="${tableName}">
                                <label class="form-check-label" for="${fieldId}">
                                    <i class="fas fa-columns icon"></i> ${field.name} (${field.data_type}, ${field.is_nullable ? 'NULL' : 'NOT NULL'}, ${field.column_default ? 'Default: ' + field.column_default : 'No Default'})
                                </label>
                            </div>`;
                        fieldList.append(fieldItem);
                    });
                },
                error: function (err) {
                    console.error("Error fetching fields: ", err);
                }
            });
        }

        // Handle field checkbox click event to select/deselect fields
        $(document).on('change', '.field-checkbox', function () {
            var fieldName = $(this).val();
            var tableName = $(this).data('table');
            var schemaName = $(this).data('schema');
            var isChecked = $(this).is(':checked');

            if (!selectedSchemas[schemaName]) {
                selectedSchemas[schemaName] = { tables: {} };
            }

            if (!selectedSchemas[schemaName].tables[tableName]) {
                selectedSchemas[schemaName].tables[tableName] = { fields: [] };
            }

            if (isChecked) {
                selectedSchemas[schemaName].tables[tableName].fields.push(fieldName);
            } else {
                var fieldIndex = selectedSchemas[schemaName].tables[tableName].fields.indexOf(fieldName);
                if (fieldIndex > -1) {
                    selectedSchemas[schemaName].tables[tableName].fields.splice(fieldIndex, 1);
                }
            }
            toggleGenerateDumpButton();
        });

        // Enable or disable the "Generate SQL Dump" and "Export CSV" buttons based on selections
        function toggleGenerateDumpButton() {
            var anyFieldsSelected = false;

            $.each(selectedSchemas, function (schemaName, schema) {
                $.each(schema.tables, function (tableName, table) {
                    if (table.fields.length > 0) {
                        anyFieldsSelected = true;
                    }
                });
            });

            if (anyFieldsSelected) {
                $('#generateDump').removeClass('d-none');
                $('#exportCsv').removeClass('d-none');
            } else {
                $('#generateDump').addClass('d-none');
                $('#exportCsv').addClass('d-none');
            }
        }
    });
</script>
</body>
</html>
