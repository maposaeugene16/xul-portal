<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth_views.php';
require_once __DIR__ . '/includes/student_records.php';

try {
    $needsSetup = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
} catch (Throwable $e) {
    http_response_code(500);
    ?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database setup · Mavambo SchoolOS</title><link rel="stylesheet" href="assets/style.css"><body class="auth-body"><main class="auth-card"><h1>Database needs attention</h1><p class="auth-subtitle">The portal cannot read its user table. Check the database connection and required migrations.</p><p class="auth-foot">Ask your system administrator to check the MariaDB log before continuing.</p></main></body></html><?php
    exit;
}
if ($needsSetup) {
    if (production_mode() && !getenv('SCHOOL_SETUP_KEY')) { http_response_code(503); echo 'Initial setup is locked. Configure SCHOOL_SETUP_KEY for a one-time administrator setup.'; exit; }
    render_auth_page('setup'); exit;
}
$user = current_user();
if (!$user) { render_auth_page('login', $_SESSION['auth_error'] ?? null); unset($_SESSION['auth_error']); exit; }

$active = $_GET['page'] ?? 'dashboard';
if (!empty($user['must_change_password']) && $active !== 'account') { header('Location: /?page=account'); exit; }
if ($user['role']==='Guardian' && $active==='dashboard') $active='communications';
$allowed = ['dashboard','students','academics','attendance','fees','expenses','reports','communications','library','welfare','admissions','inventory','exams','staffing','payroll','boarding','transport','activities','audit','settings','account'];
if (!in_array($active, $allowed, true)) $active = 'dashboard';
if (!role_can_access_page($user['role'], $active)) { http_response_code(403); require __DIR__ . '/includes/forbidden_view.php'; exit; }
$notice = $_SESSION['notice'] ?? null; unset($_SESSION['notice']);
$error = $_SESSION['error'] ?? null; unset($_SESSION['error']);
$students = [];
$unreadNotices = 0; $latestNotice = null; $nextSchoolEvent = null;
$dashboardCanSeeFinance = role_can_access_page($user['role'], 'fees');
try {
    $db = db();
    $schoolProfile = $db->query('SELECT school_name,province,district FROM school_profile WHERE id=1')->fetch() ?: [];
    $currentTerms = $db->query('SELECT name,academic_year,term_number,starts_on,ends_on FROM school_terms WHERE is_current=1 ORDER BY academic_year DESC,term_number DESC LIMIT 2')->fetchAll();
    $currentTerm = $currentTerms[0] ?? null;
    $currentTermConflict = count($currentTerms) > 1;
    $schoolDisplayName = $schoolProfile['school_name'] ?? 'School Portal';
    $schoolPlace = trim(($schoolProfile['district'] ?? '').(($schoolProfile['province'] ?? '') ? ' · '.$schoolProfile['province'] : '')) ?: 'Zimbabwe';
    $termDisplay = $currentTerm['name'] ?? 'Term not set';
    $termYear = $currentTerm['academic_year'] ?? date('Y');
    if (role_can_access_page($user['role'],'communications')) {
        $audiences=in_array($user['role'],['Administrator','Head'],true)?['Everyone','Staff','Guardians']:($user['role']==='Guardian'?['Everyone','Guardians']:['Everyone','Staff']);
        $audienceSql=implode(',',array_fill(0,count($audiences),'?'));
        $noticeStmt=$db->prepare('SELECT a.id,a.title,a.priority,a.published_at FROM announcements a WHERE a.status=\'Published\' AND a.published_at<=NOW() AND a.audience IN ('.$audienceSql.') ORDER BY a.published_at DESC,a.id DESC LIMIT 1');$noticeStmt->execute($audiences);$latestNotice=$noticeStmt->fetch()?:null;
        $unreadStmt=$db->prepare('SELECT COUNT(*) FROM announcements a LEFT JOIN announcement_reads r ON r.announcement_id=a.id AND r.user_id=? WHERE a.status=\'Published\' AND a.published_at<=NOW() AND a.audience IN ('.$audienceSql.') AND r.user_id IS NULL');$unreadStmt->execute(array_merge([(int)$user['id']],$audiences));$unreadNotices=(int)$unreadStmt->fetchColumn();
        $eventStmt=$db->prepare('SELECT id,title,starts_at,venue FROM school_events WHERE status=\'Published\' AND starts_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND audience IN ('.$audienceSql.') ORDER BY starts_at LIMIT 1');$eventStmt->execute($audiences);$nextSchoolEvent=$eventStmt->fetch()?:null;
    }
    $dashboardLearnerScope = '1=1';
    $dashboardScopeParams = [];
    if ($user['role'] === 'Teacher') {
        $dashboardLearnerScope = "EXISTS (SELECT 1 FROM class_groups cg LEFT JOIN class_subjects cs ON cs.class_id=cg.id WHERE cg.form_level=s.form_level AND COALESCE(cg.stream,'')=COALESCE(s.stream,'') AND cg.academic_year=YEAR(CURDATE()) AND (cg.class_teacher_id=:dashboard_teacher_class OR cs.teacher_id=:dashboard_teacher_subject))";
        $dashboardScopeParams = ['dashboard_teacher_class'=>(int)$user['id'],'dashboard_teacher_subject'=>(int)$user['id']];
    }
    if ($active === 'dashboard') {
        $recentStmt = $db->prepare("SELECT s.id,s.admission_no,s.first_name,s.last_name,s.sex,s.form_level,s.stream,s.status FROM students s WHERE s.status='Active' AND $dashboardLearnerScope ORDER BY s.created_at DESC LIMIT 5");
        $recentStmt->execute($dashboardScopeParams);
        $students = $recentStmt->fetchAll();
    }
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM students s WHERE s.status='Active' AND $dashboardLearnerScope");
    $totalStmt->execute($dashboardScopeParams);
    $totalStudents = (int)$totalStmt->fetchColumn();
    $presentStmt = $db->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.attendance_date=CURDATE() AND a.status='Present' AND s.status='Active' AND $dashboardLearnerScope");
    $presentStmt->execute($dashboardScopeParams);
    $presentToday = (int)$presentStmt->fetchColumn();
    $periodStart=$currentTerm['starts_on']??date('Y-01-01');$periodEnd=$currentTerm['ends_on']??date('Y-12-31');
    $feeTotals=['USD'=>0.0,'ZiG'=>0.0];$outstandingByCurrency=['USD'=>0.0,'ZiG'=>0.0];
    if ($dashboardCanSeeFinance) {
        $feeStmt=$db->prepare("SELECT currency,SUM(amount) AS paid FROM fee_ledger WHERE transaction_type='Payment' AND transaction_date BETWEEN :start AND :end GROUP BY currency");$feeStmt->execute(['start'=>$periodStart,'end'=>$periodEnd]);
        foreach($feeStmt->fetchAll() as $feeRow)$feeTotals[$feeRow['currency']]=(float)$feeRow['paid'];
        $outstandingStmt=$db->query("SELECT currency,COALESCE(SUM(CASE WHEN transaction_type='Payment' THEN -amount ELSE amount END),0) AS balance FROM fee_ledger GROUP BY currency");foreach($outstandingStmt->fetchAll() as $accountRow)$outstandingByCurrency[$accountRow['currency']]=(float)$accountRow['balance'];
    }
    $weekStart=new DateTimeImmutable('monday this week');$weekEnd=$weekStart->modify('+4 days');
    $attendanceStmt=$db->prepare("SELECT a.attendance_date,SUM(a.status='Present') AS present,SUM(a.status='Absent') AS absent,SUM(a.status='Late') AS late,SUM(a.status='Excused') AS excused FROM attendance a JOIN students s ON s.id=a.student_id WHERE s.status='Active' AND a.attendance_date BETWEEN :start AND :end AND $dashboardLearnerScope GROUP BY a.attendance_date");$attendanceStmt->execute(['start'=>$weekStart->format('Y-m-d'),'end'=>$weekEnd->format('Y-m-d')]+$dashboardScopeParams);
    $attendanceByDate=[];foreach($attendanceStmt->fetchAll() as $attendanceRow)$attendanceByDate[$attendanceRow['attendance_date']]=$attendanceRow;
    $attendanceChart=[];$attendanceDaysLogged=0;for($i=0;$i<5;$i++){$day=$weekStart->modify('+'.$i.' days');$dateKey=$day->format('Y-m-d');$row=$attendanceByDate[$dateKey]??['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];$presentPct=$totalStudents?round((int)$row['present']/$totalStudents*100,1):0;$absentPct=$totalStudents?round((int)$row['absent']/$totalStudents*100,1):0;$latePct=$totalStudents?round((int)$row['late']/$totalStudents*100,1):0;$excusedPct=$totalStudents?round((int)$row['excused']/$totalStudents*100,1):0;if(array_sum([(int)$row['present'],(int)$row['absent'],(int)$row['late'],(int)$row['excused']])>0)$attendanceDaysLogged++;$attendanceChart[]=[$day->format('D'),$presentPct,$absentPct,$latePct,$excusedPct];}
} catch (Throwable $e) {
    $dbError = 'Database is not connected yet. Import database/schema.sql and set your credentials in config.php.';
    $schoolDisplayName = 'School Portal'; $schoolPlace = 'Zimbabwe'; $termDisplay = 'Term not set'; $termYear = date('Y'); $currentTerm=null;$currentTermConflict=false;
    $totalStudents = 0; $presentToday = 0; $feeTotals=['USD'=>0.0,'ZiG'=>0.0];$outstandingByCurrency=['USD'=>0.0,'ZiG'=>0.0];$attendanceChart=[];$attendanceDaysLogged=0;
}
$pageTitles = ['dashboard'=>'Dashboard','students'=>'Learners','academics'=>'Academics','attendance'=>'Attendance','fees'=>'Fees & accounts','expenses'=>'Operating expenses','reports'=>'Reports','communications'=>'Communications','library'=>'Library','welfare'=>'Welfare & discipline','admissions'=>'Admissions','inventory'=>'Stores & supplies','exams'=>'ZIMSEC entries','staffing'=>'Staffing','payroll'=>'Payroll','boarding'=>'Boarding','transport'=>'School transport','activities'=>'Clubs & sports','audit'=>'Audit log','settings'=>'School settings','account'=>'My account'];
function esc($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#143d35"><title><?= esc($pageTitles[$active]) ?> · Mavambo SchoolOS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/secure.css"><link rel="stylesheet" href="assets/workflows.css"><link rel="stylesheet" href="assets/readability.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><div class="brand-mark">M</div><div><strong>Mavambo</strong><span>SchoolOS</span></div><button class="mobile-close" id="closeNav" aria-label="Close navigation">×</button></div>
    <div class="school-switch"><div class="school-crest">MS</div><div class="school-info"><b><?= esc($schoolDisplayName) ?></b><span><?= esc($schoolPlace) ?></span></div><span class="chevron">⌄</span></div>
    <div class="nav-label">SCHOOL</div>
    <nav class="nav-list">
      <a class="nav-link <?= $active==='dashboard'?'active':'' ?>" href="?page=dashboard"><span class="nav-icon">⌂</span>Overview</a>
      <?php if (role_can_access_page($user['role'],'students')): ?>
      <a class="nav-link <?= $active==='students'?'active':'' ?>" href="?page=students"><span class="nav-icon">♧</span>Learners<span class="nav-count"><?= number_format($totalStudents) ?></span></a>
      <?php endif; if (role_can_access_page($user['role'],'academics')): ?>
      <a class="nav-link <?= $active==='academics'?'active':'' ?>" href="?page=academics"><span class="nav-icon">▤</span>Academics</a>
      <?php endif; if (role_can_access_page($user['role'],'attendance')): ?>
      <a class="nav-link <?= $active==='attendance'?'active':'' ?>" href="?page=attendance"><span class="nav-icon">◷</span>Attendance</a>
      <?php endif; if (role_can_access_page($user['role'],'fees')): ?>
      <a class="nav-link <?= $active==='fees'?'active':'' ?>" href="?page=fees"><span class="nav-icon">＄</span>Fees & accounts</a>
      <?php endif; if (role_can_access_page($user['role'],'expenses')): ?>
      <a class="nav-link <?= $active==='expenses'?'active':'' ?>" href="?page=expenses"><span class="nav-icon">⇩</span>Operating expenses</a>
      <?php endif; if (role_can_access_page($user['role'],'reports')): ?>
      <a class="nav-link <?= $active==='reports'?'active':'' ?>" href="?page=reports"><span class="nav-icon">▥</span>Reports</a>
      <?php endif; ?>
      <?php if (role_can_access_page($user['role'],'library')): ?><a class="nav-link <?= $active==='library'?'active':'' ?>" href="?page=library"><span class="nav-icon">▣</span>Library</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'welfare')): ?><a class="nav-link <?= $active==='welfare'?'active':'' ?>" href="?page=welfare"><span class="nav-icon">♧</span>Welfare & discipline</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'admissions')): ?><a class="nav-link <?= $active==='admissions'?'active':'' ?>" href="?page=admissions"><span class="nav-icon">⇥</span>Admissions</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'inventory')): ?><a class="nav-link <?= $active==='inventory'?'active':'' ?>" href="?page=inventory"><span class="nav-icon">▦</span>Stores & supplies</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'exams')): ?><a class="nav-link <?= $active==='exams'?'active':'' ?>" href="?page=exams"><span class="nav-icon">▤</span>ZIMSEC entries</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'staffing')): ?><a class="nav-link <?= $active==='staffing'?'active':'' ?>" href="?page=staffing"><span class="nav-icon">◷</span>Staffing</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'payroll')): ?><a class="nav-link <?= $active==='payroll'?'active':'' ?>" href="?page=payroll"><span class="nav-icon">＄</span>Payroll</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'boarding')): ?><a class="nav-link <?= $active==='boarding'?'active':'' ?>" href="?page=boarding"><span class="nav-icon">⌂</span>Boarding</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'transport')): ?><a class="nav-link <?= $active==='transport'?'active':'' ?>" href="?page=transport"><span class="nav-icon">▰</span>Transport</a><?php endif; ?>
      <?php if (role_can_access_page($user['role'],'activities')): ?><a class="nav-link <?= $active==='activities'?'active':'' ?>" href="?page=activities"><span class="nav-icon">⚑</span>Clubs & sports</a><?php endif; ?>
    </nav>
    <div class="nav-label nav-label-spaced">COMMUNITY</div>
    <nav class="nav-list">
      <?php if (role_can_access_page($user['role'],'communications')): ?>
      <a class="nav-link <?= $active==='communications'?'active':'' ?>" href="?page=communications"><span class="nav-icon">◌</span>Messages<?php if($unreadNotices): ?><span class="nav-count"><?= $unreadNotices>99?'99+':$unreadNotices ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if (role_can_access_page($user['role'],'communications')): ?><a class="nav-link" href="?page=communications&tab=calendar"><span class="nav-icon">▦</span>School calendar</a><?php endif; ?>
    </nav>
    <div class="sidebar-bottom"><?php if (role_can_access_page($user['role'],'audit')): ?><a class="nav-link <?= $active==='audit'?'active':'' ?>" href="?page=audit"><span class="nav-icon">▤</span>Audit log</a><?php endif; ?><?php if (role_can_access_page($user['role'],'settings')): ?><a class="nav-link <?= $active==='settings'?'active':'' ?>" href="?page=settings"><span class="nav-icon">⚙</span>Settings</a><?php endif; ?><div class="help-card"><div class="help-orb">?</div><b>Need a hand?</b><p>Our school support team is here for you.</p><a href="mailto:support@mavambo.school">Get support <span>↗</span></a></div><div class="user-profile"><div class="avatar"><?= esc(strtoupper(substr($user['full_name'],0,1).(str_contains($user['full_name'],' ')?substr(strrchr($user['full_name'],' '),1,1):''))) ?></div><div class="user-copy"><b><a href="?page=account"><?= esc($user['full_name']) ?></a></b><span><?= esc($user['role']) ?></span></div><form action="actions/logout.php" method="post" class="logout-form"><?= csrf_field() ?><button aria-label="Sign out" title="Sign out" class="more">⇥</button></form></div></div>
  </aside>
  <main class="main-area">
    <header class="topbar"><button class="menu-toggle" id="openNav" aria-label="Open navigation">☰</button><div class="breadcrumb"><?= esc($schoolDisplayName) ?> <span>/</span> <b><?= esc($pageTitles[$active]) ?></b></div><div class="top-actions"><div class="term-pill"><span class="term-dot"></span><?= esc($termDisplay) ?> <span class="term-year">· <?= (int)$termYear ?></span><span class="chevron">⌄</span></div><?php if(role_can_access_page($user['role'],'communications')): ?><a class="icon-button" href="?page=communications" aria-label="Notifications"><?= $unreadNotices?'♧<i></i>':'♧' ?></a><?php endif; ?><div class="top-avatar"><?= esc(strtoupper(substr($user['full_name'],0,1))) ?></div></div></header>
    <div class="content">
      <?php if (!empty($dbError)): ?><div class="alert alert-warn"><?= esc($dbError) ?></div><?php endif; ?>
      <?php if ($notice): ?><div class="alert alert-success"><?= esc($notice) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>
      <?php if ($active === 'dashboard'): ?>
        <div class="welcome-row"><div><div class="eyebrow"><?= esc(strtoupper(date('l, d F Y'))) ?> <span class="eyebrow-line"></span> <?= esc(strtoupper($termDisplay)) ?></div><h1>Good morning, <?= esc(explode(' ', $user['full_name'])[0]) ?> <span class="wave">✳</span></h1><p class="subheading">Here’s what’s happening at your school today.</p></div><?php if (in_array($user['role'],['Administrator','Head','Clerk'],true)): ?><button class="primary-button" onclick="window.location='?page=students&add=1'"><span>＋</span> Add a learner</button><?php endif; ?></div>
        <?php if(in_array($user['role'],['Administrator','Head','Clerk'],true)&&(!$currentTerm||$currentTermConflict)): ?><div class="alert alert-warn"><?php if($currentTermConflict): ?>More than one school term is marked current.<?php else: ?>No current school term is configured.<?php endif; ?> Learners cannot be enrolled until the term calendar is corrected.<?php if(role_can_access_page($user['role'],'academics')): ?> <a class="text-button" href="?page=academics&tab=terms">Open term settings →</a><?php else: ?> Contact an administrator or school head to set the current term.<?php endif; ?></div><?php endif; ?>
        <section class="stat-grid<?= $dashboardCanSeeFinance ? '' : ' dashboard-stat-grid-limited' ?>">
          <article class="stat-card"><div class="stat-top"><span class="stat-label">ENROLLED LEARNERS</span><span class="stat-icon green-icon">♧</span></div><div class="stat-value"><?= number_format($totalStudents) ?></div><div class="stat-foot"><span>Active records</span><span class="foot-detail">Forms 1–6</span></div></article>
          <article class="stat-card"><div class="stat-top"><span class="stat-label">PRESENT TODAY</span><span class="stat-icon blue-icon">◷</span></div><div class="stat-value"><?= number_format($presentToday) ?><small> / <?= number_format(max($totalStudents, 0)) ?></small></div><div class="stat-foot"><span class="trend trend-blue">● <?= $totalStudents ? number_format($presentToday / $totalStudents * 100, 1) : '0.0' ?>%</span><span>attendance rate</span><span class="foot-detail">Recorded today</span></div></article>
          <?php if ($dashboardCanSeeFinance): ?><article class="stat-card"><div class="stat-top"><span class="stat-label">FEES COLLECTED · <?= esc(strtoupper($termDisplay)) ?></span><span class="stat-icon orange-icon">＄</span></div><div class="stat-value currency">$<?= number_format($feeTotals['USD'],2) ?></div><div class="stat-foot"><span>ZiG <?= number_format($feeTotals['ZiG'],2) ?></span><span class="foot-detail">Recorded payments</span></div></article>
          <article class="stat-card"><div class="stat-top"><span class="stat-label">OUTSTANDING BALANCE</span><span class="stat-icon red-icon">↗</span></div><div class="stat-value currency">$<?= number_format($outstandingByCurrency['USD'], 2) ?></div><div class="stat-foot"><span>ZiG <?= number_format($outstandingByCurrency['ZiG'],2) ?></span><span class="foot-detail">Separate currency balances</span></div></article><?php endif; ?>
        </section>
        <section class="dashboard-grid"><article class="panel attendance-panel"><div class="panel-heading"><div><h2>Attendance overview</h2><p>Saved learner registers for this week</p></div><span class="select-control">Monday–Friday</span></div><div class="chart-legend"><span><i class="legend-present"></i>Present</span><span><i class="legend-absent"></i>Absent</span><span><i class="legend-late"></i>Late</span><span><i class="legend-excused"></i>Excused</span></div><div class="chart-wrap"><div class="y-axis"><span>100%</span><span>75%</span><span>50%</span><span>25%</span><span>0%</span></div><div class="chart-area"><div class="grid-lines"><i></i><i></i><i></i><i></i><i></i></div><div class="bar-chart"><?php foreach ($attendanceChart as $day): ?><div class="bar-group"><div class="bars"><i class="bar-present" style="height:<?= $day[1] ?>%"></i><i class="bar-absent" style="height:<?= $day[2] ?>%"></i><i class="bar-late" style="height:<?= $day[3] ?>%"></i><i class="bar-excused" style="height:<?= $day[4] ?>%"></i></div><span><?= esc($day[0]) ?></span></div><?php endforeach; ?></div></div></div><div class="chart-note"><span class="note-check">✓</span><span><b><?= (int)$attendanceDaysLogged ?> of 5 school days</b> have saved registers</span><a href="?page=attendance">Open register <span>→</span></a></div></article>
        <article class="panel activity-panel"><div class="panel-heading"><div><h2>Today at school</h2><p><?= esc(date('l, d F')) ?></p></div><?php if(role_can_access_page($user['role'],'communications')): ?><a class="text-button" href="?page=communications">View notices <span>→</span></a><?php endif; ?></div><div class="timeline"><div class="timeline-item last"><span class="timeline-dot dot-green"></span><?php if($nextSchoolEvent): ?><b><?= esc($nextSchoolEvent['title']) ?></b><small><?= esc(date('D, d M · H:i',strtotime($nextSchoolEvent['starts_at']))) ?><?= $nextSchoolEvent['venue']?' · '.esc($nextSchoolEvent['venue']):'' ?></small><?php elseif($latestNotice): ?><b><?= esc($latestNotice['title']) ?></b><small><?= esc($latestNotice['priority']) ?> notice · <?= esc(date('d M Y H:i',strtotime($latestNotice['published_at']))) ?></small><?php else: ?><b>School calendar and notices</b><small>New events and notices will appear here.</small><?php endif; ?></div></div></article></section>
        <section class="panel learners-panel"><div class="panel-heading"><div><h2>Recent learners</h2><p>Your latest learner records</p></div><a class="text-button" href="?page=students">View all learners <span>→</span></a></div><?php renderStudentTable($students, true); ?></section>
      <?php elseif ($active === 'students'): require __DIR__ . '/pages/students.php'; ?>
      <?php elseif ($active === 'academics'): require __DIR__ . '/pages/academics.php'; ?>
      <?php elseif ($active === 'attendance'): require __DIR__ . '/pages/attendance.php'; ?>
      <?php elseif ($active === 'fees'): require __DIR__ . '/pages/fees.php'; ?>
      <?php elseif ($active === 'reports'): require __DIR__ . '/pages/reports.php'; ?>
      <?php elseif ($active === 'library'): require __DIR__ . '/pages/library.php'; ?>
      <?php elseif ($active === 'welfare'): require __DIR__ . '/pages/welfare.php'; ?>
      <?php elseif ($active === 'admissions'): require __DIR__ . '/pages/admissions.php'; ?>
      <?php elseif ($active === 'inventory'): require __DIR__ . '/pages/inventory.php'; ?>
      <?php elseif ($active === 'exams'): require __DIR__ . '/pages/exams.php'; ?>
      <?php elseif ($active === 'staffing'): require __DIR__ . '/pages/staffing.php'; ?>
      <?php elseif ($active === 'payroll'): require __DIR__ . '/pages/payroll.php'; ?>
      <?php elseif ($active === 'boarding'): require __DIR__ . '/pages/boarding.php'; ?>
      <?php elseif ($active === 'transport'): require __DIR__ . '/pages/transport.php'; ?>
      <?php elseif ($active === 'activities'): require __DIR__ . '/pages/activities.php'; ?>
      <?php elseif ($active === 'expenses'): require __DIR__ . '/pages/expenses.php'; ?>
      <?php elseif ($active === 'audit'): require __DIR__ . '/pages/audit.php'; ?>
      <?php elseif ($active === 'communications'): require __DIR__ . '/pages/communications.php'; ?>
      <?php elseif ($active === 'settings'): require __DIR__ . '/pages/settings.php'; ?>
      <?php elseif ($active === 'account'): require __DIR__ . '/pages/account.php'; endif; ?>
      <footer class="footer"><span>© <?= date('Y') ?> Mavambo SchoolOS</span><span>Built for Zimbabwean schools <b>♥</b></span><a href="#privacy">Privacy · Help</a></footer>
    </div>
  </main>
