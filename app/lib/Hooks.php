<?php
declare(strict_types=1);

/**
 * Minimaler Event-Bus als Integrationspunkt (z. B. VylarisGroup / Vylaris-Licence,
 * Webhooks, E-Mail-Benachrichtigungen, spätere Zahlungsanbieter).
 *
 * Ereignisse: user.registered, booking.requested, booking.accepted, booking.declined,
 * booking.cancelled, booking.paid, booking.refunded, booking.completed, payout.recorded,
 * entity.reviewed, user.deleted
 *
 * Listener dürfen den Kernablauf nie blockieren: Fehler werden geloggt, nicht geworfen.
 */
final class Hooks
{
    /** @var array<string, list<callable>> */
    private static array $listeners = [];

    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    public static function fire(string $event, array $payload = []): void
    {
        foreach (array_merge(self::$listeners[$event] ?? [], self::$listeners['*'] ?? []) as $listener) {
            try {
                $listener($payload, $event);
            } catch (Throwable $e) {
                error_log("[VylarisAdvertise] Hook '$event' fehlgeschlagen: " . $e->getMessage());
            }
        }
    }
}
