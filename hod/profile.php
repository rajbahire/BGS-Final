<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user = currentUser();
$uid  = $user['id'];
$row  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $row->execute([$user['id']]); $row=$row->fetch();
if (!$row) { setFlash('error','User record not found.'); header('Location: dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name) {
            $pdo->prepare("UPDATE users SET name=?,phone=? WHERE id=?")->execute([$name,$phone,$user['id']]);
            $_SESSION['user_name'] = $name;
            setFlash('success','Profile updated.');
        } else { setFlash('error','Name is required.'); }
    }
    if ($action === 'password') {
        $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['confirm_password']??'';
        if (!password_verify($cur,$row['password']))  { setFlash('error','Current password incorrect.'); }
        elseif (strlen($new)<6)                       { setFlash('error','Min. 6 characters.'); }
        elseif ($new!==$conf)                         { setFlash('error','Passwords do not match.'); }
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]); setFlash('success','Password changed.'); }
    }
    if ($action === 'upload_photo') {
        $uploadDir = '../assets/uploads/profiles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error']===0) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext,['jpg','jpeg','png'])) {
                $fname = $uid.'_photo_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir.$fname)) {
                    $pdo->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$fname,$uid]);
                    $_SESSION['profile_photo'] = $fname;
                    setFlash('success','Profile photo updated.');
                } else { setFlash('error','Failed to save photo. Check folder permissions.'); }
            } else { setFlash('error','Only JPG, JPEG, PNG files are allowed.'); }
        } else { setFlash('error','Please select a valid photo to upload.'); }
    }
    if ($action === 'remove_photo') {
        if (!empty($row['profile_photo'])) {
            $photoPath = '../assets/uploads/profiles/' . $row['profile_photo'];
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
            $pdo->prepare("UPDATE users SET profile_photo=NULL WHERE id=?")->execute([$uid]);
            $_SESSION['profile_photo'] = '';
            setFlash('success','Profile photo removed.');
        }
    }
    header('Location: profile.php'); exit;
}

// Refresh the first-login gate on each profile page load (see auth.php).
$_SESSION['profile_completed'] = isProfileComplete($row) ? 1 : 0;

$deptName = $row['department_id'] ? deptName($pdo,(int)$row['department_id']) : '—';

