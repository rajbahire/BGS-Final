<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireHOD();
$user   = currentUser();
$deptId = $user['dept_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_teacher') {
        $name   = trim($_POST['name']   ?? '');
        $email  = trim($_POST['email']  ?? '');
        $phone  = trim($_POST['phone']  ?? '');
        $type   = $_POST['teacher_type']  ?? 'regular';
        $mode   = $_POST['teacher_mode']  ?? 'theory';
        $subj   = (int)$_POST['subject_id'];
        $subj2  = (int)($_POST['subject_id_2'] ?? 0);
        $rateT  = (float)$_POST['rate_theory'];
        $rateP  = (float)$_POST['rate_practical'];
        $rateO  = (float)$_POST['rate_other'];
        $appNo  = trim($_POST['appointment_order_no'] ?? '');
        $pass   = $_POST['password'] ?? 'teacher@1234';

        if ($name && $email) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
            if ($dup->fetch()) { setFlash('error','Email already exists.'); }
            else {
                $pdo->prepare("INSERT INTO users (name,email,password,role,department_id,teacher_type,teacher_mode,subject_id,subject_id_2,rate_theory,rate_practical,rate_other,appointment_order_no,phone) VALUES (?,?,?,'teacher',?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$deptId,$type,$mode,$subj?:null,$subj2?:null,$rateT,$rateP,$rateO,$appNo,$phone]);
                logActivity($pdo,$user['id'],'add_teacher',"Added teacher: $name");
                setFlash('success',"Teacher \"$name\" added. Password: $pass");
            }
        } else { setFlash('error','Name and email are required.'); }
    }

    if ($action === 'add_student') {
        $name       = trim($_POST['name']              ?? '');
        $email      = trim($_POST['email']             ?? '');
        $phone      = trim($_POST['phone']             ?? '');
        $enrollment = trim($_POST['enrollment_number'] ?? '');
        $classId    = (int)$_POST['class_id'];
        $rate       = (float)$_POST['rate_per_hour'];
        $pass       = $_POST['password'] ?? 'student@1234';
        if ($name && $email) {
            $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
            if ($dup->fetch()) { setFlash('error','Email already exists.'); }
            else {
                $pdo->prepare("INSERT INTO users (name,email,password,role,department_id,class_id,rate_per_hour,phone,enrollment_number) VALUES (?,?,?,'student',?,?,?,?,?)")
                    ->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$deptId,$classId?:null,$rate,$phone,$enrollment]);
                logActivity($pdo,$user['id'],'add_student',"Added student: $name");
                setFlash('success',"Student \"$name\" added. Password: $pass");
            }
        } else { setFlash('error','Name and email required.'); }
    }

    if ($action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $tab     = $_POST['tab'] ?? 'teachers';
        $name    = trim($_POST['name']  ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $active  = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $newPass = trim($_POST['password'] ?? '');

        if (!$id || !$name || !$email) {
            setFlash('error', 'Name and email are required.');
            header('Location: manage-users.php?tab=' . $tab); exit;
        }

        // Verify target user belongs to this HOD's department
        $check = $pdo->prepare("SELECT id, role, name FROM users WHERE id=? AND department_id=?");
        $check->execute([$id, $deptId]);
        $targetUser = $check->fetch();
        if (!$targetUser) {
            setFlash('error', 'User not found or access denied.');
            header('Location: manage-users.php?tab=' . $tab); exit;
        }

        // Email uniqueness check (exclude current user)
        $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $dup->execute([$email, $id]);
        if ($dup->fetch()) {
            setFlash('error', 'Email already exists for another user.');
            header('Location: manage-users.php?tab=' . $tab); exit;
        }

        if ($targetUser['role'] === 'student') {
            $enrollment = trim($_POST['enrollment_number'] ?? '');
            $classId    = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
            $rateH      = (float)($_POST['rate_per_hour'] ?? 0);

            if (!$classId) {
                setFlash('error', 'Please select a valid class for the student.');
                header('Location: manage-users.php?tab=students'); exit;
            }

            // Verify class belongs to this department
            $chkClass = $pdo->prepare("SELECT id FROM classes WHERE id=? AND department_id=?");
            $chkClass->execute([$classId, $deptId]);
            if (!$chkClass->fetch()) {
                setFlash('error', 'Selected class does not exist or does not belong to your department.');
                header('Location: manage-users.php?tab=students'); exit;
            }

            if ($newPass !== '') {
                $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,enrollment_number=?,class_id=?,rate_per_hour=?,is_active=?,password=? WHERE id=? AND department_id=?")
                    ->execute([$name, $email, $phone, $enrollment ?: null, $classId, $rateH, $active, password_hash($newPass, PASSWORD_DEFAULT), $id, $deptId]);
            } else {
                $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,enrollment_number=?,class_id=?,rate_per_hour=?,is_active=? WHERE id=? AND department_id=?")
                    ->execute([$name, $email, $phone, $enrollment ?: null, $classId, $rateH, $active, $id, $deptId]);
            }

            logActivity($pdo, $user['id'], 'edit_student', "Updated student: $name");
            setFlash('success', "Student \"$name\" updated successfully.");
            header('Location: manage-users.php?tab=students'); exit;
        } else {
            // Teacher
            $type   = $_POST['teacher_type'] ?? 'regular';
            $mode   = $_POST['teacher_mode'] ?? 'theory';
            $subj   = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
            $subj2  = ($mode === 'theory & practical' && !empty($_POST['subject_id_2'])) ? (int)$_POST['subject_id_2'] : null;
            $rateT  = (float)($_POST['rate_theory'] ?? 0);
            $rateP  = (float)($_POST['rate_practical'] ?? 0);
            $rateO  = (float)($_POST['rate_other'] ?? 0);
            $appNo  = trim($_POST['appointment_order_no'] ?? '');

            if ($newPass !== '') {
                $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,teacher_type=?,teacher_mode=?,subject_id=?,subject_id_2=?,rate_theory=?,rate_practical=?,rate_other=?,appointment_order_no=?,is_active=?,password=? WHERE id=? AND department_id=?")
                    ->execute([$name, $email, $phone, $type ?: null, $mode ?: null, $subj, $subj2, $rateT, $rateP, $rateO, $appNo, $active, password_hash($newPass, PASSWORD_DEFAULT), $id, $deptId]);
            } else {
                $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,teacher_type=?,teacher_mode=?,subject_id=?,subject_id_2=?,rate_theory=?,rate_practical=?,rate_other=?,appointment_order_no=?,is_active=? WHERE id=? AND department_id=?")
                    ->execute([$name, $email, $phone, $type ?: null, $mode ?: null, $subj, $subj2, $rateT, $rateP, $rateO, $appNo, $active, $id, $deptId]);
            }

            logActivity($pdo, $user['id'], 'edit_teacher', "Updated teacher: $name");
            setFlash('success', "Teacher \"$name\" updated successfully.");
            header('Location: manage-users.php?tab=teachers'); exit;
        }
    }

    if ($action === 'reset_password') {
        $id   = (int)$_POST['id'];
        $role = $_POST['urole'] ?? 'teacher';
        $pass = $role === 'student' ? 'student@1234' : 'teacher@1234';
        $pdo->prepare("UPDATE users SET password=? WHERE id=? AND department_id=?")->execute([password_hash($pass,PASSWORD_DEFAULT),$id,$deptId]);
        setFlash('success',"Password reset to: $pass");
    }

    if ($action === 'deactivate') {
        $id     = (int)$_POST['id'];
        $urole  = $_POST['urole'] ?? 'teacher';
        if ($id) {
            $check = $pdo->prepare("SELECT id,name FROM users WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            $row = $check->fetch();
            if ($row) {
                $pdo->prepare("UPDATE users SET is_active=0 WHERE id=? AND department_id=?")->execute([$id,$deptId]);
                logActivity($pdo,$user['id'],'deactivate_user',"HOD deactivated {$urole}: {$row['name']}");
                setFlash('warning',"User \"{$row['name']}\" has been deactivated.");
            } else {
                setFlash('error','User not found or access denied.');
            }
        }
    }

    if ($action === 'activate') {
        $id    = (int)$_POST['id'];
        $urole = $_POST['urole'] ?? 'teacher';
        if ($id) {
            $check = $pdo->prepare("SELECT id,name FROM users WHERE id=? AND department_id=?");
            $check->execute([$id, $deptId]);
            $row = $check->fetch();
            if ($row) {
                $pdo->prepare("UPDATE users SET is_active=1 WHERE id=? AND department_id=?")->execute([$id,$deptId]);
                logActivity($pdo,$user['id'],'activate_user',"HOD activated {$urole}: {$row['name']}");
                setFlash('success',"User \"{$row['name']}\" has been reactivated.");
            } else {
                setFlash('error','User not found or access denied.');
            }
        }
    }

    header('Location: manage-users.php?tab='.($_POST['tab']??'teachers')); exit;
}

$activeTab = $_GET['tab'] ?? 'teachers';

$teachers = $pdo->prepare(
    "SELECT u.*,
            s1.subject_name, s1.subject_code, s1.mode AS subject_mode,
            s2.subject_name AS subject_name_2, s2.subject_code AS subject_code_2
     FROM users u
     LEFT JOIN subjects s1 ON s1.id=u.subject_id
     LEFT JOIN subjects s2 ON s2.id=u.subject_id_2
     WHERE u.role='teacher' AND u.department_id=? ORDER BY u.name"
); $teachers->execute([$deptId]); $teachers=$teachers->fetchAll();

$students = $pdo->prepare(
    "SELECT u.*, c.label AS class_label FROM users u
     LEFT JOIN classes c ON c.id=u.class_id
     WHERE u.role='student' AND u.department_id=? ORDER BY u.name"
); $students->execute([$deptId]); $students=$students->fetchAll();

// Pagination config for teachers
$tPerPage = 15;
$tPage    = currentPage();
$tOffset  = paginationOffset($tPage, $tPerPage);
$tTotal   = count($teachers);
$tPages   = totalPages($tTotal, $tPerPage);
$allTeachers = $teachers;
$teachers = array_slice($teachers, $tOffset, $tPerPage);

// Pagination config for students
$sPerPage = 15;
$sPage    = currentPage();
$sOffset  = paginationOffset($sPage, $sPerPage);
$sTotal   = count($students);
$sPages   = totalPages($sTotal, $sPerPage);
$students = array_slice($students, $sOffset, $sPerPage);

// All active subjects in this dept (for the teacher subject selectors).
// Availability per teacher is enforced client-side via $subjectOwners
// (a subject is selectable if it is owned by no one, or only by the teacher being edited).
$allDeptSubjects = $pdo->prepare(
    "SELECT s.*, c.label AS class_label FROM subjects s
     JOIN classes c ON c.id=s.class_id
     WHERE c.department_id=? AND s.is_active=1
     ORDER BY c.year,c.semester,s.subject_name"
);
$allDeptSubjects->execute([$deptId]);
$allDeptSubjects = $allDeptSubjects->fetchAll();

// subjectId => list of teacher ids who already hold that subject (either slot)
$subjectOwners = [];
foreach ($allTeachers as $t) {
    if (!empty($t['subject_id']))   $subjectOwners[(int)$t['subject_id']][]   = (int)$t['id'];
    if (!empty($t['subject_id_2'])) $subjectOwners[(int)$t['subject_id_2']][] = (int)$t['id'];
}
foreach ($subjectOwners as $sid => $owners) $subjectOwners[$sid] = array_values(array_unique($owners));

$allClasses = $pdo->prepare("SELECT * FROM classes WHERE department_id=? ORDER BY year,semester");
$allClasses->execute([$deptId]); $allClasses=$allClasses->fetchAll();
$classes = array_values(array_filter($allClasses, fn($c) => (int)$c['is_active'] === 1));

renderHead('Manage Users');
?>
<div class="app-layout">
<?php renderSidebar('manage-users','hod',$user); ?>
<div class="main-content">
<?php renderTopbar('Manage Users', [
    ['label' => 'Home',         'href' => 'dashboard.php'],
    ['label' => 'Manage Users'],
]); ?>
<div class="page-body">
    <?= getFlash() ?>
    <div class="page-header page-header-btn">
        <div>
            <h1>Manage Users</h1>
            <p>Add and manage teachers and Earn & Learn students</p>
        </div>
        <div>
            <?php if ($activeTab === 'teachers'): ?>
            <button class="btn btn-primary" onclick="openModal('modal-teacher-add')"><?= svgIcon('add') ?> Add Teacher</button>
            <?php else: ?>
            <button class="btn btn-primary" onclick="openModal('modal-student-add')"><?= svgIcon('add') ?> Add Student</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="d-flex gap-8 mb-2" style="border-bottom:1px solid var(--border);padding-bottom:0">
        <a href="?tab=teachers" class="btn <?= $activeTab==='teachers'?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('teacher') ?> Teachers (<?= count($teachers) ?>)</a>
        <a href="?tab=students" class="btn <?= $activeTab==='students'?'btn-primary':'btn-outline' ?> btn-sm"><?= svgIcon('student') ?> E&L Students (<?= count($students) ?>)</a>
    </div>

    <?php if($activeTab==='teachers'): ?>
    <div>
        <div class="card">
            <div class="card-header">
                <h3>Teachers (<?= count($teachers) ?>)</h3>
            </div>
            <?php if($teachers): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Type</th><th>Subject Code</th><th>Subject</th><th>Rate T/P</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($teachers as $i => $t): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td>
                            <div class="fw-500"><?= e($t['name']) ?></div>
                            <div class="text-xs text-muted"><?= e($t['email']) ?></div>
                        </td>
                        <td><?= teacherTypeBadge($t['teacher_type']??'regular') ?></td>
                        <td class="text-sm">
                            <?php if($t['subject_name']): ?>
                                <?= e($t['subject_code']) ?>
                                    <?php if($t['subject_name_2']): ?><br><?= e($t['subject_code_2']) ?><?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?php if($t['subject_name']): ?>
                                <?= e($t['subject_name']) ?>
                            <?php if($t['subject_name_2']): ?><br><?= e($t['subject_name_2']) ?><?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-sm"><?= formatINR($t['rate_theory']) ?> / <?= formatINR($t['rate_practical']) ?></td>
                        <td><?= $t['is_active']?'<span class="badge badge-approved">Active</span>':'<span class="badge badge-rejected">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8" style="flex-wrap:wrap">
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="openModal('modal-teacher-<?= $t['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                <form method="POST" style="margin:0"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="urole" value="teacher"><input type="hidden" name="tab" value="teachers"><button class="btn btn-outline btn-sm" onclick="return confirmAction('Reset password to teacher@1234?')"><?= svgIcon('reset') ?></button></form>
                                <?php if ($t['is_active']): ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Deactivate this teacher?')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="urole" value="teacher"><input type="hidden" name="tab" value="teachers"><button class="btn btn-delete btn-sm"><?= svgIcon('delete') ?> Deactivate</button></form>
                                <?php else: ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Activate this teacher?')"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="urole" value="teacher"><input type="hidden" name="tab" value="teachers"><button class="btn btn-activate btn-sm"><?= svgIcon('check') ?> Activate</button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon"><?= svgIcon('teacher') ?></div><h3>No teachers added yet</h3></div><?php endif; ?>
        <?php if ($tPages > 1): ?>
            <?= renderPagination($tPage, $tPages, $tTotal, $tPerPage, $tOffset, 'teachers', 'Teachers pagination') ?>
        <?php endif; ?>
        </div>
    </div>

    <?php else: // students tab ?>
    <div>
        <div class="card">
            <div class="card-header">
                <h3>Earn & Learn Students (<?= count($students) ?>)</h3>
            </div>
            <?php if($students): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Enrollment No.</th><th>Email</th><th>Class</th><th>Rate/Hr</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($students as $i => $s): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-500"><?= e($s['name']) ?></td>
                        <td class="text-sm"><?= e($s['enrollment_number'] ?? '—') ?></td>
                        <td class="text-sm"><?= e($s['email']) ?></td>
                        <td class="text-sm"><?= e($s['class_label']??'—') ?></td>
                        <td><?= formatINR($s['rate_per_hour']) ?>/hr</td>
                        <td><?= $s['is_active']?'<span class="badge badge-approved">Active</span>':'<span class="badge badge-rejected">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-8" style="flex-wrap:wrap">
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="openModal('modal-student-<?= $s['id'] ?>-edit')"><?= svgIcon('edit') ?> Edit</button>
                                <form method="POST" style="margin:0"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="urole" value="student"><input type="hidden" name="tab" value="students"><button class="btn btn-outline btn-sm" onclick="return confirmAction('Reset password to student@1234?')"><?= svgIcon('reset') ?></button></form>
                                <?php if ($s['is_active']): ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Deactivate this student?')"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="urole" value="student"><input type="hidden" name="tab" value="students"><button class="btn btn-delete btn-sm"><?= svgIcon('delete') ?> Deactivate</button></form>
                                <?php else: ?>
                                <form method="POST" style="margin:0" onsubmit="return confirmAction('Activate this student?')"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="urole" value="student"><input type="hidden" name="tab" value="students"><button class="btn btn-activate btn-sm"><?= svgIcon('check') ?> Activate</button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><div class="empty-state"><div class="icon"><?= svgIcon('student') ?></div><h3>No students added yet</h3></div><?php endif; ?>
        <?php if ($sPages > 1): ?>
            <?= renderPagination($sPage, $sPages, $sTotal, $sPerPage, $sOffset, 'students', 'Students pagination') ?>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>

<?php
// ── Modals (rendered outside the layout so display:none ancestors don't trap them) ──
?>
<!-- Add Teacher Modal -->
<div class="modal-backdrop" id="modal-teacher-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add Teacher</h3></span>
            <button class="modal-close" onclick="closeModal('modal-teacher-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST" data-owner="0" data-subj1="0" data-subj2="0">
            <input type="hidden" name="action" value="add_teacher">
            <input type="hidden" name="tab" value="teachers">
            <div class="modal-body">
                <div class="form-group"><label for="add_t_name">Full Name <span style="color:red">*</span></label><input type="text" id="add_t_name" name="name" class="form-control" required autocomplete="name"></div>
                <div class="form-group"><label for="add_t_email">Email <span style="color:red">*</span></label><input type="email" id="add_t_email" name="email" class="form-control" required placeholder="teacher@gcea.edu" autocomplete="email"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="add_t_password">Password</label><input type="text" id="add_t_password" name="password" class="form-control" value="teacher@1234" autocomplete="new-password"></div>
                    <div class="form-group"><label for="add_t_phone">Phone</label><input type="text" id="add_t_phone" name="phone" class="form-control" autocomplete="tel"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div class="form-group"><label for="add_t_type">Teacher Type <span style="color:red">*</span></label>
                    <select id="add_t_type" name="teacher_type" class="form-control" required>
                        <option value="">— Select Type —</option>
                        <!-- Regular option — temporarily disabled
                        <option value="regular">Regular</option>
                        -->
                        <option value="expert">Expert / Sectional Expert</option>
                        <option value="adjunct">Adjunct</option>
                    </select>
                </div>
                <div class="form-group"><label for="add_t_mode">Mode <span style="color:red">*</span></label>
                    <select id="add_t_mode" name="teacher_mode" class="form-control sel-mode" required onchange="updateSubjectFields(this)">
                        <option value="">— Select Mode —</option>
                        <option value="theory">Theory</option>
                        <option value="practical">Practical</option>
                        <option value="theory & practical">Theory & Practical</option>
                    </select>
                </div>
                </div>
                <div class="subject-fields"></div>
                <div class="form-group"><label for="add_t_order">Appointment Order No.</label><input type="text" id="add_t_order" name="appointment_order_no" class="form-control"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                    <div class="form-group"><label for="add_t_rate_theory">Rate Theory (₹) <span style="color:red">*</span></label><input type="number" id="add_t_rate_theory" name="rate_theory" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="form-group"><label for="add_t_rate_practical">Rate Practical (₹) <span style="color:red">*</span></label><input type="number" id="add_t_rate_practical" name="rate_practical" class="form-control" step="0.01" min="0" value="0"></div>
                    <div class="form-group"><label for="add_t_rate_other">Rate Other (₹) <span style="color:red">*</span></label><input type="number" id="add_t_rate_other" name="rate_other" class="form-control" step="0.01" min="0" value="0"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-teacher-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Teacher</button>
            </div>
        </form>
    </div>
</div>

<?php foreach($teachers as $t): ?>
<!-- Edit Teacher Modal: teacher-<?= $t['id'] ?> -->
<div class="modal-backdrop" id="modal-teacher-<?= $t['id'] ?>-edit">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('edit') ?><h3>Edit Teacher</h3></span>
            <button class="modal-close" onclick="closeModal('modal-teacher-<?= $t['id'] ?>-edit')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST" data-owner="<?= (int)$t['id'] ?>" data-subj1="<?= (int)($t['subject_id']??0) ?>" data-subj2="<?= (int)($t['subject_id_2']??0) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="tab" value="teachers">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <div class="modal-body">
                <div class="form-group"><label for="edit_t_name_<?= $t['id'] ?>">Full Name <span style="color:red">*</span></label><input type="text" id="edit_t_name_<?= $t['id'] ?>" name="name" class="form-control" required value="<?= e($t['name']) ?>" autocomplete="name"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="edit_t_email_<?= $t['id'] ?>">Email <span style="color:red">*</span></label><input type="email" id="edit_t_email_<?= $t['id'] ?>" name="email" class="form-control" required placeholder="teacher@gcea.edu" value="<?= e($t['email']) ?>" autocomplete="email"></div>
                    <div class="form-group"><label for="edit_t_phone_<?= $t['id'] ?>">Phone</label><input type="text" id="edit_t_phone_<?= $t['id'] ?>" name="phone" class="form-control" value="<?= e($t['phone']??'') ?>" autocomplete="tel"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <div class="form-group"><label for="edit_t_type_<?= $t['id'] ?>">Teacher Type <span style="color:red">*</span></label>
                    <select id="edit_t_type_<?= $t['id'] ?>" name="teacher_type" class="form-control" required>
                        <option value="">— Select Type —</option>
                        <?php foreach(['regular'=>'Regular','expert'=>'Expert / Sectional Expert','adjunct'=>'Adjunct'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= ($t['teacher_type']??'')===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label for="edit_t_mode_<?= $t['id'] ?>">Mode <span style="color:red">*</span></label>
                    <select id="edit_t_mode_<?= $t['id'] ?>" name="teacher_mode" class="form-control sel-mode" required onchange="updateSubjectFields(this)">
                        <option value="">— Select Mode —</option>
                        <option value="theory"    <?= ($t['teacher_mode']??'')==='theory'    ?'selected':'' ?>>Theory</option>
                        <option value="practical" <?= ($t['teacher_mode']??'')==='practical' ?'selected':'' ?>>Practical</option>
                        <option value="theory & practical" <?= ($t['teacher_mode']??'')==='theory & practical' ?'selected':'' ?>>Theory & Practical</option>
                    </select>
                </div>
                </div>
                <div class="subject-fields"></div>
                <div class="form-group"><label for="edit_t_order_<?= $t['id'] ?>">Appointment Order No.</label><input type="text" id="edit_t_order_<?= $t['id'] ?>" name="appointment_order_no" class="form-control" value="<?= e($t['appointment_order_no']??'') ?>"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                    <div class="form-group"><label for="edit_t_rate_theory_<?= $t['id'] ?>">Rate Theory (₹) <span style="color:red">*</span></label><input type="number" id="edit_t_rate_theory_<?= $t['id'] ?>" name="rate_theory" class="form-control" step="0.01" min="0" value="<?= $t['rate_theory']??0 ?>"></div>
                    <div class="form-group"><label for="edit_t_rate_practical_<?= $t['id'] ?>">Rate Practical (₹) <span style="color:red">*</span></label><input type="number" id="edit_t_rate_practical_<?= $t['id'] ?>" name="rate_practical" class="form-control" step="0.01" min="0" value="<?= $t['rate_practical']??0 ?>"></div>
                    <div class="form-group"><label for="edit_t_rate_other_<?= $t['id'] ?>">Rate Other (₹) <span style="color:red">*</span></label><input type="number" id="edit_t_rate_other_<?= $t['id'] ?>" name="rate_other" class="form-control" step="0.01" min="0" value="<?= $t['rate_other']??0 ?>"></div>
                </div>
                <div class="form-group"><label for="edit_t_status_<?= $t['id'] ?>">Status</label><select id="edit_t_status_<?= $t['id'] ?>" name="is_active" class="form-control"><option value="1" <?= $t['is_active']?'selected':'' ?>>Active</option><option value="0" <?= !$t['is_active']?'selected':'' ?>>Inactive</option></select></div>
                <div class="form-group"><label for="edit_t_password_<?= $t['id'] ?>">New Password <span class="text-xs text-muted">(leave blank to keep unchanged)</span></label><input type="text" id="edit_t_password_<?= $t['id'] ?>" name="password" class="form-control" placeholder="Leave blank to keep unchanged" autocomplete="new-password"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-teacher-<?= $t['id'] ?>-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Update Teacher</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- Add Student Modal -->
<div class="modal-backdrop" id="modal-student-add">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('add') ?><h3>Add Student</h3></span>
            <button class="modal-close" onclick="closeModal('modal-student-add')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_student">
            <input type="hidden" name="tab" value="students">
            <div class="modal-body">
                <div class="form-group"><label for="add_s_name">Full Name <span style="color:red">*</span></label><input type="text" id="add_s_name" name="name" class="form-control" placeholder="Enter Full Name" required autocomplete="name"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="add_s_email">Email <span style="color:red">*</span></label><input type="email" id="add_s_email" name="email" class="form-control" required placeholder="student@gcea.edu" autocomplete="email"></div>
                    <div class="form-group"><label for="add_s_phone">Phone</label><input type="text" id="add_s_phone" name="phone" class="form-control" placeholder="Phone Number" autocomplete="tel"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="add_s_enrollment">Enrollment Number</label><input type="text" id="add_s_enrollment" name="enrollment_number" class="form-control" placeholder="e.g. 2023CSE001"></div>
                    <div class="form-group"><label for="add_s_class">Class <span style="color:red">*</span></label>
                        <select id="add_s_class" name="class_id" class="form-control" required>
                            <option value="">— Select Class —</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="add_s_password">Password</label><input type="text" id="add_s_password" name="password" class="form-control" value="student@1234" autocomplete="new-password"></div>
                    <div class="form-group"><label for="add_s_rate">Rate per Hour (₹) <span style="color:red">*</span></label><input type="number" id="add_s_rate" name="rate_per_hour" class="form-control" step="0.01" min="0" value="50" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-student-add')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Student</button>
            </div>
        </form>
    </div>
</div>

<?php foreach($students as $s): ?>
<!-- Edit Student Modal: student-<?= $s['id'] ?> -->
<div class="modal-backdrop" id="modal-student-<?= $s['id'] ?>-edit">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span style="display:flex;align-items:center;gap:8px"><?= svgIcon('edit') ?><h3>Edit Student</h3></span>
            <button class="modal-close" onclick="closeModal('modal-student-<?= $s['id'] ?>-edit')"><?= svgIcon('close') ?></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="tab" value="students">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <div class="modal-body">
                <div class="form-group"><label for="edit_s_name_<?= $s['id'] ?>">Full Name <span style="color:red">*</span></label><input type="text" id="edit_s_name_<?= $s['id'] ?>" name="name" class="form-control" required value="<?= e($s['name']) ?>" autocomplete="name"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="edit_s_email_<?= $s['id'] ?>">Email <span style="color:red">*</span></label><input type="email" id="edit_s_email_<?= $s['id'] ?>" name="email" class="form-control" required placeholder="student@gcea.edu" value="<?= e($s['email']) ?>" autocomplete="email"></div>
                    <div class="form-group"><label for="edit_s_phone_<?= $s['id'] ?>">Phone</label><input type="text" id="edit_s_phone_<?= $s['id'] ?>" name="phone" class="form-control" value="<?= e($s['phone']??'') ?>" autocomplete="tel"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="edit_s_enrollment_<?= $s['id'] ?>">Enrollment Number</label><input type="text" id="edit_s_enrollment_<?= $s['id'] ?>" name="enrollment_number" class="form-control" placeholder="e.g. 2023CSE001" value="<?= e($s['enrollment_number']??'') ?>"></div>
                    <div class="form-group"><label for="edit_s_class_<?= $s['id'] ?>">Class <span style="color:red">*</span></label>
                        <select id="edit_s_class_<?= $s['id'] ?>" name="class_id" class="form-control" required>
                            <option value="">— Select Class —</option>
                            <?php 
                            $classFound = false;
                            foreach($classes as $c): 
                                $isSelected = (($s['class_id']??0) == $c['id']);
                                if ($isSelected) $classFound = true;
                            ?>
                            <option value="<?= $c['id'] ?>" <?= $isSelected ? 'selected' : '' ?>><?= e($c['label']) ?></option>
                            <?php endforeach; ?>
                            <?php if (!$classFound && !empty($s['class_id']) && !empty($s['class_label'])): ?>
                            <option value="<?= $s['class_id'] ?>" selected><?= e($s['class_label']) ?> (Inactive)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <div class="form-group"><label for="edit_s_rate_<?= $s['id'] ?>">Rate per Hour (₹) <span style="color:red">*</span></label><input type="number" id="edit_s_rate_<?= $s['id'] ?>" name="rate_per_hour" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars((string)($s['rate_per_hour'] ?? 0)) ?>" required></div>
                    <div class="form-group"><label for="edit_s_status_<?= $s['id'] ?>">Status</label><select id="edit_s_status_<?= $s['id'] ?>" name="is_active" class="form-control"><option value="1" <?= $s['is_active']?'selected':'' ?>>Active</option><option value="0" <?= !$s['is_active']?'selected':'' ?>>Inactive</option></select></div>
                </div>
                <div class="form-group"><label for="edit_s_password_<?= $s['id'] ?>">New Password <span class="text-xs text-muted">(leave blank to keep unchanged)</span></label><input type="text" id="edit_s_password_<?= $s['id'] ?>" name="password" class="form-control" placeholder="Leave blank to keep unchanged" autocomplete="new-password"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-student-<?= $s['id'] ?>-edit')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= svgIcon('save') ?> Update Student</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php renderFooter(); ?>
<script>
// All active subjects in this dept, keyed by id, with mode info
const allSubjects    = <?= json_encode(array_values(array_map(fn($s) => [
    'id'    => (int)$s['id'],
    'label' => $s['subject_name'].' ('.$s['subject_code'].')',
    'mode'  => $s['mode'],        // 'theory' | 'practical' | 'theory & practical'
], $allDeptSubjects))) ?>;
// subjectId => list of teacher ids who already hold that subject (either slot)
const subjectOwners = <?= json_encode($subjectOwners ?: new stdClass) ?>;

// A subject is selectable by a teacher iff it is owned by no one,
// or owned only by that teacher (matches the original server-side exclusion).
function availableSubjects(ownerId) {
    return allSubjects.filter(s => {
        const owners = subjectOwners[s.id];
        return !owners || owners.length === 0 || owners.every(o => o === ownerId);
    });
}

function buildSelect(name, filterFn, selectedId, labelText, pool, fieldId) {
    const wrap = document.createElement('div');
    wrap.className = 'form-group';
    const lbl = document.createElement('label');
    if (fieldId) {
        lbl.htmlFor = fieldId;
    }
    lbl.innerHTML = labelText + ' <span style="color:red">*</span>';
    const sel = document.createElement('select');
    if (fieldId) {
        sel.id = fieldId;
    }
    sel.name = name;
    sel.className = 'form-control';
    sel.required = true;
    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = '— Select Subject —';
    sel.appendChild(blank);
    pool.filter(filterFn).forEach(s => {
        const o = document.createElement('option');
        o.value = s.id;
        o.textContent = s.label;
        if (s.id === selectedId) o.selected = true;
        sel.appendChild(o);
    });
    wrap.appendChild(lbl);
    wrap.appendChild(sel);
    return wrap;
}

// Build the subject dropdown(s) for whichever teacher modal the changed <select> belongs to.
function updateSubjectFields(modeSelect) {
    const form = modeSelect.closest('form');
    if (!form) return;
    const container = form.querySelector('.subject-fields');
    if (!container) return;
    const mode = modeSelect.value;
    container.innerHTML = '';
    if (!mode) return;

    const ownerId = parseInt(form.getAttribute('data-owner') || '0', 10);
    const subj1   = parseInt(form.getAttribute('data-subj1') || '0', 10);
    const subj2   = parseInt(form.getAttribute('data-subj2') || '0', 10);
    const pool    = availableSubjects(ownerId);

    const theoryFilter    = s => s.mode === 'theory' || s.mode === 'theory & practical';
    const practicalFilter = s => s.mode === 'practical' || s.mode === 'theory & practical';
    const fieldPrefix = 'subj_' + ownerId + '_';

    if (mode === 'theory') {
        container.appendChild(buildSelect('subject_id',   theoryFilter,    subj1, 'Theory Subject',    pool, fieldPrefix + 'theory'));
    } else if (mode === 'practical') {
        container.appendChild(buildSelect('subject_id',   practicalFilter, subj1, 'Practical Subject', pool, fieldPrefix + 'practical'));
    } else if (mode === 'theory & practical') {
        container.appendChild(buildSelect('subject_id',   theoryFilter,    subj1, 'Theory Subject',    pool, fieldPrefix + 'theory'));
        container.appendChild(buildSelect('subject_id_2', practicalFilter, subj2, 'Practical Subject', pool, fieldPrefix + 'practical'));
    }
}

// Initialise every teacher modal's subject fields on load (edit modals are pre-filled)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sel-mode').forEach(function (sel) {
        updateSubjectFields(sel);
    });
});
</script>