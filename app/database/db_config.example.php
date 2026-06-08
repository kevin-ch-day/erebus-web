<?php
// Example DB config via environment variables.
// Copy values into your environment (do NOT commit secrets).
//
// Required:
//   DB_HOST=127.0.0.1
//   DB_PORT=3306
//   DB_NAME=erebus_threat_intel_prod
//   DB_USER=root
//   DB_PASS=your_password
//   DB_SOCKET=/run/mysqld/mysqld.sock   # optional Linux/MariaDB local socket
//
// Optional split-catalog support for Permission Intel:
//   PERMISSION_INTEL_DB_NAME=android_permission_intel
//
// IMPORTANT:
// - This web app can read split PI catalogs only when both catalogs are reachable
//   through the same MariaDB host/user/socket connection.
// - Separate PI credentials/hosts are not supported by the current single-PDO design.
