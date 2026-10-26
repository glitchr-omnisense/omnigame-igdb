# omnigame/igdb

[omnisense/omnigame](https://github.com/glitchr-omnisense/omnigame)'s IGDB, Twitch's games database,
under any PHP host: an application token got from Twitch with the client credentials and renewed a
minute before it expires; search and a game's record through Apicalypse queries (`POST /v4/games`);
a game found by its IGDB number or by its Steam or GOG number (`/v4/external_games`); its studios,
platforms, genres, themes, cover and screenshots (`images.igdb.com`), its score, and its
identifiers on Steam, GOG, Epic and itch.io (external game sources 1, 5, 26 and 30).

```php
$igdb = (new IgdbFactory($httpClient))->create(['client_id' => getenv('TWITCH_CLIENT_ID'), 'client_secret' => getenv('TWITCH_CLIENT_SECRET')]);
$portal = $igdb->game(GameRef::steam(620));
```

Options: `client_id` and `client_secret` (required: a Twitch application's), `api`,
`token_url`, `images`.

## IGDB's terms

IGDB is used under [Twitch's developer agreement](https://www.twitch.tv/p/legal/developer-agreement/)
and IGDB's own terms ([api-docs.igdb.com](https://api-docs.igdb.com/#about)): 4 requests a second,
credit to IGDB.com. The documentation refused a re-reading on 2026-10-24 (403): read it before going
live.

## Verified, and not

- Verified (the core's harness, PHP 8.4): the token got once, a search by Apicalypse (quotes
  escaped), a game by its Steam number, unknown; `BareTest`; PHPStan max. The answers are **shaped
  after IGDB's documentation and written by hand**: no Twitch application was used. IGDB moved
  external games from `category` to `external_game_source`: both are read, the lookup asks the
  latter - not seen live.
- Not verified live: no credentials.

## Responsibility

An independent, unofficial client, affiliated with, endorsed by or sponsored by none of the services
it reaches. The MIT licence covers this code only and grants no right to the service or its data:
whoever runs it complies with the terms above and obtains what they require. The authors publish
it free of charge, make no commercial use of it and accept no liability for its use.

## Licence

MIT.
