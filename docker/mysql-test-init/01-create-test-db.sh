#!/bin/bash

set -e

TEST_DB="${MYSQL_TEST_DATABASE:-gym_app_test}"
TEST_USER="${MYSQL_USER:-user}"

echo "[mysql-test-init] Creating test database: ${TEST_DB}"

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${TEST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${TEST_DB}\`.* TO '${TEST_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${TEST_DB}\`.* TO 'root'@'%';
FLUSH PRIVILEGES;
EOSQL

echo "[mysql-test-init] Test database '${TEST_DB}' is ready."
