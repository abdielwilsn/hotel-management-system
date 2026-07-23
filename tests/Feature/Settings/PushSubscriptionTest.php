<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use NotificationChannels\WebPush\PushSubscription;

uses(RefreshDatabase::class);

test('a browser can register a push subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/push-subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-endpoint',
            'public_key' => 'BExamplePublicKey',
            'auth_token' => 'exampleAuthToken',
            'content_encoding' => 'aesgcm',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $user->id,
        'subscribable_type' => $user->getMorphClass(),
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/example-endpoint',
        'public_key' => 'BExamplePublicKey',
    ]);
});

test('re-registering the same endpoint updates it rather than duplicating it', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/example-endpoint';

    $this->actingAs($user)->post('/settings/push-subscriptions', [
        'endpoint' => $endpoint,
        'public_key' => 'first-key',
    ]);

    $this->actingAs($user)->post('/settings/push-subscriptions', [
        'endpoint' => $endpoint,
        'public_key' => 'second-key',
    ]);

    expect(PushSubscription::query()->where('endpoint', $endpoint)->count())->toBe(1);
    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => $endpoint,
        'public_key' => 'second-key',
    ]);
});

test('removing a subscription deletes it', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/example-endpoint';

    $this->actingAs($user)->post('/settings/push-subscriptions', ['endpoint' => $endpoint]);

    $this->actingAs($user)
        ->delete('/settings/push-subscriptions', ['endpoint' => $endpoint])
        ->assertRedirect();

    $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
});

test('the notification settings page reports the vapid public key and subscription state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/notification-preferences')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Notifications')
            ->where('hasSubscriptions', false));
});
