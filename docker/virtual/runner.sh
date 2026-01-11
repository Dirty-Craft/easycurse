#!/bin/sh

while true; do
    echo "Checking for new run requests..."
    files=$(find /shared/virtual -type f -name "runner.pick")
    for file in $files; do
        if [ -n "$file" ]; then
            echo "Processing: $file"
            rm "$file"
            parent_dir=$(dirname "$file")
            docker run --rm -v "$parent_dir":/workspace -w /workspace eclipse-temurin:21-jdk sh run.sh
        fi
    done
    sleep 1
done