renderHead('My Profile');
?>
<div class="app-layout">
<?php renderSidebar('profile','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('My Profile', [
    ['label' => 'Home',       'href' => 'dashboard.php'],
    ['label' => 'My Profile'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>My Profile</h1><p>Manage your HOD account</p></div>

    <div style="max-width:680px;margin:0 auto;display:flex;flex-direction:column;gap:1.5rem">

        <!-- Profile Hero (desktop: left photo, right info / mobile: stacked) -->
        <div class="card">
            <div class="card-body profile-hero-card" style="display:flex;align-items:center;justify-content:center">
                <form method="POST" enctype="multipart/form-data" id="photo-form-hero">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="file" name="photo" id="photo-input-hero" accept=".jpg,.jpeg,.png" style="display:none"
                           onchange="this.form.submit()" aria-label="Upload profile photo">
                </form>
                <form method="POST" id="remove-photo-form-hero" style="display:none">
                    <input type="hidden" name="action" value="remove_photo">
                </form>

                <!-- Left: Photo Section -->
                <div class="profile-hero-photo" style="margin:0">
                    <div style="position:relative;width:96px;height:96px;margin:0 auto;cursor:pointer;flex-shrink:0"
                         onclick="<?= $row['profile_photo'] ? 'openAvatarModal()' : "document.getElementById('photo-input-hero').click()" ?>"
                         title="<?= $row['profile_photo'] ? 'Click to manage photo' : 'Click to upload photo' ?>">
                        <?php if($row['profile_photo']): ?>
                        <img src="../assets/uploads/profiles/<?= e($row['profile_photo']) ?>" alt="Photo"
                             style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--border);display:block">
                        <?php else: ?>
                        <div style="width:96px;height:96px;border-radius:50%;background:var(--primary-lt);border:3px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:var(--primary)">
                            <?= e(getInitials($row['name'])) ?>
                        </div>
                        <?php endif; ?>

                        <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);border:2px solid var(--card-bg);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 5px rgba(0,0,0,0.15)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </div>
                    </div>
                    <div class="text-muted" style="font-size:0.72rem;margin-top:8px;text-align:center">
                        Click to <?= $row['profile_photo'] ? 'change' : 'upload' ?>
                        <?php if($row['profile_photo']): ?>
                         · <a href="javascript:void(0)" onclick="if(confirm('Are you sure you want to remove your profile photo?')) document.getElementById('remove-photo-form-hero').submit();" style="color:#ef4444;text-decoration:none;font-weight:500">Remove</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Info / Data Section -->
                <div class="profile-hero-info" style="margin:0">
                    <div style="font-size:1.3rem;font-weight:700;line-height:1.25"><?= e($row['name']) ?></div>
                    <div class="text-muted text-sm"><?= e($row['email']) ?></div>
                    <div class="text-sm" style="color:var(--text);font-weight:500"><?= e($deptName) ?></div>
                    <span class="badge badge-adjunct" style="margin-top:4px;display:inline-block">Head Of Department</span>
                </div>
            </div>
        </div>

        <?php if($row['profile_photo']): ?>
        <!-- Modern Photo Action Modal -->
        <div id="avatarActionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(3px)" onclick="if(event.target===this)closeAvatarModal()">
            <div style="background:var(--card-bg,#fff);border:1px solid var(--border);border-radius:14px;max-width:320px;width:100%;padding:1.5rem;text-align:center;box-shadow:0 12px 32px rgba(0,0,0,0.2)">
                <div style="font-weight:700;font-size:1.1rem;margin-bottom:0.4rem;color:var(--text)">Profile Photo</div>
                <div class="text-muted text-sm" style="margin-bottom:1.25rem">Choose an action for your profile picture</div>
                <div style="display:flex;flex-direction:column;gap:0.6rem">
                    <button type="button" class="btn btn-primary" onclick="closeAvatarModal();document.getElementById('photo-input-hero').click()" style="justify-content:center">
                        <?= svgIcon('camera') ?> Upload New Photo
                    </button>
                    <button type="button" class="btn btn-outline" style="color:#ef4444;border-color:#ef4444;justify-content:center" onclick="closeAvatarModal();if(confirm('Are you sure you want to remove your profile photo?'))document.getElementById('remove-photo-form-hero').submit()">
                        <?= svgIcon('delete') ?> Remove Photo
                    </button>
                    <button type="button" class="btn" style="background:none;border:none;color:var(--muted);justify-content:center;margin-top:0.25rem" onclick="closeAvatarModal()">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
        <script>
        function openAvatarModal() { var m = document.getElementById('avatarActionModal'); if (m) m.style.display = 'flex'; }
        function closeAvatarModal() { var m = document.getElementById('avatarActionModal'); if (m) m.style.display = 'none'; }
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeAvatarModal(); });
        </script>
        <?php endif; ?>

        <!-- Personal Information -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('profile') ?> Personal Information</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="profile">
                    <div class="form-group">
                        <label for="hod_name">Full Name <span style="color:red">*</span></label>
                        <input type="text" id="hod_name" name="name" class="form-control" autocomplete="name" required value="<?= e($row['name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="hod_email">Email</label>
                        <input type="email" id="hod_email" name="email" class="form-control" autocomplete="email" value="<?= e($row['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="hod_dept">Department</label>
                        <input type="text" id="hod_dept" name="department" class="form-control" autocomplete="off" value="<?= e($deptName) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="hod_phone">Phone <span style="color:red">*</span></label>
                        <input type="text" id="hod_phone" name="phone" class="form-control" autocomplete="tel" value="<?= e($row['phone']??'') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('key') ?> Change Password</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="password">
                    <div class="form-group">
                        <label for="current_password">Current Password <span style="color:red">*</span></label>
                        <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password <span style="color:red">*</span></label>
                        <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password" required placeholder="Min. 6 characters">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span style="color:red">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" required placeholder="Repeat new password">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('reset') ?> Change Password</button>
                </form>
            </div>
        </div>

    </div>
</div>
</div>
</div>
<?php renderFooter(); ?>
