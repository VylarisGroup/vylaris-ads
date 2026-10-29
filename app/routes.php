<?php
declare(strict_types=1);

// Route => [Controller-Datei, Funktion, erlaubte Methoden, Zugriff]
// Zugriff: public | guest | auth | advertiser | publisher | admin | tracking (ohne Sitzung)
// Jede POST-Route wird zentral auf CSRF geprüft; die Rollenprüfung erfolgt ebenfalls zentral.

return [
    'home'                   => ['market',     'market_index',          ['GET'],         'public'],
    'slot'                   => ['market',     'market_slot',           ['GET'],         'public'],

    'privacy'                => ['legal',      'legal_privacy',         ['GET'],         'public'],
    'imprint'                => ['legal',      'legal_imprint',         ['GET'],         'public'],
    'terms'                  => ['legal',      'legal_terms',           ['GET'],         'public'],

    'login'                  => ['auth',       'auth_login',            ['GET', 'POST'], 'guest'],
    'register'               => ['auth',       'auth_register',         ['GET', 'POST'], 'guest'],
    'verify'                 => ['auth',       'auth_verify',           ['GET'],         'public'],
    'verify.resend'          => ['auth',       'auth_verify_resend',    ['GET', 'POST'], 'guest'],
    'logout'                 => ['auth',       'auth_logout',           ['POST'],        'auth'],
    'account'                => ['auth',       'auth_account',          ['GET', 'POST'], 'auth'],

    'account.export'         => ['privacy',    'privacy_export',        ['POST'],        'auth'],
    'account.delete'         => ['privacy',    'privacy_delete',        ['POST'],        'auth'],

    'dashboard'              => ['dashboard',  'dashboard_index',       ['GET'],         'auth'],

    // Werbetreibende
    'campaigns'              => ['advertiser', 'campaigns_index',       ['GET'],         'advertiser'],
    'campaign.form'          => ['advertiser', 'campaign_form',         ['GET', 'POST'], 'advertiser'],
    'campaign.delete'        => ['advertiser', 'campaign_delete',       ['POST'],        'advertiser'],
    'booking.request'        => ['bookings',   'booking_request',       ['POST'],        'advertiser'],
    'booking.report_payment' => ['bookings',   'booking_report_payment', ['POST'],       'advertiser'],

    // Webseitenbetreiber
    'sites'                  => ['publisher',  'sites_index',           ['GET'],         'publisher'],
    'site.form'              => ['publisher',  'site_form',             ['GET', 'POST'], 'publisher'],
    'site.toggle'            => ['publisher',  'site_toggle',           ['POST'],        'publisher'],
    'site.delete'            => ['publisher',  'site_delete',           ['POST'],        'publisher'],
    'slot.form'              => ['publisher',  'slot_form',             ['GET', 'POST'], 'publisher'],
    'slot.toggle'            => ['publisher',  'slot_toggle',           ['POST'],        'publisher'],
    'slot.delete'            => ['publisher',  'slot_delete',           ['POST'],        'publisher'],
    'booking.accept'         => ['bookings',   'booking_accept',        ['POST'],        'publisher'],
    'booking.decline'        => ['bookings',   'booking_decline',       ['POST'],        'publisher'],
    'earnings'               => ['publisher',  'earnings_index',        ['GET'],         'publisher'],

    // Gemeinsam (Berechtigung je Buchung im Controller)
    'bookings'               => ['bookings',   'bookings_index',        ['GET'],         'auth'],
    'booking'                => ['bookings',   'booking_show',          ['GET'],         'auth'],
    'booking.cancel'         => ['bookings',   'booking_cancel',        ['POST'],        'auth'],
    'receipt'                => ['bookings',   'booking_receipt',       ['GET'],         'auth'],
    'banner'                 => ['media',      'media_banner',          ['GET'],         'auth'],

    // Assistenten (Schritt für Schritt; senden an dieselbe Logik wie die klassischen Formulare, die bestehen bleiben)
    'wizard.site'            => ['wizard',     'wizard_site',           ['GET', 'POST'], 'publisher'],
    'wizard.campaign'        => ['wizard',     'wizard_campaign',       ['GET'],         'advertiser'],
    'wizard.booking'         => ['wizard',     'wizard_booking',        ['GET'],         'advertiser'],
    'wizard.setup'           => ['wizard',     'wizard_setup',          ['GET', 'POST'], 'admin'],

    // Administration
    'admin.reviews'          => ['admin',      'admin_reviews',         ['GET'],         'admin'],
    'admin.review'           => ['admin',      'admin_review',          ['POST'],        'admin'],
    'admin.users'            => ['admin',      'admin_users',           ['GET'],         'admin'],
    'admin.user'             => ['admin',      'admin_user',            ['POST'],        'admin'],
    'admin.bookings'         => ['admin',      'admin_bookings',        ['GET'],         'admin'],
    'admin.payment'          => ['admin',      'admin_payment',         ['POST'],        'admin'],
    'admin.refund'           => ['admin',      'admin_refund',          ['POST'],        'admin'],
    'admin.payouts'          => ['admin',      'admin_payouts',         ['GET', 'POST'], 'admin'],
    'admin.finance'          => ['admin',      'admin_finance',         ['GET'],         'admin'],
    'admin.legal'            => ['admin',      'admin_legal',           ['POST'],        'admin'],
    'admin.settings'         => ['admin',      'admin_settings',        ['GET', 'POST'], 'admin'],
    'admin.update'           => ['admin',      'admin_update',          ['GET', 'POST'], 'admin'],
    'admin.changelog'        => ['admin',      'admin_changelog',       ['GET', 'POST'], 'admin'],
    'admin.mail'             => ['admin',      'admin_mail',            ['GET', 'POST'], 'admin'],
    'admin.audit'            => ['admin',      'admin_audit',           ['GET'],         'admin'],

    // Einbindung beim Betreiber (öffentlich, ohne Sitzung/Cookies)
    'serve'                  => ['media',      'media_serve',           ['GET'],         'tracking'],
    'click'                  => ['media',      'media_click',           ['GET'],         'tracking'],
];
