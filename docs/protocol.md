# Contenir preview protocol, version 1

How the Contenir CMS asks a site to render a record that is being edited, including changes that
have not been saved, and how the site answers. The CMS is the **issuer**; the site is the
**renderer**. This package (`contenir/contenir-preview`) implements the parts both sides share;
`contenir/contenir-preview-laminas-mvc` and `contenir/contenir-preview-mezzio` implement the
renderer for each framework.

## 1. Overview

1. An editor opens a record's Preview in the CMS. The CMS shows a frame beside the record.
2. The CMS signs a **preview token** carrying the record's identity and its current field values
   (saved or not) and posts it into the frame, to the site's **preview endpoint**.
3. The site verifies the token, works out which of its own pages shows the record, and renders
   that page with the token's values standing in for the stored record.
4. Each time the editor changes the record, the CMS signs a new token and posts it again.

Nothing in the token is stored by the site, and a preview never changes the site's data.

## 2. Shared configuration

Both sides hold the same:

| Setting    | Meaning                                                                     |
|------------|-----------------------------------------------------------------------------|
| `key_id`   | Names the secret, so a new one can be introduced before the old is retired. |
| `secret`   | At least 32 bytes of random data, never sent over the wire.                 |
| `issuer`   | The CMS's identifier, usually its base URL (`https://admin.example.com`).   |
| `audience` | The site's base URL (`https://www.example.com`), which also names it.        |

The site may accept several `key_id`s at once (while rotating); the CMS signs with one. Every
site's **preview endpoint** is the fixed path `/api/resource/preview`, so the CMS posts to
`{audience}/api/resource/preview`.

## 3. The preview token

A compact JSON Web Signature (RFC 7515) using HMAC-SHA256: three base64url segments (no padding)
joined by dots, `header.payload.signature`, where the signature is
`HMAC-SHA256(secret, header + "." + payload)`.

### 3.1 Header

```json
{ "alg": "HS256", "typ": "contenir-preview+jwt", "kid": "2026-10" }
```

The renderer rejects any other `alg` or `typ`, and any `kid` it does not hold.

### 3.2 Payload

```json
{
  "iss": "https://admin.example.com",
  "aud": "https://www.example.com",
  "iat": 1792236000,
  "exp": 1792236300,
  "jti": "9b1f3c0e4a2d47f8b6e1c5a7d3f90e21",
  "sub": "12",
  "ver": 1,
  "rec": {
    "section": "site.page.home",
    "keys": { "resource_id": "101" },
    "values": {
      "slug": "spa-rituals",
      "title": "Spa Rituals",
      "description": "<p>Unwind with our signature treatments.</p>",
      "images": ["401", "402"],
      "active": "pending"
    }
  }
}
```

| Claim         | Rule                                                                                          |
|---------------|-----------------------------------------------------------------------------------------------|
| `iss`         | The CMS's `issuer`. Must match the renderer's configuration.                                  |
| `aud`         | The site's `audience`. Must match the renderer's configuration.                               |
| `iat`         | Issued at, seconds since the epoch. Rejected if more than 30 seconds in the future.           |
| `exp`         | Expiry. At most 300 seconds after `iat`; rejected once passed (30 seconds' leeway).           |
| `jti`         | 128 random bits as 32 hex digits. Each token is accepted once (§5).                           |
| `sub`         | The CMS user previewing, as a string. For the site's logs only; it grants nothing.            |
| `ver`         | The protocol version, `1`.                                                                    |
| `rec.section` | The record's section: `module.category.section` as the CMS names it (`site.page.home`).       |
| `rec.keys`    | The record's primary key, column => string. Empty (`{}`) for a record not saved yet.          |
| `rec.values`  | The record's fields, field name => value, as the CMS's edit form holds them (§3.3).           |

### 3.3 Values

Each value is a string, `null`, or a list of strings, exactly as the CMS would save it: a date
as `Y-m-d H:i:s`, a flag as `"1"` or `"0"`, a list of related records as their keys, rich text
and block fields as the HTML or JSON they are stored as. Fields the editor cannot see in the
form, and fields the CMS never stores, are left out. The renderer must not assume any field is
present.

The encoded token must not exceed 1 MiB; the renderer rejects anything larger before decoding.

## 4. Transport

The CMS submits an HTML form into the preview frame:

```html
<form method="post" action="https://www.example.com/api/resource/preview" target="contenir-preview">
  <input type="hidden" name="contenir_preview" value="eyJhbGciOi…">
</form>
<iframe name="contenir-preview" title="Preview"></iframe>
```

A form post keeps the token out of URLs, history and logs, needs no CORS, and carries payloads
larger than a URL can. The endpoint is always `/api/resource/preview`; it accepts only `POST` with
`application/x-www-form-urlencoded` or `multipart/form-data`, reading the single field
`contenir_preview`; anything else is answered `405` or `400`.

The CMS signs tokens on its own server (the secret never reaches the browser): its page sends the
edit form's values to the CMS, which answers with a fresh token, and the page posts that into the
frame.

## 5. Verifying a token

The renderer checks, in this order, and stops at the first failure:

1. The field is present and no larger than 1 MiB → else `400`.
2. Three base64url segments, a JSON header and payload → else `400`.
3. `alg` is `HS256`, `typ` is `contenir-preview+jwt`, `kid` is known → else `401`.
4. The signature matches, compared in constant time → else `401`.
5. `ver` is `1`; `iss` and `aud` match; `iat` and `exp` are within bounds (§3.2) → else `401`.
6. `jti` has not been seen before → else `409`. The renderer remembers each accepted `jti` until
   its `exp` has passed (a shared cache when the site runs on more than one server).
7. `rec` has the shape in §3.2 → else `400`.

The answers to failures are short plain pages naming the problem (never the secret, the token or
the expected values), so an editor sees why the frame is empty.

## 6. Rendering

Once verified, the token becomes a **preview request**: the section, the keys, the values and the
user. The renderer then:

1. Finds the **resolver** the site registered for the section. A resolver says which of the
   site's pages shows the record, usually from the values: a path (`/{slug}` for a page), a named
   route and its parameters, or a **handler** and its parameters, which the site dispatches
   without routing. A handler serves pages the router does not know: drafts, records not saved
   yet, and sites whose routes are built from published records (such as contenir-workflow's).
   No resolver → `404`, "This section has no preview on this site".
2. Holds the preview request in a **preview context** for the rest of the request, so the site's
   data layer can lay the token's values over the stored record (or stand them in for a record not
   saved yet) wherever it loads that section's records.
3. Renders the resolved page as the site normally would, inside the same request.
4. Answers with the page and these headers, whatever the page set:

   ```
   Cache-Control: no-store, private
   X-Robots-Tag: noindex, nofollow
   Referrer-Policy: no-referrer
   Content-Security-Policy: frame-ancestors {issuer}
   ```

   Each renderer may add a visible "Preview" marker, on by default.

Links followed inside the frame lead to the site's ordinary pages, not previews.

## 7. Security

- The secret signs every token; a token cannot be altered or made without it. The CMS signs only
  for editors allowed to view the record.
- Tokens live five minutes and are accepted once, so one copied from a browser is soon useless.
- A preview changes no data and is never cached, indexed or framed by anyone but the CMS.
- Values are the editor's own unsaved input: the site renders them through its usual templates
  and escaping, exactly as it would the saved record.

## 8. Versioning

`ver` and the `typ` header name the protocol version. A renderer answers a version it does not
know with `400`. A change that an existing renderer would misread is a new version.
