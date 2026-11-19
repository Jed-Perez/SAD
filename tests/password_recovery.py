"""Password recovery checks, using a loopback-only SMTP sink; no email leaves tests."""
import json
import queue
import re
import socketserver
import threading


class Mailbox:
    def __init__(self):
        self.messages = queue.Queue()
        messages = self.messages

        class Handler(socketserver.StreamRequestHandler):
            def handle(self):
                self.wfile.write(b'220 localhost test SMTP\r\n')
                while True:
                    line = self.rfile.readline()
                    if not line:
                        return
                    command = line.split(b' ', 1)[0].strip().upper()
                    if command == b'DATA':
                        self.wfile.write(b'354 Send message\r\n')
                        body = []
                        while True:
                            line = self.rfile.readline()
                            if line in (b'.\r\n', b''):
                                break
                            body.append(line)
                        messages.put(b''.join(body).decode('utf-8'))
                        self.wfile.write(b'250 Accepted\r\n')
                    elif command == b'QUIT':
                        self.wfile.write(b'221 Bye\r\n')
                        return
                    else:
                        self.wfile.write(b'250 OK\r\n')

        self.server = socketserver.ThreadingTCPServer(('127.0.0.1', 0), Handler)
        self.server.daemon_threads = True
        self.port = self.server.server_address[1]
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()

    def token(self):
        return re.search(r'#reset=([a-f0-9]{64})', self.messages.get(timeout=5)).group(1)

    def close(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=2)


def recovery_checks(Client, php, check, mailbox):
    victim = Client()
    victim.ok('register', {'name': 'Recovery Test', 'email': 'recover@test.local', 'password': 'Original-Password-123'})
    guest = Client()
    check(guest.call('forgot_password')[0] == 405, 'Recovery requires POST')
    check(guest.request('api.php?action=forgot_password', b'{}', {'Content-Type': 'application/json'})[0] == 419, 'Recovery requires CSRF')
    check(guest.call('forgot_password', {'email': 'invalid'})[0] == 422, 'Recovery rejects invalid email')
    unknown = guest.ok('forgot_password', {'email': 'missing@test.local'})
    response = guest.ok('forgot_password', {'email': 'recover@test.local'})
    secret = mailbox.token()
    check(response == unknown and secret not in json.dumps(response), 'Recovery hides account existence and token')
    stored = php("require 'app/bootstrap.php';echo query('SELECT token_hash FROM password_resets')->fetchColumn();")
    check(stored != secret and len(stored) == 64, 'Database stores only a hash of the reset token')
    guest.ok('forgot_password', {'email': 'recover@test.local'})
    check(mailbox.messages.empty(), 'Repeated request respects account email cooldown')
    data = {'reset_token': secret, 'password': 'Replacement-Password-123', 'confirm_password': 'Replacement-Password-123'}
    check(guest.call('reset_password', {**data, 'reset_token': '0' * 64})[0] == 422, 'Unknown reset token rejected')
    check(guest.call('reset_password', {**data, 'confirm_password': 'Mismatch-123'})[0] == 422, 'Reset requires matching passwords')
    check(guest.call('reset_password', {**data, 'password': 'short', 'confirm_password': 'short'})[0] == 422, 'Reset enforces minimum password length')
    php("require 'app/bootstrap.php';query('UPDATE password_resets SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE)');")
    check(guest.call('reset_password', data)[0] == 422, 'Expired reset link rejected')
    php("require 'app/bootstrap.php';query('UPDATE password_resets SET expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE)');")
    guest.ok('reset_password', data)
    check(victim.ok('session')['user'] is None, 'Password reset invalidates existing sessions')
    check(guest.ok('session')['user'] is None, 'Password reset does not automatically sign in')
    check(guest.call('reset_password', data)[0] == 422, 'Reset token cannot be reused')
    check(guest.call('login', {'email': 'recover@test.local', 'password': 'Original-Password-123'})[0] == 401, 'Old password rejected after reset')
    guest.login('recover@test.local', 'Replacement-Password-123')
    check(guest.ok('session')['user']['email'] == 'recover@test.local', 'New password signs in successfully')
    guest.ok('forgot_password', {'email': 'recover@test.local'})
    stale = mailbox.token()
    guest.ok('profile', {'name': 'Recovery Test', 'current_password': 'Replacement-Password-123', 'new_password': 'Changed-Again-123'})
    check(guest.ok('session')['user'] is not None, 'Profile password change preserves current session')
    check(guest.call('reset_password', {**data, 'reset_token': stale})[0] == 422, 'Password change invalidates outstanding reset links')
    php("require 'app/bootstrap.php';query(\"UPDATE users SET active=0 WHERE email='recover@test.local'\");")
    check(guest.ok('forgot_password', {'email': 'recover@test.local'}) == unknown, 'Inactive accounts receive generic response')
    check(mailbox.messages.empty(), 'Inactive accounts receive no reset email')
    for _ in range(4):
        guest.ok('forgot_password', {'email': 'missing@test.local'})
    check(guest.call('forgot_password', {'email': 'missing@test.local'})[0] == 429, 'Password recovery rate limit enforced')
    # Clear only this isolated database's attempt fixtures for subsequent browser checks.
    php("require 'app/bootstrap.php';query('DELETE FROM login_attempts');")


