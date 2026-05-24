(function () {
  'use strict';

  const textEncoder = new TextEncoder();
  const textDecoder = new TextDecoder();

  function bytesToBase64Url(bytes) {
    let binary = '';
    for (const b of bytes) binary += String.fromCharCode(b);
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function base64UrlToBytes(value) {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=');
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
    return bytes;
  }

  async function deriveMaterial(passphrase, saltB64, iterations) {
    const salt = base64UrlToBytes(saltB64);
    const baseKey = await crypto.subtle.importKey(
      'raw',
      textEncoder.encode(passphrase),
      'PBKDF2',
      false,
      ['deriveBits']
    );
    const bits = await crypto.subtle.deriveBits(
      {
        name: 'PBKDF2',
        hash: 'SHA-256',
        salt,
        iterations
      },
      baseKey,
      512
    );
    const material = new Uint8Array(bits);
    return {
      aesKeyBytes: material.slice(0, 32),
      accessTokenBytes: material.slice(32, 64)
    };
  }

  async function importAesKey(keyBytes, usages) {
    return crypto.subtle.importKey('raw', keyBytes, { name: 'AES-GCM' }, false, usages);
  }

  async function encryptMessage(plaintext, passphrase, iterations) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const saltB64 = bytesToBase64Url(salt);
    const material = await deriveMaterial(passphrase, saltB64, iterations);
    const aesKey = await importAesKey(material.aesKeyBytes, ['encrypt']);
    const ciphertext = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      aesKey,
      textEncoder.encode(plaintext)
    );

    return {
      ciphertext: bytesToBase64Url(new Uint8Array(ciphertext)),
      iv: bytesToBase64Url(iv),
      salt: saltB64,
      accessToken: bytesToBase64Url(material.accessTokenBytes),
      kdf: 'PBKDF2-SHA256',
      iterations
    };
  }

  async function accessTokenFor(passphrase, salt, iterations) {
    const material = await deriveMaterial(passphrase, salt, iterations);
    return bytesToBase64Url(material.accessTokenBytes);
  }

  async function decryptMessage(ciphertextB64, ivB64, saltB64, passphrase, iterations) {
    const material = await deriveMaterial(passphrase, saltB64, iterations);
    const aesKey = await importAesKey(material.aesKeyBytes, ['decrypt']);
    const plaintext = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: base64UrlToBytes(ivB64) },
      aesKey,
      base64UrlToBytes(ciphertextB64)
    );
    return textDecoder.decode(plaintext);
  }

  window.TalkCrypto = {
    encryptMessage,
    accessTokenFor,
    decryptMessage,
    bytesToBase64Url,
    base64UrlToBytes
  };
}());
