<?php
// ============================================================
//  validation.php — the rules about what counts as valid input.
//  The browser checks forms too (required, pattern, min), but a browser check
//  is only a convenience: anyone can send a request without using our form.
//  Every rule that matters has to be enforced here as well.
// ============================================================

defined('MALAEB') or exit('Direct access is not allowed.');

// The sport and status lists live here once. They used to be typed out again in
// courts.php, add_court.php and edit_court.php, so adding a sport meant
// remembering three files — and the database ENUM was a silent fourth.
const SPORTS = ['Football', 'Padel', 'Basketball', 'Volleyball', 'Tennis'];
const COURT_STATUSES = ['available' => 'Available', 'maintenance' => 'Maintenance'];

// How far ahead customers may book, and how long one booking may run.
const MAX_BOOKING_DAYS_AHEAD = 90;
const MAX_BOOKING_HOURS = 8;

// Read a form/query field as a trimmed string without tripping PHP's
// "undefined array key" warning when a field is missing entirely.
function input(array $source, string $key): string {
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

// Is this one of the values we actually allow? Anything else is either a typo
// in our own code or someone hand-crafting a request.
function valid_choice(string $value, array $allowed): bool {
    return in_array($value, array_is_list($allowed) ? $allowed : array_keys($allowed), true);
}

// "2025-11-20" and a real calendar date (so 2025-02-30 is rejected).
function valid_date(string $date): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

// "18:00" or "18:00:00". Browsers send either depending on the step attribute.
function valid_time(string $time): bool {
    return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time);
}

// Normalise "18:00" and "18:00:00" to one form so string comparison and MySQL
// both behave. Comparing "9:00" against "18:00" as text would sort wrong, but
// the regex above already rejects a missing leading zero.
function normalize_time(string $time): string {
    return substr($time, 0, 5) . ':00';
}

// Whole/half hours only, matching the step="1800" on the time inputs.
function on_half_hour(string $time): bool {
    return in_array(substr($time, 3, 2), ['00', '30'], true);
}

function valid_email(string $email): bool {
    return strlen($email) <= 120 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Saudi mobile format, the same rule the register form's pattern attribute uses.
function valid_phone(string $phone): bool {
    return (bool)preg_match('/^05\d{8}$/', $phone);
}

function valid_name(string $name): bool {
    $len = mb_strlen($name);
    return $len >= 2 && $len <= 100;
}

// Shared date/time checks for booking.php and edit_booking.php, which had
// drifted apart: only one of them refused dates in the past.
// Returns an error message, or '' when everything is fine.
function validate_slot(string $date, string $start, string $end): string {
    if (!valid_date($date)) {
        return "Please choose a valid date.";
    }
    if (!valid_time($start) || !valid_time($end)) {
        return "Please choose a valid start and end time.";
    }
    if (!on_half_hour($start) || !on_half_hour($end)) {
        return "Bookings start and end on the hour or half hour.";
    }
    if (normalize_time($end) <= normalize_time($start)) {
        return "End time must be after start time.";
    }

    $today = new DateTimeImmutable('today');
    $day   = new DateTimeImmutable($date);
    if ($day < $today) {
        return "You cannot book a date in the past.";
    }
    if ($day > $today->modify('+' . MAX_BOOKING_DAYS_AHEAD . ' days')) {
        return "You can only book up to " . MAX_BOOKING_DAYS_AHEAD . " days ahead.";
    }
    if (slot_hours($start, $end) > MAX_BOOKING_HOURS) {
        return "A single booking can be at most " . MAX_BOOKING_HOURS . " hours.";
    }

    return '';
}

// Length of a slot in hours. Built from the clock values rather than strtotime()
// so it cannot be thrown off by the server's timezone or by daylight saving.
function slot_hours(string $start, string $end): float {
    [$sh, $sm] = array_map('intval', explode(':', $start));
    [$eh, $em] = array_map('intval', explode(':', $end));
    return (($eh * 60 + $em) - ($sh * 60 + $sm)) / 60;
}
