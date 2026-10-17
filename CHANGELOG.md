# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- The Contenir preview protocol, version 1 ([docs/protocol.md](docs/protocol.md)):
  the CMS signs a preview token carrying a record and its values, saved or
  not, and posts it to the site's fixed `/api/resource/preview` endpoint.
- `TokenSigner` and `TokenVerifier` for compact HS256 tokens, with key
  rotation, a five-minute lifetime and single use.
- `PreviewEntry`, `PreviewContext` and `PreviewResponse`, the parts every
  framework renderer shares.
- Path, route and handler targets, with `PathResolver`, `RouteResolver` and
  `ResolverRegistry`.
- `FileReplayGuard`, `CacheReplayGuard` (any PSR-16 cache, 1.x to 3.x) and
  `InMemoryReplayGuard`, and a `SystemClock`.
