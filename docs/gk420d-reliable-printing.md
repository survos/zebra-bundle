# Zebra GK420d Reliable Printing

This note documents the failure mode that made a GK420d print blank labels and
`|______|`, and the deployment pattern currently used by depot.

## Root Cause

The printer entered a non-ZPL interpreter state, likely line or diagnostic mode.
That was the real failure. Transport debugging was misleading because the printer
was not interpreting ZPL correctly, so several otherwise reasonable transport tests
looked broken too.

Symptoms:

- blank labels for ZPL, EPL, and SGD commands;
- `|______|` output after hardware button actions;
- print jobs that looked successful from Linux but produced no useful label.

## Recovery

Factory reset the printer from hardware:

1. Power the printer off.
2. Hold Feed while powering on.
3. Release Feed at 4 flashes.

That reset restored the interpreter to ZPL.

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

Then send the label:

```zpl
^XA
...label content...
^XZ
```

Depot prepends the guard before sending ZPL jobs.

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

## Avoid

Avoid encoding the wrong lesson:

- do not treat CUPS as the fix for a bad printer reset;
- do not make the web app responsible for printer hardware access;
- do not assume blank labels mean the ZPL template is wrong;
- assuming interpreter state is persistent across failures.

## Mental Model

The printer has three independent layers:

1. transport: USB, CUPS, TCP, BrowserPrint;
2. interpreter: ZPL, EPL, line, diagnostic;
3. media and calibration.

This failure was at the interpreter layer. The `^XA^SZ2^XZ` guard makes each print
job reassert ZPL before sending the label. Transport still needs to be validated
on the actual depot machine.
