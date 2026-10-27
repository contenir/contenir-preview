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

An editor can also send a client a link to a record's draft, behind a code (§9).

## 2. Shared configuration

Both sides hold the same:

| Setting    | Meaning                                                                     |
|------------|-----------------------------------------------------------------------------|
| `key_id`   | Names the secret, so a new one can be introduced before the old is retired. |
| `secret`   | At least 32 bytes of random data, never sent over the wire.                 |
| `issuer`   | The CMS's origin (`https://admin.example.com`), which names it.             |
| `audience` | The site's origin (`https://www.example.com`), which also names it.         |

The site may accept several `key_id`s at once (while rotating); the CMS signs with one. Every
site's **preview endpoint** is the fixed path `/api/resource/preview`, so the CMS posts to
`{audience}/api/resource/preview`.

`issuer` and `audience` are **origins**: a scheme, a host and an optional port, and nothing after
them. Each names one side whole and is matched whole against the token's `iss` and `aud`, so both
sides hold the same string. The issuer is also sent as the preview's `frame-ancestors` (§6, step 4),
where a browser matches the framing document's *origin*: a source expression carrying a path matches
nothing, so the preview would answer `200` and render as a blank frame, with only a console line to
say why. A setting carrying a path, query or fragment is refused where it is read, before any token
is signed or answered.

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

## 9. Shared previews

An editor can also send someone outside the CMS, such as a client, a **link** to one record's
draft. A link opens many times, until the end the editor chose or until the CMS revokes it, and only
for a visitor who enters the link's **code**. A shared preview signs no token: the CMS and the site
share the site's database, so the link is a row there that the site reads on every visit, and
revoking it deletes the row. The editor's own previews (§1 to §8) are unchanged.

### 9.1 The link

| Part      | Rule                                                                                       |
|-----------|--------------------------------------------------------------------------------------------|
| id        | 32 random bytes as base64url without padding: 43 characters. Compared byte for byte.        |
| code      | 6 characters from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (no 0/O, 1/I/L).                        |
| record    | The record's section and primary key, as in §3.2. Only a saved record can be shared.        |
| note      | Who the link is for, as the editor put it; up to 255 characters, or none.                   |
| creator   | The CMS user who made it.                                                                  |
| ends      | Chosen by the editor: after now and at most 90 days after the link is made.                 |

The CMS keeps links in the site database's `preview_share` table, which this package's migration
creates (the CMS runs it). The site only reads the table.

The link is `{audience}/preview/{id}`. The editor sends the link and the code, together or apart.

### 9.2 What the CMS does

- **Make** a link: a fresh id and code, for a saved record, ending when the editor chose.
- **List** a record's live links, ending soonest first.
- **New code**: replaces a live link's code, so the old code and every pass made with it (§9.3)
  stop working.
- **Revoke**: deletes the link, so it stops opening at once.
- **Forget** a record's links when the record is deleted, and **prune** links that have ended.

### 9.3 Opening a link

The renderer takes only `GET` and `POST` to `/preview/` followed by exactly 43 base64url
characters, before routing, so a page of the site's own at `/preview` still routes. Any other
method → `405`. Then:

1. It finds the link. A link that is missing or has ended → `404`, "This preview link has expired
   or been withdrawn.", the same for both.
2. On a `GET` with a **pass** that matches, it shows the record (§9.4). Without one, it answers
   `200` with a form asking for the code.
3. On a `POST` it checks the posted `code`, ignoring case, spaces and dashes, in constant time:
   - right → `303` back to the link, setting the pass;
   - wrong → `401` with the form again, saying the code isn't right;
   - after 5 wrong codes from one address in 15 minutes, or 25 from any addresses, → `429`,
     "Too many tries. Try again in 15 minutes.", until 15 minutes after the first of them. Each
     link counts apart.

The **pass** is a cookie, `contenir_share`, holding base64url of
`HMAC-SHA256(secret, id + "." + code + "." + ends)`, with `ends` in seconds since the epoch, under
the key the site signs with. It is sent with `Path=/preview/{id}`, `HttpOnly`, `SameSite=Lax`,
`Secure` on an HTTPS site, and expires when the link does. A pass made under any key the site
holds is accepted, so rotating keys turns no one away; a new code or a new end turns every pass
away.

### 9.4 Rendering

The renderer begins the preview context (§6, step 2) with the link's record and **no values**, so
the record shows as it is saved, whatever its status, and resolves and renders it as in §6. The
preview request says it is **shared**, so the site can tell a visitor's view from an editor's
preview (to leave it out of analytics, say). The request it renders carries
`Cache-Control: no-cache, no-store`, so no page cache serves or stores it.

Every answer to a link (the page, the form and each refusal) carries:

```
Cache-Control: no-store, private
X-Robots-Tag: noindex, nofollow
Referrer-Policy: no-referrer
Content-Security-Policy: frame-ancestors 'none'
```

A shared preview shows the record as saved, never the editor's unsaved changes, and only that
record: others it lists are shown as the site shows them to anyone. Links followed from it lead to
the site's ordinary pages.

### 9.5 Security

- Anyone with the link and the code sees the draft until the link ends or is revoked: that is what
  a link is for. Send the code apart from the link where that matters.
- The id cannot be guessed, and the throttle makes guessing a code for a known link take thousands
  of years.
- The site never writes the table; wrong codes are counted where accepted tokens are remembered.
- Links are not signed, so rotating keys revokes none; revoke a link, or a record's links, instead.
