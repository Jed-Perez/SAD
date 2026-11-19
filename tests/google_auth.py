"""Google OAuth checks: never exchange codes or contact Google."""
import json
import urllib.parse


def google_checks(Client, php, check):
    client = Client()
    check(client.call('google_start')[0] == 405, 'Google start requires POST')
    check(client.request('api.php?action=google_start', b'{}', {'Content-Type': 'application/json'})[0] == 419, 'Google start requires CSRF')
    url = client.ok('google_start', {})['url']
    parsed = urllib.parse.urlparse(url)
    params = urllib.parse.parse_qs(parsed.query)
    check(parsed.scheme == 'https' and parsed.netloc == 'accounts.google.com', 'Google authorization uses fixed HTTPS endpoint')
    check(params['scope'] == ['openid email profile'] and params['code_challenge_method'] == ['S256'], 'Google requests identity scopes with PKCE')
    check('client_secret' not in params and 'code_verifier' not in params, 'Google authorization URL contains no server secrets')
    check('google-auth.php' in params['redirect_uri'][0], 'Google uses configured callback URL')
    state = params['state'][0]
    # urllib follows the local callback redirect, never the Google authorization URL.
    status, body, _ = client.request('google-auth.php?' + urllib.parse.urlencode({'state': state, 'error': 'access_denied'}))
    check(status == 200 and b'cancelled or declined' in body, 'Cancelled Google consent returns an understandable error')
    check(client.ok('session')['user'] is None, 'Cancellation does not sign in')
    _, body, _ = client.request('google-auth.php?' + urllib.parse.urlencode({'state': state, 'code': 'must-not-be-exchanged'}))
    check(b'expired or could not be verified' in body, 'Callback replay rejected before contacting Google')
    attacker = Client()
    pending = urllib.parse.parse_qs(urllib.parse.urlparse(client.ok('google_start', {})['url']).query)
    _, body, _ = attacker.request('google-auth.php?' + urllib.parse.urlencode({'state': pending['state'][0], 'code': 'must-not-be-exchanged'}))
    check(b'expired or could not be verified' in body, 'Another browser cannot use a Google attempt')
    _, body, _ = client.request('google-auth.php?state[]=bad&code[]=bad')
    check(b'expired or could not be verified' in body, 'Malformed callback parameters fail safely')
    output = php("require 'tests/google_auth_db.php';")
    for line in output.splitlines():
        if line.startswith('PASS '):
            check(True, line[5:])
    # Exhaust only this isolated database's Google limiter, after browser checks.
    limited = False
    for _ in range(16):
        status, _ = client.call('google_start', {})
        if status == 429:
            limited = True
            break
        check(status == 200, 'Google retry starts safely')
    check(limited, 'Google authentication is rate limited')


def google_browser_checks(page, port, check):
    base = f'http://127.0.0.1:{port}'
    check(page.get_by_role('button', name='Continue with Google', exact=True).is_enabled(), 'Google button available on signup')
    page.route('https://accounts.google.com/**', lambda route: route.fulfill(status=200, content_type='text/html', body='<p>Google navigation intercepted by test</p>'))
    page.get_by_role('button', name='Continue with Google', exact=True).click()
    page.wait_for_url('https://accounts.google.com/**')
    params = urllib.parse.parse_qs(urllib.parse.urlparse(page.url).query)
    check(params['response_type'] == ['code'], 'Google button opens authorization code flow')
    page.goto(base + '/google-auth.php?' + urllib.parse.urlencode({'state': params['state'][0], 'error': 'access_denied'}))
    page.locator('#auth-error').wait_for(state='visible')
    check('cancelled or declined' in page.locator('#auth-error').inner_text(), 'Google cancellation error is visible')
    page.reload()
    page.locator('#auth').wait_for(state='visible')
    check(page.locator('#auth-error').is_hidden(), 'Google error is consumed once')
    page.locator('#forgot-password').click()
    check(page.locator('#google-auth-option').is_hidden(), 'Recovery does not show Google sign-in')
    page.evaluate("setAuthMode('register')")
    page.unroute('https://accounts.google.com/**')
