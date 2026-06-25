<?php

declare(strict_types=1);

namespace G8scope\Core;

use G8scope\Core\Alerting\AlertDispatcher;
use G8scope\Core\Contracts\EndpointResolver;
use G8scope\Core\Contracts\EntitlementGate;
use G8scope\Core\Contracts\Fingerprinter;
use G8scope\Core\Contracts\IssueStore;
use G8scope\Core\Contracts\Notifier;
use G8scope\Core\Contracts\OccurrenceStore;
use G8scope\Core\Contracts\OccurrenceWriter;
use G8scope\Core\Contracts\ReportGenerator;
use G8scope\Core\Contracts\Scrubber;
use G8scope\Core\Contracts\SupportProvider;
use G8scope\Core\Endpoints\DefaultEndpointResolver;
use G8scope\Core\Entitlement\EloquentEntitlementGate;
use G8scope\Core\Fingerprinting\DefaultFingerprinter;
use G8scope\Core\Fingerprinting\NormalizerRegistry;
use G8scope\Core\Fingerprinting\Normalizers\GoNormalizer;
use G8scope\Core\Fingerprinting\Normalizers\JavascriptNormalizer;
use G8scope\Core\Fingerprinting\Normalizers\PhpNormalizer;
use G8scope\Core\Fingerprinting\Normalizers\PythonNormalizer;
use G8scope\Core\Models\Project;
use G8scope\Core\Notifications\ChannelNotifier;
use G8scope\Core\Notifications\Channels\MailChannel;
use G8scope\Core\Notifications\Channels\SlackChannel;
use G8scope\Core\Notifications\Channels\TelegramChannel;
use G8scope\Core\Notifications\Channels\WebhookChannel;
use G8scope\Core\Observers\ProjectObserver;
use G8scope\Core\Privacy\PiiProtectionScrubber;
use G8scope\Core\Reporting\FilesystemReportGenerator;
use G8scope\Core\Stores\ClickHouseOccurrenceStore;
use G8scope\Core\Stores\EloquentIssueStore;
use G8scope\Core\Support\G8deskSupportProvider;
use Illuminate\Support\ServiceProvider;

class G8scopeCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/g8scope.php', 'g8scope');

        $this->bindFingerprinter();
        $this->bindDriver(Scrubber::class, 'scrubber', fn () => new PiiProtectionScrubber);
        $this->bindDriver(IssueStore::class, 'issue_store', fn () => new EloquentIssueStore);
        $this->bindOccurrenceStore();
        $this->bindNotifier();

        // P2 seams.
        $this->bindDriver(EntitlementGate::class, 'entitlement_gate', fn () => new EloquentEntitlementGate);
        $this->bindDriver(EndpointResolver::class, 'endpoint_resolver', fn () => new DefaultEndpointResolver);
        $this->bindDriver(ReportGenerator::class, 'report_generator', fn () => new FilesystemReportGenerator);
        $this->bindDriver(
            SupportProvider::class,
            'support_provider',
            fn () => G8deskSupportProvider::fromConfig((array) config('g8scope.g8desk', [])),
        );

        $this->app->singleton(
            AlertDispatcher::class,
            fn ($app) => new AlertDispatcher($app->make(Notifier::class)),
        );
    }

    public function boot(): void
    {
        Project::observe(ProjectObserver::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/g8scope.php' => config_path('g8scope.php'),
            ], 'g8scope-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'g8scope-migrations');
        }
    }

    /**
     * Bind a contract to its configured driver class, or a default factory.
     *
     * @param  callable():object  $default
     */
    private function bindDriver(string $contract, string $configKey, callable $default): void
    {
        $this->app->singleton($contract, function ($app) use ($configKey, $default) {
            $class = config("g8scope.drivers.{$configKey}");

            return $class ? $app->make($class) : $default();
        });
    }

    private function bindFingerprinter(): void
    {
        $this->app->singleton(NormalizerRegistry::class, fn () => new NormalizerRegistry([
            new PhpNormalizer,
            new JavascriptNormalizer,
            new PythonNormalizer,
            new GoNormalizer,
        ]));

        $this->bindDriver(
            Fingerprinter::class,
            'fingerprinter',
            fn () => new DefaultFingerprinter($this->app->make(NormalizerRegistry::class)),
        );
    }

    private function bindOccurrenceStore(): void
    {
        $this->app->singleton(ClickHouseOccurrenceStore::class, function ($app) {
            $class = config('g8scope.drivers.occurrence_store');
            if ($class) {
                return $app->make($class);
            }

            return ClickHouseOccurrenceStore::fromConfig((array) config('g8scope.clickhouse', []));
        });

        $this->app->alias(ClickHouseOccurrenceStore::class, OccurrenceStore::class);
        $this->app->alias(ClickHouseOccurrenceStore::class, OccurrenceWriter::class);
    }

    private function bindNotifier(): void
    {
        $this->bindDriver(Notifier::class, 'notifier', fn () => new ChannelNotifier([
            'mail' => new MailChannel,
            'slack' => new SlackChannel,
            'telegram' => new TelegramChannel,
            'webhook' => new WebhookChannel,
        ]));
    }
}
