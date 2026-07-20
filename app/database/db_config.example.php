<?php
// Example DB config via environment variables.
// Copy values into your environment (do NOT commit secrets).
//
// Required:
//   EREBUS_DB_HOST=127.0.0.1
//   EREBUS_DB_PORT=3306
//   EREBUS_DB_NAME=erebus_threat_intel_prod
//   EREBUS_DB_USER=erebus_web
//   EREBUS_DB_PASSWORD=your_password
//   EREBUS_DB_SOCKET=/run/mysqld/mysqld.sock   # optional Linux/MariaDB local socket
//
// Optional split-catalog support for Permission Intel:
//   EREBUS_PERMISSION_INTEL_DB_NAME=android_permission_intel
//
// IMPORTANT:
// - This web app can read split PI catalogs only when both catalogs are reachable
//   through the same MariaDB host/user/socket connection.
// - Separate PI credentials/hosts are not supported by the current single-PDO design.
