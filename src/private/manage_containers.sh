#!/bin/bash

# Full path to docker and logger
DOCKER_BIN="/usr/bin/docker"
LOG_FILE="/var/www/html/logs/container_operations.log"

# Verify Docker binary exists and is executable
if [[ ! -x "$DOCKER_BIN" ]]; then
    echo "Docker binary not found or not executable"
    exit 1
fi

# Move logging to PHP for now...

log_action() {
    # local ACTION="$1"
    # local CONTAINER="$2"
    # local RESULT="$3"
    # local EXEC_USER
    # local TIMESTAMP

    # EXEC_USER=$(whoami)
    # TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

    # Ensure log path exists BEFORE writing
    # mkdir -p "$(dirname "$LOG_FILE")"
    # touch "$LOG_FILE"
    # chmod 640 "$LOG_FILE"

    # echo "$TIMESTAMP | USER=$EXEC_USER | CONTAINER=$CONTAINER | ACTION=$ACTION | RESULT=$RESULT" >> "$LOG_FILE"
    echo "logging disabled"
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

        if [[ -z "$CONTAINER_NAME" ]]; then
            echo "Container name required"
            exit 1
        fi

        validate_container_name "$CONTAINER_NAME"
        RESULT=$($DOCKER_BIN start "$CONTAINER_NAME" 2>&1)
        STATUS=$?
        # log_action "START" "$CONTAINER_NAME" "$RESULT"
        echo "$RESULT"
        exit $STATUS
        ;;
    stop)
        CONTAINER_NAME="$2"

        if [[ -z "$CONTAINER_NAME" ]]; then
            echo "Container name required"
            exit 1
        fi

        validate_container_name "$CONTAINER_NAME"
        RESULT=$($DOCKER_BIN stop "$CONTAINER_NAME" 2>&1)
        STATUS=$?
        # log_action "STOP" "$CONTAINER_NAME" "$RESULT"
        echo "$RESULT"
        exit $STATUS
        ;;
    status)
        CONTAINER_NAME="$2"

        if [[ -z "$CONTAINER_NAME" ]]; then
            echo "Container name required"
            exit 1
        fi

        validate_container_name "$CONTAINER_NAME"
        RESULT=$($DOCKER_BIN inspect -f '{{.State.Status}}' "$CONTAINER_NAME" 2>&1)
        STATUS=$?
        # log_action "STATUS" "$CONTAINER_NAME" "$RESULT"
        echo "$RESULT"
        exit $STATUS
        ;;
    logs)
        CONTAINER_NAME="$2"

        if [[ -z "$CONTAINER_NAME" ]]; then
            echo "Container name required"
            exit 1
        fi

        validate_container_name "$CONTAINER_NAME"

        LINES=${3:-30}

        # Enforce numeric-only log line input
        if ! [[ "$LINES" =~ ^[0-9]+$ ]]; then
            echo "Invalid log line count"
            exit 1
        fi

        FULL_LOGS=$($DOCKER_BIN logs "$CONTAINER_NAME" 2>&1)
        STATUS=$?

        RESULT=$(echo "$FULL_LOGS" | tail -n "$LINES")

        # log_action "LOGS" "$CONTAINER_NAME" "Retrieved last $LINES lines"
        echo "$RESULT"
        exit $STATUS
        ;;
    list)
        # List all containers with their status
        RESULT=$($DOCKER_BIN ps -a --format "{{.Names}},{{.Status}},{{.Image}},{{.Ports}}" 2>&1)
        STATUS=$?
        # log_action "LIST" "ALL" "Listed all containers"
        echo "$RESULT"
        exit $STATUS
        ;;
    *)
        # log_action "INVALID" "${2:-unknown}" "Invalid action: $1"
        echo "Usage: $0 {start|stop|status|logs|list} [container_name] [log_lines]"
        exit 1
        ;;
esac
