# PostgreSQL Dump File Generator

A dynamic PostgreSQL Dump File Generator with a user-friendly tree view interface, allowing users to generate schema, table, and field-specific SQL dumps. This web-based tool is built using PHP, Bootstrap, and jQuery to provide an intuitive and easy-to-navigate UI.

## Features

- **Dynamic Tree View:** Displays schemas, tables, and fields in a tree structure.
- **Iconography:** Clear icons for schemas, tables, and fields to improve user experience.
- **Flexible Selection:** Users can select any combination of schemas, tables, and specific fields to generate a customized SQL dump.
- **SQL Dump Generation:** Generates SQL dumps based on selected schemas, tables, and fields.
- **Downloadable Output:** Download the generated SQL dump file for immediate use in PostgreSQL databases.

## Technologies Used

- **PHP**: Backend logic for database connection and SQL dump generation.
- **PostgreSQL**: The target database for schema and table export.
- **Bootstrap 5**: For a responsive and visually appealing design.
- **jQuery**: For managing dynamic content, AJAX requests, and tree view interaction.

## Requirements

- PHP (>=7.4)
- PostgreSQL
- A local or remote PostgreSQL database with schemas and tables.
- Basic web server setup (Apache, Nginx, etc.)
