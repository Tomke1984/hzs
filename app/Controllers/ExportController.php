<?php
/** Gegevens exporteren naar CSV (Excel). Medische gegevens en noodcontacten zitten bewust nooit in een export. */
final class ExportController
{
    public static function index(): void
    {
        render('export/index', [
            'groups' => Database::all('SELECT id, name, category FROM `groups` ORDER BY category, name'),
            'seasons' => Database::all('SELECT id, name FROM seasons ORDER BY start_date DESC'),
            'from' => date('Y-m-d', strtotime('-90 days')), 'to' => today(),
        ], 'Exporteren');
    }

    public static function download(): void
    {
        $what = (string) ($_GET['what'] ?? '');
        $date = date('Y-m-d');
        switch ($what) {
            case 'members':
                $gid = (int) ($_GET['g'] ?? 0);
                $status = (string) ($_GET['status'] ?? 'ACTIVE');
                $where = ['1=1'];
                $params = [];
                if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                    $where[] = 'm.status = ?';
                    $params[] = $status;
                }
                if ($gid) {
                    $where[] = 'EXISTS (SELECT 1 FROM group_memberships x WHERE x.member_id = m.id AND x.group_id = ?)';
                    $params[] = $gid;
                }
                $rows = [];
                foreach (Database::all(
                    "SELECT m.*, (SELECT g.name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id WHERE gm.member_id = m.id ORDER BY gm.id LIMIT 1) AS group_name
                     FROM members m WHERE " . implode(' AND ', $where) . ' ORDER BY m.last_name, m.first_name',
                    $params
                ) as $m) {
                    $parents = Database::all('SELECT u.display_name, u.email FROM guardian_links gl JOIN users u ON u.id = gl.user_id WHERE gl.member_id = ? ORDER BY u.display_name', [$m['id']]);
                    $rows[] = [$m['last_name'], $m['first_name'], $m['birth_date'], $m['email'], $m['phone'], $m['street'], $m['postal_code'], $m['city'],
                        $m['status'] === 'ACTIVE' ? 'Actief' : 'Inactief', $m['status_reason'], $m['group_name'],
                        implode(' | ', array_column($parents, 'display_name')), implode(' | ', array_column($parents, 'email'))];
                }
                self::log('members', count($rows));
                Csv::send("leden-$date.csv", ['Achternaam', 'Voornaam', 'Geboortedatum', 'E-mail', 'Telefoon', 'Straat', 'Postcode', 'Gemeente', 'Status', 'Reden', 'Groep', 'Ouders/voogden', 'E-mail ouders'], $rows);
                // no break (Csv::send beëindigt het verzoek)
            case 'attendance':
                $from = valid_date((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-d', strtotime('-90 days'));
                $to = valid_date((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : today();
                $gid = (int) ($_GET['g'] ?? 0);
                $params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
                $extra = '';
                if ($gid) {
                    $extra = ' AND EXISTS (SELECT 1 FROM group_memberships x WHERE x.member_id = m.id AND x.group_id = ?)';
                    $params[] = $gid;
                }
                $rows = [];
                foreach (Database::all(
                    "SELECT a.starts_at, a.title, t.name AS type_name, m.last_name, m.first_name, at.status
                     FROM attendance at JOIN activities a ON a.id = at.activity_id JOIN activity_types t ON t.id = a.type_id JOIN members m ON m.id = at.member_id
                     WHERE a.starts_at >= ? AND a.starts_at < ?$extra ORDER BY a.starts_at, m.last_name, m.first_name",
                    $params
                ) as $r) {
                    $rows[] = [substr($r['starts_at'], 0, 10), substr($r['starts_at'], 11, 5), $r['title'], $r['type_name'], $r['last_name'], $r['first_name'], attendance_label($r['status'])];
                }
                self::log('attendance', count($rows));
                Csv::send("aanwezigheden-$from-$to.csv", ['Datum', 'Uur', 'Activiteit', 'Type', 'Achternaam', 'Voornaam', 'Status'], $rows);
            case 'registrations':
                $rows = [];
                foreach (Database::all(
                    'SELECT r.*, p.first_name, p.last_name, p.birth_date, p.preferred_category FROM registrations r
                     LEFT JOIN registration_persons p ON p.registration_id = r.id ORDER BY r.id DESC, p.id'
                ) as $r) {
                    $rows[] = [substr($r['created_at'], 0, 10), $r['status'], $r['last_name'], $r['first_name'], $r['birth_date'], $r['preferred_category'],
                        $r['contact_name'], $r['relationship'], $r['contact_email'], $r['contact_phone'], $r['street'], $r['postal_code'], $r['city']];
                }
                self::log('registrations', count($rows));
                Csv::send("inschrijvingen-$date.csv", ['Aangemeld', 'Status', 'Achternaam', 'Voornaam', 'Geboortedatum', 'Voorkeur', 'Contact', 'Relatie', 'E-mail', 'Telefoon', 'Straat', 'Postcode', 'Gemeente'], $rows);
            case 'swimtests':
                $rows = [];
                foreach (Database::all('SELECT * FROM swimtest_requests ORDER BY id DESC') as $r) {
                    $rows[] = [substr($r['created_at'], 0, 10), $r['status'], $r['child_last_name'], $r['child_first_name'], $r['child_birth_date'],
                        SwimTestController::DIPLOMAS[$r['diploma']] ?? $r['diploma'], $r['contact_name'], $r['contact_email'], $r['contact_phone'], $r['postal_code'], $r['city'], $r['internal_note']];
                }
                self::log('swimtests', count($rows));
                Csv::send("zwemtest-aanvragen-$date.csv", ['Aangemeld', 'Status', 'Achternaam', 'Voornaam', 'Geboortedatum', 'Diploma', 'Contact', 'E-mail', 'Telefoon', 'Postcode', 'Gemeente', 'Interne notitie'], $rows);
            case 'dues':
                Auth::require('fees.manage');
                $sid = (int) ($_GET['season'] ?? 0);
                $rows = [];
                foreach (Database::all(
                    "SELECT d.*, m.last_name, m.first_name, s.name AS season_name,
                        (SELECT g.name FROM group_memberships gm JOIN `groups` g ON g.id = gm.group_id WHERE gm.member_id = m.id ORDER BY gm.id LIMIT 1) AS group_name
                     FROM dues d JOIN members m ON m.id = d.member_id JOIN seasons s ON s.id = d.season_id WHERE d.season_id = ? ORDER BY m.last_name, m.first_name",
                    [$sid]
                ) as $r) {
                    $rows[] = [$r['season_name'], $r['last_name'], $r['first_name'], $r['group_name'], number_format($r['amount_cents'] / 100, 2, ',', ''),
                        Fees::statusLabel($r['status']), $r['paid_at'], Fees::reference((int) $r['id'])];
                }
                self::log('dues', count($rows));
                Csv::send("lidgeld-$date.csv", ['Seizoen', 'Achternaam', 'Voornaam', 'Groep', 'Bedrag (EUR)', 'Status', 'Betaald op', 'Mededeling'], $rows);
            case 'subscribers':
                $rows = [];
                foreach (Database::all('SELECT * FROM subscribers WHERE confirmed_at IS NOT NULL AND unsubscribed_at IS NULL ORDER BY email') as $r) {
                    $rows[] = [$r['email'], $r['name'], substr($r['confirmed_at'], 0, 10)];
                }
                self::log('subscribers', count($rows));
                Csv::send("nieuwsbrief-$date.csv", ['E-mail', 'Naam', 'Bevestigd op'], $rows);
            default:
                abort(404, 'Onbekende export.');
        }
    }

    private static function log(string $what, int $rows): void
    {
        audit('EXPORT', 'export', null, "$what: $rows rijen");
    }
}
