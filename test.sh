#!/usr/bin/env bash
# Direct-print smoke test for a Zebra GK420d (or any direct-thermal ZPL printer)
# bound to the kernel usblp driver.
#
# Usage: ./test.sh [port]
#   port: the N in /dev/usb/lpN (default: 1)
#
# Run with sudo if the device isn't writable by your user.

set -euo pipefail

PORT="${1:-1}"
DEVICE="/dev/usb/lp${PORT}"

echo "== Zebra direct-print test =="
echo "Target: $DEVICE"
echo

if [[ ! -e "$DEVICE" ]]; then
    echo "ERROR: $DEVICE does not exist." >&2
    echo
    echo "Existing /dev/usb/ entries:"
    ls -la /dev/usb/ 2>/dev/null || echo "  (directory missing)"
    echo
    echo "USB printer-class devices visible via lsusb:"
    lsusb | grep -iE 'zebra|printer' || echo "  (no Zebra/printer detected)"
    echo
    echo "If lsusb sees the printer but no /dev/usb/lpN exists, the kernel 'usblp'"
    echo "module didn't bind. Find the bus/port (e.g. 3-2) under /sys/bus/usb/devices"
    echo "with idVendor 0a5f, then bind manually:"
    echo "  echo '<bus-port>:1.0' | sudo tee /sys/bus/usb/drivers/usblp/bind"
    exit 1
fi

ls -la "$DEVICE"

if [[ ! -w "$DEVICE" ]]; then
    echo
    echo "NOTE: $DEVICE is not writable by $(id -un)." >&2
    echo "Re-run with sudo, or add your user to the 'lp' group." >&2
fi

ZPL=$'^XA\n^MD30\n^FO40,40^A0N,40,40^FDHELLO^FS\n^FO40,90^A0N,22,22^FDtest.sh OK^FS\n^XZ\n'

printf '%s' "$ZPL" > "$DEVICE"
echo
echo "Sent ZPL to $DEVICE. A label with HELLO + 'test.sh OK' should print."
