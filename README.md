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
    dpmm: 8
    width_inches: 4.0
    height_inches: 2.0
    format: png
```

## Twig Usage

```twig
<img src="{{ zpl_preview(zpl) }}" alt="Label preview">
```

If `symfony/ux-twig-component` is installed, the bundle also registers:

```twig
<twig:Zebra:Preview :zpl="zpl" />
```