def recovery_browser_checks(page, port, check, mailbox):
    page.goto(f'http://127.0.0.1:{port}/portal.php?register=1')
    page.locator('#register-name').fill('Browser Recovery')
    page.locator('#login-email').fill('browser-recovery@test.local')
    page.locator('#login-password').fill('Browser-Original-123')
    page.locator('#confirm-password').fill('Browser-Original-123')
    toggle = page.locator('[data-password-toggle="login-password"]')
    toggle.click()
    check(page.locator('#login-password').get_attribute('type') == 'text', 'Show password works on registration')
    toggle.click()
    check(page.locator('#login-password').get_attribute('type') == 'password', 'Hide password masks registration password')
    page.locator('#auth-submit').click()
    page.locator('#workspace').wait_for(state='visible')
    page.locator('#logout').click()
    page.locator('#auth').wait_for(state='visible')
    page.locator('#login-password').fill('Visible-Test-123')
    toggle.click()
    check(page.locator('#login-password').input_value() == 'Visible-Test-123' and toggle.get_attribute('aria-pressed') == 'true', 'Sign-in visibility toggle preserves value and accessible state')
    page.locator('#forgot-password').click()
    check(page.locator('#password-fields').is_hidden(), 'Forgot-password form needs only email')
    page.locator('#login-email').fill('browser-recovery@test.local')
    page.locator('#auth-submit').click()
    page.locator('#auth-success').wait_for(state='visible')
    secret = mailbox.token()
    page.goto(f'http://127.0.0.1:{port}/portal.php#reset={secret}')
    page.locator('#confirm-password').wait_for(state='visible')
    check('#reset=' not in page.url, 'Reset secret removed from browser address bar')
    page.set_viewport_size({'width': 390, 'height': 844})
    check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), 'Reset form fits mobile viewport')
    page.locator('#login-password').fill('Browser-Replacement-123')
    page.locator('#confirm-password').fill('Wrong-Password-123')
    page.locator('#auth-submit').click()
    page.locator('#auth-error').wait_for(state='visible')
    check('do not match' in page.locator('#auth-error').inner_text(), 'Reset screen explains mismatched passwords')
    page.locator('#confirm-password').fill('Browser-Replacement-123')
    page.locator('[data-password-toggle="confirm-password"]').click()
    check(page.locator('#confirm-password').get_attribute('type') == 'text', 'Confirmation password can be shown')
    page.locator('#auth-submit').click()
    page.locator('#auth-success').wait_for(state='visible')
    check(page.locator('#auth-submit').inner_text() == 'Sign in' and page.locator('#login-password').get_attribute('type') == 'password', 'Reset returns to sign-in with passwords masked')
    page.locator('#login-email').fill('browser-recovery@test.local')
    page.locator('#login-password').fill('Browser-Replacement-123')
    page.locator('#auth-submit').click()
    page.locator('#workspace').wait_for(state='visible')
    check(True, 'Browser completes email recovery and signs in with new password')
    page.set_viewport_size({'width': 1440, 'height': 1000})
    page.locator('#logout').click()
    page.locator('#auth').wait_for(state='visible')
