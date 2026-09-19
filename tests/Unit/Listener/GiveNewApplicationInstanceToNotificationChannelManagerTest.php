<?php
declare(strict_types=1);

namespace Crustum\Stimulus\Test\Unit\Listener;

use Cake\Datasource\EntityInterface;
use Cake\Http\ServerRequest;
use Crustum\Notification\AnonymousNotifiable;
use Crustum\Notification\Channel\ChannelInterface;
use Crustum\Notification\Notification;
use Crustum\Notification\NotificationManager;
use Crustum\Stimulus\Event\RequestReceivedEvent;
use Crustum\Stimulus\Listener\GiveNewApplicationInstanceToNotificationChannelManager;
use Crustum\Stimulus\Test\TestCase;
use stdClass;

/**
 * Tests for the notification channel manager refreshing listener.
 *
 * Covers the notification channel manager state scenario: resolved channels
 * are forgotten while their registrations survive for the rebuild.
 */
class GiveNewApplicationInstanceToNotificationChannelManagerTest extends TestCase
{
    /**
     * Resolved channels are forgotten so the next send rebuilds them.
     *
     * @return void
     */
    public function testResolvedChannelsAreForgotten(): void
    {
        $channel = new class implements ChannelInterface {
            /**
             * Discards the notification.
             *
             * @param \Cake\Datasource\EntityInterface|\Crustum\Notification\AnonymousNotifiable $notifiable Entity receiving the notification.
             * @param \Crustum\Notification\Notification $notification Notification to send.
             * @return mixed Channel-specific response.
             */
            public function send(EntityInterface|AnonymousNotifiable $notifiable, Notification $notification): mixed
            {
                return null;
            }
        };

        NotificationManager::getRegistry()->set('stimulus_test_channel', $channel);

        $this->assertTrue(NotificationManager::getRegistry()->has('stimulus_test_channel'));

        try {
            $event = new RequestReceivedEvent(new stdClass(), new stdClass(), new ServerRequest(['url' => '/']));

            (new GiveNewApplicationInstanceToNotificationChannelManager())->handleRequestReceived($event);

            $this->assertFalse(NotificationManager::getRegistry()->has('stimulus_test_channel'));
        } finally {
            NotificationManager::getRegistry()->unload('stimulus_test_channel');
        }
    }
}
