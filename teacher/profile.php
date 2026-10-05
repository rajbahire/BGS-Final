<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireTeacher();
$user = currentUser();
$uid  = $user['id'];

$row = $pdo->prepare("SELECT u.*,s.subject_name,s.subject_code,c.label AS class_label,d.name AS dept_name FROM users u LEFT JOIN subjects s ON s.id=u.subject_id LEFT JOIN classes c ON c.id=u.class_id LEFT JOIN departments d ON d.id=u.department_id WHERE u.id=?");
$row->execute([$uid]); $row=$row->fetch();
if (!$row) { setFlash('error','User record not found.'); header('Location: dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name   = trim($_POST['name']   ?? '');
        $phone  = trim($_POST['phone']  ?? '');
        $appNo  = trim($_POST['appointment_order_no'] ?? '');
        if ($name) {
            $pdo->prepare("UPDATE users SET name=?,phone=?,appointment_order_no=? WHERE id=?")->execute([$name,$phone,$appNo,$uid]);
            $_SESSION['user_name'] = $name;
            setFlash('success','Profile updated.');
        } else { setFlash('error','Name is required.'); }
    }

    if ($action === 'bank') {
        $bank = trim($_POST['bank_name']  ?? '');
        $acc  = trim($_POST['account_no'] ?? '');
        $ifsc = strtoupper(trim($_POST['ifsc'] ?? ''));
        $pan  = strtoupper(trim($_POST['pan']  ?? ''));
        $pdo->prepare("UPDATE users SET bank_name=?,account_no=?,ifsc=?,pan=? WHERE id=?")->execute([$bank,$acc,$ifsc,$pan,$uid]);
        setFlash('success','Bank details updated.');
    }

    if ($action === 'password') {
        $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['confirm_password']??'';
        if (!password_verify($cur,$row['password']))  { setFlash('error','Current password incorrect.'); }
        elseif (strlen($new)<6)                       { setFlash('error','Min. 6 characters.'); }
        elseif ($new!==$conf)                         { setFlash('error','Passwords do not match.'); }
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$uid]); setFlash('success','Password changed.'); }
    }

    if ($action === 'upload_kyc') {
        $uploadDir = '../assets/uploads/kyc/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fields = ['pan_image'=>'pan','aadhar_image'=>'aadhar','appointment_image'=>'appointment'];
        foreach ($fields as $dbCol => $fileKey) {
            if (!empty($_FILES[$fileKey]['tmp_name']) && $_FILES[$fileKey]['error']===0) {
                $ext  = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','pdf'];
                if (in_array($ext,$allowed)) {
                    $fname = $uid.'_'.$dbCol.'_'.time().'.'.$ext;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir.$fname)) {
                        $pdo->prepare("UPDATE users SET $dbCol=? WHERE id=?")->execute([$fname,$uid]);
                    }
                }
            }
        }
        setFlash('success','Documents uploaded.');
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

