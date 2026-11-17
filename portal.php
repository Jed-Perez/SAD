<?php
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/Core/assets.php';
$installed=true;
try { $installed=(bool)query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch(Throwable $e){ $installed=false; }
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="<?=htmlspecialchars(csrf(),ENT_QUOTES,'UTF-8')?>"><title>TechTrack Portal · NCPCS</title><link rel="stylesheet" href="<?=asset_url('assets/vendor/bootstrap.min.css')?>"><link rel="stylesheet" href="<?=asset_url('assets/app.css')?>"><link rel="stylesheet" href="<?=asset_url('assets/css/theme.css')?>"><link rel="stylesheet" href="<?=asset_url('assets/css/showcase.css')?>"><link rel="stylesheet" href="<?=asset_url('assets/css/inventory.css')?>"><link rel="stylesheet" href="<?=asset_url('assets/css/sidebar.css')?>"><link rel="stylesheet" href="<?=asset_url('assets/css/auth.css')?>"></head>
<body>
<?php if(!$installed): ?>
<main class="setup-card"><div class="brand-mark">T</div><h1>Welcome to TechTrack</h1><p>Start MySQL in XAMPP, then initialize your database from this project folder:</p><pre>C:\xampp\php\php.exe install.php</pre><p>The installer prints your administrator login. Database settings are in <code>config.php</code>; use <code>config.local.php</code> for custom credentials.</p><a class="btn btn-primary" href="portal.php">Check again</a></main>
<?php else: ?>
<div id="auth" class="auth-layout" hidden>
 <aside class="auth-story"><a class="brand" href="index.php"><span class="brand-mark">T</span><span>TechTrack<small>NCPCS MANAGEMENT SYSTEM</small></span></a><div><span class="eyebrow light">CONNECTED SERVICE. CLEARER SECURITY.</span><h1>A better view.<br>A smarter way<br>to take care.</h1><p>From the first service request to the final camera placement, keep everything in one place.</p><div class="plan-art" aria-hidden="true"><svg viewBox="0 0 440 230"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="#ffffff" stroke-opacity=".08"/></pattern></defs><rect width="440" height="230" fill="url(#grid)"/><path d="M60 35H370V195H60ZM60 110H175V35M175 110H270V195M270 110V35M175 140V195" fill="none" stroke="#a6c8bf" stroke-width="4"/><path d="M65 40L145 70L65 120Z M365 190L285 155L365 110Z" fill="#84e4b5" fill-opacity=".18"/><circle cx="65" cy="40" r="8" fill="#9aefbf"/><circle cx="365" cy="190" r="8" fill="#9aefbf"/><text x="207" y="79" fill="#c2d6ce" font-size="10">LIVING AREA</text><text x="87" y="160" fill="#c2d6ce" font-size="10">ENTRY</text><text x="296" y="79" fill="#c2d6ce" font-size="10">GARAGE</text></svg><span><i class="live-dot"></i> Thoughtful coverage starts with a plan</span></div></div><small>Nasugbu Computer Parts and CCTV Services · Batangas</small></aside>
 <main class="auth-form-wrap"><div class="auth-form">
  <div class="eyebrow">WELCOME TO TECHTRACK</div>
  <h2 id="auth-title">Good to have you back.</h2>
  <p class="muted" id="auth-subtitle">Sign in to manage your services and stay connected.</p>
  <div id="auth-error" class="alert alert-danger" hidden role="alert"></div>
  <div id="auth-success" class="alert alert-success" hidden role="status"></div>
  <form id="auth-form">
   <div id="register-fields" hidden><label class="form-label" for="register-name">Full name</label><input id="register-name" class="form-control" name="name" autocomplete="name" maxlength="120" disabled></div>
   <div id="email-fields"><label class="form-label" for="login-email">Email address</label><input id="login-email" class="form-control" type="email" name="email" required autocomplete="username" maxlength="190" placeholder="you@example.com"></div>
   <div id="password-fields">
    <label class="form-label" id="password-label" for="login-password">Password</label>
    <div class="password-control"><input id="login-password" class="form-control" type="password" name="password" required autocomplete="current-password" maxlength="200" placeholder="Enter your password"><button class="password-toggle" type="button" data-password-toggle="login-password" aria-controls="login-password" aria-label="Show password" aria-pressed="false">Show</button></div>
   </div>
   <div id="confirm-password-fields" hidden>
    <label class="form-label" for="confirm-password">Confirm new password</label>
    <div class="password-control"><input id="confirm-password" class="form-control" type="password" name="confirm_password" autocomplete="new-password" maxlength="200" placeholder="Re-enter your new password" disabled><button class="password-toggle" type="button" data-password-toggle="confirm-password" aria-controls="confirm-password" aria-label="Show confirm password" aria-pressed="false">Show</button></div>
   </div>
   <div id="forgot-password-wrap" class="auth-recovery"><button type="button" id="forgot-password" class="link-button">Forgot password?</button></div>
   <button class="btn btn-primary w-100 mt-4" type="submit" id="auth-submit">Sign in <span aria-hidden="true">&rarr;</span></button>
  </form>
  <p class="auth-switch"><span id="auth-switch-label">New to NCPCS?</span> <button type="button" id="auth-toggle" class="link-button">Create an account</button></p>
  <div class="auth-note">Your services, messages, and home plans are accessible through your own account.</div>
 </div></main>
</div>
<div id="workspace" hidden>
 <aside class="sidebar" id="sidebar"><a class="brand" href="#dashboard"><span class="brand-mark">T</span><span>TechTrack<small>NCPCS MANAGEMENT SYSTEM</small></span></a><div class="workspace-label">WORKSPACE <span id="role-badge"></span></div><nav id="navigation" aria-label="Main navigation"></nav><div class="sidebar-bottom"><div class="sidebar-help"><span class="eyebrow light">A LITTLE MORE PEACE OF MIND</span><p>Plan your coverage.<br>Connect with our team.</p></div><button class="profile-button" id="profile-button"><span class="avatar" id="avatar"></span><span><strong id="sidebar-name"></strong><small>View your profile</small></span><span>↗</span></button><button class="logout-button" id="logout">Sign out</button></div></aside>
 <div id="sidebar-backdrop" class="sidebar-backdrop" hidden></div>
 <div class="main-wrap"><header class="topbar"><button id="menu-button" class="btn btn-light mobile-menu" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">☰</button><div class="breadcrumb-label">Workspace <span>/</span> <strong id="breadcrumb">Overview</strong></div><a id="message-alert" class="message-alert" href="#messages" hidden aria-label="Messages"><span>Messages</span><span class="unread-badge" data-unread hidden></span></a><span id="message-announcement" class="visually-hidden" role="status" aria-live="polite"></span><div class="topbar-right"><span class="status-dot"></span> NCPCS · Nasugbu <span class="topbar-divider"></span><span id="today"></span></div></header><main id="content" class="main-content" tabindex="-1"><div class="loading-state">Loading your workspace…</div></main><footer class="app-footer">TechTrack <span>Inventory & service management · NCPCS</span></footer></div>
</div>
<div class="modal fade" id="editor-modal" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="modal-title"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="modal-body"></div></div></div></div>
<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="notice" class="toast" role="status" aria-live="polite" aria-atomic="true"><div class="d-flex"><div class="toast-body" id="notice-body"></div><button class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div></div>
<script src="<?=asset_url('assets/vendor/bootstrap.bundle.min.js')?>"></script><script src="<?=asset_url('assets/js/Core/core.js')?>"></script><script src="<?=asset_url('assets/js/Dashboard/dashboard.js')?>"></script><script src="<?=asset_url('assets/js/Inventory/inventory.js')?>"></script><script src="<?=asset_url('assets/js/Users/users.js')?>"></script><script src="<?=asset_url('assets/js/Services/services.js')?>"></script><script src="<?=asset_url('assets/js/Messages/messages.js')?>"></script><script src="<?=asset_url('assets/js/Planner/planner.js')?>"></script><script src="<?=asset_url('assets/js/Reports/reports.js')?>"></script><script src="<?=asset_url('assets/js/Settings/settings.js')?>"></script><script src="<?=asset_url('assets/js/Events/events.js')?>"></script>
<?php endif; ?>
</body></html>
