<?php
declare(strict_types=1);

const BOOKING_SELECT = "SELECT b.*, s.name AS slot_name, s.format, s.width, s.height, s.placement, s.site_id,
    si.name AS site_name, si.url AS site_url, c.name AS campaign_name, c.target_url, c.alt_text, c.banner_file, c.banner_mime,
    a.name AS adv_name, a.company AS adv_company, a.email AS adv_email,
    p.name AS pub_name, p.company AS pub_company, p.email AS pub_email
    FROM bookings b
    JOIN ad_slots s ON s.id = b.slot_id
    JOIN sites si ON si.id = s.site_id
    JOIN campaigns c ON c.id = b.campaign_id
    JOIN users a ON a.id = b.advertiser_id
    JOIN users p ON p.id = b.publisher_id";

/** Lädt eine Buchung und prüft, ob der aktuelle Benutzer beteiligt oder Admin ist. */
function load_booking(int $id): array
{
    $b = one(BOOKING_SELECT . ' WHERE b.id = ?', [$id]);
    $u = current_user();
    if (!$b || !($u['role'] === 'admin' || (int) $b['advertiser_id'] === (int) $u['id'] || (int) $b['publisher_id'] === (int) $u['id'])) {
        abort(404, 'Buchung nicht gefunden.');
    }
    return $b;
}

function bookings_index(): void
{
    $u = current_user();
    if ($u['role'] === 'admin') {
        redirect('admin.bookings');
    }
    // Konto mit beiden Rollen: Sicht wählbar (Standard: alle Buchungen, in denen das Konto Partei ist)
    $asFilter = '';
    if ($u['role'] === 'both') {
        $asFilter = in_array(input('as'), ['advertiser', 'publisher'], true) ? input('as') : '';
        [$where, $params] = match ($asFilter) {
            'advertiser' => ['b.advertiser_id = ?', [$u['id']]],
            'publisher'  => ['b.publisher_id = ?', [$u['id']]],
            default      => ['(b.advertiser_id = ? OR b.publisher_id = ?)', [$u['id'], $u['id']]],
        };
    } else {
        $where = ($u['role'] === 'advertiser' ? 'b.advertiser_id' : 'b.publisher_id') . ' = ?';
        $params = [$u['id']];
    }
    $status = input('status');
    if (isset(STATUS_LABELS[$status]) && in_array($status, ['requested', 'accepted', 'paid', 'completed', 'declined', 'cancelled', 'expired', 'refunded'], true)) {
        $where .= ' AND b.status = ?';
        $params[] = $status;
    }
    $result = paginate(BOOKING_SELECT . " WHERE $where ORDER BY b.created_at DESC, b.id DESC", $params, 20);
    $stats = Billing::statsFor(array_column($result['rows'], 'id'));
    view('bookings/index', ['title' => 'Buchungen', 'result' => $result, 'stats' => $stats, 'status' => $status, 'asFilter' => $asFilter]);
}

function booking_show(): void
{
    $b = load_booking(input_int('id'));
    $daily = all('SELECT * FROM booking_stats WHERE booking_id = ? ORDER BY day', [$b['id']]);
    $payments = all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id', [$b['id']]);
    view('bookings/show', [
        'title' => 'Buchung ' . $b['reference'],
        'b' => $b,
        'daily' => $daily,
        'payments' => $payments,
        'user' => current_user(),
    ]);
}

function booking_request(): void
{
    $slotId = input_int('slot_id');
    $id = Booking::request(uid(), $slotId, input_int('campaign_id'), input('start_date'), input('end_date'), input('message'));
    flash('success', 'Ihre Buchungsanfrage wurde gesendet. Der Webseitenbetreiber wird sie prüfen.');
    redirect('booking', ['id' => $id]);
}

function booking_accept(): void
{
    $id = input_int('id');
    Booking::accept(uid(), $id, input('note'));
    flash('success', 'Buchung angenommen. Der Werbetreibende erhält die Zahlungsinformationen.');
    redirect('booking', ['id' => $id]);
}

function booking_decline(): void
{
    $id = input_int('id');
    Booking::decline(uid(), $id, input('note'));
    flash('success', 'Anfrage abgelehnt.');
    redirect('booking', ['id' => $id]);
}

function booking_cancel(): void
{
    $id = input_int('id');
    Booking::cancel(current_user(), $id, input('note'));
    flash('success', 'Buchung storniert.');
    redirect('booking', ['id' => $id]);
}

function booking_report_payment(): void
{
    $id = input_int('id');
    Booking::reportPayment(uid(), $id);
    flash('success', 'Danke! Die Zahlung wurde als überwiesen gemeldet. Die Buchung wird nach Zahlungseingang freigeschaltet.');
    redirect('booking', ['id' => $id]);
}

/** Druckbarer Beleg: Rechnung (Werbetreibender) bzw. Gutschrift (Betreiber). */
function booking_receipt(): void
{
    $b = load_booking(input_int('id'));
    if (!$b['invoice_no']) {
        throw new UserError('Für diese Buchung liegt noch kein Beleg vor (erst nach Zahlungseingang).');
    }
    $u = current_user();
    $type = input('type') === 'credit' || (int) $u['id'] === (int) $b['publisher_id'] ? 'credit' : 'invoice';
    if ($type === 'credit' && $u['role'] !== 'admin' && (int) $u['id'] !== (int) $b['publisher_id']) {
        $type = 'invoice';
    }
    if ($type === 'invoice' && $u['role'] !== 'admin' && (int) $u['id'] !== (int) $b['advertiser_id']) {
        $type = 'credit';
    }
    $payments = all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id', [$b['id']]);
    view('bookings/receipt', ['title' => 'Beleg ' . $b['invoice_no'], 'b' => $b, 'type' => $type, 'payments' => $payments], 'print');
}
