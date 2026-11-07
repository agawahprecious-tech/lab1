<?php
declare(strict_types=1);
session_start();

/* ---------- Configuration: edit for your environment ---------- */
const DB_HOST = 'localhost';
const DB_NAME = 'lab01_student_registration';
const DB_USER = 'root';
const DB_PASS = '';

/* ---------- Helpers ---------- */
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function post(string $key): string
{
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? trim($_POST[$key]) : '';
}

function err(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="error" id="' . h($key) . '-error" role="alert">' . h($errors[$key]) . '</p>' : '';
}

function inv(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . h($key) . '-error"' : '';
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ---------- Allowed values ---------- */
$programmes = ['BSc Software Engineering', 'BSc Computer Science', 'BSc Information Systems', 'BSc Cybersecurity'];
$levels     = ['Year 1', 'Year 2', 'Year 3', 'Year 4'];
$genders    = ['Male', 'Female'];

/* ---------- State ---------- */
$errors = [];
$old = ['full_name' => '', 'email' => '', 'telephone' => '', 'date_of_birth' => '',
        'programme' => '', 'level' => '', 'gender' => '', 'policy' => ''];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* ---------- Handle submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], post('csrf'))) {
        $errors['form'] = 'Your session expired. Reload the page and submit again.';
    }

    foreach ($old as $key => $_) {
        $old[$key] = post($key);
    }
    $old['full_name'] = preg_replace('/\s+/u', ' ', $old['full_name']);

    // Full name: 3-80 characters, letters plus space . ' -
    $len = mb_strlen($old['full_name']);
    if ($old['full_name'] === '') {
        $errors['full_name'] = 'Enter your full name.';
    } elseif ($len < 3 || $len > 80) {
        $errors['full_name'] = 'Name must be 3 to 80 characters.';
    } elseif (!preg_match("/^\p{L}[\p{L} .'\-]*$/u", $old['full_name'])) {
        $errors['full_name'] = "Use letters, spaces, apostrophes, hyphens and full stops only.";
    }

    // Email: valid format (uniqueness is checked against the database below)
    $email = mb_strtolower($old['email']);
    if ($email === '') {
        $errors['email'] = 'Enter your email address.';
    } elseif (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, e.g. name@example.com.';
    }

    // Telephone: 9 digits, starting with 6 (Cameroon mobile). Spaces, hyphens and +237 are tolerated.
    $phone = '';
    if ($old['telephone'] === '') {
        $errors['telephone'] = 'Enter your telephone number.';
    } else {
        $compact = preg_replace('/[\s\-]/', '', $old['telephone']);
        if (preg_match('/^(?:\+?237)?(6\d{8})$/', $compact, $m)) {
            $phone = $m[1];
        } else {
            $errors['telephone'] = 'Enter 9 digits starting with 6, e.g. 671234567.';
        }
    }

    // Date of birth: real calendar date, in the past
    $dob = DateTime::createFromFormat('!Y-m-d', $old['date_of_birth']);
    if ($old['date_of_birth'] === '') {
        $errors['date_of_birth'] = 'Enter your date of birth.';
    } elseif (!$dob || $dob->format('Y-m-d') !== $old['date_of_birth']) {
        $errors['date_of_birth'] = 'Enter a valid date (YYYY-MM-DD).';
    } elseif ($dob >= new DateTime('today')) {
        $errors['date_of_birth'] = 'Date of birth must be in the past.';
    } elseif ($dob < new DateTime('1900-01-01')) {
        $errors['date_of_birth'] = 'Date of birth must be 1900 or later.';
    }

    // Programme, level, gender, policy
    if ($old['programme'] === '') {
        $errors['programme'] = 'Choose a programme.';
    } elseif (!in_array($old['programme'], $programmes, true)) {
        $errors['programme'] = 'Choose a programme from the list.';
    }
    if ($old['level'] === '') {
        $errors['level'] = 'Choose a level.';
    } elseif (!in_array($old['level'], $levels, true)) {
        $errors['level'] = 'Choose a level from the list.';
    }
    if ($old['gender'] !== '' && !in_array($old['gender'], $genders, true)) {
        $errors['gender'] = 'Choose one of the listed options.';
    }
    if ($old['policy'] !== '1') {
        $errors['policy'] = 'You must accept the registration policy.';
    }

    // Insert only when everything is valid
    if (!$errors) {
        try {
            $pdo = db();
            $dup = $pdo->prepare('SELECT 1 FROM students WHERE email = :email LIMIT 1');
            $dup->execute([':email' => $email]);

            if ($dup->fetchColumn()) {
                $errors['email'] = 'This email address is already registered.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO students (full_name, email, telephone, date_of_birth, programme, level, gender, policy_agreed)
                     VALUES (:full_name, :email, :telephone, :dob, :programme, :level, :gender, 1)'
                );
                $stmt->execute([
                    ':full_name' => $old['full_name'],
                    ':email'     => $email,
                    ':telephone' => $phone,
                    ':dob'       => $dob->format('Y-m-d'),
                    ':programme' => $old['programme'],
                    ':level'     => $old['level'],
                    ':gender'    => $old['gender'] !== '' ? $old['gender'] : null,
                ]);

                if ($stmt->rowCount() === 1) {
                    $_SESSION['flash'] = ['type' => 'success',
                        'msg' => 'Registration saved. Your student number is ' . $pdo->lastInsertId() . '.'];
                    header('Location: index.php', true, 303);
                    exit;
                }
                $errors['form'] = 'The registration could not be saved. Please try again.';
            }
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {          // duplicate key (race condition)
                $errors['email'] = 'This email address is already registered.';
            } else {
                error_log('Lab01 insert failed: ' . $e->getMessage());
                $errors['form'] = 'The database is unavailable right now. Please try again later.';
            }
        }
    }
}

