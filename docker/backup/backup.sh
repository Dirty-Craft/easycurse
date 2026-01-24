#!/bin/bash

# Database backup script
# This script creates a daily backup of the MariaDB database

# We'll handle errors manually in critical sections
set +e

# Get environment variables with defaults
DB_HOST="${DB_HOST:-db}"
DB_NAME="${DB_NAME:-easycurse_db}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_ROOT_PASSWORD:-toor}"
BACKUP_DIR="/backup"
BACKUP_SCHEDULE="${BACKUP_SCHEDULE:-0 2 * * *}"  # Default: 2 AM daily (cron format)

# Determine which commands to use (mariadb-* preferred, fallback to mysql-*)
if command -v mariadb-dump >/dev/null 2>&1; then
    MYSQLDUMP_CMD="mariadb-dump"
elif command -v mysqldump >/dev/null 2>&1; then
    MYSQLDUMP_CMD="mysqldump"
else
    echo "Error: Neither mariadb-dump nor mysqldump found"
    exit 1
fi

# Function to perform backup
perform_backup() {
    set -e  # Enable strict error checking for backup operations
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_FILE="${BACKUP_DIR}/backup_${TIMESTAMP}.sql"
    ZIP_FILE="${BACKUP_DIR}/backup_${TIMESTAMP}.zip"
    
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] Starting database backup..."
    
    # Create database dump using MYSQL_PWD for authentication
    export MYSQL_PWD="${DB_PASSWORD}"
    ${MYSQLDUMP_CMD} -h "${DB_HOST}" \
                      -u "${DB_USER}" \
                      --skip-ssl \
                      --single-transaction \
                      --routines \
                      --triggers \
                      "${DB_NAME}" > "${BACKUP_FILE}"
    DUMP_EXIT_CODE=$?
    unset MYSQL_PWD
    
    if [ ${DUMP_EXIT_CODE} -ne 0 ]; then
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] Error: Failed to create database dump (exit code: ${DUMP_EXIT_CODE})"
        set +e
        return 1
    fi
    
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] Database dump created successfully"
    
    # Create zip file with database backup
    cd "${BACKUP_DIR}"
    if ! zip -q "${ZIP_FILE}" "${BACKUP_FILE}"; then
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] Error: Failed to compress backup"
        set +e
        return 1
    fi
    
    # Add logs.txt files from docker/virtual directory, preserving directory structure
    VIRTUAL_DIR="/virtual"
    if [ -d "${VIRTUAL_DIR}" ]; then
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] Adding logs.txt files from virtual directory..."
        cd "${VIRTUAL_DIR}"
        # Find all logs.txt files and add them to zip preserving directory structure
        # Create a temporary directory to build the virtual/ structure
        TEMP_DIR=$(mktemp -d)
        find . -type f -name "logs.txt" | while read -r log_file; do
            # Remove leading ./ from path
            relative_path="${log_file#./}"
            # Create directory structure in temp dir
            dir_path=$(dirname "${relative_path}")
            mkdir -p "${TEMP_DIR}/virtual/${dir_path}"
            # Copy file to temp structure
            cp "${log_file}" "${TEMP_DIR}/virtual/${relative_path}"
        done
        # Add the virtual directory structure to zip if any logs.txt files were found
        if [ -d "${TEMP_DIR}/virtual" ] && [ "$(find "${TEMP_DIR}/virtual" -type f | wc -l)" -gt 0 ]; then
            cd "${TEMP_DIR}"
            zip -q -r "${ZIP_FILE}" virtual/ 2>/dev/null || true
            echo "[$(date +'%Y-%m-%d %H:%M:%S')] Added logs.txt files to backup"
        else
            echo "[$(date +'%Y-%m-%d %H:%M:%S')] No logs.txt files found in virtual directory"
        fi
        rm -rf "${TEMP_DIR}"
    else
        echo "[$(date +'%Y-%m-%d %H:%M:%S')] Virtual directory not found, skipping logs.txt files"
    fi
    
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] Backup compressed: ${ZIP_FILE}"
    # Remove SQL file after zipping
    rm -f "${BACKUP_FILE}"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] Backup completed successfully"
    set +e
    return 0
}

# Wait for database to be ready
echo "Waiting for database to be ready..."
echo "Connecting to database at ${DB_HOST} as ${DB_USER}..."
echo "Database name: ${DB_NAME}"
echo "Password set: $([ -n "${DB_PASSWORD}" ] && echo 'Yes' || echo 'No')"

# Verify we have the password
if [ -z "${DB_PASSWORD}" ]; then
    echo "Error: DB_PASSWORD is not set. Check that DB_ROOT_PASSWORD environment variable is set."
    exit 1
fi

export MYSQL_PWD="${DB_PASSWORD}"
MAX_WAIT=300  # Maximum wait time in seconds (5 minutes)
WAIT_COUNT=0

# Give the database a moment to be fully ready even after healthcheck passes
sleep 5

# Determine mysql client command
if command -v mariadb >/dev/null 2>&1; then
    MYSQL_CMD="mariadb"
elif command -v mysql >/dev/null 2>&1; then
    MYSQL_CMD="mysql"
