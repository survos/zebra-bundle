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
    kiosk-usb:
      type: usb
      device: /dev/usb/lp0
      vendor_id: '0A5F'
      product_id: '00A1'
```

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
