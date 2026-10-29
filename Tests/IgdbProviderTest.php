<?php

namespace Omnigame\Igdb\Tests;

use Omnigame\Exception\NotSupportedException;
use Omnigame\Igdb\IgdbFactory;
use Omnigame\Igdb\IgdbProvider;
use Omnigame\Model\Game;
use Omnigame\Model\GameRef;
use Omnigame\Model\ImageKind;
use Omnigame\Model\Platform;
use Omnigame\Model\Query;
use Omnigame\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * IGDB's and Twitch's answers shaped after IGDB's API documentation
 * (api-docs.igdb.com), values written by hand: no Twitch application was
 * used, nothing here was captured from IGDB.
 */
final class IgdbProviderTest extends TestCase
{
    private const PORTAL = '[{"id": 72, "name": "Portal 2", "slug": "portal-2", "summary": "Sequel to the acclaimed Portal.", "first_release_date": 1303084800, "url": "https://www.igdb.com/games/portal-2", "cover": {"id": 1, "image_id": "co1rs4"}, "screenshots": [{"id": 2, "image_id": "sc0001"}], "genres": [{"id": 9, "name": "Puzzle"}, {"id": 8, "name": "Platform"}], "themes": [{"id": 18, "name": "Science fiction"}], "platforms": [{"id": 6, "name": "PC (Microsoft Windows)"}, {"id": 14, "name": "Mac"}, {"id": 3, "name": "Linux"}, {"id": 9, "name": "PlayStation 3"}, {"id": 12, "name": "Xbox 360"}], "involved_companies": [{"id": 1, "developer": true, "publisher": false, "company": {"id": 56, "name": "Valve"}}, {"id": 2, "developer": false, "publisher": true, "company": {"id": 57, "name": "Electronic Arts"}}, {"id": 3, "developer": false, "publisher": true, "company": {"id": 56, "name": "Valve"}}], "external_games": [{"id": 10, "external_game_source": 1, "uid": "620"}, {"id": 11, "category": 5, "uid": "1207658924"}, {"id": 12, "external_game_source": 14, "uid": "twitch-only"}], "total_rating": 92.3456, "total_rating_count": 1200}]';

    public function testTheTokenThenASearchByApicalypse(): void
    {
        $http = self::http();
        $games = self::provider($http)->search(new Query('portal "2"', limit: 5));

        self::assertSame(['POST', 'https://id.twitch.tv/oauth2/token'], [$http->requests[0]['method'], $http->requests[0]['url']]);
        // Twitch's client credentials grant, form-encoded in the body as its documentation sends it
        // (https://dev.twitch.tv/docs/authentication/getting-tokens-oauth/#client-credentials-grant-flow):
        // the secret never in the URL, where logs and messages keep it.
        self::assertSame(['client_id' => 'not-a-real-client-id', 'client_secret' => 'not-a-real-client-secret', 'grant_type' => 'client_credentials'], $http->requests[0]['options']['body'] ?? null);
        self::assertArrayNotHasKey('query', $http->requests[0]['options']);
        self::assertSame('https://api.igdb.com/v4/games', $http->requests[1]['url']);
        self::assertStringStartsWith('search "portal \"2\""; fields name,slug,', self::body($http, 1));
        self::assertStringEndsWith('; limit 5;', self::body($http, 1));
        self::assertSame(['not-a-real-client-id', 'Bearer not-a-real-app-token'], [$http->header('Client-ID'), $http->header('Authorization')]);

        self::assertSame(['Portal 2'], array_map(static fn (Game $g) => $g->title, $games));
        $portal = $games[0];
        self::assertSame(['gog' => '1207658924', 'igdb' => '72', 'steam' => '620'], $portal->identifiers->toArray());
        self::assertSame(['2011-04-18', ['Valve'], ['Electronic Arts', 'Valve']], [$portal->released?->format('Y-m-d'), $portal->developers, $portal->publishers]);
        self::assertSame([Platform::Windows, Platform::Mac, Platform::Linux, Platform::PlayStation, Platform::Xbox], $portal->platforms);
        self::assertSame('https://images.igdb.com/igdb/image/upload/t_cover_big/co1rs4.jpg', $portal->image(ImageKind::Cover)?->url);
        self::assertSame([92.3, 1200], [$portal->ratings[0]->value ?? null, $portal->ratings[0]->votes ?? null]);
    }

    public function testAGameByItsSteamNumberThroughExternalGames(): void
    {
        $http = self::http();
        $provider = self::provider($http);
        $game = $provider->game(GameRef::steam(620));

        self::assertSame('Portal 2', $game?->title);
        self::assertSame('https://api.igdb.com/v4/external_games', $http->requests[1]['url']);
        self::assertSame('fields game; where uid = "620" & external_game_source = 1; limit 1;', $http->requests[1]['options']['body'] ?? null);
        self::assertStringEndsWith('; where id = 72;', self::body($http, 2));
        self::assertNull($provider->game(GameRef::igdb(1)));
        self::assertCount(1, array_filter($http->requests, static fn (array $r) => str_contains($r['url'], 'oauth2')), 'the token got once');
    }

    public function testTheSecretStaysOutOfAnUnreachableMessage(): void
    {
        $http = new \Symfony\Component\HttpClient\MockHttpClient(static function (string $method, string $url, array $options): never {
            throw new \Symfony\Component\HttpClient\Exception\TransportException(\sprintf('Could not resolve host: id.twitch.tv for "%s".', $url));
        });

        try {
            (new IgdbFactory($http))->create(['client_id' => 'not-a-real-client-id', 'client_secret' => 'not-a-real-client-secret'])->search(new Query('portal'));
            self::fail('no exception');
        } catch (\Omnigame\Exception\UnreachableException $e) {
            self::assertStringNotContainsString('not-a-real-client-secret', $e->getMessage());
            self::assertStringContainsString('POST /oauth2/token', $e->getMessage());
        }
    }

    public function testOnlyIgdbSteamAndGogNumbersAreKnown(): void
    {
        $this->expectException(NotSupportedException::class);
        self::provider(self::http())->game(GameRef::itch('leafo/x-moon'));
    }

    private static function http(): RecordedHttpClient
    {
        return new RecordedHttpClient(static function (string $method, string $url, array $options): array {
            $body = \is_string($options['body'] ?? null) ? $options['body'] : '';

            return match (true) {
                str_contains($url, 'oauth2/token') => [200, '{"access_token": "not-a-real-app-token", "expires_in": 5000000, "token_type": "bearer"}'],
                str_ends_with($url, '/external_games') => [200, str_contains($body, 'uid = "620"') ? '[{"id": 10, "game": 72}]' : '[]'],
                str_ends_with($url, '/games') && (str_starts_with($body, 'search') || str_contains($body, 'where id = 72;')) => [200, self::PORTAL],
                default => [200, '[]'],
            };
        });
    }

    private static function body(RecordedHttpClient $http, int $request): string
    {
        $body = $http->requests[$request]['options']['body'] ?? null;

        return \is_string($body) ? $body : '';
    }

    /** @param array<string, mixed> $options */
    private static function provider(RecordedHttpClient $http, array $options = []): IgdbProvider
    {
        $provider = (new IgdbFactory($http))->create($options + ['client_id' => 'not-a-real-client-id', 'client_secret' => 'not-a-real-client-secret']);
        self::assertInstanceOf(IgdbProvider::class, $provider);

        return $provider;
    }
}
