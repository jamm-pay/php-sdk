<?php

/**
 * Jamm SDK - Root Namespace Entry Point
 *
 * This provides a cleaner API by allowing:
 *   $jamm = new \Jamm(...)
 * instead of:
 *   $jamm = new \Jamm\Client(...)
 *
 * Usage:
 * $jamm = new \Jamm(
 *     clientId: 'your-client-id',
 *     clientSecret: 'your-client-secret',
 *     environment: 'prod'
 * );
 *
 * Access services:
 * $jamm->customer->create($buyer);
 * $jamm->payment->onSession(...);
 * $jamm->webhook->parse($payload);
 * $jamm->healthcheck->ping();
 */
class Jamm extends \Jamm\Client
{
    // Inherits all functionality from \Jamm\Client
    // This allows usage: new \Jamm() instead of new \Jamm\Client()
}
