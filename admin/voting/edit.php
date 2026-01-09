<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

// Admin gate
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$electionId = (int)($_GET['id'] ?? 0);
if ($electionId < 1) {
    $_SESSION['error_message'] = 'Invalid election ID.';
    header('Location: /admin/voting/');
    exit;
}

// Fetch election
$stmt = $pdo->prepare("SELECT * FROM elections WHERE id = :id");
$stmt->execute([':id' => $electionId]);
$election = $stmt->fetch();

if (!$election) {
    $_SESSION['error_message'] = 'Election not found.';
    header('Location: /admin/voting/');
    exit;
}

// Only allow editing draft elections
if ($election['status'] !== 'draft') {
    $_SESSION['error_message'] = 'Only draft elections can be edited. This election is already ' . $election['status'] . '.';
    header('Location: /admin/voting/');
    exit;
}

// Fetch existing candidates
$candidateStmt = $pdo->prepare("
    SELECT id, candidate_name, candidate_description, display_order
    FROM election_candidates
    WHERE election_id = :election_id
    ORDER BY display_order ASC
");
$candidateStmt->execute([':election_id' => $electionId]);
$existingCandidates = $candidateStmt->fetchAll();

$errors = [];

// Default form values from existing data
$title = $election['title'];
$description = $election['description'] ?? '';
$max_selections = $election['max_selections'];
$opens_at_date = date('Y-m-d', strtotime($election['opens_at']));
$opens_at_time = date('H:i', strtotime($election['opens_at']));
$closes_at_date = date('Y-m-d', strtotime($election['closes_at']));
$closes_at_time = date('H:i', strtotime($election['closes_at']));

$candidates = [];
$candidateDescriptions = [];
foreach ($existingCandidates as $c) {
    $candidates[] = $c['candidate_name'];
    $candidateDescriptions[] = $c['candidate_description'] ?? '';
}

// Ensure at least 2 slots
while (count($candidates) < 2) {
    $candidates[] = '';
    $candidateDescriptions[] = '';
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $max_selections = (int)($_POST['max_selections'] ?? 1);
    $opens_at_date = trim($_POST['opens_at_date'] ?? '');
    $opens_at_time = trim($_POST['opens_at_time'] ?? '');
    $closes_at_date = trim($_POST['closes_at_date'] ?? '');
    $closes_at_time = trim($_POST['closes_at_time'] ?? '');

    // Collect candidates (filter out empty ones)
    $rawCandidates = $_POST['candidates'] ?? [];
    $rawDescriptions = $_POST['candidate_descriptions'] ?? [];
    $candidateData = [];

    foreach ($rawCandidates as $index => $name) {
        $name = trim($name);
        if ($name !== '') {
            $candidateData[] = [
                'name' => $name,
                'description' => trim($rawDescriptions[$index] ?? '')
            ];
        }
    }

    // Validation
    if ($title === '') {
        $errors[] = 'Election title is required.';
    }
    if (count($candidateData) < 2) {
        $errors[] = 'At least two candidates are required for an election.';
    }
    if ($max_selections < 1) {
        $errors[] = 'Voters must be able to select at least 1 candidate.';
    }
    if ($max_selections > count($candidateData) + 1) {
        $errors[] = 'Max selections cannot exceed the number of candidates plus one write-in.';
    }

    // Validate dates
    if ($opens_at_date === '' || $opens_at_time === '') {
        $errors[] = 'Opening date and time are required.';
    }
    if ($closes_at_date === '' || $closes_at_time === '') {
        $errors[] = 'Closing date and time are required.';
    }

    $opens_at = $opens_at_date . ' ' . $opens_at_time . ':00';
    $closes_at = $closes_at_date . ' ' . $closes_at_time . ':00';

    if (strtotime($closes_at) <= strtotime($opens_at)) {
        $errors[] = 'Closing date/time must be after opening date/time.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();

        try {
            // Store old values for activity log
            $oldValues = [
                'title' => $election['title'],
                'max_selections' => $election['max_selections'],
                'opens_at' => $election['opens_at'],
                'closes_at' => $election['closes_at'],
                'candidates' => count($existingCandidates)
            ];

            // Update election
            $sql = "UPDATE elections SET
                        title = :title,
                        description = :description,
                        max_selections = :max_selections,
                        opens_at = :opens_at,
                        closes_at = :closes_at
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':description' => $description !== '' ? $description : null,
                ':max_selections' => $max_selections,
                ':opens_at' => $opens_at,
                ':closes_at' => $closes_at,
                ':id' => $electionId
            ]);

            // Delete old candidates and insert new ones
            $pdo->prepare("DELETE FROM election_candidates WHERE election_id = :id")
                ->execute([':id' => $electionId]);

            $candidateSql = "INSERT INTO election_candidates
                                (election_id, candidate_name, candidate_description, display_order)
                             VALUES
                                (:election_id, :name, :description, :display_order)";
            $candidateStmt = $pdo->prepare($candidateSql);

            foreach ($candidateData as $order => $candidate) {
                $candidateStmt->execute([
                    ':election_id' => $electionId,
                    ':name' => $candidate['name'],
                    ':description' => $candidate['description'] !== '' ? $candidate['description'] : null,
                    ':display_order' => $order + 1
                ]);
            }

            // Log activity
            $activity_sql = "INSERT INTO activity_logs (
                                user_id, action_type, entity_type, entity_id,
                                description, old_value, new_value, ip_address, user_agent
                             ) VALUES (
                                :user_id, :action_type, :entity_type, :entity_id,
                                :description, :old_value, :new_value, :ip_address, :user_agent
                             )";
            $activity_stmt = $pdo->prepare($activity_sql);
            $activity_stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':action_type' => 'election_updated',
                ':entity_type' => 'election',
                ':entity_id' => $electionId,
                ':description' => "Updated election: {$title}",
                ':old_value' => json_encode($oldValues),
                ':new_value' => json_encode([
                    'title' => $title,
                    'candidates' => count($candidateData),
                    'max_selections' => $max_selections,
                    'opens_at' => $opens_at,
                    'closes_at' => $closes_at
                ]),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            $pdo->commit();

            $_SESSION['success_message'] = "Election \"{$title}\" updated successfully.";
            header('Location: /admin/voting/');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Election update error: ' . $e->getMessage());
            $errors[] = 'An error occurred while updating the election. Please try again.';
        }
    }

    // Preserve candidate entries for form redisplay
    $candidates = array_column($candidateData, 'name');
    $candidateDescriptions = array_column($candidateData, 'description');
}

