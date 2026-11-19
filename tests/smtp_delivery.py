"""Exercise real PHPMailer SMTP against a local TLS server; no external email."""
import base64
from email import policy
from email.parser import BytesParser
import json
import os
from pathlib import Path
import queue
import socketserver
import ssl
import subprocess
import tempfile
import threading

ROOT = Path(__file__).resolve().parents[1]
PHP = r'C:\xampp\php\php.exe'
OPENSSL = r'C:\xampp\apache\bin\openssl.exe'


def run():
    with tempfile.TemporaryDirectory(prefix='techtrack-smtp-') as tmp:
        tmp = Path(tmp)
        cert, key, log = tmp/'cert.pem', tmp/'key.pem', tmp/'php.log'
        cnf = tmp/'openssl.cnf'
        cnf.write_text('[req]\ndistinguished_name=dn\n[dn]\n')
        subprocess.run([OPENSSL,'req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(key),'-out',str(cert),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost','-config',str(cnf)],check=True,capture_output=True)
        tls = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        tls.load_cert_chain(cert,key)
        messages = queue.Queue()
        auth = queue.Queue()

        class Server(socketserver.ThreadingTCPServer):
            daemon_threads = True

            def get_request(self):
                sock, addr = super().get_request()
                sock.settimeout(5)
                try:
                    return tls.wrap_socket(sock,server_side=True), addr
                except ssl.SSLError:
                    sock.close()
                    raise

        class Handler(socketserver.StreamRequestHandler):
            def handle(self):
                self.wfile.write(b'220 localhost SMTP test\r\n')
                authenticated = False
                while line := self.rfile.readline():
                    command = line.split(b' ',1)[0].strip().upper()
                    if command == b'EHLO':
                        self.wfile.write(b'250-localhost\r\n250 AUTH LOGIN\r\n')
                    elif command == b'AUTH':
                        self.wfile.write(b'334 VXNlcm5hbWU6\r\n')
                        username = base64.b64decode(self.rfile.readline().strip()).decode()
                        self.wfile.write(b'334 UGFzc3dvcmQ6\r\n')
                        password = base64.b64decode(self.rfile.readline().strip()).decode()
                        authenticated = (username,password)==('sender@example.test','test-app-secret')
                        auth.put(authenticated)
                        self.wfile.write(b'235 Authenticated\r\n' if authenticated else b'535 Rejected\r\n')
                    elif command == b'DATA' and authenticated:
                        self.wfile.write(b'354 Send message\r\n')
                        body = []
                        while (line := self.rfile.readline()) not in (b'.\r\n',b''):
                            body.append(line[1:] if line.startswith(b'..') else line)
                        messages.put(b''.join(body))
                        self.wfile.write(b'250 Accepted\r\n')
                    elif command == b'QUIT':
                        self.wfile.write(b'221 Bye\r\n')
                        return
                    else:
                        self.wfile.write(b'250 OK\r\n' if authenticated else b'530 Authenticate first\r\n')

        server = Server(('127.0.0.1',0),Handler)
        thread = threading.Thread(target=server.serve_forever,daemon=True)
        thread.start()
        settings = dict(mail_transport='smtp',mail_from='sender@example.test',smtp_host='localhost',smtp_port=server.server_address[1],smtp_encryption='ssl',smtp_username='sender@example.test',smtp_password='test-app-secret')
        env = {k:v for k,v in os.environ.items() if k not in ['MAIL_TRANSPORT','SMTP_HOST','SMTP_PORT','SMTP_ENCRYPTION','SMTP_USERNAME','SMTP_PASSWORD']}
        code = "require 'app/Mail/delivery.php';$config=json_decode(stream_get_contents(STDIN),true);echo json_encode(send_account_email('recipient@example.test','Reset your TechTrack password',\"Reset link: https://example.test/portal.php#reset=test-private-token\\n\"));"

        def send(overrides=None, trusted=True):
            args = [PHP,'-d','display_errors=0','-d','log_errors=1','-d','error_log='+str(log)]
            if trusted:
                args += ['-d','openssl.cafile='+str(cert)]
            result = subprocess.run(args+['-r',code],input=json.dumps(settings | (overrides or {})),text=True,capture_output=True,cwd=ROOT,env=env,check=True)
            assert not result.stderr, 'Private errors must stay out of the response'
            return json.loads(result.stdout)

        try:
            assert send() is True
            assert auth.get(timeout=2) is True
            message = BytesParser(policy=policy.default).parsebytes(messages.get(timeout=2))
            assert message['To']=='recipient@example.test'
            assert message['From']=='TechTrack <sender@example.test>'
            assert '#reset=test-private-token' in message.get_content()
            print('PASS TLS SMTP authentication delivers the reset link to the intended recipient')
            assert send({'smtp_password':'wrong-private-password'}) is False
            assert auth.get(timeout=2) is False
            assert messages.empty()
            print('PASS Authentication failure does not send or fall back to PHP mail')
            assert send(trusted=False) is False
            print('PASS Untrusted TLS certificates are rejected')
            assert send({'smtp_encryption':''}) is False
            assert send({'smtp_password':''}) is False
            assert send({'mail_transport':'unknown'}) is False
            print('PASS Missing credentials, plaintext SMTP and unknown transports are rejected')
            errors = log.read_text()
            assert all(secret not in errors for secret in ['test-app-secret','wrong-private-password','test-private-token','recipient@example.test'])
            print('PASS Failure logs contain no credentials, recipients or reset links')
        finally:
            server.shutdown()
            server.server_close()
            thread.join(timeout=2)


if __name__ == '__main__':
    run()
