# omnigame/igdb

IGDB, Twitch's games database, for [omnisense/omnigame](https://github.com/glitchr-omnisense/omnigame):
search and a game's record through Apicalypse queries, a game found by its IGDB number or by its
Steam or GOG number, its studios, platforms, genres, themes, cover and screenshots, its score, and
its identifiers on Steam, GOG, Epic and itch.io. The provider calls:

| Operation | Request |
|---|---|
| token | `POST https://id.twitch.tv/oauth2/token?client_id=...&client_secret=...&grant_type=client_credentials` |
| search | `POST https://api.igdb.com/v4/games`, body `search "..."; fields ...; limit n;` |
| a record | `POST /v4/games`, body `fields ...; where id = n;` |
| by a store's number | `POST /v4/external_games`, body `fields game; where uid = "620" & external_game_source = 1; limit 1;` |

Every query sends `Client-ID`, `Authorization: Bearer <token>`, `Content-Type: text/plain`.
Pictures come from `https://images.igdb.com/igdb/image/upload`.

## Installation

```sh
composer require omnigame/igdb
```

Requires PHP 8.2, `omnisense/omnigame` (1.x) and `symfony/http-client` (6.4, 7 or 8).

## Credentials

A **Twitch application** (dev.twitch.tv, an application with client credentials): its client id
and client secret. The token is the application's (no user), asked on the first query and asked
again a minute before it expires.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `client_id` | string | - | yes | |
| `client_secret` | string | - | yes | |
| `api` | string | `https://api.igdb.com/v4` | no | |
| `token_url` | string | `https://id.twitch.tv/oauth2/token` | no | |
| `images` | string | `https://images.igdb.com/igdb/image/upload` | no | |

## Capabilities

| Contract | |
|---|---|
| `SearchInterface` | yes |
| `CatalogueInterface` | by igdb, steam, gog |
| `LibraryInterface`, `OwnershipInterface` | no |
| prices | no; a key is needed |

`attribution`: `Data from IGDB.com`.

## Behaviour

The fields asked: `name, slug, summary, first_release_date, url, status, cover.image_id,
screenshots.image_id, genres.name, themes.name, platforms.name, involved_companies.developer,
involved_companies.publisher, involved_companies.company.name, external_games.category,
external_games.external_game_source, external_games.uid, total_rating, total_rating_count`.
Search escapes quotes and backslashes; language and country are not used (IGDB has neither).

| Field | Read from |
|---|---|
| `title`, `url`, `summary` | `name`, `url`, `summary` |
| `identifiers` | the IGDB number, and each external game whose source (`external_game_source`, else the former `category`) is 1 (steam), 5 (gog), 26 (epic) or 30 (itch): its `uid` |
| `released` | `first_release_date` (a UNIX time), at midnight UTC |
| `comingSoon` | that date in the future |
| `developers`, `publishers` | `involved_companies` with `developer` / `publisher` true |
| `genres`, `tags` | `genres[].name`, `themes[].name` |
| `platforms` | `platforms[].name` folded (`PC (Microsoft Windows)` -> windows, `PlayStation 3` -> playstation) |
| `images` | the cover at `t_cover_big` (264 x 374), screenshots at `t_screenshot_big` (889 x 500) |
| `ratings` | `igdb`, `total_rating` rounded to 1, out of 100, with `total_rating_count`, when rated |

No price. A Steam or GOG number not found in `external_games` is `null`.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnigame\Igdb\IgdbFactory;
use Omnigame\Model\GameRef;
use Omnigame\Model\Scheme;
use Symfony\Component\HttpClient\HttpClient;

$igdb = (new IgdbFactory(HttpClient::create()))->create(['client_id' => getenv('TWITCH_CLIENT_ID'), 'client_secret' => getenv('TWITCH_CLIENT_SECRET')]);

$portal = $igdb->game(GameRef::steam(620));                  // found through external_games
echo $portal->title, ' - GOG ', $portal->identifiers->get(Scheme::Gog), "\n";
```

The record read from the package's test answers (written after IGDB's documentation; no Twitch
application was used), in the core's harness on 2026-10-27 - each field, then the calls:

```
Portal 2 | 2011-04-18 | no price
  ids {"gog":"1207658924","igdb":"72","steam":"620"} platforms ["windows","mac","linux","playstation","xbox"]
  dev ["Valve"] pub ["Electronic Arts","Valve"] genres ["Puzzle","Platform"] tags ["Science fiction"]
  summary Sequel to the acclaimed Portal.
  cover https://images.igdb.com/igdb/image/upload/t_cover_big/co1rs4.jpg
  screenshot https://images.igdb.com/igdb/image/upload/t_screenshot_big/sc0001.jpg
  rating igdb 92.3/100 (1200)
  https://www.igdb.com/games/portal-2
  > POST https://id.twitch.tv/oauth2/token {"client_id":"example-id","client_secret":"example-secret","grant_type":"client_credentials"}
  > POST https://api.igdb.com/v4/external_games body: fields game; where uid = "620" & external_game_source = 1; limit 1;
  > POST https://api.igdb.com/v4/games body: fields name,slug,summary,first_release_date,url,status,cover.image_id,screenshots.image_id,genres.name,themes.name,platforms.name,involved_companies.developer,involved_companies.publisher,involved_companies.company.name,external_games.category,external_games.external_game_source,external_games.uid,total_rating,total_rating_count; where id = 72;
```

In Symfony:

```yaml
services:
    Omnigame\Igdb\IgdbFactory:
        arguments: ['@http_client']
    Omnigame\Registry:
        arguments:
            $factories: ['@Omnigame\Igdb\IgdbFactory']
            $config:
                igdb: { factory: igdb, options: { client_id: '%env(TWITCH_CLIENT_ID)%', client_secret: '%env(TWITCH_CLIENT_SECRET)%' } }
```

## Errors

- a token refused: `ProviderException` (`IGDB answered 400 for POST /oauth2/token. {"status":400,"message":"invalid client secret"}`);
  an answer without a token: `ProviderException` naming Twitch;
- unknown: `null`; a ref of another scheme (itch, rawg, epic): `NotSupportedException`;
- a status of 400 or more but 404 (a 429 above 4 requests a second): `ProviderException`; a
  network error: `UnreachableException`;
- a missing `client_id` or `client_secret`: `InvalidConfigException`.

> [!WARNING]
> Known defect: the token is asked with the client secret in the query string, so a network error's
> message quotes it (`... for "https://id.twitch.tv/oauth2/token?client_id=ID&client_secret=SECRET&grant_type=client_credentials".`,
> seen in a container with no network, 2026-10-27), where secrets are expected to stay out of
> messages. Keep these messages out of pages and shared logs.

## Verified, and not

Tested offline (`Tests/IgdbProviderTest.php`, in the core's harness, PHP 8.4): the token got once, a
search by Apicalypse (quotes escaped), a game by its Steam number, unknown, only IGDB, Steam and GOG
numbers known. The answers are shaped after IGDB's documentation and written by hand.
`Tests/BareTest.php` loads every class with the package's requirements alone.

Not verified: no Twitch application was used. IGDB moved external games from `category` to
`external_game_source`: both are read, the lookup asks the latter - not seen live.

## Responsibility

An independent, unofficial client, affiliated with IGDB or Twitch in no way. Whoever runs it
complies with [Twitch's developer agreement](https://www.twitch.tv/p/legal/developer-agreement/)
and IGDB's terms ([api-docs.igdb.com](https://api-docs.igdb.com/#about)): 4 requests a second,
credit to IGDB.com. See the README.
