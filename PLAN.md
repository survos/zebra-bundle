# survos/zebra-bundle — Design Document

A Symfony bundle for the full Zebra ZPL lifecycle: build → preview → cache → print. Bundles the three concerns currently scattered across `andersonls/zpl`, `robgridley/zebra`, Labelary, and hand-rolled TCP code.

## Scope & philosophy

One bundle, three layers, each usable independently:

1. **Builder** — fluent ZPL generation (barcodes, text, images, QR, boxes)
2. **Preview** — Labelary client (hosted or self-hosted Docker) with PSR-6 caching, Twig integration, profiler panel
3. **Print** — raw TCP client for port 9100, async via Messenger, with dry-run mode

Users can `composer require survos/zebra-bundle` and use just the preview piece, or the whole pipeline. No hard dependency on a specific HTTP client (uses `HttpClientInterface`), no Guzzle.

Fits the Survos monorepo conventions: `#[Argument]` attribute syntax, AssetMapper-friendly, EasyAdmin-compatible, Symfony 8 / PHP 8.4+.

## Package layout

```
survos/zebra-bundle/
├── composer.json
├── config/
│   └── services.php              # PHP config, not YAML
├── src/
│   ├── SurvosZebraBundle.php     # AbstractBundle, single-file config
│   ├── Builder/
│   │   ├── ZplBuilder.php        # Fluent API
│   │   ├── Element/              # Text, Barcode, QrCode, Image, Box, Line
│   │   └── Unit.php              # enum: DOTS, MM, INCHES
│   ├── Preview/
│   │   ├── LabelaryClient.php    # HTTP client, ZPL → PNG/PDF bytes
│   │   ├── PreviewService.php    # Caching wrapper around LabelaryClient
│   │   ├── PreviewRequest.php    # DTO: zpl, width, height, dpmm, format, index, rotation
│   │   └── PreviewResult.php     # DTO: bytes, mimeType, warnings
│   ├── Print/
│   │   ├── PrinterClient.php     # interface
│   │   ├── TcpPrinterClient.php  # port 9100 raw socket
│   │   ├── CupsPrinterClient.php # lp/lpr shell-out for USB-via-CUPS
│   │   ├── BrowserPrintClient.php # generates JS payload for client-side USB
│   │   ├── NullPrinterClient.php # dry-run, logs ZPL
│   │   ├── FilePrinterClient.php # writes .zpl to disk (tests, audits)
│   │   └── PrinterRegistry.php   # multi-printer by name
│   ├── Messenger/
│   │   ├── PrintLabelMessage.php
│   │   └── PrintLabelHandler.php
│   ├── Controller/
│   │   ├── PreviewController.php       # /zebra/preview/{hash} route
│   │   └── BrowserPrintController.php  # /zebra/browserprint/{hash} → ZPL payload
│   ├── Twig/
│   │   ├── ZebraExtension.php    # zpl_preview(), zpl_preview_url()
│   │   └── Components/
│   │       └── Preview.php        # <twig:Zebra:Preview /> (optional, see below)
│   ├── Command/
│   │   ├── ZebraPreviewCommand.php  # zebra:preview file.zpl --output=out.png
│   │   ├── ZebraPrintCommand.php    # zebra:print file.zpl --printer=front-desk
│   │   └── ZebraValidateCommand.php # zebra:validate file.zpl (via Labelary linter)
│   ├── Profiler/
│   │   ├── ZebraDataCollector.php
│   │   └── Resources/views/profiler/zebra.html.twig
│   └── Attribute/
│       └── AsLabelTemplate.php   # tag services as label template generators
├── assets/
│   └── controllers/
│       └── browser_print_controller.js  # Stimulus; wraps Zebra BrowserPrint SDK
├── templates/
│   ├── preview.html.twig
│   └── components/
│       └── Zebra/
│           └── Preview.html.twig
├── tests/
└── README.md
```

## Configuration

Single-file bundle config using `AbstractBundle`:

```yaml
# config/packages/survos_zebra.yaml
survos_zebra:
  labelary:
    endpoint: 'http://api.labelary.com/v1'  # or http://localhost:8080 for Docker
    api_key: '%env(default::LABELARY_API_KEY)%'  # optional, paid tier only
    timeout: 10
    http_client: null  # null = default, or scoped client service id

  cache:
    enabled: true
    pool: cache.app
    ttl: 86400  # previews are deterministic on ZPL hash

  defaults:
    dpmm: 8  # GD420 = 203 dpi = 8 dpmm
    width_inches: 4.0
    height_inches: 2.0
    format: png  # png | pdf | json

  printers:
    front-desk:
      type: tcp
      host: 192.168.1.50
      port: 9100
      timeout: 5
    archive-room:
      type: tcp
      host: 192.168.1.51
    usb-volunteer-laptop:
      type: cups
      queue: ZebraGD420          # CUPS queue name; printer must be set to raw
    kiosk-browser:
      type: browserprint          # client-side USB via Zebra BrowserPrint
    dev:
      type: file
      path: '%kernel.project_dir%/var/zpl'
    test:
      type: 'null'

  default_printer: front-desk

  profiler:
    enabled: '%kernel.debug%'
```