/* ---------- Extension: searchable list ---------- */
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$rows = [];
$listError = null;
try {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $stmt = db()->prepare(
        'SELECT student_id, full_name, email, programme, level, created_at
         FROM students
         WHERE full_name LIKE :q1 OR email LIKE :q2 OR programme LIKE :q3
         ORDER BY created_at DESC, student_id DESC LIMIT 50'
    );
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Lab01 list failed: ' . $e->getMessage());
    $listError = 'The registrations list is unavailable (database connection problem).';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Registration</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
  <h1 class="page-title">Student registration</h1>
  <p class="subtitle">Register for a degree programme. Fields marked * are required.</p>

  <section class="card" aria-labelledby="form-title">
    <h2 id="form-title">Registration form</h2>

    <?php if ($flash): ?>
      <div class="alert alert-success" role="status"><?= h($flash['msg']) ?></div>
    <?php endif; ?>
    <?php if (isset($errors['form'])): ?>
      <div class="alert alert-error" role="alert"><?= h($errors['form']) ?></div>
    <?php elseif ($errors): ?>
      <div class="alert alert-error" role="alert">Please correct the highlighted fields.</div>
    <?php endif; ?>

    <form method="post" action="index.php" novalidate>
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

      <div class="form-group">
        <label class="required" for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" maxlength="80" autocomplete="name"
               value="<?= h($old['full_name']) ?>"<?= inv($errors, 'full_name') ?>>
        <?= err($errors, 'full_name') ?>
      </div>

      <div class="form-group">
        <label class="required" for="email">Email address</label>
        <input type="email" id="email" name="email" maxlength="150" autocomplete="email"
               value="<?= h($old['email']) ?>"<?= inv($errors, 'email') ?>>
        <?= err($errors, 'email') ?>
      </div>

      <div class="form-group">
        <label class="required" for="telephone">Telephone</label>
        <input type="tel" id="telephone" name="telephone" inputmode="numeric" autocomplete="tel"
               placeholder="671234567" value="<?= h($old['telephone']) ?>"<?= inv($errors, 'telephone') ?>>
        <p class="hint">9 digits starting with 6. A +237 prefix is accepted and removed.</p>
        <?= err($errors, 'telephone') ?>
      </div>

      <div class="form-group">
        <label class="required" for="date_of_birth">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth" max="<?= h(date('Y-m-d', strtotime('-1 day'))) ?>"
               value="<?= h($old['date_of_birth']) ?>"<?= inv($errors, 'date_of_birth') ?>>
        <?= err($errors, 'date_of_birth') ?>
      </div>

      <div class="form-group">
        <label class="required" for="programme">Programme</label>
        <select id="programme" name="programme"<?= inv($errors, 'programme') ?>>
          <option value="">Choose an option</option>
          <?php foreach ($programmes as $p): ?>
            <option value="<?= h($p) ?>"<?= $old['programme'] === $p ? ' selected' : '' ?>><?= h($p) ?></option>
          <?php endforeach; ?>
        </select>
        <?= err($errors, 'programme') ?>
      </div>

      <div class="form-group">
        <label class="required" for="level">Level</label>
        <select id="level" name="level"<?= inv($errors, 'level') ?>>
          <option value="">Choose an option</option>
          <?php foreach ($levels as $l): ?>
            <option value="<?= h($l) ?>"<?= $old['level'] === $l ? ' selected' : '' ?>><?= h($l) ?></option>
          <?php endforeach; ?>
        </select>
        <?= err($errors, 'level') ?>
      </div>

      <fieldset>
        <legend>Gender</legend>
        <?php foreach ($genders as $g): ?>
          <label class="choice">
            <input type="radio" name="gender" value="<?= h($g) ?>"<?= $old['gender'] === $g ? ' checked' : '' ?>>
            <?= h($g) ?>
          </label>
        <?php endforeach; ?>
        <?= err($errors, 'gender') ?>
      </fieldset>

      <div class="form-group">
        <span class="required" style="font-weight:600">Accept registration policy</span><br>
        <label class="checkbox-row" for="policy">
          <input type="checkbox" id="policy" name="policy" value="1"<?= $old['policy'] === '1' ? ' checked' : '' ?><?= inv($errors, 'policy') ?>>
          I agree
        </label>
        <?= err($errors, 'policy') ?>
      </div>

      <div class="actions">
        <button type="submit" class="btn-primary">Submit Form</button>
        <button type="reset" class="btn-secondary">Reset</button>
      </div>
    </form>
  </section>

  <section class="card" aria-labelledby="list-title">
    <h2 id="list-title">Registered students</h2>
    <form method="get" action="index.php" class="search-row" role="search">
      <label for="q" class="visually-hidden" style="position:absolute;left:-9999px">Search registrations</label>
      <input type="search" id="q" name="q" placeholder="Search by name, email or programme" value="<?= h($q) ?>">
      <button type="submit" class="btn-primary">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-secondary" href="index.php">Clear</a><?php endif; ?>
    </form>

    <?php if ($listError): ?>
      <p class="error"><?= h($listError) ?></p>
    <?php elseif (!$rows): ?>
      <p class="empty">No registrations found.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>No.</th><th>Name</th><th>Email</th><th>Programme</th><th>Level</th><th>Registered</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['student_id']) ?></td>
              <td><?= h($r['full_name']) ?></td>
              <td><?= h($r['email']) ?></td>
              <td><?= h($r['programme']) ?></td>
              <td><?= h($r['level']) ?></td>
              <td><?= h($r['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
