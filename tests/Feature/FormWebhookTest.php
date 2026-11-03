<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentCms\Forms\HostResolver;
use Vortechron\FilamentCms\Forms\WebhookSender;
use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormWebhookTest extends TestCase
{
    use MakesForms;

    /**
     * @var list<array{request: Request, options: array<string, mixed>}>
     */
    private array $sent = [];

    /**
     * What the webhook answers.
     */
    private ?\Closure $answer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new class implements HostResolver
        {
            public function addresses(string $host): array
            {
                return [
                    'hooks.example.com' => ['93.184.216.34'],
                    'v6.example.com' => ['2606:2800:220:1:248:1893:25c8:1946'],
                    'internal.example.com' => ['127.0.0.1'],
                    'mixed.example.com' => ['93.184.216.34', '10.0.0.5'],
                    'metadata.example.com' => ['169.254.169.254'],
                    'loop6.example.com' => ['::1'],
                    'nat64.example.com' => ['64:ff9b::7f00:1'],
                    'tailnet.example.com' => ['100.84.248.50'],
                    'multicast.example.com' => ['224.0.0.1'],
                ][$host] ?? [];
            }
        });

        Http::preventStrayRequests();
        Http::fake(function (Request $request, array $options) {
            $this->sent[] = ['request' => $request, 'options' => $options];

            return $this->answer === null ? Http::response(['ok' => true]) : ($this->answer)();
        });
    }

    public function test_the_webhook_gets_the_form_page_and_data_on_the_checked_address(): void
    {
        $page = $this->pageWithForm(self::exampleForm([['type' => 'webhook', 'url' => 'https://hooks.example.com/x?token=rahsia']]));

        $this->send($page)->assertRedirect(url('/hubungi').'#form-frm00001')->assertSessionHas('filament-cms.form_sent');

        $this->assertCount(1, $this->sent);
        ['request' => $request, 'options' => $options] = $this->sent[0];
        $this->assertSame('POST', $request->method());
        $this->assertSame('https://hooks.example.com/x?token=rahsia', $request->url());
        $this->assertSame('Borang pertanyaan', $request['form']);
        $this->assertSame('/hubungi', $request['page']);
        $this->assertSame(['nama' => 'Siti', 'telefon' => '012-345 6789', 'servis' => 'Parcel', 'mesej' => 'Berapa harga?', 'hari' => ['Isnin'], 'setuju' => true], $request['data']);
        $this->assertSame(FormSubmission::query()->sole()->getKey(), $request['id']);
        $this->assertSame(5, $options['timeout']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(['hooks.example.com:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
    }

    public function test_an_ipv6_answer_and_another_port_are_pinned_too(): void
    {
        $this->assertTrue(app(WebhookSender::class)->send('https://v6.example.com:8443/hook', ['a' => 1]));

        $this->assertSame(['v6.example.com:8443:[2606:2800:220:1:248:1893:25c8:1946]'], $this->sent[0]['options']['curl'][CURLOPT_RESOLVE]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refusedUrls(): array
    {
        return [
            'plain http' => ['http://hooks.example.com/x'],
            'a private address' => ['https://10.0.0.1/x'],
            'loopback' => ['https://127.0.0.1/x'],
            'ipv6 loopback' => ['https://[::1]/x'],
            'link-local metadata' => ['https://169.254.169.254/latest/meta-data'],
            'a name that resolves to 127.0.0.1' => ['https://internal.example.com/x'],
            'one private answer among public ones' => ['https://mixed.example.com/x'],
            'a name that resolves to metadata' => ['https://metadata.example.com/x'],
            'a name that resolves to ::1' => ['https://loop6.example.com/x'],
            'NAT64 of loopback' => ['https://nat64.example.com/x'],
            'carrier-grade NAT (Tailscale)' => ['https://tailnet.example.com/x'],
            'multicast' => ['https://multicast.example.com/x'],
            'a name that does not resolve' => ['https://nowhere.example.com/x'],
            'no host' => ['https:///x'],
            'not a url' => ['javascript:alert(1)'],
        ];
    }

    #[DataProvider('refusedUrls')]
    public function test_the_webhook_refuses_addresses_inside_the_network(string $url): void
    {
        Log::spy();

        $this->assertFalse(app(WebhookSender::class)->send($url, ['a' => 1]));

        $this->assertSame([], $this->sent);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Form webhook refused'))->once();
    }

    public function test_a_refused_or_failing_webhook_never_shows_to_the_visitor_or_logs_the_link(): void
    {
        Log::spy();
        $refused = $this->pageWithForm(self::exampleForm([['type' => 'webhook', 'url' => 'https://internal.example.com/x?token=rahsia']]));
        $failing = $this->pageWithForm(self::exampleForm([['type' => 'webhook', 'url' => 'https://hooks.example.com/x?token=rahsia']]), path: '/gagal');
        $this->answer = fn () => throw new ConnectionException('cURL error 28 for https://hooks.example.com/x?token=rahsia');

        $this->send($refused)->assertRedirect(url('/hubungi').'#form-frm00001')->assertSessionHas('filament-cms.form_sent')->assertSessionHasNoErrors();
        $this->send($failing)->assertRedirect(url('/gagal').'#form-frm00001')->assertSessionHas('filament-cms.form_sent')->assertSessionHasNoErrors();

        $this->assertSame(2, FormSubmission::query()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'Form webhook') && ! str_contains(json_encode([$message, $context]), 'rahsia'))->twice();
        Log::shouldNotHaveReceived('warning', [\Mockery::on(fn (string $message): bool => str_contains($message, 'rahsia')), \Mockery::any()]);
    }

    public function test_a_webhook_answering_with_an_error_is_logged(): void
    {
        Log::spy();
        $this->answer = fn () => Http::response('nope', 500);

        $this->assertFalse(app(WebhookSender::class)->send('https://hooks.example.com/x', []));

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Form webhook failed') && $context['status'] === 500 && $context['host'] === 'hooks.example.com')->once();
    }
}