Env var override for the common switch: `LABELARY_ENDPOINT=http://localhost:8080` flips the app to self-hosted without config changes — useful for Dokku deploys where prod uses Docker Labelary and local dev hits the public API.

## The builder layer

Fluent, immutable-ish, unit-aware. Influence from `andersonls/zpl` with `readonly` DTOs and an enum API:

```php
use Survos\ZebraBundle\Builder\ZplBuilder;
use Survos\ZebraBundle\Builder\Unit;

$zpl = ZplBuilder::create(Unit::MM, dpmm: 8)
    ->label(width: 102, height: 51)              // 4" x 2" GD420
    ->text('Rappahannock Historical Society', x: 5, y: 5, font: '0', size: 4)
    ->barcode128('RHS-2026-0142', x: 5, y: 15, height: 15, humanReadable: true)
    ->qrCode('https://museado.org/ark:/99999/x1', x: 80, y: 15, size: 4)
    ->image('/path/to/logo.png', x: 5, y: 35, width: 30)
    ->box(x: 0, y: 0, width: 102, height: 51, thickness: 1)
    ->toZpl();
```

Returns a plain ZPL string. No HTTP or I/O dependencies — trivially testable, usable outside Symfony.

## The preview layer

```php
interface PreviewServiceInterface
{
    public function preview(PreviewRequest $request): PreviewResult;
    public function previewUrl(string $zpl, ?PreviewRequest $opts = null): string;
}
```

- **Caching** — key is `zebra_preview_{xxh128($zpl . serialize($opts))}`. ZPL is deterministic, so cache hits are free PNG reuse.
- **Rate limiting** — wraps `LabelaryClient` with exponential backoff on 429, honoring `Retry-After`. Free tier is 5 req/sec, which you hit fast in bulk preview.
- **Warnings** — Labelary returns `X-Warnings` header on malformed ZPL; `PreviewResult` surfaces them so the UI can show "renders but with 2 warnings."

## Twig integration — function vs. component

**Ship both. They serve different needs.**

### Twig functions (always ship)

```twig
{# Data URI — inline, great for emails & printable sheets #}
<img src="{{ zpl_preview(zpl_string) }}" alt="Label preview">

{# Route URL — lazy, cacheable via HTTP cache, works in any template #}
<img src="{{ zpl_preview_url(zpl_string) }}">
```

Zero JS, zero dependencies beyond Twig. Works in EasyAdmin field templates, email templates, PDF generation — anywhere a `<twig:>` tag would be awkward or unsupported.

### Twig component (optional, gated on symfony/ux-twig-component)

```twig
<twig:Zebra:Preview :zpl="zpl" :width="4" :height="2" :show-warnings="true" :allow-download="true" />
```

Renders: preview image + warning badges + "download PNG / PDF / ZPL" buttons + optional "print to {printer}" button that dispatches to Messenger.

**Why both:** the function is the primitive, the component is the polished EasyAdmin/admin-UI affordance. Components drag in `symfony/ux-twig-component` and some Stimulus; you don't want that forced on users who just need an `<img>` tag. Listing the component under `suggest` in composer.json keeps the core bundle dependency-light.

For ScanStation specifically, the component is where you want volunteers to live — one widget with preview + print. For batch curator tooling or the Museado public layer, the function is enough.

## How do we actually print these?

Printing ZPL is a platform question, not just a code question. The GD420 supports four realistic paths. Your deployment picks one (or several):

### 1. Network TCP (port 9100) — the default, best path

Zebra printers with an Ethernet or Wi-Fi module listen on port 9100 for raw ZPL. You send bytes, printer prints. No drivers, no spooler, no OS involvement.

```php
$printer = $registry->get('front-desk');  // TcpPrinterClient
$printer->send($zpl);
```

Under the hood:

```php
$sock = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
fwrite($sock, $zpl);
fclose($sock);
```

**Catch:** the GD420 ships with USB only. You need the **GD420t with LAN module** (part ending in `-100*` vs `-000*`) or an add-on Ethernet module, or plug it into a cheap USB-to-Ethernet print server (TP-Link, StarTech — ~$40) which then exposes it on 9100. For ScanStation, a USB print server is the most deployable answer: one per site, fixed IP on their LAN, Tac's cloud hub sends ZPL to the local router/printer directly or via a tunnel.

**Feedback:** port 9100 is fire-and-forget. No job status, no ack. If you need "did it print?" you query via SGD (Set/Get/Do commands over the same socket), which the bundle can layer on later.

