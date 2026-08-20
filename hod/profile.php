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
    header('Location: profile.php'); exit;
}

// Refresh the first-login gate on each profile page load (see auth.php).
$_SESSION['profile_completed'] = isProfileComplete($row) ? 1 : 0;

$deptName = $row['department_id'] ? deptName($pdo,(int)$row['department_id']) : '—';

renderHead('HOD Profile');
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

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
        <!-- Left column -->
        <div style="display:flex;flex-direction:column;gap:1.5rem">
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('profile') ?> Personal Information</h3></div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:14px;padding:1rem;background:var(--bg);border-radius:var(--radius);margin-bottom:1.2rem">
                    <?php if($row['profile_photo']): ?>
                    <img src="../assets/uploads/profiles/<?= e($row['profile_photo']) ?>" alt="Photo" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
                    <?php else: ?>
                    <div style="width:52px;height:52px;border-radius:50%;background:var(--primary-lt);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:600;color:var(--primary)"><?= e(getInitials($row['name'])) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-600"><?= e($row['name']) ?></div>
                        <div class="text-muted text-sm"><?= e($row['email']) ?></div>
                        <div class="text-sm"><?= e($deptName) ?> <span class="badge badge-adjunct">HOD</span></div>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="profile">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" class="form-control" required value="<?= e($row['name']) ?>"></div>
                    <div class="form-group"><label>Email</label><input type="email" class="form-control" value="<?= e($row['email']) ?>" disabled></div>
                    <div class="form-group"><label>Department</label><input type="text" class="form-control" value="<?= e($deptName) ?>" disabled></div>
                    <div class="form-group"><label>Phone <span style="color:red">*</span></label><input type="text" name="phone" class="form-control" value="<?= e($row['phone']??'') ?>" required></div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3><?= svgIcon('upload-photo') ?> Update Profile Photo</h3></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_photo">
                    <div class="form-group"><label>Select Photo (JPG, JPEG, PNG)</label><input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png"></div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('upload-photo') ?> Upload Photo</button>
                </form>
            </div>
        </div>
        </div><!-- /left-col -->

        <!-- Right column -->
        <div style="display:flex;flex-direction:column;gap:1.5rem"><!-- right-col -->
            <!-- Password -->
            <div class="card">
                <div class="card-header"><h3><?= svgIcon('key') ?> Change Password</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="password">
                        <div class="form-group"><label>Current Password <span style="color:red">*</span></label><input type="password" name="current_password" class="form-control" required></div>
                        <div class="form-group"><label>New Password <span style="color:red">*</span></label><input type="password" name="new_password" class="form-control" required placeholder="Min. 6 characters"></div>
                        <div class="form-group"><label>Confirm New Password <span style="color:red">*</span></label><input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password"></div>
                        <button type="submit" class="btn btn-primary"><?= svgIcon('reset') ?> Change Password</button>
                    </form>
                </div>
            </div>
        </div><!-- /right-col -->
    </div><!-- /grid -->
</div>
</div>
</div>
<?php renderFooter(); ?>