else
    echo "Error: Neither mariadb nor mysql client found"
    exit 1
fi

# Test connection with better error handling
# Disable exit on error during connection attempts (we'll handle errors manually)
set +e
ATTEMPT_COUNT=0
while true; do
    ATTEMPT_COUNT=$((ATTEMPT_COUNT + 1))
    
    # First, try to connect without specifying database (just test server connectivity)
    CONNECTION_ERROR=$(${MYSQL_CMD} -h "${DB_HOST}" -u "${DB_USER}" --skip-ssl -e "SELECT 1;" 2>&1)
    CONNECTION_EXIT_CODE=$?
    
    if [ ${CONNECTION_EXIT_CODE} -eq 0 ]; then
        # Server is up, now try to connect to the specific database
        DB_CONNECTION_ERROR=$(${MYSQL_CMD} -h "${DB_HOST}" -u "${DB_USER}" --skip-ssl "${DB_NAME}" -e "SELECT 1;" 2>&1)
        DB_CONNECTION_EXIT_CODE=$?
        
        if [ ${DB_CONNECTION_EXIT_CODE} -eq 0 ]; then
            echo "Database connection successful!"
            break
        else
            # Database might not exist yet, wait a bit more
            if [ $((WAIT_COUNT % 10)) -eq 0 ]; then
                echo "Database server is up but database '${DB_NAME}' is not ready yet. Waiting... (${WAIT_COUNT}s/${MAX_WAIT}s)"
                if [ ${ATTEMPT_COUNT} -le 5 ]; then
                    echo "Connection error: ${DB_CONNECTION_ERROR}"
                fi
            fi
        fi
    else
        # Server is not up yet
        if [ $((WAIT_COUNT % 10)) -eq 0 ]; then
            echo "Database server is not ready yet. Waiting... (${WAIT_COUNT}s/${MAX_WAIT}s)"
            if [ ${ATTEMPT_COUNT} -le 5 ]; then
                echo "Connection error: ${CONNECTION_ERROR}"
            fi
        fi
    fi
    
    WAIT_COUNT=$((WAIT_COUNT + 2))
    if [ ${WAIT_COUNT} -ge ${MAX_WAIT} ]; then
        echo "Warning: Database did not become ready within ${MAX_WAIT} seconds"
        echo "Attempted to connect to: ${DB_HOST}"
        echo "User: ${DB_USER}"
        echo "Database: ${DB_NAME}"
        echo "Testing connection (showing errors)..."
        ${MYSQL_CMD} -h "${DB_HOST}" -u "${DB_USER}" --skip-ssl -e "SELECT 1;" 2>&1 || true
        echo "Please check:"
        echo "  1. Database container is running: docker compose -f docker-compose.prod.yml ps db"
        echo "  2. Database container logs: docker compose -f docker-compose.prod.yml logs db"
        echo "  3. Network connectivity from backuper container"
        echo "  4. Database credentials in .env file match DB_ROOT_PASSWORD"
        # Don't exit - instead, keep retrying indefinitely to avoid container restart loop
        echo "Will continue retrying connection (resetting wait counter)..."
        WAIT_COUNT=0
        sleep 10
        continue
    fi
    
    sleep 2
done
unset MYSQL_PWD

echo "Database is ready. Starting backup scheduler..."

# Parse cron schedule format: "MINUTE HOUR * * *"
# Extract hour and minute from BACKUP_SCHEDULE
if [[ "${BACKUP_SCHEDULE}" =~ ^([0-9]+)\ ([0-9]+)\ \*\ \*\ \*$ ]]; then
    MINUTE="${BASH_REMATCH[1]}"
    HOUR="${BASH_REMATCH[2]}"
    
    # Pad with leading zero if needed
    HOUR=$(printf "%02d" "${HOUR}")
    MINUTE=$(printf "%02d" "${MINUTE}")
    
    echo "Backup scheduled to run daily at ${HOUR}:${MINUTE}"
else
    # Default to 2 AM if format is invalid
    HOUR="02"
    MINUTE="00"
    echo "Invalid schedule format, using default: daily at 02:00"
fi
perform_backup
# Main loop - check every minute if it's time to backup
LAST_BACKUP_DATE=""
while true; do
    CURRENT_HOUR=$(date +%H)
    CURRENT_MINUTE=$(date +%M)
    CURRENT_DATE=$(date +%Y-%m-%d)
    
    # Check if it's the right time and we haven't backed up today
    if [ "${CURRENT_HOUR}" -eq "${HOUR}" ] && [ "${CURRENT_MINUTE}" -eq "${MINUTE}" ] && [ "${LAST_BACKUP_DATE}" != "${CURRENT_DATE}" ]; then
        if perform_backup; then
            LAST_BACKUP_DATE="${CURRENT_DATE}"
        else
            echo "[$(date +'%Y-%m-%d %H:%M:%S')] Backup failed, will retry at next scheduled time"
        fi
        # Sleep for 60 seconds to avoid running multiple times in the same minute
        sleep 60
    fi
    
    # Check every minute
    sleep 60
done