### 2. CUPS queue — when the printer is USB-attached to a Linux host

If the printer is USB-attached to the ScanStation mini-PC (BeeLink running Linux), set up a CUPS raw queue pointing at the USB device:

```bash
lpadmin -p ZebraGD420 -E -v usb://Zebra/ZTC%20GD420-... -m raw
cupsenable ZebraGD420
cupsaccept ZebraGD420
```

Then `CupsPrinterClient` shells out:

```php
// CupsPrinterClient::send()
$proc = proc_open(['lp', '-d', $this->queue, '-o', 'raw'], [...]);
fwrite($stdin, $zpl);
```

Works great when the BeeLink is the print host. Slightly messier on Windows (the ScanStation kiosk is Windows Pro in the current design) — Windows has `copy /b file.zpl \\localhost\ZebraGD420` but it's fragile. Recommend Linux host for USB printing where possible.

### 3. Zebra BrowserPrint — client-side USB, no server involvement

Zebra ships a **BrowserPrint** app (install once on the kiosk PC) that exposes a localhost HTTP endpoint (`http://localhost:9100/`… actually port varies, typically 9101) that browsers can POST ZPL to via a JS SDK. The printer plugs into USB on the kiosk.

This is the sane path for the **kiosk volunteer workflow**: Symfony renders a page with a "Print label" button, page ships ZPL to the browser, Stimulus controller POSTs to BrowserPrint localhost, printer prints. Server never touches the printer.

