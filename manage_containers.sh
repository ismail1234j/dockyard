#!/bin/bash

# Full path to docker and logger
DOCKER_BIN="/usr/bin/docker"
LOG_FILE="/var/www/html/logs/container_operations.log"

# User executing this command
EXEC_USER=$(whoami)
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

log_action() {
    echo "$TIMESTAMP | USER=$EXEC_USER | CONTAINER=$2 | ACTION=$1 | RESULT=$3" >> "$LOG_FILE"
    mkdir -p "$(dirname "$LOG_FILE")"
    touch "$LOG_FILE"
    chmod 666 "$LOG_FILE"
}

# Function to validate container name to prevent command injection
validate_container_name() {
    if [[ ! "$1" =~ ^[a-zA-Z0-9_.-]+$ ]]; then
        echo "Invalid container name"
        exit 1
    fi
}

case "$1" in
    start)
        CONTAINER_NAME="$2"
        validate_container_name "$CONTAINER_NAME"
        RESULT=$($DOCKER_BIN start "$CONTAINER_NAME" 2>&1)
        STATUS=$?
        log_action "START" "$CONTAINER_NAME" "$RESULT"
        echo "$RESULT"
        exit $STATUS
        ;;
    stop)
        CONTAINER_NAME="$2"
        validate_container_name "$CONTAINER_NAME"
        RESULT=$($DOCKER_BIN stop "$CONTAINER_NAME" 2>&1)
        STATUS=$?
        log_action "STOP" "$CONTAINER_NAME" "$RESULT"
        echo "$RESULT"
        exit $STATUS
        ;;
    status)
        CONTAINER_NAME="$2"
        validate_container_name "$CONTAINER_NAME"
        RESULT=$($DOCKER_BIN inspect -f '{{.State.Status}}' "$CONTAINER_NAME" 2>&1)
        STATUS=$?
        log_action "STATUS" "$CONTAINER_NAME" "$RESULT"
        echo "$RESULT"
        exit $STATUS
        ;;
    logs)
        CONTAINER_NAME="$2"
        validate_container_name "$CONTAINER_NAME"
        LINES=${3:-30}  # Default to 30 lines if not specified
        RESULT=$($DOCKER_BIN logs "$CONTAINER_NAME" 2>&1 | tail -n "$LINES")
        STATUS=$?
        log_action "LOGS" "$CONTAINER_NAME" "Retrieved last $LINES lines"
        echo "$RESULT"
        exit $STATUS
        ;;
    list)
        # List all containers with their status
        RESULT=$($DOCKER_BIN ps -a --format "{{.Names}},{{.Status}},{{.Image}},{{.Ports}}" 2>&1)
        STATUS=$?
        log_action "LIST" "ALL" "Listed all containers"
        echo "$RESULT"
        exit $STATUS
        ;;
    *)
        log_action "INVALID" "${2:-unknown}" "Invalid action: $1"
        echo "Usage: $0 {start|stop|status|logs|list} [container_name] [log_lines]"
        exit 1
        ;;
esac