const crypto = require('crypto');

const { publicKey, privateKey } = crypto.generateKeyPairSync('ec', {
  namedCurve: 'prime256v1',
});

const pubJwk = publicKey.export({ format: 'jwk' });
const privJwk = privateKey.export({ format: 'jwk' });

function b64urlToBuf(s) {
  s = s.replace(/-/g, '+').replace(/_/g, '/');
  while (s.length % 4) s += '=';
  return Buffer.from(s, 'base64');
}

const x = b64urlToBuf(pubJwk.x);
const y = b64urlToBuf(pubJwk.y);
const d = b64urlToBuf(privJwk.d);
const publicUncompressed = Buffer.concat([Buffer.from([0x04]), x, y]);

const b64u = (buf) => buf.toString('base64url');
const secret = crypto.randomBytes(24).toString('hex');

process.stdout.write(`VAPID_PUBLIC_KEY=${b64u(publicUncompressed)}\n`);
process.stdout.write(`VAPID_PRIVATE_KEY=${b64u(d)}\n`);
process.stdout.write(`VAPID_SUBJECT=mailto:admin@ict.local\n`);
process.stdout.write(`WEBPUSH_NOTIFY_SECRET=${secret}\n`);