</div><div class="mobile-scrim" id="scrim"></div><script src="assets/app.js"></script>
</body></html>
<?php
function renderStudentTable(array $students, bool $compact): void {
    $list = $compact ? array_slice($students, 0, 5) : $students;
    echo '<div class="table-scroll"><table class="data-table"><thead><tr><th>Learner</th><th>Admission no.</th><th>Form / stream</th><th>Sex</th><th>Status</th><th></th></tr></thead><tbody>';
    if (!$list) { echo '<tr><td colspan="6" class="empty-cell">No learner records yet. Add your first learner to get started.</td></tr>'; }
    foreach ($list as $s) { $name = trim($s['first_name'].' '.$s['last_name']); $initial = strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)); $form = 'Form '.$s['form_level'].($s['stream'] ? ' · '.$s['stream'] : ''); echo '<tr data-search="'.htmlspecialchars(strtolower($name.' '.$s['admission_no']), ENT_QUOTES, 'UTF-8').'"><td><div class="learner-cell"><span class="learner-avatar">'.htmlspecialchars($initial,ENT_QUOTES,'UTF-8').'</span><b>'.htmlspecialchars($name,ENT_QUOTES,'UTF-8').'</b></div></td><td class="muted-cell">'.htmlspecialchars($s['admission_no'],ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($form,ENT_QUOTES,'UTF-8').'</td><td>'.($s['sex']==='F'?'Female':'Male').'</td><td><span class="status-pill">'.htmlspecialchars($s['status'],ENT_QUOTES,'UTF-8').'</span></td><td class="row-more">···</td></tr>'; }
    echo '</tbody></table></div>';
}
?>