renderHead('My Profile');
?>
<div class="app-layout">
<?php renderSidebar('profile','teacher',$user); ?>
<div class="main-content">
<?php renderTopbar('My Profile', [
    ['label' => 'Home',   'href' => 'dashboard.php'],
    ['label' => 'My Profile'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header"><h1>My Profile</h1><p>Manage your account, bank details, and KYC documents</p></div>

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
                    <div class="text-sm" style="color:var(--text);font-weight:500"><?= e($row['dept_name']??'') ?></div>
                    <div style="display:flex;gap:8px;margin-top:4px">
                        <?= teacherTypeBadge($row['teacher_type']??'regular') ?>
                        <?= modeBadge($row['teacher_mode']??'theory') ?>
                    </div>
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
                        <label for="teacher_name">Full Name <span style="color:red">*</span></label>
                        <input type="text" id="teacher_name" name="name" class="form-control" autocomplete="name" required value="<?= e($row['name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="teacher_email">Email</label>
                        <input type="email" id="teacher_email" name="email" class="form-control" autocomplete="email" value="<?= e($row['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="teacher_phone">Phone <span style="color:red">*</span></label>
                        <input type="text" id="teacher_phone" name="phone" class="form-control" autocomplete="tel" required value="<?= e($row['phone']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label for="teacher_dept">Department</label>
                        <input type="text" id="teacher_dept" name="department" class="form-control" autocomplete="off" value="<?= e($row['dept_name']??'—') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="teacher_type">Teacher Type</label>
                        <input type="text" id="teacher_type" name="teacher_type" class="form-control" autocomplete="off" value="<?= teacherTypeLabel($row['teacher_type'] ?? '—') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="teacher_mode">Mode</label>
                        <input type="text" id="teacher_mode" name="teacher_mode" class="form-control" autocomplete="off" value="<?= ucfirst($row['teacher_mode']??'—') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="teacher_subject">Assigned Subject</label>
                        <input type="text" id="teacher_subject" name="subject" class="form-control" autocomplete="off" value="<?= e(($row['subject_name']??'—').($row['subject_code']?' ('.$row['subject_code'].')':'')) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="teacher_appointment_order_no">Appointment Order No. <span style="color:red">*</span></label>
                        <input type="text" id="teacher_appointment_order_no" name="appointment_order_no" class="form-control" autocomplete="off" value="<?= e($row['appointment_order_no']??'') ?>" placeholder="Enter appointment order number" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Bank Details -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('building') ?> Bank Details</h3></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="bank">
                    <div class="form-group">
                        <label for="bank_name">Bank Name <span style="color:red">*</span></label>
                        <input type="text" id="bank_name" name="bank_name" class="form-control" autocomplete="off" value="<?= e($row['bank_name']??'') ?>" placeholder="Name of Bank" required>
                    </div>
                    <div class="form-group">
                        <label for="account_no">Account Number <span style="color:red">*</span></label>
                        <input type="text" id="account_no" name="account_no" class="form-control" autocomplete="off" value="<?= e($row['account_no']??'') ?>" placeholder="Account Number" required>
                    </div>
                    <div class="form-group">
                        <label for="ifsc">IFSC Code <span style="color:red">*</span></label>
                        <input type="text" id="ifsc" name="ifsc" class="form-control" style="text-transform:uppercase" autocomplete="off" value="<?= e($row['ifsc']??'') ?>" placeholder="IFSC Code" required>
                    </div>
                    <div class="form-group">
                        <label for="pan">PAN Number <span style="color:red">*</span></label>
                        <input type="text" id="pan" name="pan" class="form-control" style="text-transform:uppercase" autocomplete="off" value="<?= e($row['pan']??'') ?>" placeholder="PAN Number" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Save Bank Details</button>
                </form>
            </div>
        </div>

        <!-- KYC Documents -->
        <div class="card">
            <div class="card-header"><h3><?= svgIcon('paperclip') ?> KYC Documents</h3></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_kyc">
                    <div class="form-group">
                        <label for="kyc_pan">PAN Card (JPG/PNG/PDF) <span style="color:red">*</span></label>
                        <?php if($row['pan_image']): ?>
                        <div class="text-sm text-muted" style="margin-bottom:5px"><?= svgIcon('check') ?> Uploaded: <?= e($row['pan_image']) ?></div>
                        <?php endif; ?>
                        <input type="file" id="kyc_pan" name="pan" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <div class="form-group">
                        <label for="kyc_aadhar">Aadhar Card (JPG/PNG/PDF) <span style="color:red">*</span></label>
                        <?php if($row['aadhar_image']): ?>
                        <div class="text-sm text-muted" style="margin-bottom:5px"><?= svgIcon('check') ?> Uploaded: <?= e($row['aadhar_image']) ?></div>
                        <?php endif; ?>
                        <input type="file" id="kyc_aadhar" name="aadhar" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <div class="form-group">
                        <label for="kyc_appointment">Appointment Order Letter (JPG/PNG/PDF) <span style="color:red">*</span></label>
                        <?php if($row['appointment_image']): ?>
                        <div class="text-sm text-muted" style="margin-bottom:5px"><?= svgIcon('check') ?> Uploaded: <?= e($row['appointment_image']) ?></div>
                        <?php endif; ?>
                        <input type="file" id="kyc_appointment" name="appointment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= svgIcon('upload') ?> Upload Documents</button>
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
                        <label for="confirm_password">Confirm Password <span style="color:red">*</span></label>
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
