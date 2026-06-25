<?php

declare(strict_types=1);

use G8Scope\Core\Concerns\HasDualKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('dummies', function ($table): void {
        $table->id();
        $table->uuid('uuid')->nullable();
        $table->timestamps();
    });
});

it('auto-fills a uuid on create and binds routes on uuid', function (): void {
    $model = new class extends Model
    {
        use HasDualKey;

        protected $table = 'dummies';

        protected $guarded = [];
    };

    $saved = $model->newInstance();
    $saved->save();

    expect($saved->uuid)->not->toBeNull()
        ->and($saved->getRouteKeyName())->toBe('uuid')
        ->and($saved->id)->toBeInt();
});
