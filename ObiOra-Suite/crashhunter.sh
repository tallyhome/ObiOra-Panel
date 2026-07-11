#!/bin/bash
# LEGACY — Remplacé par le daemon Python Crash Hunter.
# Installation: cd ObiOra-Suite && sudo ./install.sh
# Ce script est conservé comme référence / fallback minimal.
BASE="/opt/crashhunter"
LOG="$BASE/logs"
STATE="$BASE/state"
RING="$LOG/ring"
INTERVAL=5
MAX=720
mkdir -p "$LOG" "$STATE" "$RING"
BOOTFILE="$STATE/last_boot"
BOOTID=$(cat /proc/sys/kernel/random/boot_id)
if [ -f "$BOOTFILE" ]; then
 LAST=$(cat "$BOOTFILE")
 if [ "$LAST" != "$BOOTID" ]; then
   mkdir -p "$LOG/reports"
   echo "Reboot detected $(date -Is)" > "$LOG/reports/report-$(date +%F-%H%M%S).txt"
 fi
fi
echo "$BOOTID" > "$BOOTFILE"
count=0
while true; do
 TS=$(date +"%Y-%m-%dT%H:%M:%S")
 FILE="$RING/snap_$count.json"
 LOAD=$(cat /proc/loadavg)
 MEM=$(free -m)
 TOP=$(ps -eo pid,comm,%cpu,%mem --sort=-%cpu | head -15)
 VMS=$(virsh list --all 2>/dev/null)
 IO=$(iostat -xz 1 1 2>/dev/null)
 NET=$(ip -s link)
 KLOG=$(journalctl -k -n 20 --no-pager 2>/dev/null)
 cat > "$FILE" <<EOF
{
 "timestamp":"$TS",
 "load":"$LOAD",
 "memory":"$(echo "$MEM"|tr '\n' '|')",
 "top":"$(echo "$TOP"|tr '\n' '|')",
 "vms":"$(echo "$VMS"|tr '\n' '|')",
 "iostat":"$(echo "$IO"|tr '\n' '|')",
 "network":"$(echo "$NET"|tr '\n' '|')",
 "kernel":"$(echo "$KLOG"|tr '\n' '|')"
}
EOF
 echo "$KLOG" | grep -Eiq "panic|watchdog|oom|hung|stall|segfault" && echo "$TS ALERT kernel anomaly" >> "$LOG/alerts.log"
 count=$(( (count+1)%MAX ))
 sleep "$INTERVAL"
done
