<?php

declare(strict_types=1);

namespace G8Scope\Core;

use G8Scope\Core\Alerting\AlertDispatcher;
use G8Scope\Core\Apm\EloquentApmStore;
use G8Scope\Core\Billing\ManualBillingProvider;
use G8Scope\Core\Contracts\ApmStore;
use G8Scope\Core\Contracts\BillingProvider;
use G8Scope\Core\Contracts\EndpointResolver;
use G8Scope\Core\Contracts\EntitlementGate;
use G8Scope\Core\Contracts\Fingerprinter;
use G8Scope\Core\Contracts\IssueStore;
use G8Scope\Core\Contracts\MetricStore;
use G8Scope\Core\Contracts\Notifier;
use G8Scope\Core\Contracts\OccurrenceStore;
use G8Scope\Core\Contracts\OccurrenceWriter;
use G8Scope\Core\Contracts\ReportGenerator;
use G8Scope\Core\Contracts\Scrubber;
use G8Scope\Core\Contracts\SupportProvider;
use G8Scope\Core\Endpoints\DefaultEndpointResolver;
use G8Scope\Core\Entitlement\EloquentEntitlementGate;
use G8Scope\Core\Fingerprinting\DefaultFingerprinter;
use G8Scope\Core\Fingerprinting\NormalizerRegistry;
use G8Scope\Core\Fingerprinting\Normalizers\GoNormalizer;
use G8Scope\Core\Fingerprinting\Normalizers\JavascriptNormalizer;
use G8Scope\Core\Fingerprinting\Normalizers\PhpNormalizer;
use G8Scope\Core\Fingerprinting\Normalizers\PythonNormalizer;
use G8Scope\Core\Metrics\EloquentMetricStore;
use G8Scope\Core\Models\Project;
use G8Scope\Core\Notifications\ChannelNotifier;
use G8Scope\Core\Notifications\Channels\MailChannel;
use G8Scope\Core\Notifications\Channels\SlackChannel;
use G8Scope\Core\Notifications\Channels\TelegramChannel;
use G8Scope\Core\Notifications\Channels\WebhookChannel;
use G8Scope\Core\Observers\ProjectObserver;
use G8Scope\Core\Privacy\PiiProtectionScrubber;
use G8Scope\Core\Reporting\FilesystemReportGenerator;
use G8Scope\Core\Stores\ClickHouseOccurrenceStore;
use G8Scope\Core\Stores\EloquentIssueStore;
use G8Scope\Core\Support\G8DeskSupportProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class G8ScopeCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // spatie/laravel-package-tools: config/g8scope.php (tag g8scope-config) +
        // auto-discovered, publishable, auto-run migrations from database/migrations.
        $package
            ->name('g8scope')
            ->hasConfigFile()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->bindFingerprinter();
        $this->bindDriver(Scrubber::class, 'scrubber', fn () => new PiiProtectionScrubber);
        $this->bindDriver(IssueStore::class, 'issue_store', fn () => new EloquentIssueStore);
        $this->bindOccurrenceStore();
        $this->bindNotifier();

        // P2 seams.
        $this->bindDriver(EntitlementGate::class, 'entitlement_gate', fn () => new EloquentEntitlementGate);
        $this->bindDriver(BillingProvider::class, 'billing_provider', fn () => new ManualBillingProvider);
        $this->bindDriver(ApmStore::class, 'apm_store', fn () => new EloquentApmStore);
        $this->bindDriver(MetricStore::class, 'metric_store', fn () => new EloquentMetricStore);
        $this->bindDriver(EndpointResolver::class, 'endpoint_resolver', fn () => new DefaultEndpointResolver);
        $this->bindDriver(ReportGenerator::class, 'report_generator', fn () => new FilesystemReportGenerator);
        $this->bindDriver(
            SupportProvider::class,
            'support_provider',
            fn () => G8DeskSupportProvider::fromConfig((array) config('g8scope.g8desk', [])),
        );

        $this->app->singleton(
            AlertDispatcher::class,
            fn ($app) => new AlertDispatcher($app->make(Notifier::class)),
        );
    }

    public function packageBooted(): void
    {
        Project::observe(ProjectObserver::class);

        // Ensure migrations load for consumers and tests (not just publishable).
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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