$page_title = 'Edit Election | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Edit Election</h1>
                <p class="lead" style="margin-bottom: 0;">Modify the draft election details.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 large-7 cell">
        <?php if (!empty($errors)): ?>
            <div class="callout alert">
                <h5>There were some problems:</h5>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card arm-card">
            <div class="card-section">
                <form method="post" action="" id="election-form">
                    <label>Election Title *
                        <input type="text" name="title" required
                            placeholder="e.g. 2025 Board of Directors Election"
                            value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                    </label>

                    <label>Description
                        <textarea name="description" rows="3"
                            placeholder="Optional details about the election, positions being filled, etc."><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>

                    <hr style="margin: 1.5rem 0;">

                    <h4>Voting Period</h4>

                    <div class="grid-x grid-margin-x">
                        <div class="small-12 medium-6 cell">
                            <label>Opens On *
                                <input type="date" name="opens_at_date" required
                                    value="<?php echo htmlspecialchars($opens_at_date, ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                        <div class="small-12 medium-6 cell">
                            <label>At Time *
                                <input type="time" name="opens_at_time" required
                                    value="<?php echo htmlspecialchars($opens_at_time, ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                    </div>

                    <div class="grid-x grid-margin-x">
                        <div class="small-12 medium-6 cell">
                            <label>Closes On *
                                <input type="date" name="closes_at_date" required
                                    value="<?php echo htmlspecialchars($closes_at_date, ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                        <div class="small-12 medium-6 cell">
                            <label>At Time *
                                <input type="time" name="closes_at_time" required
                                    value="<?php echo htmlspecialchars($closes_at_time, ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                    </div>

                    <p class="help-text">All times are in Arizona time (MST).</p>

                    <hr style="margin: 1.5rem 0;">

                    <h4>Candidates</h4>

                    <label>Maximum Selections *
                        <select name="max_selections">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $max_selections == $i ? 'selected' : ''; ?>>
                                    <?php echo $i === 1 ? '1 candidate' : "Up to {$i} candidates"; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>

                    <div id="candidates-container">
                        <?php foreach ($candidates as $index => $candidateName): ?>
                            <div class="candidate-row" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                <div class="grid-x grid-margin-x">
                                    <div class="small-11 cell">
                                        <label>Candidate <?php echo $index + 1; ?> Name *
                                            <input type="text" name="candidates[]"
                                                placeholder="Full name"
                                                value="<?php echo htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8'); ?>">
                                        </label>
                                        <label>Brief Description (optional)
                                            <input type="text" name="candidate_descriptions[]"
                                                placeholder="e.g. Current board member, Member since 2020"
                                                value="<?php echo htmlspecialchars($candidateDescriptions[$index] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        </label>
                                    </div>
                                    <div class="small-1 cell" style="display: flex; align-items: center; justify-content: center;">
                                        <?php if ($index >= 2): ?>
                                            <button type="button" class="remove-candidate" style="background: none; border: none; color: #cc4b37; cursor: pointer; font-size: 1.25rem;" title="Remove candidate">&times;</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" id="add-candidate" class="button secondary small" style="border-radius: 8px;">
                        + Add Another Candidate
                    </button>

                    <hr style="margin: 1.5rem 0;">

                    <div class="grid-x grid-margin-x">
                        <div class="small-12 medium-6 cell">
                            <a href="/admin/voting/" class="button secondary expanded" style="border-radius: 8px;">Cancel</a>
                        </div>
                        <div class="small-12 medium-6 cell">
                            <button type="submit" class="button primary expanded" style="border-radius: 8px;">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="small-12 medium-4 large-5 cell">
        <div class="callout warning" style="border-radius: 8px;">
            <h5>Draft Status</h5>
            <p>This election is still in draft mode. No votes have been cast.</p>
            <p style="margin-bottom: 0;">Once you publish the election, it cannot be edited while voting is open.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('candidates-container');
    const addButton = document.getElementById('add-candidate');
    let candidateCount = container.querySelectorAll('.candidate-row').length;

    addButton.addEventListener('click', function() {
        candidateCount++;
        const newRow = document.createElement('div');
        newRow.className = 'candidate-row';
        newRow.style.cssText = 'background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;';
        newRow.innerHTML = `
            <div class="grid-x grid-margin-x">
                <div class="small-11 cell">
                    <label>Candidate ${candidateCount} Name *
                        <input type="text" name="candidates[]" placeholder="Full name">
                    </label>
                    <label>Brief Description (optional)
                        <input type="text" name="candidate_descriptions[]" placeholder="e.g. Current board member, Member since 2020">
                    </label>
                </div>
                <div class="small-1 cell" style="display: flex; align-items: center; justify-content: center;">
                    <button type="button" class="remove-candidate" style="background: none; border: none; color: #cc4b37; cursor: pointer; font-size: 1.25rem;" title="Remove candidate">&times;</button>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        renumberCandidates();
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-candidate')) {
            e.target.closest('.candidate-row').remove();
            renumberCandidates();
        }
    });

    function renumberCandidates() {
        const rows = container.querySelectorAll('.candidate-row');
        rows.forEach((row, index) => {
            const label = row.querySelector('label');
            if (label) {
                label.childNodes[0].textContent = `Candidate ${index + 1} Name *`;
            }
        });
        candidateCount = rows.length;
    }
});
</script>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
