# Changelog

## Unreleased (1.x)

### Fixed
- Twitch's token is asked with the client id and secret in a form-encoded body, as Twitch's
  documentation sends them (client credentials grant), no longer in the query string, where
  the HTTP client's messages and logs kept the secret; an unreachable Twitch's
  `UnreachableException` names `POST /oauth2/token` only.
