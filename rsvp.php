<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_verify();

$id     = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'join';
$back   = $_POST['back'] ?? 'index.php';
if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $back) || str_starts_with($back, '//')) $back = 'index.php';

if (!user()) {
    flash('Log in to join gatherings.');
    redirect('auth.php?next=' . urlencode('event.php?id=' . $id));
}

switch ($action) {
    case 'accept_invite':
        $res = respond_invite($id, user_id(), 'accept');
        flash($res === 'joined' ? 'You\'re in! See you there.' : 'Couldn\'t accept — the gathering may be full or closed.', $res === 'joined' ? 'ok' : 'err');
        break;
    case 'decline_invite':
        respond_invite($id, user_id(), 'decline');
        flash('Invitation declined.');
        break;
    case 'cancel':
        rsvp($id, 'cancel');
        flash('RSVP cancelled.');
        break;
    case 'waitlist':
        $res = waitlist_join($id, user_id());
        flash($res === 'already' ? 'You\'re already on the waitlist.' : 'You\'re on the waitlist — we\'ll notify you if a spot opens.');
        waitlist_promote($id); // a seat may have freed between page load and POST
        break;
    case 'unwaitlist':
        waitlist_leave($id, user_id());
        flash('Removed from the waitlist.');
        break;
    case 'claim':
        $res = waitlist_claim($id, user_id());
        match ($res) {
            'joined'  => flash('You\'re in! Spot claimed.'),
            'expired' => flash('That offer expired — the spot went to the next person.', 'err'),
            'full'    => flash('The spot was taken before you could claim it.', 'err'),
            default   => flash('No offer to claim.', 'err'),
        };
        break;
    case 'decline_offer':
        waitlist_decline($id, user_id());
        flash('Passed — the spot goes to the next person.');
        break;
    default:
        $res = rsvp($id, 'join');
        match ($res) {
            'joined'    => flash('You\'re in! See you there.'),
            'full'      => flash('That gathering is already full.', 'err'),
            'cancelled' => flash('RSVP cancelled.'),
            default     => flash('That gathering is no longer open.', 'err'),
        };
        // notify host
        if ($res === 'joined') {
            $g = find_gathering($id);
            if ($g && $g['creator_id'] && (int) $g['creator_id'] !== user_id()) {
                notify((int) $g['creator_id'], 'rsvp', user()['name'] . ' joined "' . $g['title'] . '".', 'event.php?id=' . $id);
            }
        }
}
redirect($back);
