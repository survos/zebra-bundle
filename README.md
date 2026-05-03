# Survos Zebra Bundle

Symfony bundle for Zebra ZPL preview and printing workflows.

## Status

This repository is currently a scaffold based on `PLAN.md`.

Implemented so far:

- `SurvosZebraBundle` using `AbstractBundle`
- In-bundle service registration without `services.php`
- Labelary-backed preview client and cache wrapper
- Twig extension with `zpl_preview()` support
- Optional Twig component for label preview
- TCP, CUPS, file, USB, and null print transports

## Configuration

```yaml
survos_zebra:
  labelary:
    endpoint: 'https://api.labelary.com/v1'
    api_key: null
    timeout: 10
  cache:
    enabled: true
    pool: cache.app
    ttl: 86400
  defaults:
    label_size: gk420d_2_25x1_25
    dpmm: 8
    width_inches: 4.0
    height_inches: 2.0
    format: png
  label_sizes:
    gk420d_2_25x1_25:
      width_inches: 2.25
      height_inches: 1.25
      dpmm: 8
      description: 'Zebra GK420d 2.25 x 1.25 inch label'
  printers:
    zebra:
      type: cups
      queue: zebra
      dpi: 203
      label_width_in: 2.25
      label_height_in: 1.25
    spool:
      type: file
      path: '%kernel.project_dir%/var/zpl'
      dpi: 203
      label_width_in: 2.25
      label_height_in: 1.25
  default_printer: zebra
```

For USB-attached GK420d printers on Linux, prefer a CUPS queue that uses the CUPS USB
backend, for example `usb://Zebra/...`, then print with `type: cups`. Avoid writing
directly to `/dev/usb/lp*`; that path depends on `usblp` and can leave transport and
interpreter failures hard to distinguish. See [docs/gk420d-reliable-printing.md](docs/gk420d-reliable-printing.md).

The print service automatically prepends the ZPL interpreter guard:

```zpl
^XA^SZ2^XZ
```

It also wraps each label with the configured media dimensions:

```zpl
^XA
^PW457
^LL254
...label body...
^XZ
```

Do not rely on printer-stored defaults for print width or label length. A GK420d
defaults to a 4 inch print width, so narrower stock can produce blank or partial
labels when content lands outside the physical media. Configure each printer
profile with `dpi`, `label_width_in`, and `label_height_in`; the bundle converts
inches to dots with:

```text
dots = inches * dpi
```

Common 203 dpi sizes:

| Label size | `^PW` | `^LL` |
| --- | ---: | ---: |
| 2.25 x 1.25 in | 457 | 254 |
| 2.25 x 1 in | 457 | 203 |
| 4 x 6 in | 812 | 1218 |
| 4 x 2 in | 812 | 406 |
| 3 x 1 in | 609 | 203 |

`PrinterServiceInterface::testLabel()` prints a small known-good label using the
configured dimensions. `calibrate()` sends `^XA^JC^XZ` for new media. `saveSettings()`
persists `^PW` and `^LL` with `^JUS`, but should be treated as an advanced
single-printer setup command; normal jobs remain explicit per print.

## Twig Usage

```twig
<img src="{{ zpl_preview(zpl) }}" alt="Label preview">
```

Named label sizes are also supported:

```twig
<img src="{{ zpl_preview(zpl, 'gk420d_2_25x1_25') }}" alt="GK420d label preview">
```

If `symfony/ux-twig-component` is installed, the bundle also registers:

```twig
<twig:Zebra:Preview :zpl="zpl" />
```

You can override the configured default size per component render:

```twig
<twig:Zebra:Preview :zpl="zpl" labelSize="gk420d_2_25x1_25" />
```