The bundle's contribution here:
- `BrowserPrintController` returns the ZPL payload as JSON keyed by a short-lived signed hash (so the page doesn't have to carry full ZPL in DOM)
- A Stimulus controller `browser_print_controller.js` that wraps the Zebra JS SDK, handles device discovery, sends ZPL, shows status
- The Twig component optionally renders a BrowserPrint-backed "Print" button when the printer config says `type: browserprint`

Tradeoff: requires the Zebra BrowserPrint desktop app installed and running on each kiosk. One-time setup, then zero-drivers from the Symfony app's perspective. For your Windows Pro kiosk setup with Assigned Access, this fits cleanly — install BrowserPrint once when provisioning the kiosk, and the printer "just works" from the web UI.

### 4. File dump / dry-run

`FilePrinterClient` writes ZPL to `var/zpl/{timestamp}-{printer}.zpl`. Useful for:
- Tests
- Audit trails (every label printed gets archived)
- "ScanStation installed at museum without a printer yet"
- Local dev where you preview-then-inspect

### Which to use for ScanStation?

Honest recommendation based on your deployment:

| Deployment | Best path |
|---|---|
| Museum with wired network, printer near ScanStation | **TCP (USB print server)** — most reliable, fewest moving parts |
| BeeLink hosts everything including USB-attached printer | **CUPS** — if BeeLink is Linux |
| Kiosk with USB printer, volunteer operator, browser-driven UX | **BrowserPrint** — cleanest UX, no server involvement |
| Early pilot, no printer yet | **File dump** — print to disk, review later |

Partners on small budgets with one BeeLink + one USB printer will almost always land on CUPS or BrowserPrint. Partners with existing label printers on their LAN will land on TCP. The bundle supports all four behind the same `PrinterClient` interface, so your controllers stay identical — only config changes.

### Async by default

Anything touching hardware goes through Messenger:

```php
$bus->dispatch(new PrintLabelMessage(
    zpl: $zpl,
    printer: 'front-desk',
    jobId: $job->getId(),
));
```

`PrintLabelHandler` sends the ZPL, logs the outcome, dispatches a `LabelPrintedEvent` so your app can update `Job` status. Failed prints retry with backoff (printer powered off, paper out, network blip).

## Console commands

```bash
bin/console zebra:preview templates/shelf-label.zpl --output=preview.png --width=4 --height=1
bin/console zebra:print templates/shelf-label.zpl --printer=front-desk
bin/console zebra:print templates/shelf-label.zpl --printer=dev   # dry-run
bin/console zebra:validate templates/shelf-label.zpl
bin/console zebra:printers   # list configured printers with TCP ping status
```

All use `#[Argument('desc')]` concise attribute syntax per your preference.

## Profiler panel

When `kernel.debug`, every preview + print in a request gets captured:

- Each preview: thumbnail, dimensions, cache hit/miss, Labelary response time, warnings
- Each print: printer name, ZPL size, bytes sent, socket timing, success/failure
- Total Labelary requests (to watch rate-limit budget during dev)

Tab visible in the Symfony profiler. This is the feature that sells the bundle — curators iterating on label layouts see instant visual feedback without leaving the profiler.

## Label templates (optional higher layer)

```php
#[AsLabelTemplate(name: 'item-shelf', label: 'Shelf label (4x2)')]
class ItemShelfLabel implements LabelTemplateInterface
{
    public function generate(object $entity, array $options = []): string
    {
        assert($entity instanceof Item);
        return ZplBuilder::create(Unit::MM, dpmm: 8)
            ->label(102, 51)
            ->text($entity->getAccessionNumber(), 5, 5)
            ->qrCode($entity->getArkUrl(), 70, 10, size: 5)
            ->toZpl();
    }
}
```

Auto-discovered via compiler pass. EasyAdmin actions, batch jobs, and the profiler can enumerate registered templates.

## Integration with Survos ecosystem

- **MuseadoArkBundle** — label templates embed ARK URLs in QR codes
- **LocationBundle** — location-scoped templates (shelf labels include building/room/shelf from `Geo`)
- **survos/batch** — print jobs dispatched through Messenger land in your job UI
- **EasyAdmin** — `ZplPreviewField` type shows inline preview for any entity field containing ZPL or referencing a label template
- **AssetMapper** — bundle ships no mandatory JS; Stimulus controllers are importmap entries, used only for BrowserPrint

## Public API surface (what users actually touch)

```php
// 90% of users, 90% of the time:
$service->preview(new PreviewRequest(zpl: $zpl));
$service->previewUrl($zpl);
$printer->send($zpl);

// Builder:
ZplBuilder::create()->text(...)->toZpl();

// Twig:
{{ zpl_preview(zpl) }}
<twig:Zebra:Preview :zpl="zpl" />

// Templates:
#[AsLabelTemplate(name: 'my-label')]
```

Everything else is opt-in.

## Testing strategy

- `LabelaryClient` tested with `MockHttpClient` returning fixture PNG bytes
- `PreviewService` cache behavior tested against `ArrayAdapter`
- `TcpPrinterClient` tested with a local socket server spun up per test
- `CupsPrinterClient` tested by mocking `proc_open` (or integration-tagged with real CUPS)
- Builder: golden ZPL files in `tests/fixtures/` — each chain produces exact-match ZPL
- Full-stack: `FilePrinterClient` + real `LabelaryClient` against public API, tagged `@group external`, skippable in CI

## What this bundle deliberately does NOT do

- **No ZPL parser.** Labelary-as-linter is good enough; parsing ZPL in PHP is a years-long rabbit hole
- **No designer UI.** ZebraDesigner exists; we generate ZPL, we don't design visually
- **No direct Windows USB printing without CUPS or BrowserPrint.** Windows raw USB is a driver nightmare; we hand it off to BrowserPrint
- **No GS1 / label-law validation.** Domain-specific bundle, if ever needed

## Composer manifest

```json
{
  "name": "survos/zebra-bundle",
  "description": "Zebra ZPL label generation, Labelary preview with caching, and multi-transport printing for Symfony",
  "type": "symfony-bundle",
  "license": "MIT",
  "require": {
    "php": ">=8.4",
    "symfony/framework-bundle": "^7.1|^8.0",
    "symfony/http-client": "^7.1|^8.0",
    "symfony/cache-contracts": "^3.5",
    "psr/log": "^3.0"
  },
  "require-dev": {
    "symfony/twig-bundle": "^7.1|^8.0",
    "symfony/messenger": "^7.1|^8.0",
    "symfony/ux-twig-component": "^2.19",
    "phpunit/phpunit": "^11"
  },
  "suggest": {
    "symfony/messenger": "For async printing (recommended)",
    "symfony/ux-twig-component": "For <twig:Zebra:Preview /> component",
    "ext-sockets": "For lower-level TCP diagnostics"
  }
}
```

## Build order

Each step testable before moving on:

1. **Bundle skeleton** — `AbstractBundle`, config, service wiring, empty test passing
2. **`LabelaryClient`** — raw HTTP only, no cache, fixture-based tests
3. **`PreviewService`** — cache wrapper, rate-limit handling
4. **Controller + Twig function** — `zpl_preview()` working end-to-end with a hardcoded ZPL sample
5. **`ZplBuilder`** — fluent API, golden-file tests (parallel to 2–4, standalone)
6. **Print layer** — `PrinterClient` interface, `NullPrinterClient`, `FilePrinterClient`, then `TcpPrinterClient`
7. **`CupsPrinterClient`** — `proc_open` wrapper, integration tests
8. **Messenger integration** — message + handler + event
9. **Console commands**
10. **Profiler + data collector**
11. **Twig component** (`<twig:Zebra:Preview />`)
12. **BrowserPrint integration** — controller + Stimulus + `BrowserPrintClient`
13. **`#[AsLabelTemplate]` + resolver**
14. **README + Packagist publish**

Ready to start on #1 whenever. Want me to scaffold the `AbstractBundle` class first, or jump to `LabelaryClient` for the most immediate visual payoff?
