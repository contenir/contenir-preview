# contenir-preview

The Contenir preview protocol: the CMS signs a **preview token** carrying a record and its current
values, saved or not, and posts it into a frame on the site; the site verifies the token and renders
the page that shows the record with those values in place of the stored ones.

This package is framework-free and holds the parts both sides share. The renderer for each site
framework lives in its own package:

- [`contenir/contenir-preview-laminas-mvc`](../contenir-preview-laminas-mvc)
- [`contenir/contenir-preview-mezzio`](../contenir-preview-mezzio)

The protocol itself is in [docs/protocol.md](docs/protocol.md).

## Shared configuration

Both sides build the same `PreviewConfig`: who issues tokens, for which site (its base URL), and the
secrets that sign them by key id (at least 32 bytes each).

```php
use Contenir\Preview\PreviewConfig;

$config = new PreviewConfig(
    issuer:   'https://admin.example.com',
    audience: 'https://www.example.com',
    secrets:  ['2026-10' => $secret],
    signWith: '2026-10',
);
```

## On the CMS: signing

```php
use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Token\TokenSigner;

$token = (new TokenSigner($config, $clock))->sign(
    new PreviewRecord('site.page.home', ['resource_id' => '101'], $formValues),
    (string) $userId,
);
```

Post the token as the `contenir_preview` field into a frame aimed at `$config->endpoint()`: the
site's base URL (the `audience`) with the protocol's fixed path, `/api/resource/preview`. Sign a new
token each time the preview refreshes: each is accepted once and lives five minutes.

## On a site: verifying and rendering

A framework adapter does this for you. Underneath, it is:

```php
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewEntry;
use Contenir\Preview\Replay\CacheReplayGuard;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Token\TokenVerifier;

$context = new PreviewContext();
$entry   = new PreviewEntry(
    new TokenVerifier($config, $clock, new CacheReplayGuard($cache, $clock)),
    new ResolverRegistry([
        'site.page.home'       => new PathResolver('/{slug}'),
        'library.news.articles' => new PathResolver('/news/{slug}'),
    ]),
    $context,
);

$target = $entry->open($method, $postedToken); // throws InvalidPreview with its HTTP status
// render $target (a path or a named route) as the site normally would
```

Wherever the site loads a previewable section's records, it lets the context lay the preview's
values over them:

```php
$row = $context->overlay('site.page.home', ['resource_id' => $row['resource_id']], $row);
```

and, for a record not saved yet, `$context->unsaved('site.page.home')` gives its values.

Every preview answer carries `PreviewResponse::headers()` (no caching or indexing, framed only by
the CMS), and a refusal answers with `PreviewResponse::refusal()` and the refusal's status.

## Quality

```sh
composer check          # Mago format, lint and analyze, then the tests
composer mutation-test  # Infection
```
