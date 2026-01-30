#!/bin/sh

# Function to cleanup directory, keeping only logs.txt
cleanup_directory() {
    local dir=$1
    local logs_file="$dir/logs.txt"
    
    if [ ! -d "$dir" ]; then
        return
    fi
    
    echo "Cleaning up directory: $dir (keeping logs.txt)"
    # Delete all files except logs.txt
    find "$dir" -type f ! -name "logs.txt" -delete
    # Delete all subdirectories
    find "$dir" -mindepth 1 -type d -exec rm -rf {} + 2>/dev/null || true
}

# Function to monitor logs and kill container when server is done
monitor_and_kill() {
    local container_name=$1
    local logs_file=$2
    local parent_dir=$(dirname "$logs_file")
    local wait_timeout=60  # Wait up to 60 seconds for log file to appear
    local monitor_timeout=300  # Monitor for up to 5 minutes
    local elapsed=0
    
    # Wait for log file to be created (and check for user-requested stop)
    while [ ! -f "$logs_file" ] && [ $elapsed -lt $wait_timeout ]; do
        if [ -f "$parent_dir/runner.stop" ]; then
            echo "User requested stop for $container_name, stopping container..."
            docker stop "$container_name" > /dev/null 2>&1
            cleanup_directory "$parent_dir"
            return
        fi
        sleep 1
        elapsed=$((elapsed + 1))
    done
    
    if [ ! -f "$logs_file" ]; then
        echo "Warning: Log file not created for $container_name after ${wait_timeout}s, stopping container"
        docker stop "$container_name" > /dev/null 2>&1
        cleanup_directory "$parent_dir"
        return
    fi
    
    # Monitor log file for completion message or user-requested stop
    elapsed=0
    while [ $elapsed -lt $monitor_timeout ]; do
        if [ -f "$parent_dir/runner.stop" ]; then
            echo "User requested stop for $container_name, stopping container..."
            docker stop "$container_name" > /dev/null 2>&1
            cleanup_directory "$parent_dir"
            return
        fi
        if grep -q "\[Server thread/INFO\]: Done" "$logs_file" 2>/dev/null; then
            echo "Server initialization complete for $container_name, stopping container..."
            docker stop "$container_name" > /dev/null 2>&1
            cleanup_directory "$parent_dir"
            return
        fi
        sleep 1
        elapsed=$((elapsed + 1))
    done
    
    # Timeout reached, kill the container
    echo "Timeout reached for $container_name (${monitor_timeout}s), stopping container..."
    docker stop "$container_name" > /dev/null 2>&1
    cleanup_directory "$parent_dir"
}

# Function to start a Minecraft server run
start_server_run() {
    local parent_dir=$1
    local dir_name=$(basename "$parent_dir")
    local timestamp=$(date +%s)
    local container_name="minecraft-run-${dir_name}-${timestamp}-$$"
    local logs_file="${parent_dir}/logs.txt"
    
    echo "Starting server run: $container_name"
    
    # Start container in background
    docker run --rm --name "$container_name" \
        -v "$parent_dir":/workspace \
        -w /workspace \
        eclipse-temurin:21-jdk sh run.sh > /dev/null 2>&1 &
    
    # Give container a moment to start
    sleep 2
    
    # Start monitoring process in background
    monitor_and_kill "$container_name" "$logs_file" &
}

# Cleanup function for background processes
cleanup() {
    echo "Cleaning up..."
    # Kill all background monitoring processes
    for job in $(jobs -p); do
        kill "$job" 2>/dev/null
    done
    exit 0
}

trap cleanup INT TERM

while true; do
    echo "Checking for new run requests..."
    files=$(find /shared/virtual -type f -name "runner.pick" 2>/dev/null)
    
    if [ -n "$files" ]; then
        for file in $files; do
            if [ -f "$file" ]; then
                parent_dir=$(dirname "$file")
                # If user already requested stop, skip starting this run
                if [ -f "$parent_dir/runner.stop" ]; then
                    echo "Skipping run (stop requested): $parent_dir"
                    rm -f "$file" "$parent_dir/runner.stop"
                    continue
                fi
                echo "Processing: $file"
                # Remove the pick file before starting to avoid reprocessing
                rm "$file"
                # Start the server run
                start_server_run "$parent_dir"
            fi
        done
    fi
    
    sleep 1
done
