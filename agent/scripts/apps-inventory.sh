#!/usr/bin/env bash
set -euo pipefail

packages=()
if command -v dpkg &>/dev/null; then
    count="$(dpkg -l 2>/dev/null | grep -c '^ii' || echo 0)"
    packages+=("{\"type\":\"dpkg\",\"count\":${count}}")
elif command -v rpm &>/dev/null; then
    count="$(rpm -qa 2>/dev/null | wc -l | tr -d ' ')"
    packages+=("{\"type\":\"rpm\",\"count\":${count}}")
fi

containers=0
if command -v docker &>/dev/null; then
    containers="$(docker ps -q 2>/dev/null | wc -l | tr -d ' ')"
fi

echo "OK:{\"packages\":[$(IFS=,; echo "${packages[*]:-}")],\"docker_running\":${containers}}"
