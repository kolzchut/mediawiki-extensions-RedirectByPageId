# RedirectByPageId

A small MediaWiki extension that adds **`Special:RedirectByPageId/<id>`**, which
takes a numeric **page ID** and issues an HTTP redirect to that page's
**canonical (pretty) URL** — with a **configurable** redirect status (301 or
302).

It exists to serve *permalinks by page ID* — for example a legacy
`/page/<lang>/<id>` URL scheme — in a way that consolidates traffic onto the
real article URL instead of fragmenting it.

```
Special:RedirectByPageId/80   →   302/301   →   https://example.org/he/<Canonical_Title>
```

## Why not core's `Special:Redirect`?

MediaWiki core already ships `Special:Redirect/page/<id>`. Its page handler
(`SpecialRedirect::dispatchPage()`) returns an `index.php?curid=<id>` URL, so it
**302-redirects to a non-canonical URL and then serves the article there**
(HTTP 200, no further redirect). The visitor — and any crawler following the
permalink — ends up on `?curid=<id>`, not on the clean article URL. The result
is duplicate-content / canonical-URL noise and split analytics.

`RedirectByPageId` instead returns a **`Title`** from `getRedirect()`. The base
class `RedirectSpecialPage` redirects a `Title` target through
`Title::getFullUrlForRedirect()`, which produces the **canonical article URL**.
The only thing this extension adds on top of the base class is a configurable
status code (core hardcodes 302).

## Why not `Extension:ShortUrl`?

`ShortUrl` maintains a **separate `shorturls` table** keyed by a fresh
base36-encoded autoincrement ID (and on `namespace`+`title`, which it must keep
in sync on every page move). That id space is unrelated to MediaWiki's
`page_id`, so it cannot resolve existing `page_id`-based permalinks, and it adds
a table, a maintenance backfill script, and per-edit hooks.

`RedirectByPageId` carries **no schema and no state**. It resolves the built-in,
move-stable `page_id` directly, so existing `page_id` permalinks keep working
with zero migration.

## Configuration

| Variable                        | Default | Meaning                                                        |
|---------------------------------|---------|----------------------------------------------------------------|
| `$wgRedirectByPageIdStatusCode` | `302`   | HTTP status for the redirect. `301` (permanent) or `302` (temporary). |

The default is **302** because a page's canonical URL changes when the page is
renamed. Switch to **301** once your URLs are stable and you want crawlers to
consolidate link equity onto the canonical URL:

```php
$wgRedirectByPageIdStatusCode = 301;
```

Any value other than `301` or `302` falls back to `302`.

## Behaviour details

- **Query-string passthrough.** Parameters are forwarded to the target URL:
  the base set (`uselang`, `useskin`, `variant`, `debug`, `safemode`) plus
  `action` (so e.g. `?action=history` works).
- **Missing / deleted pages.** A page ID that resolves to no existing page
  returns a **404** error page (not the base class's `LogicException`).
- **Non-numeric or zero IDs** are treated as not-found.

## Installation

```php
wfLoadExtension( 'RedirectByPageId' );
// optional, defaults to 302:
$wgRedirectByPageIdStatusCode = 302;
```

### Fronting a legacy permalink path with nginx

If you serve a legacy permalink scheme such as `/page/<lang>/<id>`, point it at
this special page instead of at `?curid=`:

```nginx
# /page/he/80  ->  Special:RedirectByPageId/80 on the "he" wiki
location ~ ^/page/(?<lang>[a-z]{2})/(?<pageid>\d+)/?$ {
    return 302 /$lang/index.php?title=Special:RedirectByPageId/$pageid;
}
```

The special page then issues the *real* redirect to the canonical article URL,
at the status you configured.

## Requirements

- MediaWiki 1.43+

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).
