<?php
declare(strict_types=1);

// Route => [Controller-Datei, Funktion, erlaubte Methoden, Zugriff]
// Fünftes Element (optional): Lizenz-Scope, siehe licence_scopes() in app/lib/Licence.php; ohne Scope immer erreichbar.
// Zugriff: public | guest | auth | advertiser | publisher | admin | tracking (ohne Sitzung)
// Jede POST-Route wird zentral auf CSRF geprüft; die Rollenprüfung erfolgt ebenfalls zentral.

return [
    'home'                   => ['market',     'market_index',          ['GET'],         'public', 'market.browse'],
    'slot'                   => ['market',     'market_slot',           ['GET'],         'public', 'market.browse'],

    'privacy'                => ['legal',      'legal_privacy',         ['GET'],         'public'],
    'imprint'                => ['legal',      'legal_imprint',         ['GET'],         'public'],
    'terms'                  => ['legal',      'legal_terms',           ['GET'],         'public'],

    'login'                  => ['auth',       'auth_login',            ['GET', 'POST'], 'guest'],
    'register'               => ['auth',       'auth_register',         ['GET', 'POST'], 'guest'],
    'logout'                 => ['auth',       'auth_logout',           ['POST'],        'auth'],
    'account'                => ['auth',       'auth_account',          ['GET', 'POST'], 'auth'],

    'account.export'         => ['privacy',    'privacy_export',        ['POST'],        'auth'],
    'account.delete'         => ['privacy',    'privacy_delete',        ['POST'],        'auth'],

    'dashboard'              => ['dashboard',  'dashboard_index',       ['GET'],         'auth'],

    // Werbetreibende
    'campaigns'              => ['advertiser', 'campaigns_index',       ['GET'],         'advertiser', 'campaigns.manage'],
    'campaign.form'          => ['advertiser', 'campaign_form',         ['GET', 'POST'], 'advertiser', 'campaigns.manage'],
    'campaign.delete'        => ['advertiser', 'campaign_delete',       ['POST'],        'advertiser', 'campaigns.manage'],
    'booking.request'        => ['bookings',   'booking_request',       ['POST'],        'advertiser', 'bookings.manage'],
    'booking.report_payment' => ['bookings',   'booking_report_payment', ['POST'],       'advertiser'],

    // Webseitenbetreiber
    'sites'                  => ['publisher',  'sites_index',           ['GET'],         'publisher', 'sites.manage'],
    'site.form'              => ['publisher',  'site_form',             ['GET', 'POST'], 'publisher', 'sites.manage'],
    'site.toggle'            => ['publisher',  'site_toggle',           ['POST'],        'publisher', 'sites.manage'],
    'site.delete'            => ['publisher',  'site_delete',           ['POST'],        'publisher', 'sites.manage'],
    'slot.form'              => ['publisher',  'slot_form',             ['GET', 'POST'], 'publisher', 'sites.manage'],
    'slot.toggle'            => ['publisher',  'slot_toggle',           ['POST'],        'publisher', 'sites.manage'],
    'slot.delete'            => ['publisher',  'slot_delete',           ['POST'],        'publisher', 'sites.manage'],
    'booking.accept'         => ['bookings',   'booking_accept',        ['POST'],        'publisher', 'bookings.manage'],
    'booking.decline'        => ['bookings',   'booking_decline',       ['POST'],        'publisher', 'bookings.manage'],
    'earnings'               => ['publisher',  'earnings_index',        ['GET'],         'publisher', 'stats.view'],

    // Gemeinsam (Berechtigung je Buchung im Controller)
    'bookings'               => ['bookings',   'bookings_index',        ['GET'],         'auth'],
    'booking'                => ['bookings',   'booking_show',          ['GET'],         'auth'],
    'booking.cancel'         => ['bookings',   'booking_cancel',        ['POST'],        'auth', 'bookings.manage'],
    'receipt'                => ['bookings',   'booking_receipt',       ['GET'],         'auth'],
    'banner'                 => ['media',      'media_banner',          ['GET'],         'auth'],

    // Administration
    'admin.reviews'          => ['admin',      'admin_reviews',         ['GET'],         'admin', 'admin.review'],
    'admin.review'           => ['admin',      'admin_review',          ['POST'],        'admin', 'admin.review'],
    'admin.users'            => ['admin',      'admin_users',           ['GET'],         'admin', 'users.manage'],
    'admin.user'             => ['admin',      'admin_user',            ['POST'],        'admin', 'users.manage'],
    'admin.bookings'         => ['admin',      'admin_bookings',        ['GET'],         'admin'],
    'admin.payment'          => ['admin',      'admin_payment',         ['POST'],        'admin', 'payments.manage'],
    'admin.refund'           => ['admin',      'admin_refund',          ['POST'],        'admin', 'payments.manage'],
    'admin.payouts'          => ['admin',      'admin_payouts',         ['GET', 'POST'], 'admin', 'payouts.manage'],
    'admin.finance'          => ['admin',      'admin_finance',         ['GET'],         'admin', 'stats.view'],
    'admin.legal'            => ['admin',      'admin_legal',           ['POST'],        'admin', 'legal.edit'],
    'admin.settings'         => ['admin',      'admin_settings',        ['GET', 'POST'], 'admin'],
    'admin.licence'          => ['admin',      'admin_licence',         ['GET', 'POST'], 'admin'],
    'admin.update'           => ['admin',      'admin_update',          ['GET', 'POST'], 'admin'],
    'admin.audit'            => ['admin',      'admin_audit',           ['GET'],         'admin', 'audit.view'],

    // Einbindung beim Betreiber (öffentlich, ohne Sitzung/Cookies)
    'serve'                  => ['media',      'media_serve',           ['GET'],         'tracking'],
    'click'                  => ['media',      'media_click',           ['GET'],         'tracking'],
];
