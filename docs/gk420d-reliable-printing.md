# Zebra GK420d Reliable Printing

This note documents the failure modes that made a GK420d print blank labels and
the deployment pattern currently used by depot.

## Root Cause

The recurring blank-label bug was a media-width mismatch. A GK420d ships with a
stored print width of 832 dots, roughly 4 inches at 203 dpi. When narrower stock
is loaded, such as 2.25 x 1.25 inch labels, a ZPL job that does not explicitly
set `^PW` and `^LL` can place content outside the physical label. The printer
receives and parses the job, feeds a label, and silently prints into the gap.

The printer can also enter a non-ZPL interpreter state, likely line or diagnostic
mode. Keep the interpreter guard, but treat media dimensions as part of every
print job.

Symptoms:

- blank or partial labels even though the label feeds;
- output after hardware button actions that indicates non-ZPL mode;
- print jobs that looked successful from Linux but produced no useful label.

## Media Diagnostic

For 2.25 x 1.25 inch labels at 203 dpi, force the media dimensions in the job:

```bash
printf '%s' '^XA^PW457^LL254^FO20,20^A0N,30,30^FDHELLO^FS^XZ' > /dev/usb/lp0
```

If `HELLO` appears, transport and ZPL parsing are working. The prior blank label
was caused by missing or wrong `^PW`/`^LL`.

## Recovery

Factory reset the printer from hardware:

1. Power the printer off.
2. Hold Feed while powering on.
3. Release Feed at 4 flashes.

That reset restores the interpreter to ZPL if the printer is in the wrong mode.
It does not remove the need for per-job media dimensions.

## Depot Architecture

In the SSAI/depot deployment, only depot should talk to the physical printer.
SSAI creates or queues ZPL; depot claims print jobs and sends them to the printer.

The local depot machine currently uses a CUPS queue:

```bash
lpadmin -p zebra -E -v 'usb://Zebra/...' -m raw
cupsenable zebra
cupsaccept zebra
```

This is a deployment choice, not a root-cause claim. The root cause was the
printer interpreter state. If another transport is proven more reliable on the
depot host, keep the same queue boundary and change depot's local print command.

Do not make the hosted web app print directly.

Bundle configuration:

```yaml
survos_zebra:
  printers:
    zebra:
      type: cups
      queue: zebra
      dpi: 203
      label_width_in: 2.25
      label_height_in: 1.25
  default_printer: zebra
```

Depot requires an explicit printer queue name:

```dotenv
ZEBRA_PRINTER_NAME=zebra
```

If this variable is missing, depot should fail loudly instead of guessing.

## Printing Contract

Before every label payload, force the printer back to ZPL mode:

```zpl
^XA^SZ2^XZ
```

Then send the label with explicit print width and label length:

```zpl
^XA
^PW457
^LL254
...label content...
^XZ
```

Depot prepends the guard before sending ZPL jobs. The bundle also injects `^PW`
and `^LL` from the configured printer profile.

Equivalent CLI smoke test:

```bash
printf '%s' "$ZPL" | lp -d zebra -o raw
```

## Failure Detection

If the printer outputs blank labels or `|______|`, treat it as an interpreter-layer
failure, not a label-template problem.

Agent routine:

1. Attempt print.
2. If output is blank, raise `ZEBRA_INTERPRETER_BROKEN`.
3. Human resets the printer with the 4-flash Feed-button factory reset.

Optional health check:

```zpl
^XA^HH^XZ
```

If no configuration label prints, the printer is probably not interpreting ZPL.

Bundle health check:

```php
$printerService->testLabel('zebra');
```

This emits a small `HELLO` label using the configured `dpi`, `label_width_in`, and
`label_height_in`.

New-media calibration:

```php
$printerService->calibrate('zebra');
```

## Avoid

Avoid encoding the wrong lesson:

- do not treat CUPS as the fix for a bad printer reset;
- do not make the web app responsible for printer hardware access;
- do not assume blank labels mean the ZPL template is wrong;
- do not rely on opaque printer-stored `^PW` and `^LL` defaults;
- do not casually persist `^JUS` settings in multi-printer deployments;
- do not assume interpreter state is persistent across failures.

## Mental Model

The printer has three independent layers:

1. transport: USB, CUPS, TCP, BrowserPrint;
2. interpreter: ZPL, EPL, line, diagnostic;
3. media and calibration.

The width-mismatch failure sits at the media layer. The `^XA^SZ2^XZ` guard makes
each print job reassert ZPL before sending the label. The `^PW` and `^LL` commands
make each print job independent of printer-stored media defaults. Transport still
needs to be validated on the actual depot machine.
